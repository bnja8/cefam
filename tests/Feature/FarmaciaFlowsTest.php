<?php


namespace Tests\Feature;

use App\Models\Usuario;
use App\Models\Rol;
use App\Providers\AppServiceProvider;
use App\Services\Alerts\StockAlertService;
use App\Services\Inventory\InventoryOperationService;
use App\Support\Formatting\NumberFormat;
use App\Support\People\Rut;
use Database\Seeders\DemoDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class FarmaciaFlowsTest extends TestCase
{
    use RefreshDatabase;

    private const ADMIN_TEST_PASSWORD = 'Test-only-Admin-Password-2026';

    // Prepara los datos compartidos para las pruebas.
    protected function setUp(): void
    {
        parent::setUp();
        config(['app.administrator_initial_password' => self::ADMIN_TEST_PASSWORD]);
        $this->seed();
        $this->actingAs(Usuario::where('rut', '12345674')->firstOrFail());
    }

    public function test_dashboard_prioritizes_operational_access_without_metric_cards(): void
    {
        $response = $this->get(route('dashboard'));
        $response
            ->assertOk()
            ->assertSee('Panel de Control')
            ->assertSee('Saltar al contenido principal')
            ->assertSee('logo.jpg?v=', false)
            ->assertSee('id="main-content"', false)
            ->assertSee('aria-current="page"', false)
            ->assertDontSee('id="global-search"', false)
            ->assertSee('id="sidebar-toggle"', false)
            ->assertSee('class="sidebar-logo-icon"', false)
            ->assertSee('CESFAM · Tu salud nos importa')
            ->assertSee('id="sidebar-module-search"', false)
            ->assertSee('placeholder="Filtrar módulo clínico..."', false)
            ->assertSee('data-sidebar-module-search', false)
            ->assertSee('Abrir alertas')
            ->assertSee('Administración CESFAM Farmacia')
            ->assertSee('Cerrar sesión')
            ->assertDontSee('Pacientes activos')
            ->assertDontSee('Productos sin stock')
            ->assertSee('<header class="topbar">', false)
            ->assertSee('Accesos rápidos')
            ->assertSee('class="quick-action-link"', false)
            ->assertSee('Recetas')
            ->assertSee('Dispensación')
            ->assertSee('Registrar dispensación')
            ->assertSee('Filtrar módulo clínico...')
            ->assertSee('Mapa operativo')
            ->assertSee('href="'.route('dispensations.index').'"', false)
            ->assertSee('href="'.route('patients.index').'"', false)
            ->assertSee('href="'.route('inventory.index').'"', false)
            ->assertSee('href="'.route('alerts.map').'"', false)
            ->assertSee('Existencias por categoría')
            ->assertSee('Productos más dispensados')
            ->assertSee('Salud del inventario')
            ->assertSee('Próximos vencimientos')
            ->assertSee('RETIRO')
            ->assertSee('Atenciones hoy')
            ->assertSee('Recetas pendientes')
            ->assertSee('Stock crítico')
            ->assertSee('Lotes por vencer')
            ->assertSee('Hoy (por hora)')
            ->assertSee('onchange="this.form.submit()"', false)
            ->assertDontSee('>Ver</button>', false)
            ->assertSee('COMPROBANTE')
            ->assertSee('Amoxicilina 500 mg');

        self::assertTrue(
            strpos($response->getContent(), 'Últimas entregas de hoy')
                < strpos($response->getContent(), 'Actividad de dispensación')
        );
        self::assertSame(1, preg_match('/<header class="topbar">(.*?)<\/header>/s', $response->getContent(), $topbar));
        self::assertStringNotContainsString('global-search', $topbar[1]);
        self::assertStringNotContainsString('Buscar paciente, medicamento', $topbar[1]);

        $hourlyResponse = $this->get(route('dashboard', ['periodo_actividad' => 'hoy']));
        $hourlyResponse
            ->assertOk()
            ->assertSee('>00:00<', false)
            ->assertSee('>21:00<', false)
            ->assertDontSee('00:00-04:00', false)
            ->assertDontSee('20:00-24:00', false);
    }

    public function test_dashboard_hourly_chart_reports_deliveries_in_their_actual_hour(): void
    {
        $patientRut = DB::table('pacientes')->value('rut');
        DB::table('entregas')->insert([
            'paciente_rut' => $patientRut,
            'ficha_id' => DB::table('fichas_pacientes')->where('paciente_rut', $patientRut)->value('ficha_id'),
            'usuario_rut' => '12345674',
            'fecha_hora' => now()->startOfDay()->addHours(21)->addMinutes(3),
            'estado' => 'CONFIRMADA',
        ]);

        $this->get(route('dashboard', ['periodo_actividad' => 'hoy']))
            ->assertOk()
            ->assertSee('>21:00<', false)
            ->assertSee('<title>21:00: 1 atenciones</title>', false);
    }

    public function test_base_schema_rollback_preserves_an_imported_schema_without_ownership_marker(): void
    {
        $this->assertDatabaseHas('farmacia_migration_ownership', [
            'migration' => '2026_09_08_000000_prepare_farmacia_schema',
        ]);

        \Illuminate\Support\Facades\Schema::dropIfExists('farmacia_migration_ownership');

        $migration = require database_path('migrations/2026_09_08_000000_prepare_farmacia_schema.php');
        $migration->down();

        $this->assertTrue(\Illuminate\Support\Facades\Schema::hasTable('pacientes'));
        $this->assertTrue(\Illuminate\Support\Facades\Schema::hasTable('usuarios'));
    }

    public function test_unused_product_and_prescription_columns_are_removed_from_schema(): void
    {
        $this->assertFalse(Schema::hasColumn('productos', 'cantidad_envases_secundarios'));
        $this->assertFalse(Schema::hasColumn('productos', 'tipo_envase_secundario'));
        $this->assertFalse(Schema::hasColumn('receta_detalle', 'tomas_diarias'));
        $this->assertFalse(Schema::hasColumn('preparacion_unidosis_detalle', 'dosis'));
    }

    public function test_role_checks_reflect_pivot_updates_and_role_relationship_is_inverse(): void
    {
        $user = Usuario::query()->findOrFail('12345674');

        $this->assertTrue($user->hasAnyRole(['Administrador']));
        $pharmacistRoleId = DB::table('roles')->where('nombre', 'Químico Farmacéutico')->value('rol_id');
        DB::table('usuario_rol')->where('usuario_rut', $user->getKey())->update(['rol_id' => $pharmacistRoleId]);

        $this->assertFalse($user->hasAnyRole(['Administrador']));
        $this->assertTrue($user->hasAnyRole(['Químico Farmacéutico']));
        $this->assertFalse($user->hasAnyRole([]));
        $this->assertTrue(
            Rol::query()
                ->where('nombre', 'Químico Farmacéutico')
                ->firstOrFail()
                ->usuarios()
                ->where('usuarios.rut', $user->getKey())
                ->exists()
        );
    }

    public function test_dashboard_dispensing_chart_can_select_a_time_period(): void
    {
        $response = $this->get(route('dashboard', ['periodo_actividad' => '3m']));
        $response
            ->assertOk()
            ->assertSee('name="periodo_actividad"', false)
            ->assertSee('<option value="3m" selected>Últimos 3 meses</option>', false)
            ->assertSee('Atenciones confirmadas durante últimos 3 meses')
            ->assertSee('atenciones en el período');
        self::assertMatchesRegularExpression('/<path class="chart-area-fill" d="M[^"]+Z" \/>/', $response->getContent());

        $this->get(route('dashboard', ['periodo_actividad' => 'invalid']))
            ->assertSessionHasErrors('periodo_actividad');
    }

    public function test_patients_can_be_filtered_by_status_and_current_program(): void
    {
        $programId = DB::table('programas_salud')->insertGetId([
            'nombre' => 'Programa filtro cardiovascular',
            'descripcion' => 'Programa para probar filtros de pacientes',
            'activo' => true,
        ]);
        DB::table('programas_salud')->insert([
            'nombre' => 'Programa filtro inactivo',
            'descripcion' => 'Programa inactivo que no debe aparecer en el selector',
            'activo' => false,
        ]);
        $patientWithProgram = Rut::limpiarRut('12.345.678-5');
        DB::table('paciente_programa')->insert([
            'paciente_rut' => $patientWithProgram,
            'programa_id' => $programId,
            'fecha_inicio' => now()->subDay()->toDateString(),
            'fecha_termino' => now()->addMonth()->toDateString(),
            'activo' => true,
        ]);
        DB::table('pacientes')->where('rut', Rut::limpiarRut('15.876.543-8'))->update(['activo' => false]);

        $this->get(route('patients.index', [
            'programa_id' => $programId,
            'estado' => 'activo',
            'programa_estado' => 'con_programa',
        ]))
            ->assertOk()
            ->assertSee('name="programa_id"', false)
            ->assertSee('<option value="'.$programId.'" selected>Programa filtro cardiovascular</option>', false)
            ->assertDontSee('Programa filtro inactivo')
            ->assertSee('name="estado"', false)
            ->assertSee('name="programa_estado"', false)
            ->assertDontSee('id="nuevo-paciente"', false)
            ->assertSee('María González López')
            ->assertDontSee('Juan Pérez Morales');

        $this->get(route('patients.index', ['estado' => 'inactivo']))
            ->assertOk()
            ->assertSee('Juan Pérez Morales')
            ->assertDontSee('María González López');

        $this->get(route('patients.index', ['programa_estado' => 'sin_programa']))
            ->assertOk()
            ->assertSee('Juan Pérez Morales')
            ->assertDontSee('María González López');
    }

    public function test_dispensation_patient_search_returns_only_matching_active_patients(): void
    {
        $inactivePatientRut = Rut::limpiarRut('15.876.543-8');
        DB::table('pacientes')->where('rut', $inactivePatientRut)->update(['activo' => false]);

        $this->getJson(route('patients.api.search', ['q' => 'María González']))
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonFragment([
                'rut' => Rut::limpiarRut('12.345.678-5'),
                'nombre_completo' => 'María González López',
            ])
            ->assertJsonMissing(['rut' => $inactivePatientRut]);

        $this->getJson(route('api.v1.patients.search', ['q' => 'María González']))
            ->assertOk()
            ->assertJsonFragment([
                'rut' => Rut::limpiarRut('12.345.678-5'),
                'nombre_completo' => 'María González López',
            ]);

        $this->getJson(route('patients.api.search', ['q' => 'a']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('q');

        $this->getJson(route('patients.api.search', ['q' => '--']))
            ->assertOk()
            ->assertExactJson([]);

        foreach (range(1, 12) as $index) {
            DB::table('pacientes')->insert([
                'rut' => '91'.str_pad((string) $index, 7, '0', STR_PAD_LEFT),
                'nombre' => 'Paciente',
                'apellidoPaterno' => 'Carga',
                'apellidoMaterno' => 'Volumen',
                'fecha_nacimiento' => '1990-01-01',
                'activo' => true,
            ]);
        }

        $this->getJson(route('patients.api.search', ['q' => 'Carga Volumen']))
            ->assertOk()
            ->assertJsonCount(10);

        $this->get(route('dispensations.index', ['paciente_rut' => Rut::limpiarRut('12.345.678-5')]))
            ->assertOk()
            ->assertSee('id="patient-search"', false)
            ->assertSee('value="María González López · '.Rut::limpiarRut('12.345.678-5').'"', false)
            ->assertSee('name="paciente_rut"', false)
            ->assertSee('type="hidden"', false)
            ->assertSee('value="'.Rut::limpiarRut('12.345.678-5').'"', false);
    }

    public function test_versioned_person_lookup_prefills_existing_records_and_rejects_invalid_ruts(): void
    {
        $this->getJson(route('api.v1.people.lookup', ['rut' => '12.345.678-5']))
            ->assertOk()
            ->assertJsonPath('records.pacientes.nombre', 'María')
            ->assertJsonPath('records.pacientes.apellidoPaterno', 'González');

        $this->getJson(route('api.v1.people.lookup', ['rut' => 'invalid']))
            ->assertUnprocessable();
    }

    public function test_versioned_patient_search_returns_only_matching_active_patients(): void
    {
        $inactivePatientRut = Rut::limpiarRut('15.876.543-8');
        DB::table('pacientes')->where('rut', $inactivePatientRut)->update(['activo' => false]);

        $this->getJson(route('api.v1.patients.search', ['q' => 'María González']))
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonFragment([
                'rut' => Rut::limpiarRut('12.345.678-5'),
                'nombre_completo' => 'María González López',
            ])
            ->assertJsonMissing(['rut' => $inactivePatientRut]);

        $this->getJson(route('api.v1.patients.search', ['q' => 'a']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('q');
    }

    public function test_person_lookup_prefills_existing_records_and_user_registration_rejects_duplicate_ruts(): void
    {
        $this->getJson(route('people.lookup', ['rut' => '12.345.678-5']))
            ->assertOk()
            ->assertJsonPath('records.pacientes.nombre', 'María')
            ->assertJsonPath('records.pacientes.apellidoPaterno', 'González');

        $this->getJson(route('people.lookup', ['rut' => 'invalid']))
            ->assertUnprocessable();

        $this->get(route('users.create'))
            ->assertOk()
            ->assertSee('data-person-lookup', false)
            ->assertSee('data-person-type="usuarios"', false)
            ->assertSee('data-person-rut', false);

        $this->from(route('users.create'))->post(route('users.store'), [
            'rut' => '1.234.567-4',
            'nombre' => 'Usuario',
            'apellidoPaterno' => 'Duplicado',
            'apellidoMaterno' => 'Prueba',
            'password' => 'UnaClaveSegura!2026',
            'rol_id' => DB::table('roles')->value('rol_id'),
        ])->assertRedirect(route('users.create'))
            ->assertSessionHasErrors('rut');
    }

    public function test_health_personnel_can_be_registered_by_administrators_and_pharmacists_and_consulted_by_technicians(): void
    {
        $this->get(route('professionals.create'))
            ->assertOk()
            ->assertDontSee('Registro profesional')
            ->assertDontSee('registro_profesional')
            ->assertSee('<select class="form-control" id="tipo-profesional" name="tipo_profesional" required>', false)
            ->assertSee('value="Médico General"', false)
            ->assertSee('value="Médico Cirujano"', false)
            ->assertSee('value="Técnico en Enfermería (TENS)"', false);

        $professional = [
            'rut' => '10.000.013-K',
            'nombre' => 'Valentina',
            'apellidoPaterno' => 'Rojas',
            'apellidoMaterno' => 'Méndez',
            'tipo_profesional' => 'Enfermera',
        ];
        $this->post(route('professionals.store'), $professional)
            ->assertRedirect(route('professionals.index'));
        $this->assertDatabaseHas('personal_salud', [
            'rut' => '10000013K',
            'nombre' => 'Valentina',
            'tipo_profesional' => 'Enfermera',
        ]);
        $this->assertFalse(Schema::hasColumn('personal_salud', 'registro_profesional'));

        $pharmacistRoleId = DB::table('roles')->where('nombre', 'Químico Farmacéutico')->value('rol_id');
        DB::table('usuario_rol')->where('usuario_rut', '12345674')->delete();
        DB::table('usuario_rol')->insert(['usuario_rut' => '12345674', 'rol_id' => $pharmacistRoleId]);
        $this->post(route('professionals.store'), array_merge($professional, [
            'rut' => '11.111.111-1',
        ]))->assertRedirect(route('professionals.index'));
        $this->assertDatabaseHas('personal_salud', ['rut' => '111111111', 'tipo_profesional' => 'Enfermera']);

        $professionalRut = '10000013K';
        $this->get(route('professionals.edit', $professionalRut))
            ->assertOk()
            ->assertSee('Editar personal de salud')
            ->assertDontSee('Registro profesional')
            ->assertDontSee('registro_profesional');
        $this->put(route('professionals.update', $professionalRut), [
            'nombre' => 'Valentina Editada',
            'apellidoPaterno' => 'Rojas',
            'apellidoMaterno' => 'Méndez',
            'tipo_profesional' => 'Enfermera',
            'activo' => '1',
        ])->assertRedirect(route('professionals.index'));
        $this->assertDatabaseHas('personal_salud', ['rut' => $professionalRut, 'nombre' => 'Valentina Editada', 'activo' => true]);
        $this->patch(route('professionals.toggle', $professionalRut))->assertRedirect(route('professionals.index'));
        $this->assertDatabaseHas('personal_salud', ['rut' => $professionalRut, 'activo' => false]);

        $technicianRoleId = DB::table('roles')->where('nombre', 'Técnico Farmacéutico')->value('rol_id');
        DB::table('usuario_rol')->where('usuario_rut', '12345674')->delete();
        DB::table('usuario_rol')->insert(['usuario_rut' => '12345674', 'rol_id' => $technicianRoleId]);
        $this->get(route('module-hubs.show', 'personal'))
            ->assertRedirect(route('professionals.index'));
        $this->get(route('dashboard'))
            ->assertSee('Consultar personal')
            ->assertDontSee('Registrar personal');
        $this->get(route('professionals.index'))
            ->assertOk()
            ->assertSee('Valentina Editada Rojas Méndez')
            ->assertDontSee('REG-2001')
            ->assertDontSee('id="nuevo-personal"', false)
            ->assertDontSee('Registrar personal');
        $this->get(route('professionals.index', ['tipo_profesional' => 'Enfermera', 'buscar' => 'Valentina']))
            ->assertOk()
            ->assertSee('Valentina Editada Rojas Méndez')
            ->assertDontSee('Camila Fuentes Soto');
        $this->post(route('professionals.store'), array_merge($professional, ['rut' => '11.111.111-1']))
            ->assertForbidden();
        $this->get(route('professionals.index', ['estado' => 'inactivo']))
            ->assertOk();
        $this->get(route('professionals.edit', $professionalRut))->assertForbidden();
        $this->patch(route('professionals.toggle', $professionalRut))->assertForbidden();
        $this->assertDatabaseHas('personal_salud', ['rut' => '111111111', 'tipo_profesional' => 'Enfermera']);
    }

    public function test_primary_module_pages_render_their_database_data(): void
    {
        $this->get(route('patients.index'))
            ->assertOk()
            ->assertSee('Gestión de Pacientes')
            ->assertSee('action="'.route('patients.index').'"', false)
            ->assertSee('href="'.route('patients.create').'"', false)
            ->assertDontSee('id="nuevo-paciente"', false);
        $this->get(route('patients.create'))
            ->assertOk()
            ->assertSee('id="nuevo-paciente"', false)
            ->assertSee('Guardar paciente')
            ->assertDontSee('id="pacientes-registrados"', false);
        $this->get(route('inventory.index'))
            ->assertOk()
            ->assertSee('Inventario y Existencias')
            ->assertSee('1.380')
            ->assertDontSee('1.380,00');
        $this->get(route('inventory.catalog'))->assertOk()->assertSee('Productos del catálogo');
        $this->get(route('carts.index'))->assertOk()->assertSee('Carro de Paro y Unidosis');
        $this->get(route('dispensations.index'))
            ->assertOk()
            ->assertSee('Dispensaciones')
            ->assertSee('Confirmar dispensación')
            ->assertDontSee('id="entregas-recientes"', false);
        $this->get(route('dispensations.deliveries'))
            ->assertOk()
            ->assertSee('Consultar entregas recientes')
            ->assertSee('Fecha específica')
            ->assertSee('Paciente, RUT, producto, responsable u orden')
            ->assertDontSee('Nueva Dispensación');
        $this->get(route('programs.index'))->assertOk()->assertSee('Programas de Salud');
        $this->get(route('recipes.index'))->assertOk()->assertSee('Recetas Médicas');
        $this->get(route('supplies.index'))->assertOk()->assertSee('Remesas y CENABAST');
        $this->get(route('authorized-people.index'))->assertOk()->assertSee('Personas autorizadas');
        $this->get(route('alerts.index'))->assertRedirect(route('alerts.map'));
        $this->get(route('alerts.map'))->assertOk()->assertSee('Alertas');
        $this->get(route('modules.show', 'administracion'))->assertOk()->assertSee('Administración');
        $this->get(route('reports.deliveries'))->assertOk()->assertDontSee('id="movimientos-inventario"', false);
        $this->get(route('reports.movements'))->assertOk()->assertSee('id="movimientos-inventario"', false);
        $this->get(route('controlled-ledger.index'))->assertForbidden();
    }

    public function test_module_operations_have_independent_get_screens(): void
    {
        $screens = [
            ['patients.index', [], 'id="pacientes-registrados"'],
            ['patients.create', [], 'id="nuevo-paciente"'],
            ['professionals.index', [], 'id="personal-registrado"'],
            ['professionals.create', [], 'id="nuevo-personal"'],
            ['recipes.index', [], 'id="recetas-registradas"'],
            ['recipes.create', [], 'id="nueva-receta"'],
            ['programs.index', [], 'id="programas-disponibles"'],
            ['programs.affiliations.index', [], 'id="afiliaciones-recientes"'],
            ['programs.enroll.create', [], 'id="afiliar-paciente"'],
            ['programs.create', [], 'id="crear-programa"'],
            ['authorized-people.index', [], 'id="personas-autorizadas"'],
            ['authorized-people.create', [], 'id="nueva-persona-autorizada"'],
            ['supplies.index', [], 'id="remesas-recibidas"'],
            ['supplies.requests.index', [], 'id="solicitudes-reposicion"'],
            ['supplies.requests.create', [], 'id="solicitud-reposicion"'],
            ['supplies.receive.form', [], 'id="recepcion-remesa"'],
            ['inventory.index', [], 'id="existencias-inventario"'],
            ['locations.index', [], 'Ubicaciones registradas'],
            ['locations.create', [], 'Nueva ubicación'],
            ['inventory.catalog', [], 'id="catalogo-productos"'],
            ['categories.index', [], 'id="categorias"'],
            ['categories.create', [], 'Agregar categoría'],
            ['products.create', [], 'id="producto"'],
            ['inventory.transfer.form', [], 'id="traspaso"'],
            ['inventory.adjust.form', [], 'id="ajuste"'],
            ['carts.index', [], 'id="existencias-carros"'],
            ['carts.usage.index', [], 'id="uso-paro"'],
            ['carts.paro.create', [], 'id="registrar-uso-paro"'],
            ['carts.treatments.create', [], 'id="nuevo-tratamiento"'],
            ['carts.unidosis.create', [], 'id="preparacion-unidosis"'],
            ['carts.unidosis.index', [], 'id="preparaciones-unidosis"'],
            ['dispensations.index', [], 'id="registrar-entrega"'],
            ['dispensations.deliveries', [], 'id="entregas-recientes"'],
            ['reports.deliveries', [], 'id="entregas-dispensaciones"'],
            ['reports.stock', [], 'id="stock-por-categoria"'],
            ['reports.movements', [], 'id="movimientos-inventario"'],
            ['users.index', [], 'id="usuarios-registrados"'],
            ['users.create', [], 'id="crear-usuario"'],
        ];

        foreach ($screens as [$routeName, $parameters, $content]) {
            $this->get(route($routeName, $parameters))
                ->assertOk()
                ->assertSee($content, false);
        }
        $this->get('/reportes')->assertNotFound();
        $this->getJson('/api/v1/reports')->assertNotFound();

        $this->get(route('dispensations.index'))
            ->assertSee('data-add-row="dispensation-details"', false)
            ->assertDontSee('data-add-row="dispensation-details" hidden', false)
            ->assertSee('dispensation-prescription-row', false)
            ->assertSee('dispensation-field-delivery', false)
            ->assertSee('dispensation-transcription-status', false)
            ->assertSee('data-dispensation-prescription-heading', false)
            ->assertSee('data-prescription-fefo', false)
            ->assertSee('data-fefo-url=', false);

        $this->get(route('recipes.create'))
            ->assertSee('Cantidad administrada en cada toma; la frecuencia indica el intervalo entre dosis.');

        $this->get(route('supplies.receive.form'))
            ->assertSee('Cantidad de unidades por envase')
            ->assertSee('name="detalles[0][fecha_fabricacion]"', false)
            ->assertSee('name="detalles[0][unidades_por_envase]"', false)
            ->assertDontSee('Presentación de adquisición')
            ->assertDontSee('Unidades base adicionales')
            ->assertDontSee('Total recibido:')
            ->assertDontSee('Cantidad rechazada')
            ->assertDontSee('Motivo del rechazo');
        $this->get(route('inventory.receive.form'))
            ->assertRedirect(route('supplies.receive.form'));
        $this->get(route('products.create'))
            ->assertSee('Unidad base de inventario y dispensación')
            ->assertSee('Presentaciones comerciales')
            ->assertSee('Agregar nombre comercial')
            ->assertSee('Tipo de Empaquetado *')
            ->assertDontSee('Envases secundarios (opcional)')
            ->assertDontSee('Tipo de envase secundario (opcional)');
        $this->get(route('patients.index'))->assertDontSee('id="nuevo-paciente"', false);
        $this->get(route('patients.create'))->assertDontSee('id="pacientes-registrados"', false);
        $this->get(route('inventory.index'))->assertDontSee('id="catalogo-productos"', false);
        $this->get(route('supplies.requests.index'))->assertDontSee('id="remesas-recibidas"', false);
    }

    public function test_barcode_scanner_indicator_is_available_on_dispensation_and_shipment_receipt_forms(): void
    {
        $this->get(route('dispensations.index'))
            ->assertOk()
            ->assertSee('Lector de Código de Barras')
            ->assertSee('data-scanner-state="disconnected"', false)
            ->assertDontSee('data-scanner-test-button', false);

        $this->get(route('supplies.receive.form'))
            ->assertOk()
            ->assertSee('Lector de Código de Barras')
            ->assertSee('name="detalles[0][ubicacion_id]"', false)
            ->assertSee('Vitrina Farmacia')
            ->assertSee('Bodega Central')
            ->assertSee('data-scanner-state="disconnected"', false)
            ->assertDontSee('data-scanner-test-button', false);
        $this->get(route('dispensations.deliveries'))->assertDontSee('id="registrar-entrega"', false);

        DB::table('usuario_rol')->where('usuario_rut', auth()->id())->update([
            'rol_id' => DB::table('roles')->where('nombre', 'Químico Farmacéutico')->value('rol_id'),
        ]);
        foreach ([
            ['controlled-ledger.index', 'id="movimientos-controlados"'],
            ['controlled-ledger.create', 'id="nuevo-movimiento-controlado"'],
            ['controlled-ledger.reconcile.form', 'id="conciliar-controlados"'],
        ] as [$routeName, $content]) {
            $this->get(route($routeName))
                ->assertOk()
                ->assertSee($content, false);
        }
    }

    public function test_mobile_navigation_has_accessible_menu_controls(): void
    {
        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee('aria-controls="sidebar"', false)
            ->assertSee('aria-expanded="false"', false)
            ->assertSee('aria-label="Mostrar navegación"', false)
            ->assertSee('placeholder="Filtrar módulo clínico..."', false)
            ->assertSee('data-sidebar-search-empty', false)
            ->assertSee('class="sidebar-backdrop"', false)
            ->assertDontSee('Gestión Farmacéutica Prototipo');
    }

    public function test_sidebar_highlights_only_the_group_for_the_current_page(): void
    {
        $this->get(route('patients.index'))
            ->assertOk()
            ->assertSee('class="nav-group " role="group" aria-labelledby="nav-group-main-title"', false)
            ->assertSee('class="nav-group nav-group-active" role="group" aria-labelledby="nav-group-pacientes-title"', false)
            ->assertSee('aria-current="false" data-search-text="inicio panel de control"', false);

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee('class="nav-group nav-group-active" role="group" aria-labelledby="nav-group-main-title"', false)
            ->assertSee('aria-current="page" data-search-text="inicio panel de control"', false)
            ->assertSee('>Personas autorizadas</span>', false)
            ->assertSee('>Pacientes inscritos</span>', false)
            ->assertSee('>Abastecimiento y Ajustes Farmacéuticos</span>', false)
            ->assertSee('>Gestión de existencias</span>', false)
            ->assertSee('>Medicamentos e insumos</span>', false)
            ->assertSee('>Dispensación de medicamentos</span>', false)
            ->assertSee('>Historial de dispensaciones</span>', false)
            ->assertSee('>Informes de gestión</span>', false)
            ->assertDontSee('Exportar movimientos')
            ->assertSee('>Administración del sistema</span>', false)
            ->assertDontSee('>Alertas operativas</span>', false);

        $this->get(route('alerts.map'))
            ->assertOk()
            ->assertSee('class="nav-group nav-group-active" role="group" aria-labelledby="nav-group-main-title"', false)
            ->assertSee('aria-current="page" data-search-text="Principal Alertas operativas Mapa operativo', false);
    }

    public function test_sidebar_follows_the_requested_menu_order_and_nests_authorized_people_under_patients(): void
    {
        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSeeInOrder([
                route('dashboard'),
                route('alerts.map'),
                route('alerts.history'),
                route('dispensations.index'),
                route('carts.usage.index'),
                route('carts.paro.create'),
                route('carts.unidosis.create'),
                route('carts.unidosis.index'),
                route('patients.index'),
                route('patients.create'),
                route('carts.treatments.create'),
                route('programs.affiliations.index'),
                route('authorized-people.index'),
                route('programs.index'),
                route('carts.index'),
                route('inventory.index'),
                route('inventory.adjustments.index'),
                route('supplies.requests.index'),
                route('supplies.requests.create'),
                route('supplies.receive.form'),
                route('supplies.index'),
                route('professionals.index'),
                route('users.index'),
                route('reports.deliveries'),
            ])
            ->assertSee('>Personas autorizadas</span>', false)
            ->assertDontSee('>Alertas operativas</span>', false);
    }

    public function test_demo_seeder_adds_example_data_and_is_idempotent(): void
    {
        $this->seed(DemoDataSeeder::class);
        $this->seed(DemoDataSeeder::class);

        $this->assertSame(5, DB::table('productos')->where('numero_serie', 'like', 'DEMO-%')->count());
        $this->assertSame(5, DB::table('pacientes')->whereIn('nombre', [
            'Elena', 'Roberto', 'Patricia', 'Óscar', 'Inés',
        ])->count());
        $this->assertSame(2, DB::table('remesas')->where('codigo_remesa', 'like', 'DEMO-REM-%')->count());
        $this->assertSame(5, DB::table('movimientos_inventario')->where('referencia_tipo', 'DEMO_DATA')->count());
        $this->assertSame(4, DB::table('solicitudes_reposicion')
            ->join('solicitud_reposicion_detalle', 'solicitud_reposicion_detalle.solicitud_id', '=', 'solicitudes_reposicion.solicitud_id')
            ->whereIn('solicitud_reposicion_detalle.producto_id', DB::table('productos')->where('numero_serie', 'like', 'DEMO-%')->select('producto_id'))
            ->count());
    }

    public function test_module_operations_move_to_the_role_aware_sidebar(): void
    {
        $defaultRoutes = [
            'pacientes' => 'patients.index',
            'personal' => 'professionals.index',
            'recetas' => 'recipes.index',
            'programas' => 'programs.index',
            'personas-autorizadas' => 'authorized-people.index',
            'remesas' => 'supplies.index',
            'inventario' => 'inventory.index',
            'carros' => 'carts.index',
            'dispensaciones' => 'dispensations.index',
            'reportes' => 'reports.deliveries',
            'administracion' => 'users.index',
            'alertas' => 'alerts.map',
        ];
        foreach ($defaultRoutes as $module => $routeName) {
            $this->get(route('module-hubs.show', $module))
                ->assertRedirect(route($routeName));
        }

        $this->get(route('module-hubs.show', 'libro-digital'))->assertForbidden();
        $this->get(route('alerts.index'))->assertRedirect(route('alerts.map'));
        $this->get(route('alerts.map'))
            ->assertOk()
            ->assertSee('Mapa operativo')
            ->assertSee(route('alerts.history'), false)
            ->assertDontSee('Mapa crítico')
            ->assertDontSee('alert-history-card', false)
            ->assertDontSee('Volver a Alertas');

        $this->get(route('dashboard'))
            ->assertSee(route('categories.create'), false)
            ->assertSee(route('locations.index'), false)
            ->assertSee(route('inventory.transfer.form'), false)
            ->assertSee(route('supplies.receive.form'), false)
            ->assertSee(route('reports.kardex'), false)
            ->assertSee(route('alerts.history'), false);

        $pharmacistRoleId = DB::table('roles')->where('nombre', 'Químico Farmacéutico')->value('rol_id');
        $technicianRoleId = DB::table('roles')->where('nombre', 'Técnico Farmacéutico')->value('rol_id');
        $adminRoleId = DB::table('roles')->where('nombre', 'Administrador')->value('rol_id');
        DB::table('usuario_rol')->where('usuario_rut', '12345674')->update(['rol_id' => $pharmacistRoleId]);
        $this->get(route('dashboard'))
            ->assertSee(route('categories.create'), false)
            ->assertSee(route('locations.index'), false)
            ->assertDontSee(route('products.create'), false)
            ->assertSee(route('alerts.history'), false);
        DB::table('usuario_rol')->where('usuario_rut', '12345674')->update(['rol_id' => $technicianRoleId]);
        $this->get(route('dashboard'))
            ->assertDontSee(route('categories.create'), false)
            ->assertDontSee(route('locations.index'), false)
            ->assertDontSee(route('inventory.transfer.form'), false)
            ->assertDontSee(route('alerts.history'), false)
            ->assertSee(route('inventory.adjustments.index'), false)
            ->assertSee(route('supplies.requests.create'), false)
            ->assertSee(route('supplies.receive.form'), false);
        DB::table('usuario_rol')->where('usuario_rut', '12345674')->update(['rol_id' => $adminRoleId]);
        $this->get(route('dashboard'))
            ->assertSee(route('users.index'), false)
            ->assertSee(route('users.create'), false)
            ->assertSee(route('carts.paro.create'), false);
    }

    // Verifica permisos de recepción, ajustes y traspasos según el rol.
    public function test_inventory_and_supply_operations_follow_role_permissions(): void
    {
        $userRut = auth()->id();
        $technicianRoleId = DB::table('roles')->where('nombre', 'Técnico Farmacéutico')->value('rol_id');
        $pharmacistRoleId = DB::table('roles')->where('nombre', 'Químico Farmacéutico')->value('rol_id');

        DB::table('usuario_rol')->where('usuario_rut', $userRut)->update(['rol_id' => $technicianRoleId]);

        $this->get(route('supplies.requests.create'))->assertOk();
        $this->get(route('supplies.receive.form'))->assertOk();
        $this->get(route('supplies.requests.products.search', ['q' => 'pa']))->assertOk();
        $this->get(route('inventory.adjust.form'))->assertOk();
        $this->get(route('inventory.adjustments.index'))->assertOk();
        $this->patch(route('inventory.adjustments.resolve', 1), ['decision' => 'APROBAR'])->assertForbidden();
        $this->get(route('inventory.transfer.form'))->assertForbidden();
        $this->from(route('inventory.adjust.form'))->post(route('inventory.adjust'), [])
            ->assertRedirect(route('inventory.adjust.form'))
            ->assertSessionHasErrors('lote_id');
        $this->from(route('inventory.index'))->post(route('inventory.transfer'), [])->assertForbidden();
        $this->from(route('inventory.index'))->post(route('inventory.receive'), [])
            ->assertRedirect(route('inventory.index'))
            ->assertSessionHasErrors('producto_id');
        $this->from(route('supplies.requests.create'))->post(route('supplies.requests.store'), [])
            ->assertRedirect(route('supplies.requests.create'))
            ->assertSessionHasErrors('detalles');
        $this->from(route('supplies.receive.form'))->post(route('supplies.receive'), [])
            ->assertRedirect(route('supplies.receive.form'))
            ->assertSessionHasErrors('codigo_remesa');
        $this->assertDatabaseHas('auditoria', [
            'accion' => 'ACCESO_DENEGADO',
            'entidad' => 'traspasos_inventario',
            'resultado' => 'RECHAZADO',
        ]);

        DB::table('usuario_rol')->where('usuario_rut', $userRut)->update(['rol_id' => $pharmacistRoleId]);
        $this->get(route('inventory.adjust.form'))->assertOk();
        $this->get(route('inventory.adjustments.index'))->assertOk();
        $this->get(route('inventory.transfer.form'))->assertOk();
        $this->from(route('inventory.adjust.form'))->post(route('inventory.adjust'), [])
            ->assertRedirect(route('inventory.adjust.form'))
            ->assertSessionHasErrors('lote_id');
        $this->from(route('inventory.transfer.form'))->post(route('inventory.transfer'), [])
            ->assertRedirect(route('inventory.transfer.form'))
            ->assertSessionHasErrors('lote_id');
        $this->get(route('carts.paro.create'))
            ->assertOk()
            ->assertSee('id="registrar-uso-paro"', false)
            ->assertSee('Registrar uso del Carro de Paro')
            ->assertDontSee('id="existencias-carros"', false);
    }

    public function test_program_creation_and_authorized_person_registration_follow_role_permissions(): void
    {
        $adminRut = '12345674';
        $adminRoleId = DB::table('roles')->where('nombre', 'Administrador')->value('rol_id');
        $pharmacistRoleId = DB::table('roles')->where('nombre', 'Químico Farmacéutico')->value('rol_id');
        $technicianRoleId = DB::table('roles')->where('nombre', 'Técnico Farmacéutico')->value('rol_id');

        DB::table('usuario_rol')->where('usuario_rut', $adminRut)->update(['rol_id' => $technicianRoleId]);
        $this->get(route('module-hubs.show', 'programas'))
            ->assertRedirect(route('programs.index'));
        $this->get(route('dashboard'))
            ->assertDontSee(route('programs.create'), false);
        $this->get(route('programs.index'))
            ->assertOk()
            ->assertDontSee('id="crear-programa"', false);
        $this->post(route('programs.store'), ['nombre' => 'Programa no permitido'])
            ->assertForbidden();

        $this->get(route('module-hubs.show', 'personas-autorizadas'))
            ->assertRedirect(route('authorized-people.index'));
        $this->get(route('dashboard'))
            ->assertSee(route('authorized-people.create'), false);
        $this->get(route('authorized-people.create'))
            ->assertOk()
            ->assertSee('id="nueva-persona-autorizada"', false);
        $this->post(route('authorized-people.store'), [
            'paciente_rut' => '123456785',
            'rut' => '10.555.555-5',
            'nombre' => 'Representante',
            'apellidoPaterno' => 'Técnico',
            'apellidoMaterno' => 'Prueba',
            'vinculo' => 'Cuidador',
        ])->assertRedirect(route('authorized-people.index'));
        $this->assertDatabaseHas('personas_autorizadas', [
            'rut' => '105555555',
            'paciente_rut' => '123456785',
            'nombre' => 'Representante',
        ]);

        DB::table('usuario_rol')->where('usuario_rut', $adminRut)->update(['rol_id' => $pharmacistRoleId]);
        $this->get(route('module-hubs.show', 'programas'))
            ->assertRedirect(route('programs.index'));
        $this->get(route('dashboard'))
            ->assertSee(route('programs.create'), false);
        $this->get(route('programs.create'))
            ->assertOk()
            ->assertSee('id="crear-programa"', false);
        $this->post(route('programs.store'), ['nombre' => 'Programa farmacéutico'])
            ->assertRedirect(route('programs.index'));
        $this->assertDatabaseHas('programas_salud', ['nombre' => 'Programa farmacéutico']);
        $programId = DB::table('programas_salud')->where('nombre', 'Programa farmacéutico')->value('programa_id');
        $this->get(route('programs.edit', $programId))
            ->assertOk()
            ->assertSee('Editar programa')
            ->assertSee('value="Programa farmacéutico"', false);
        $this->put(route('programs.update', $programId), [
            'nombre' => 'Programa farmacéutico editado',
            'descripcion' => 'Descripción actualizada',
        ])->assertRedirect(route('programs.index'));
        $this->assertDatabaseHas('programas_salud', ['programa_id' => $programId, 'nombre' => 'Programa farmacéutico editado']);
        $this->patch(route('programs.toggle', $programId))->assertRedirect(route('programs.index'));
        $this->assertDatabaseHas('programas_salud', ['programa_id' => $programId, 'activo' => false]);
        $this->get(route('authorized-people.create'))
            ->assertForbidden()
            ->assertSee('ACCESO NO AUTORIZADO')
            ->assertSee('logo.jpg?v=', false);

        DB::table('usuario_rol')->where('usuario_rut', $adminRut)->update(['rol_id' => $adminRoleId]);
    }

    public function test_unknown_urls_render_the_custom_not_found_page(): void
    {
        $this->get('/efwefe')
            ->assertNotFound()
            ->assertSee('NO SE ENCONTRÓ LA PÁGINA')
            ->assertSee('logo.jpg?v=', false);
    }

    public function test_forms_expose_rut_validation_and_usb_barcode_scanner(): void
    {
        $this->get(route('patients.create'))
            ->assertOk()
            ->assertSee('data-rut-input', false)
            ->assertSee('data-rut-error', false);

        $unidosisProductId = DB::table('productos')->where('numero_serie', 'MED-001')->value('producto_id');
        $unidosisLocationId = DB::table('carros')->where('tipo_carro', 'UNIDOSIS')->value('ubicacion_id');
        DB::table('tratamientos')->insert([
            'paciente_rut' => '123456785',
            'descripcion' => 'Tratamiento para revisar el formulario',
            'fecha_inicio' => now()->toDateString(),
            'activo' => true,
        ]);
        $unidosisLotId = DB::table('lotes')->insertGetId([
            'producto_id' => $unidosisProductId,
            'numero_lote' => 'FORM-UNIDOSIS-01',
            'fecha_vencimiento' => now()->addMonth()->toDateString(),
        ]);
        DB::table('inventario')->insert([
            'lote_id' => $unidosisLotId,
            'ubicacion_id' => $unidosisLocationId,
            'cantidad' => 1,
        ]);

        $this->get(route('carts.unidosis.create'))
            ->assertOk()
            ->assertSee('data-product-search-input', false)
            ->assertSee('unidosis-detail-row', false)
            ->assertSee('name="detalles[0][horario]" type="time"', false)
            ->assertDontSee('name="detalles[0][dosis]"', false);

        $dispensationForm = $this->get(route('dispensations.index'))
            ->assertOk()
            ->assertSee('data-product-select', false)
            ->assertSee('data-product-search-input', false)
            ->assertSee('<dialog', false)
            ->assertSee('name="autorizador_rut"', false)
            ->assertSee('name="autorizador_password"', false)
            ->assertSee('data-prescription-verification', false)
            ->assertSee('name="confirmacion_receta"', false)
            ->assertSee('data-toggle-authorized-person', false)
            ->assertSee('name="nueva_persona_autorizada[rut]"', false)
            ->assertSee('name="nueva_persona_autorizada[vinculo]"', false)
            ->assertSee('name="observacion"', false)
            ->assertSee('data-prescription-fefo', false)
            ->assertDontSee('data-product-availability', false)
            ->assertSee('data-barcode-url', false)
            ->assertDontSee('data-barcode-input', false)
            ->assertDontSee('Leer código')
            ->assertSee('data-dispensation-details', false)
            ->assertSee('Medicamentos prescritos')
            ->assertSee('Verificación de la receta presentada')
            ->assertSee('data-recipe-medicine-rows', false)
            ->assertSee('Fármaco prescrito')
            ->assertSee('Indicaciones médicas')
            ->assertSee('cotejé la información registrada con la receta presentada')
            ->assertSee('Selecciona un paciente para cargar sus recetas vigentes.')
            ->assertSee('Buscar medicamento por nombre')
            ->assertSee('data-name="cantidad"', false)
            ->assertSee('data-name="unidades_por_toma"', false)
            ->assertSee('Dosis por toma')
            ->assertDontSee('tomas_diarias')
            ->assertSee('data-name="duracion_dias"', false)
            ->assertSee('data-name="frecuencia"', false)
            ->assertSee('data-name="cantidad_indicada"', false)
            ->assertSee('data-toggle-sos-treatment', false)
            ->assertDontSee('data-name="es_sos"', false)
            ->assertSee('data-name="indicaciones"', false);
        $dispensationForm->assertDontSee('Selecciona del catálogo completo');
        $this->get(route('dispensations.deliveries'))
            ->assertOk()
            ->assertDontSee('data-controlled-auth', false);
    }

    public function test_dispensation_product_search_returns_name_and_vitrine_stock_without_loading_the_catalog(): void
    {
        $response = $this->getJson(route('dispensations.products.search', ['q' => 'Losartán']));
        $response->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.nombre', 'Losartán 50 mg')
            ->assertJsonPath('0.stock_disponible', 300)
            ->assertJsonStructure([['unidad_base_codigo']]);

        $this->get(route('dispensations.index'))
            ->assertOk()
            ->assertDontSee('Amoxicilina 500 mg')
            ->assertSee('data-product-search-input', false);
    }

    public function test_seeded_administrator_can_sign_in(): void
    {
        auth()->logout();

        $this->post(route('login.store'), [
            'rut' => ' 1.234.567-4 ',
            'password' => self::ADMIN_TEST_PASSWORD,
        ])->assertRedirect(route('dashboard'));

        $this->assertAuthenticated();
    }

    public function test_api_authenticates_with_the_existing_session_and_serves_dashboard_json(): void
    {
        auth()->logout();

        $this->get('/api/v1/csrf-cookie')
            ->assertNoContent()
            ->assertCookie('XSRF-TOKEN');

        $this->getJson('/api/v1/user')->assertUnauthorized();

        $this->postJson('/api/v1/login', [
            'rut' => '1.234.567-4',
            'password' => self::ADMIN_TEST_PASSWORD,
        ])->assertOk()
            ->assertJsonPath('data.rut', '12345674')
            ->assertJsonPath('data.nombre', 'Administración CESFAM Farmacia')
            ->assertJsonPath('data.roles.0', 'Administrador');

        $this->getJson('/api/v1/dashboard')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'stockCount',
                    'deliveries',
                    'dispensationActivity',
                    'selectedActivityPeriod',
                    'stockByCategory',
                    'mostDispensed',
                ],
            ]);

        $this->postJson('/api/v1/logout')
            ->assertOk()
            ->assertJsonPath('data.message', 'Sesión cerrada');

        $this->getJson('/api/v1/user')->assertUnauthorized();
    }

    public function test_web_and_api_login_routes_throttle_repeated_account_attempts(): void
    {
        auth()->logout();
        $credentials = ['rut' => '1.234.567-4', 'password' => 'incorrecta'];

        for ($attempt = 0; $attempt < 10; $attempt++) {
            $this->postJson('/api/v1/login', $credentials)->assertUnprocessable();
        }

        $this->post('/login', $credentials)->assertStatus(429);
    }

    public function test_production_rejects_debug_and_insecure_session_configuration(): void
    {
        $this->app->detectEnvironment(static fn () => 'production');
        config([
            'app.env' => 'production',
            'app.debug' => true,
            'session.secure' => true,
            'trustedproxy.proxies' => ['10.0.1.10'],
        ]);
        try {
            (new AppServiceProvider($this->app))->boot();
            $this->fail('Production must reject APP_DEBUG=true.');
        } catch (\LogicException $exception) {
            $this->assertSame('APP_DEBUG must be false in production.', $exception->getMessage());
        }

        config([
            'app.debug' => false,
            'session.secure' => false,
        ]);
        try {
            (new AppServiceProvider($this->app))->boot();
            $this->fail('Production must reject an insecure session cookie.');
        } catch (\LogicException $exception) {
            $this->assertSame('SESSION_SECURE_COOKIE must be true in production.', $exception->getMessage());
        }

        config([
            'session.secure' => true,
            'trustedproxy.proxies' => [],
        ]);
        try {
            (new AppServiceProvider($this->app))->boot();
            $this->fail('Production must reject an empty trusted proxy list.');
        } catch (\LogicException $exception) {
            $this->assertSame(
                'Set TRUSTED_PROXIES to the private frontend proxy addresses in production.',
                $exception->getMessage(),
            );
        }

        foreach (['*', '0.0.0.0/0', 'REMOTE_ADDR'] as $unsafeProxy) {
            config(['trustedproxy.proxies' => [$unsafeProxy]]);
            try {
                (new AppServiceProvider($this->app))->boot();
                $this->fail('Production must reject wildcard trusted proxies.');
            } catch (\LogicException $exception) {
                $this->assertSame('TRUSTED_PROXIES must not trust all IP addresses.', $exception->getMessage());
            }
        }
    }

    public function test_administrator_seeder_migrates_legacy_rut_and_preserves_password(): void
    {
        $oldRut = '111111111';
        $newRut = '12345674';
        $adminRoleId = DB::table('roles')->where('nombre', 'Administrador')->value('rol_id');
        $passwordHash = Hash::make('existing-admin-password');

        DB::table('usuario_rol')->where('usuario_rut', $newRut)->delete();
        DB::table('usuarios')->where('rut', $newRut)->delete();
        DB::table('usuarios')->insert([
            'rut' => $oldRut,
            'nombre' => 'Administración',
            'apellidoPaterno' => 'CESFAM',
            'apellidoMaterno' => 'Farmacia',
            'password_hash' => $passwordHash,
            'activo' => true,
        ]);
        DB::table('usuario_rol')->insert(['usuario_rut' => $oldRut, 'rol_id' => $adminRoleId]);

        $this->seed(\Database\Seeders\AdministratorSeeder::class);

        $this->assertDatabaseMissing('usuarios', ['rut' => $oldRut]);
        $this->assertDatabaseHas('usuarios', ['rut' => $newRut, 'password_hash' => $passwordHash]);
        $this->assertDatabaseHas('usuario_rol', ['usuario_rut' => $newRut, 'rol_id' => $adminRoleId]);
    }

    public function test_rut_is_canonicalized_and_validated_with_modulo_11(): void
    {
        $this->assertSame('11111111K', Rut::limpiarRut(' 11111111-k '));
        $this->assertTrue(Rut::validarRut('11.111.111-1'));
        $this->assertTrue(Rut::validarRut('111111111'));
        $this->assertTrue(Rut::validarRut('10000013K'));
        $this->assertTrue(Rut::validarRut('10.000.004-0'));
        $this->assertTrue(Rut::validarRut(' 10000013-k '));
        $this->assertFalse(Rut::validarRut('17.492.355-K'));
        $this->assertFalse(Rut::validarRut('20.143.680-0'));
        $this->assertFalse(Rut::validarRut(' 11111111-k '));
        $this->assertFalse(Rut::validarRut('11.111.111-2'));
        $this->assertFalse(Rut::validarRut('111111111; DROP TABLE usuarios'));
    }

    public function test_quantity_formatting_omits_unnecessary_decimal_zeroes(): void
    {
        $this->assertSame('1.200', NumberFormat::format('1200.00'));
        $this->assertSame('12,5', NumberFormat::format('12.50'));
        $this->assertSame('—', NumberFormat::format(null));
    }

    public function test_invalid_rut_is_rejected_before_registration_and_login(): void
    {
        $this->post(route('patients.store'), [
            'rut' => '11.111.111-2',
            'nombre' => 'Inválido',
            'apellidoPaterno' => 'De Prueba',
            'apellidoMaterno' => 'CESFAM',
        ])->assertSessionHasErrors('rut');
        $this->assertDatabaseMissing('pacientes', ['rut' => '111111112']);

        auth()->logout();
        $this->post(route('login.store'), [
            'rut' => '11.111.111-2',
            'password' => self::ADMIN_TEST_PASSWORD,
        ])->assertSessionHasErrors(['rut' => 'Credenciales inválidas']);
        $this->get(route('login'))
            ->assertOk()
            ->assertSee('notification-toast-error', false)
            ->assertSee('Revisa la información ingresada:')
            ->assertDontSee('class="flash flash-error"', false);
        $this->assertGuest();

        $this->post(route('login.store'), [
            'rut' => '1.234.567-4',
            'password' => 'incorrecta',
        ])->assertSessionHasErrors(['rut' => 'Credenciales inválidas']);
    }

    public function test_administrator_can_create_user_with_schema_name_fields(): void
    {
        $roleId = DB::table('roles')->where('nombre', 'Técnico Farmacéutico')->value('rol_id');
        $pharmacistRoleId = DB::table('roles')->where('nombre', 'Químico Farmacéutico')->value('rol_id');

        $this->get(route('users.create'))
            ->assertOk()
            ->assertSee('data-chilean-mobile-phone', false)
            ->assertSee('pattern="[0-9]{8}"', false)
            ->assertSee('>+56 9</span>', false)
            ->assertSee('<option value="'.$roleId.'"', false)
            ->assertSee('Técnico Farmacéutico')
            ->assertSee('<option value="'.$pharmacistRoleId.'"', false)
            ->assertSee('Químico Farmacéutico');

        $this->post(route('users.store'), [
            'rut' => '10.555.555-5',
            'nombre' => 'Técnico',
            'apellidoPaterno' => 'Nuevo',
            'apellidoMaterno' => 'CESFAM',
            'telefono' => '12345678',
            'password' => 'UnaClaveSegura!2026',
            'rol_id' => $roleId,
        ])->assertRedirect(route('users.index'));

        $userRut = DB::table('usuarios')->where('rut', '105555555')->value('rut');
        $this->assertDatabaseHas('usuarios', [
            'rut' => '105555555',
            'nombre' => 'Técnico',
            'apellidoPaterno' => 'Nuevo',
            'apellidoMaterno' => 'CESFAM',
            'telefono' => '+56 9 12345678',
        ]);
        $this->assertDatabaseHas('usuario_rol', ['usuario_rut' => $userRut, 'rol_id' => $roleId]);
        $this->assertSame($userRut, Usuario::findOrFail($userRut)->getKey());
    }

    public function test_user_creation_explains_required_maternal_surname_and_preserves_submitted_fields(): void
    {
        $roleId = DB::table('roles')->where('nombre', 'Técnico Farmacéutico')->value('rol_id');

        $this->from(route('users.create'))
            ->post(route('users.store'), [
                'rut' => '10.555.555-5',
                'nombre' => 'Técnico',
                'apellidoPaterno' => 'Nuevo',
                'telefono' => '912345678',
                'password' => 'UnaClaveSegura!2026',
                'rol_id' => $roleId,
            ])
            ->assertRedirect(route('users.create'))
            ->assertSessionHasErrors(['apellidoMaterno' => 'El campo Apellido materno es obligatorio.'])
            ->assertSessionHas('_old_input.nombre', 'Técnico');

        $this->assertDatabaseMissing('usuarios', ['rut' => '105555555']);

        $this->get(route('users.create'))
            ->assertOk()
            ->assertSee('Apellido materno *')
            ->assertSee('El campo Apellido materno es obligatorio.')
            ->assertSee('value="Técnico"', false);
    }

    public function test_administrator_user_list_can_search_and_filter_by_status_and_role(): void
    {
        $roleId = DB::table('roles')->where('nombre', 'Químico Farmacéutico')->value('rol_id');
        $inactiveUserRut = '109999999';
        DB::table('usuarios')->insert([
            'rut' => $inactiveUserRut,
            'nombre' => 'Cuenta',
            'apellidoPaterno' => 'Desactivada',
            'apellidoMaterno' => 'Prueba',
            'telefono' => '900000001',
            'password_hash' => Hash::make('UnaClaveSegura!2026'),
            'activo' => false,
        ]);
        DB::table('usuario_rol')->insert(['usuario_rut' => $inactiveUserRut, 'rol_id' => $roleId]);

        $this->get(route('users.index', [
            'buscar' => 'Desactivada',
            'estado' => 'inactivo',
            'rol_id' => $roleId,
        ]))
            ->assertOk()
            ->assertSee('Cuenta Desactivada Prueba')
            ->assertSee('Desactivado');

        $this->get(route('users.index', [
            'buscar' => 'Desactivada',
            'estado' => 'activo',
        ]))
            ->assertOk()
            ->assertDontSee('Cuenta Desactivada Prueba');

        DB::table('usuarios')->insert([
            'rut' => '10000130',
            'nombre' => 'Cuenta',
            'apellidoPaterno' => 'RutFormateado',
            'apellidoMaterno' => 'Prueba',
            'password_hash' => Hash::make('UnaClaveSegura!2026'),
            'activo' => true,
        ]);
        $this->get(route('users.index', ['buscar' => '10000130']))
            ->assertOk()
            ->assertSee('Cuenta RutFormateado Prueba');
        $this->get(route('users.index', ['buscar' => '1000013-0']))
            ->assertOk()
            ->assertSee('Cuenta RutFormateado Prueba');
    }

    public function test_patient_staff_and_user_lists_render_actionable_cards_and_keep_filters(): void
    {
        $this->get(route('programs.index'))
            ->assertOk()
            ->assertSee('<div class="person-card-grid">', false)
            ->assertSee('name="buscar_programa"', false)
            ->assertSee('name="estado_programa"', false)
            ->assertSee('Pacientes activos')
            ->assertDontSee('<table class="data-table">', false);

        $this->get(route('programs.affiliations.index'))
            ->assertOk()
            ->assertSee('<div class="person-card-grid">', false)
            ->assertSee('name="buscar_afiliacion"', false)
            ->assertSee('name="programa_id_afiliacion"', false)
            ->assertSee('name="estado_afiliacion"', false)
            ->assertSee('name="desde_afiliacion"', false)
            ->assertSee('name="hasta_afiliacion"', false)
            ->assertDontSee('<table class="data-table">', false);

        $this->get(route('patients.index'))
            ->assertOk()
            ->assertSee('<div class="person-card-grid searchable-cards">', false)
            ->assertSee('name="buscar"', false)
            ->assertSee('name="programa_id"', false)
            ->assertSee('Dispensar')
            ->assertSee('Ficha de Paciente')
            ->assertDontSee('>Editar</a>', false)
            ->assertDontSee('<table class="data-table searchable-table">', false);

        $this->get(route('professionals.index'))
            ->assertOk()
            ->assertSee('<div class="person-card-grid searchable-cards">', false)
            ->assertSee('name="buscar"', false)
            ->assertSee('name="tipo_profesional"', false)
            ->assertSee('Editar')
            ->assertSee('Desactivar')
            ->assertDontSee('<table class="data-table searchable-table">', false);

        $this->get(route('users.index'))
            ->assertOk()
            ->assertSee('<div class="person-card-grid searchable-cards">', false)
            ->assertSee('name="buscar"', false)
            ->assertSee('name="estado"', false)
            ->assertSee('name="rol_id"', false)
            ->assertSee('Editar')
            ->assertDontSee('<table class="data-table searchable-table">', false);
    }

    public function test_authorized_authorized_person_list_can_search_and_filter_by_patient_and_status(): void
    {
        $patientId = DB::table('pacientes')->where('rut', '123456785')->value('rut');
        DB::table('personas_autorizadas')->insert([
            'paciente_rut' => $patientId,
            'rut' => '109876543',
            'nombre' => 'Representante',
            'apellidoPaterno' => 'Revocado',
            'apellidoMaterno' => 'Prueba',
            'vinculo' => 'Cuidador',
            'activo' => false,
        ]);

        $this->get(route('patients.index'))
            ->assertOk()
            ->assertSee('Personas autorizadas')
            ->assertSee('Representante Revocado Prueba')
            ->assertSee('RUT 109876543 · Cuidador')
            ->assertSee('Inactivo')
            ->assertDontSee('Ver autorizaciones');

        $this->get(route('authorized-people.index', [
            'buscar' => 'Revocado',
            'paciente_rut' => $patientId,
            'estado' => 'inactivo',
        ]))
            ->assertOk()
            ->assertSee('<div class="person-card-grid searchable-cards">', false)
            ->assertSee('name="buscar"', false)
            ->assertSee('name="paciente_rut"', false)
            ->assertSee('name="estado"', false)
            ->assertSee('Representante Revocado Prueba')
            ->assertSee('Inactivo')
            ->assertSee('Vínculo: Cuidador')
            ->assertSee('Paciente representado')
            ->assertSee('Editar')
            ->assertSee('Activar')
            ->assertDontSee('<table class="data-table">', false);

        $this->get(route('authorized-people.index', ['estado' => 'activo']))
            ->assertOk()
            ->assertDontSee('Representante Revocado Prueba');
    }

    public function test_database_backup_command_refuses_non_mysql_connections(): void
    {
        config(['database.default' => 'sqlite']);

        $this->assertSame(1, Artisan::call('farmacia:backup'));
        $this->assertStringContainsString('DB_CONNECTION=mysql', Artisan::output());
    }

    public function test_seeding_preserves_existing_operational_data_and_credentials(): void
    {
        $lotId = DB::table('lotes')->where('numero_lote', 'L250821')->value('lote_id');
        $locationId = DB::table('ubicaciones')->where('tipo', 'BODEGA')->value('ubicacion_id');
        $adminId = DB::table('usuarios')->where('rut', '12345674')->value('rut');
        $adminRoleId = DB::table('roles')->where('nombre', 'Administrador')->value('rol_id');
        $existingHash = Hash::make('Existing-password-2026!');

        DB::table('inventario')->where('lote_id', $lotId)->where('ubicacion_id', $locationId)->update(['cantidad' => 321]);
        DB::table('pacientes')->where('rut', '123456785')->update(['nombre' => 'Nombre actualizado']);
        DB::table('usuarios')->where('rut', $adminId)->update(['password_hash' => $existingHash, 'activo' => false]);
        DB::table('usuario_rol')->where('usuario_rut', $adminId)->where('rol_id', $adminRoleId)->delete();

        $this->seed();

        $this->assertDatabaseHas('inventario', ['lote_id' => $lotId, 'ubicacion_id' => $locationId, 'cantidad' => 321]);
        $this->assertDatabaseHas('pacientes', ['rut' => '123456785', 'nombre' => 'Nombre actualizado']);
        $this->assertDatabaseHas('usuarios', ['rut' => $adminId, 'password_hash' => $existingHash, 'activo' => false]);
        $this->assertDatabaseHas('usuario_rol', ['usuario_rut' => $adminId, 'rol_id' => $adminRoleId]);
    }

    public function test_patient_can_be_registered_and_duplicate_rut_is_rejected(): void
    {
        $patient = [
            'rut' => ' 8.123.456-6 ',
            'nombre' => 'Paciente',
            'apellidoPaterno' => 'De Prueba',
            'apellidoMaterno' => 'Apellido',
            'fecha_nacimiento' => '1990-01-01',
            'telefono' => '912345678',
        ];

        $this->post(route('patients.store'), $patient)->assertRedirect(route('patients.index'));
        $this->get(route('patients.index'))
            ->assertOk()
            ->assertSee('data-notification-toast', false)
            ->assertSee('Operación realizada con éxito.')
            ->assertSee('Paciente registrado correctamente.');
        $this->assertDatabaseHas('pacientes', [
            'rut' => '81234566',
            'nombre' => 'Paciente',
            'apellidoPaterno' => 'De Prueba',
            'apellidoMaterno' => 'Apellido',
            'telefono' => '+56 9 12345678',
        ]);
        $this->assertDatabaseHas('fichas_pacientes', ['paciente_rut' => '81234566']);
        $this->post(route('patients.store'), array_merge($patient, ['telefono' => '912ABC456']))
            ->assertSessionHasErrors('telefono');
        $this->post(route('patients.store'), $patient)->assertSessionHasErrors('rut');
        $this->get(route('patients.index'))
            ->assertOk()
            ->assertSee('notification-toast-error', false)
            ->assertSee('Revisa la información ingresada:')
            ->assertDontSee('class="flash flash-error"', false);
        $this->post(route('patients.store'), array_merge($patient, ['rut' => '8 123456-6']))
            ->assertSessionHasErrors('rut');
    }

    public function test_patient_file_archives_old_records_and_keeps_history_visible(): void
    {
        $patientRut = Rut::limpiarRut('12.345.678-5');
        $fichaId = DB::table('fichas_pacientes')->where('paciente_rut', $patientRut)->value('ficha_id');
        $professionalRut = $patientRut;
        $oldPrescriptionId = DB::table('recetas')->insertGetId([
            'paciente_rut' => $patientRut,
            'ficha_id' => $fichaId,
            'profesional_rut' => $professionalRut,
            'numero_receta' => 'FICHA-RECETA-ANTIGUA',
            'fecha_emision' => now()->subDays(31)->toDateString(),
            'fecha_vencimiento' => now()->addDays(30)->toDateString(),
            'tipo_receta' => 'ESTANDAR',
            'estado' => 'VIGENTE',
            'fecha_registro' => now()->subDays(31),
            'estado_ficha' => 'RECIENTE',
        ]);
        $recentPrescriptionId = DB::table('recetas')->insertGetId([
            'paciente_rut' => $patientRut,
            'ficha_id' => $fichaId,
            'profesional_rut' => $professionalRut,
            'numero_receta' => 'FICHA-RECETA-RECIENTE',
            'fecha_emision' => now()->toDateString(),
            'fecha_vencimiento' => now()->addDays(30)->toDateString(),
            'tipo_receta' => 'ESTANDAR',
            'estado' => 'VIGENTE',
            'fecha_registro' => now(),
            'estado_ficha' => 'RECIENTE',
        ]);
        $oldDeliveryId = DB::table('entregas')->insertGetId([
            'paciente_rut' => $patientRut,
            'ficha_id' => $fichaId,
            'usuario_rut' => '12345674',
            'fecha_hora' => now()->subDays(31),
            'estado' => 'CONFIRMADA',
            'estado_ficha' => 'RECIENTE',
        ]);
        $recentDeliveryId = DB::table('entregas')->insertGetId([
            'paciente_rut' => $patientRut,
            'ficha_id' => $fichaId,
            'usuario_rut' => '12345674',
            'fecha_hora' => now(),
            'estado' => 'CONFIRMADA',
            'estado_ficha' => 'RECIENTE',
        ]);
        $oldOrderId = DB::table('ordenes_entrega')->insertGetId([
            'entrega_id' => $oldDeliveryId,
            'numero_orden' => 'FICHA-ORDEN-ANTIGUA',
            'plantilla' => 'RECETA_MEDICA',
        ]);
        $recentOrderId = DB::table('ordenes_entrega')->insertGetId([
            'entrega_id' => $recentDeliveryId,
            'numero_orden' => 'FICHA-ORDEN-RECIENTE',
            'plantilla' => 'RECETA_MEDICA',
        ]);

        $this->get(route('patients.file', $patientRut))
            ->assertOk()
            ->assertSee('FICHA-RECETA-ANTIGUA')
            ->assertSee('FICHA-RECETA-RECIENTE')
            ->assertSee('Dispensaciones registradas')
            ->assertSee('data-print-prescription-url="'.route('recipes.print', $oldPrescriptionId).'"', false)
            ->assertSee('data-print-order-url="'.route('delivery-orders.receipt', $oldOrderId).'"', false)
            ->assertDontSee('target="_blank"', false);

        $this->assertDatabaseHas('recetas', ['receta_id' => $oldPrescriptionId, 'estado_ficha' => 'ARCHIVADA']);
        $this->assertDatabaseHas('recetas', ['receta_id' => $recentPrescriptionId, 'estado_ficha' => 'RECIENTE']);
        $this->assertDatabaseHas('entregas', ['entrega_id' => $oldDeliveryId, 'estado_ficha' => 'ARCHIVADA']);
        $this->assertDatabaseHas('entregas', ['entrega_id' => $recentDeliveryId, 'estado_ficha' => 'RECIENTE']);

        $this->get(route('recipes.index'))
            ->assertOk()
            ->assertDontSee('FICHA-RECETA-ANTIGUA')
            ->assertSee('FICHA-RECETA-RECIENTE');
        $this->get(route('dispensations.deliveries'))
            ->assertOk()
            ->assertDontSee('FICHA-ORDEN-ANTIGUA')
            ->assertSee('FICHA-ORDEN-RECIENTE');
    }

    public function test_product_catalog_rejects_duplicate_codes_and_audits_creation(): void
    {
        $categoryId = DB::table('categorias_producto')->where('nombre', 'Medicamentos')->value('categoria_id');
        $tabletFormId = DB::table('formas_farmaceuticas')->where('nombre', 'Comprimido')->value('forma_farmaceutica_id');
        $solutionFormId = DB::table('formas_farmaceuticas')->where('nombre', 'Solución oral')->value('forma_farmaceutica_id');
        $milligramId = DB::table('unidades_medida')->where('codigo', 'mg')->value('unidad_medida_id');
        $tabletUnitId = DB::table('unidades_medida')->where('codigo', 'comprimido')->value('unidad_medida_id');
        $milliliterId = DB::table('unidades_medida')->where('codigo', 'mL')->value('unidad_medida_id');
        $payload = [
            'codigo_producto' => 'MED-TEST',
            'nombre' => 'Paracetamol 500 mg comprimidos',
            'presentaciones_comerciales' => [
                'ACUPARA',
                'ACUPARA SOLUCIÓN PARA PERFUSIÓN 10 mg/mL',
            ],
            'categoria_id' => $categoryId,
            'es_medicamento' => '1',
            'forma_farmaceutica_id' => $tabletFormId,
            'via_administracion' => 'ORAL',
            'principios_activos' => [[
                'nombre' => 'Paracetamol',
                'concentracion_cantidad' => '500',
                'concentracion_unidad_id' => $milligramId,
                'concentracion_base_cantidad' => '1',
                'concentracion_base_unidad_id' => $tabletUnitId,
            ]],
            'contenido_envase_cantidad' => '16',
            'contenido_envase_unidad_id' => $tabletUnitId,
            'tipo_presentacion' => 'CAJA',
            'bioequivalencia_estado' => 'PENDIENTE',
            'estado_registro_sanitario' => 'PENDIENTE',
            'codigo_barra' => '780000000001',
        ];

        $this->post(route('products.store'), array_merge($payload, [
            'principios_activos' => [],
        ]))->assertSessionHasErrors('principios_activos');
        $incompleteReference = $payload;
        $incompleteReference['principios_activos'][0]['concentracion_base_unidad_id'] = '';
        $this->post(route('products.store'), $incompleteReference)
            ->assertSessionHasErrors('principios_activos.0.concentracion_base_cantidad');
        $controlledWithoutLegalType = $payload;
        $controlledWithoutLegalType['es_controlado'] = '1';
        $this->post(route('products.store'), $controlledWithoutLegalType)
            ->assertSessionHasErrors('tipo_control_legal');
        $invalidFormUnits = $payload;
        $invalidFormUnits['codigo_producto'] = 'MED-TEST-INVALID';
        $invalidFormUnits['forma_farmaceutica_id'] = $solutionFormId;
        $this->post(route('products.store'), $invalidFormUnits)
            ->assertSessionHasErrors('contenido_envase_unidad_id');
        $invalidBaseUnit = $invalidFormUnits;
        $invalidBaseUnit['codigo_producto'] = 'MED-TEST-INVALID-BASE';
        $invalidBaseUnit['contenido_envase_unidad_id'] = $milliliterId;
        $this->post(route('products.store'), $invalidBaseUnit)
            ->assertSessionHasErrors('principios_activos.0.concentracion_base_unidad_id');

        $this->post(route('products.store'), $payload)->assertRedirect(route('inventory.catalog'));

        $this->assertDatabaseHas('productos', [
            'numero_serie' => 'MED-TEST',
            'es_medicamento' => true,
            'tipo_producto' => 'MEDICAMENTO',
            'forma_farmaceutica_id' => $tabletFormId,
            'unidad_base_inventario_id' => $tabletUnitId,
            'contenido_envase_cantidad' => 16,
            'contenido_envase_unidad_id' => $tabletUnitId,
        ]);
        $savedCommercialPresentations = json_decode(
            DB::table('productos')->where('numero_serie', 'MED-TEST')->value('presentaciones_comerciales'),
            true,
            512,
            JSON_THROW_ON_ERROR
        );
        $this->assertSame([
            'ACUPARA',
            'ACUPARA SOLUCIÓN PARA PERFUSIÓN 10 mg/mL',
        ], $savedCommercialPresentations);
        $this->getJson(route('inventory.api.index', ['buscar' => 'ACUPARA']))
            ->assertOk()
            ->assertJsonFragment([
                'codigo' => 'MED-TEST',
                'nombre' => 'Paracetamol 500 mg comprimidos',
            ]);
        $this->assertDatabaseHas('productos_codigo_barra', ['codigo_barra' => '780000000001']);
        $tabletProductId = DB::table('productos')->where('numero_serie', 'MED-TEST')->value('producto_id');
        $this->assertDatabaseHas('presentaciones_producto', [
            'producto_id' => $tabletProductId,
            'nombre' => 'Caja',
            'factor_conversion' => 16,
        ]);
        $this->assertDatabaseHas('productos_principios_activos', [
            'producto_id' => $tabletProductId,
            'concentracion_cantidad' => 500,
            'concentracion_unidad_id' => $milligramId,
            'concentracion_base_cantidad' => 1,
            'concentracion_base_unidad_id' => $tabletUnitId,
        ]);
        $this->get(route('products.edit', $tabletProductId))
            ->assertOk()
            ->assertSee('name="presentaciones_comerciales[0]"', false)
            ->assertSee('ACUPARA')
            ->assertSee('Información farmacéutica')
            ->assertSee('Presentación del envase')
            ->assertSee('Información regulatoria')
            ->assertSee('una caja con 16 comprimidos equivale a 16 comprimidos')
            ->assertSee('Unidad base de inventario y dispensación')
            ->assertDontSee('Presentaciones comerciales y equivalencias')
            ->assertSee('500');
        $this->assertDatabaseHas('principios_activos', [
            'nombre' => 'Paracetamol',
            'nombre_normalizado' => 'paracetamol',
        ]);
        $this->assertDatabaseHas('auditoria', ['accion' => 'CREACION', 'entidad' => 'productos']);

        $this->post(route('products.store'), array_merge($payload, [
            'codigo_producto' => 'MED-TEST-SOL',
            'nombre' => 'Paracetamol 120 mg/5 mL solución oral',
            'forma_farmaceutica_id' => $solutionFormId,
            'principios_activos' => [[
                'nombre' => 'Paracetamol',
                'concentracion_cantidad' => '120',
                'concentracion_unidad_id' => $milligramId,
                'concentracion_base_cantidad' => '5',
                'concentracion_base_unidad_id' => $milliliterId,
            ]],
            'contenido_envase_cantidad' => '100',
            'contenido_envase_unidad_id' => $milliliterId,
            'tipo_presentacion' => 'FRASCO',
            'codigo_barra' => '',
        ]))->assertRedirect(route('inventory.catalog'));
        $solutionProductId = DB::table('productos')->where('numero_serie', 'MED-TEST-SOL')->value('producto_id');
        $this->assertDatabaseHas('productos', [
            'producto_id' => $solutionProductId,
            'unidad_base_inventario_id' => $milliliterId,
        ]);
        $this->assertDatabaseHas('presentaciones_producto', [
            'producto_id' => $solutionProductId,
            'nombre' => 'Frasco',
            'factor_conversion' => 100,
        ]);
        $this->assertDatabaseHas('productos_principios_activos', [
            'producto_id' => $solutionProductId,
            'concentracion_cantidad' => 120,
            'concentracion_base_cantidad' => 5,
            'concentracion_base_unidad_id' => $milliliterId,
        ]);
        $this->get(route('inventory.catalog'))
            ->assertOk()
            ->assertSee('ESPECIFICACIÓN')
            ->assertSee('PRESENTACIONES COMERCIALES')
            ->assertSee('ACUPARA')
            ->assertSee('Paracetamol 500 mg comprimidos')
            ->assertSee('500 mg / 1 comprimido')
            ->assertSee('120 mg / 5 mL');

        $this->post(route('products.store'), [
            'codigo_producto' => 'MED-TEST',
            'nombre' => 'Código repetido',
            'categoria_id' => $categoryId,
        ])->assertSessionHasErrors('codigo_producto');
    }

    public function test_non_medicine_catalog_product_does_not_require_pharmaceutical_details(): void
    {
        $categoryId = DB::table('categorias_producto')->value('categoria_id');
        $unitId = DB::table('unidades_medida')->where('codigo', 'unidad')->value('unidad_medida_id');

        $this->post(route('products.store'), [
            'codigo_producto' => 'INSUMO-TEST',
            'nombre' => 'Material de curación de prueba',
            'categoria_id' => $categoryId,
            'es_medicamento' => '0',
        ])->assertRedirect(route('inventory.catalog'));

        $this->assertDatabaseHas('productos', [
            'numero_serie' => 'INSUMO-TEST',
            'es_medicamento' => false,
            'tipo_producto' => 'INSUMO_CLINICO',
            'es_controlado' => false,
            'bioequivalencia_estado' => 'NO_APLICA',
            'estado_registro_sanitario' => 'NO_APLICA',
        ]);
        $productId = DB::table('productos')->where('numero_serie', 'INSUMO-TEST')->value('producto_id');
        $this->assertDatabaseHas('productos', [
            'producto_id' => $productId,
            'unidad_base_inventario_id' => $unitId,
        ]);
        $this->assertDatabaseHas('presentaciones_producto', [
            'producto_id' => $productId,
            'nombre' => 'Unidad base',
            'factor_conversion' => 1,
        ]);
        $this->assertDatabaseMissing('productos_principios_activos', ['producto_id' => $productId]);
    }

    public function test_inventory_locations_can_be_created_edited_and_managed_only_by_administrators_and_pharmacists(): void
    {
        $this->get(route('locations.index'))
            ->assertOk()
            ->assertSee('Bodega Central')
            ->assertSee('Vitrina Farmacia')
            ->assertSee('Carro de Paro');
        $this->get(route('locations.create'))
            ->assertOk()
            ->assertSee('Agregar ubicación');

        $this->post(route('locations.store'), [
            'nombre' => 'Bodega de Vacunas',
            'tipo' => 'BODEGA',
            'descripcion' => 'Almacenamiento refrigerado',
        ])->assertRedirect(route('locations.index'));
        $locationId = DB::table('ubicaciones')->where('nombre', 'Bodega de Vacunas')->value('ubicacion_id');
        $this->get(route('locations.edit', $locationId))
            ->assertOk()
            ->assertSee('Editar ubicación')
            ->assertSee('Almacenamiento refrigerado');

        $this->put(route('locations.update', $locationId), [
            'nombre' => 'Bodega de Vacunas Central',
            'tipo' => 'BODEGA',
            'descripcion' => 'Nueva descripción',
        ])->assertRedirect(route('locations.index'));
        $this->assertDatabaseHas('ubicaciones', [
            'ubicacion_id' => $locationId,
            'nombre' => 'Bodega de Vacunas Central',
            'tipo' => 'BODEGA',
            'descripcion' => 'Nueva descripción',
        ]);
        $this->assertDatabaseHas('auditoria', ['accion' => 'CREACION', 'entidad' => 'ubicaciones']);
        $this->assertDatabaseHas('auditoria', ['accion' => 'ACTUALIZACION', 'entidad' => 'ubicaciones']);

        $this->post(route('locations.store'), [
            'nombre' => 'Carro Unidosis Auxiliar',
            'tipo' => 'CARRO_UNIDOSIS',
        ])->assertRedirect(route('locations.index'));
        $cartLocationId = DB::table('ubicaciones')->where('nombre', 'Carro Unidosis Auxiliar')->value('ubicacion_id');
        $this->get(route('locations.edit', $cartLocationId))->assertSee('disabled', false);
        $this->assertDatabaseHas('carros', [
            'ubicacion_id' => $cartLocationId,
            'tipo_carro' => 'UNIDOSIS',
            'nombre' => 'Carro Unidosis Auxiliar',
        ]);
        $this->from(route('locations.edit', $cartLocationId))
            ->put(route('locations.update', $cartLocationId), [
                'nombre' => 'Carro Unidosis Auxiliar',
                'tipo' => 'BODEGA',
            ])
            ->assertSessionHasErrors('tipo');

        $pharmacistRoleId = DB::table('roles')->where('nombre', 'Químico Farmacéutico')->value('rol_id');
        DB::table('usuario_rol')->where('usuario_rut', '12345674')->update(['rol_id' => $pharmacistRoleId]);
        $this->get(route('locations.index'))->assertOk();
        $this->post(route('locations.store'), [
            'nombre' => 'Vitrina de Vacunas',
            'tipo' => 'VITRINA',
        ])->assertRedirect(route('locations.index'));

        $technicianRoleId = DB::table('roles')->where('nombre', 'Técnico Farmacéutico')->value('rol_id');
        DB::table('usuario_rol')->where('usuario_rut', '12345674')->update(['rol_id' => $technicianRoleId]);
        $this->get(route('locations.index'))->assertForbidden();
        $this->get(route('locations.create'))->assertForbidden();
        $this->get(route('locations.edit', $locationId))->assertForbidden();
        $this->post(route('locations.store'), [
            'nombre' => 'Ubicación no autorizada',
            'tipo' => 'BODEGA',
        ])->assertForbidden();
        $this->put(route('locations.update', $locationId), [
            'nombre' => 'Ubicación no autorizada',
            'tipo' => 'BODEGA',
        ])->assertForbidden();
        $this->assertDatabaseMissing('ubicaciones', ['nombre' => 'Ubicación no autorizada']);
        $this->assertDatabaseHas('auditoria', [
            'accion' => 'ACCESO_DENEGADO',
            'entidad' => 'ubicaciones',
            'resultado' => 'RECHAZADO',
        ]);
    }

    public function test_administrator_can_create_product_categories(): void
    {
        $this->get(route('categories.index'))
            ->assertOk()
            ->assertSee('Categorías de productos')
            ->assertDontSee('Agregar categoría');
        $this->get(route('categories.create'))
            ->assertOk()
            ->assertSee('Agregar categoría')
            ->assertDontSee('Categorías registradas');

        $this->post(route('categories.store'), [
            'nombre_categoria' => 'Material dental',
            'descripcion_categoria' => 'Insumos odontológicos',
        ])->assertRedirect(route('categories.index'));
        $this->assertDatabaseHas('categorias_producto', [
            'nombre' => 'Material dental',
            'descripcion' => 'Insumos odontológicos',
        ]);
        $this->assertDatabaseHas('auditoria', ['accion' => 'CREACION', 'entidad' => 'categorias_producto']);

        $this->post(route('categories.store'), ['nombre_categoria' => 'Material dental'])
            ->assertSessionHasErrors('nombre_categoria');

        $pharmacistRoleId = DB::table('roles')->where('nombre', 'Químico Farmacéutico')->value('rol_id');
        DB::table('usuario_rol')->where('usuario_rut', '12345674')->delete();
        DB::table('usuario_rol')->insert(['usuario_rut' => '12345674', 'rol_id' => $pharmacistRoleId]);
        $this->actingAs(Usuario::where('rut', '12345674')->firstOrFail());
        $this->get(route('categories.index'))
            ->assertOk()
            ->assertSee('Categorías de productos')
            ->assertDontSee('Agregar categoría')
            ->assertDontSee('Agregar producto al catálogo');
        $this->post(route('categories.store'), ['nombre_categoria' => 'Vacunas'])
            ->assertRedirect(route('categories.index'));
        $this->assertDatabaseHas('categorias_producto', ['nombre' => 'Vacunas']);
        $categoryId = DB::table('categorias_producto')->where('nombre', 'Vacunas')->value('categoria_id');
        $this->get(route('categories.edit', $categoryId))->assertOk();
        $this->put(route('categories.update', $categoryId), [
            'nombre_categoria' => 'Vacunas actualizadas',
            'descripcion_categoria' => 'Categoría administrada por farmacia',
        ])->assertRedirect(route('categories.index'));
        $this->assertDatabaseHas('categorias_producto', ['categoria_id' => $categoryId, 'nombre' => 'Vacunas actualizadas']);
    }

    public function test_administrator_can_update_users_products_patients_and_authorized_third_parties(): void
    {
        $patientId = DB::table('pacientes')->where('rut', '123456785')->value('rut');
        $this->get(route('patients.edit', $patientId))
            ->assertOk()
            ->assertSee('Editar paciente')
            ->assertSee('data-chilean-mobile-phone', false)
            ->assertSee('value="12345678"', false)
            ->assertSee('>+56 9</span>', false);
        $this->put(route('patients.update', $patientId), [
            'rut' => '12.345.678-5',
            'nombre' => 'María Actualizada',
            'apellidoPaterno' => 'González',
            'apellidoMaterno' => 'López',
            'fecha_nacimiento' => '1958-03-12',
            'telefono' => '911111111',
            'activo' => '1',
        ])->assertRedirect(route('patients.file', $patientId));
        $this->assertDatabaseHas('pacientes', ['rut' => $patientId, 'nombre' => 'María Actualizada']);

        $productId = DB::table('productos')->where('numero_serie', 'MED-001')->value('producto_id');
        $categoryId = DB::table('categorias_producto')->where('nombre', 'Medicamentos')->value('categoria_id');
        $baseUnitId = DB::table('productos')->where('producto_id', $productId)->value('unidad_base_inventario_id');
        $this->get(route('products.edit', $productId))->assertOk()->assertSee('Editar producto');
        $this->put(route('products.update', $productId), [
            'codigo_producto' => 'MED-001-EDITADO',
            'nombre' => 'Paracetamol actualizado',
            'categoria_id' => $categoryId,
            'codigo_barra' => '780000000100',
            'es_medicamento' => '0',
            'unidad_base_inventario_id' => $baseUnitId,
            'presentaciones' => [['nombre' => 'Unidad', 'factor_conversion' => '1']],
            'activo' => '1',
        ])->assertRedirect(route('inventory.catalog'));
        $this->assertDatabaseHas('productos', ['producto_id' => $productId, 'nombre' => 'Paracetamol actualizado']);
        $this->assertDatabaseHas('productos_codigo_barra', ['producto_id' => $productId, 'codigo_barra' => '780000000100', 'activo' => true]);
        $this->put(route('products.update', $productId), [
            'codigo_producto' => 'MED-001-EDITADO',
            'nombre' => 'Paracetamol actualizado',
            'categoria_id' => $categoryId,
            'codigo_barra' => '780000000100',
            'es_medicamento' => '0',
            'unidad_base_inventario_id' => $baseUnitId,
            'presentaciones' => [['nombre' => 'Unidad', 'factor_conversion' => '1']],
            'activo' => '1',
        ])->assertRedirect(route('inventory.catalog'));
        $this->assertSame(1, DB::table('productos_codigo_barra')
            ->where('producto_id', $productId)
            ->where('codigo_barra', '780000000100')
            ->where('activo', true)
            ->count());

        $authorizedPersonId = DB::table('personas_autorizadas')->insertGetId([
            'paciente_rut' => $patientId,
            'rut' => '133333339',
            'nombre' => 'Representante',
            'apellidoPaterno' => 'De Prueba',
            'apellidoMaterno' => 'Apellido',
            'vinculo' => 'Familiar',
            'activo' => true,
        ]);
        $this->get(route('authorized-people.edit', $authorizedPersonId))
            ->assertOk()
            ->assertSee('Editar persona autorizada')
            ->assertSee('data-person-lookup', false)
            ->assertSee('data-person-rut', false);
        $this->put(route('authorized-people.update', $authorizedPersonId), [
            'paciente_rut' => $patientId,
            'rut' => '13.333.333-9',
            'nombre' => 'Representante editado',
            'apellidoPaterno' => 'De Prueba',
            'apellidoMaterno' => 'Apellido',
            'vinculo' => 'Cuidador',
            'activo' => '1',
        ])->assertRedirect(route('authorized-people.index'));
        $this->assertDatabaseHas('personas_autorizadas', ['persona_autorizada_id' => $authorizedPersonId, 'nombre' => 'Representante editado', 'vinculo' => 'Cuidador']);

        $technicianId = DB::table('roles')->where('nombre', 'Técnico Farmacéutico')->value('rol_id');
        $userRut = '105555555';
        DB::table('usuarios')->insert([
            'rut' => $userRut,
            'nombre' => 'Usuario',
            'apellidoPaterno' => 'Original',
            'apellidoMaterno' => 'Prueba',
            'password_hash' => Hash::make('PasswordOriginal!2026'),
            'activo' => true,
        ]);
        DB::table('usuario_rol')->insert(['usuario_rut' => $userRut, 'rol_id' => $technicianId]);
        $this->get(route('users.edit', $userRut))
            ->assertOk()
            ->assertSee('Editar usuario')
            ->assertSee('Técnico Farmacéutico')
            ->assertSee('Químico Farmacéutico')
            ->assertSee('readonly', false);
        $this->put(route('users.update', $userRut), [
            'rut' => '10.555.555-5',
            'nombre' => 'Usuario',
            'apellidoPaterno' => 'Editado',
            'apellidoMaterno' => 'Prueba',
            'telefono' => '912345678',
            'rol_id' => $technicianId,
        ])->assertRedirect(route('users.index'));
        $this->assertDatabaseHas('usuarios', ['rut' => $userRut, 'apellidoPaterno' => 'Editado']);
        $this->assertDatabaseHas('auditoria', ['accion' => 'ACTUALIZACION', 'entidad' => 'usuarios']);

        $this->from(route('users.edit', $userRut))
            ->put(route('users.update', $userRut), [
                'rut' => '10.000.013-K',
                'nombre' => 'Usuario',
                'apellidoPaterno' => 'Editado',
                'apellidoMaterno' => 'Prueba',
                'telefono' => '912345678',
                'rol_id' => $technicianId,
            ])
            ->assertRedirect(route('users.edit', $userRut))
            ->assertSessionHasErrors('rut');
        $this->assertDatabaseHas('usuarios', ['rut' => $userRut]);
    }

    public function test_non_administrators_cannot_create_or_update_catalog_records(): void
    {
        $technicianRole = DB::table('roles')->where('nombre', 'Técnico Farmacéutico')->value('rol_id');
        $userRut = '105555555';
        DB::table('usuarios')->insert([
            'rut' => $userRut,
            'nombre' => 'Técnico',
            'apellidoPaterno' => 'Farmacéutico',
            'apellidoMaterno' => 'Prueba',
            'password_hash' => Hash::make('PasswordOriginal!2026'),
            'activo' => true,
        ]);
        DB::table('usuario_rol')->insert(['usuario_rut' => $userRut, 'rol_id' => $technicianRole]);
        $this->actingAs(Usuario::findOrFail($userRut));
        $categoryId = DB::table('categorias_producto')->where('nombre', 'Medicamentos')->value('categoria_id');

        $this->post(route('products.store'), [
            'codigo_producto' => 'DENEGADO',
            'nombre' => 'No debe crearse',
            'categoria_id' => $categoryId,
        ])->assertForbidden();
        $this->post(route('categories.store'), ['nombre_categoria' => 'Categoría denegada'])->assertForbidden();
        $productId = DB::table('productos')->where('numero_serie', 'MED-001')->value('producto_id');
        $this->get(route('products.edit', $productId))->assertForbidden();
        $this->put(route('products.update', $productId), [])->assertForbidden();
        $patientId = DB::table('pacientes')->where('rut', '123456785')->value('rut');
        $this->get(route('patients.edit', $patientId))->assertForbidden();
        $this->post(route('patients.store'), [
            'rut' => '8.123.456-6',
            'nombre' => 'No debe crearse',
            'apellidoPaterno' => 'Prueba',
            'apellidoMaterno' => 'Prueba',
        ])->assertForbidden();
    }

    public function test_barcode_lookup_returns_product_and_earliest_valid_display_lot(): void
    {
        $productId = DB::table('productos')->where('numero_serie', 'MED-001')->value('producto_id');
        DB::table('productos_codigo_barra')->insert([
            'producto_id' => $productId,
            'codigo_barra' => '7801234567890',
            'activo' => true,
        ]);

        $this->getJson(route('products.barcode.lookup', ['codigo_barra' => '7801234567890']))
            ->assertOk()
            ->assertJsonPath('producto_id', $productId)
            ->assertJsonPath('codigo_producto', 'MED-001')
            ->assertJsonPath('lote_fefo.numero_lote', 'L250821');

        $this->getJson(route('products.barcode.lookup', ['codigo_barra' => '0000000000000']))
            ->assertNotFound()
            ->assertJsonPath('message', 'No hay un producto activo asociado a ese código de barras.');
    }

    public function test_global_search_finds_patients_products_and_inventory_by_barcode(): void
    {
        $productId = DB::table('productos')->where('numero_serie', 'MED-001')->value('producto_id');
        DB::table('productos_codigo_barra')->insert([
            'producto_id' => $productId,
            'codigo_barra' => '7801234567890',
            'activo' => true,
        ]);

        $this->getJson(route('search', ['q' => 'María González']))
            ->assertOk()
            ->assertJsonFragment(['tipo' => 'Paciente', 'titulo' => 'María González López'])
            ->assertJsonPath('0.url', route('dispensations.index', ['paciente_rut' => '123456785']));

        $this->getJson(route('search', ['q' => '12.345.678-5']))
            ->assertOk()
            ->assertJsonFragment(['tipo' => 'Paciente', 'titulo' => 'María González López']);

        $this->getJson(route('search', ['q' => '7801234567890']))
            ->assertOk()
            ->assertJsonFragment(['tipo' => 'Medicamento / insumo', 'titulo' => 'Paracetamol 500 mg'])
            ->assertJsonPath('0.url', route('inventory.index', ['buscar' => 'MED-001']));

        $this->get(route('inventory.catalog', ['buscar' => '7801234567890']))
            ->assertOk()
            ->assertSee('Paracetamol 500 mg');
    }

    public function test_program_enrollment_creates_multiple_product_quotas_for_patient(): void
    {
        $patientId = DB::table('pacientes')->where('rut', '123456785')->value('rut');
        $programId = DB::table('programas_salud')->value('programa_id');
        $productIds = DB::table('productos')->whereIn('numero_serie', ['MED-001', 'MED-002'])->pluck('producto_id', 'numero_serie');

        $this->get(route('programs.enroll.create'))
            ->assertOk()
            ->assertSee('Productos autorizados')
            ->assertSee('Puedes autorizar varios productos; cada uno tiene su propia cantidad y período de vigencia.')
            ->assertSee('data-add-row="program-cuota-details"', false)
            ->assertSee('name="detalles[0][producto_id]"', false)
            ->assertSee('data-product-search-input', false)
            ->assertSee('data-product-show-result-details="false"', false)
            ->assertSee('Buscar producto por nombre')
            ->assertSee('Cantidad autorizada')
            ->assertSee('Período desde')
            ->assertSee('Período hasta');

        $this->post(route('programs.enroll'), [
            'paciente_rut' => $patientId,
            'programa_id' => $programId,
            'fecha_inicio' => now()->toDateString(),
            'fecha_termino' => now()->addYear()->toDateString(),
            'cobertura' => 'Tratamiento cardiovascular',
            'detalles' => [
                [
                    'producto_id' => $productIds['MED-001'],
                    'periodo_inicio' => now()->startOfMonth()->toDateString(),
                    'periodo_termino' => now()->endOfMonth()->toDateString(),
                    'cantidad_autorizada' => 30,
                ],
                [
                    'producto_id' => $productIds['MED-002'],
                    'periodo_inicio' => now()->startOfMonth()->toDateString(),
                    'periodo_termino' => now()->endOfMonth()->toDateString(),
                    'cantidad_autorizada' => 45,
                ],
            ],
        ])->assertRedirect(route('programs.affiliations.index'));

        $enrollmentId = DB::table('paciente_programa')
            ->where('paciente_rut', $patientId)
            ->where('programa_id', $programId)
            ->value('paciente_programa_id');
        $this->assertDatabaseHas('cuotas_paciente', [
            'paciente_programa_id' => $enrollmentId,
            'producto_id' => $productIds['MED-001'],
            'cantidad_autorizada' => 30,
            'cantidad_utilizada' => 0,
        ]);
        $this->assertDatabaseHas('cuotas_paciente', [
            'paciente_programa_id' => $enrollmentId,
            'producto_id' => $productIds['MED-002'],
            'cantidad_autorizada' => 45,
            'cantidad_utilizada' => 0,
        ]);
        $this->assertSame(2, DB::table('cuotas_paciente')->where('paciente_programa_id', $enrollmentId)->count());
        $this->assertDatabaseHas('auditoria', ['accion' => 'AFILIACION', 'resultado' => 'EXITOSO']);
    }

    public function test_program_enrollment_rejects_duplicate_quota_products_and_invalid_quota_dates(): void
    {
        $patientId = DB::table('pacientes')->where('rut', '123456785')->value('rut');
        $programId = DB::table('programas_salud')->value('programa_id');
        $productIds = DB::table('productos')->whereIn('numero_serie', ['MED-001', 'MED-002'])->pluck('producto_id', 'numero_serie');
        $quota = [
            'periodo_inicio' => now()->toDateString(),
            'periodo_termino' => now()->addMonth()->toDateString(),
            'cantidad_autorizada' => 30,
        ];
        $baseData = [
            'paciente_rut' => $patientId,
            'programa_id' => $programId,
            'fecha_inicio' => now()->toDateString(),
            'fecha_termino' => now()->addYear()->toDateString(),
        ];

        $this->from(route('programs.enroll.create'))->post(route('programs.enroll'), $baseData + [
            'detalles' => [
                ['producto_id' => $productIds['MED-001']] + $quota,
                ['producto_id' => $productIds['MED-001']] + $quota,
            ],
        ])->assertRedirect(route('programs.enroll.create'))
            ->assertSessionHasErrors('detalles.1.producto_id');

        $this->from(route('programs.enroll.create'))->post(route('programs.enroll'), $baseData + [
            'detalles' => [[
                'producto_id' => $productIds['MED-002'],
                'periodo_inicio' => now()->addMonth()->toDateString(),
                'periodo_termino' => now()->toDateString(),
                'cantidad_autorizada' => 30,
            ]],
        ])->assertRedirect(route('programs.enroll.create'))
            ->assertSessionHasErrors('detalles.0.periodo_termino');

        $this->assertDatabaseCount('paciente_programa', 0);
    }

    public function test_programs_and_affiliations_can_be_searched_and_filtered(): void
    {
        $inactiveProgramId = DB::table('programas_salud')->insertGetId([
            'nombre' => 'Programa filtrado inactivo',
            'descripcion' => 'Programa de prueba',
            'activo' => false,
        ]);
        $patientId = DB::table('pacientes')->where('rut', '123456785')->value('rut');
        $activeProgramId = DB::table('programas_salud')->where('activo', true)->value('programa_id');
        DB::table('paciente_programa')->insert([
            'paciente_rut' => $patientId,
            'programa_id' => $activeProgramId,
            'fecha_inicio' => now()->subDay()->toDateString(),
            'fecha_termino' => now()->addMonth()->toDateString(),
            'cobertura' => 'Cobertura filtro específica',
            'activo' => true,
        ]);

        $this->get(route('programs.index', [
            'buscar_programa' => 'filtrado inactivo',
            'estado_programa' => 'inactivo',
        ]))
            ->assertOk()
            ->assertSee('Programa filtrado inactivo');

        $this->get(route('programs.affiliations.index', [
            'buscar_afiliacion' => 'Cobertura filtro',
            'programa_id_afiliacion' => $activeProgramId,
            'estado_afiliacion' => 'VIGENTE',
        ]))
            ->assertOk()
            ->assertSee('Cobertura filtro específica')
            ->assertSee('VIGENTE');
    }

    public function test_program_affiliations_cannot_overlap_for_same_patient_and_program(): void
    {
        $patientId = DB::table('pacientes')->where('rut', '123456785')->value('rut');
        $programId = DB::table('programas_salud')->value('programa_id');
        $this->post(route('programs.enroll'), [
            'paciente_rut' => $patientId,
            'programa_id' => $programId,
            'fecha_inicio' => now()->subDays(10)->toDateString(),
            'fecha_termino' => now()->addDays(10)->toDateString(),
        ])->assertRedirect(route('programs.affiliations.index'));

        $this->from(route('programs.enroll.create'))->post(route('programs.enroll'), [
            'paciente_rut' => $patientId,
            'programa_id' => $programId,
            'fecha_inicio' => now()->toDateString(),
            'fecha_termino' => now()->addYear()->toDateString(),
        ])->assertRedirect(route('programs.enroll.create'))->assertSessionHasErrors('programa_id');
        $this->assertDatabaseCount('paciente_programa', 1);
    }

    public function test_inactive_patient_cannot_enter_clinical_and_program_flows(): void
    {
        $patientId = DB::table('pacientes')->where('rut', '123456785')->value('rut');
        DB::table('pacientes')->where('rut', $patientId)->update(['activo' => false]);
        $productId = DB::table('productos')->where('numero_serie', 'MED-001')->value('producto_id');
        $programId = DB::table('programas_salud')->value('programa_id');
        $professionalId = DB::table('personal_salud')->value('rut');

        $this->get(route('patients.index', ['buscar' => $patientId]))
            ->assertOk()
            ->assertSee('No se puede dispensar')
            ->assertDontSee(route('dispensations.index', ['paciente_rut' => $patientId]), false);

        $this->from(route('recipes.index'))->post(route('recipes.store'), [
            'paciente_rut' => $patientId,
            'profesional_rut' => $professionalId,
            'numero_receta' => 'INACTIVE-PATIENT-REC',
            'fecha_emision' => now()->toDateString(),
            'fecha_vencimiento' => now()->addMonth()->toDateString(),
            'tipo_receta' => 'ESTANDAR',
            'detalles' => [['producto_id' => $productId, 'cantidad_indicada' => 1]],
        ])->assertRedirect(route('recipes.index'))->assertSessionHasErrors('paciente_rut');

        $this->from(route('programs.enroll.create'))->post(route('programs.enroll'), [
            'paciente_rut' => $patientId,
            'programa_id' => $programId,
            'fecha_inicio' => now()->toDateString(),
        ])->assertRedirect(route('programs.enroll.create'))->assertSessionHasErrors('paciente_rut');
        $this->from(route('carts.treatments.create'))->post(route('carts.treatments.store'), [
            'paciente_rut' => $patientId,
            'descripcion' => 'Tratamiento de prueba',
            'fecha_inicio' => now()->toDateString(),
        ])->assertRedirect(route('carts.treatments.create'))->assertSessionHasErrors('paciente_rut');

        $this->assertDatabaseCount('recetas', 0);
        $this->assertDatabaseCount('paciente_programa', 0);
        $this->assertDatabaseCount('tratamientos', 0);
    }

    // Impide que un profesional inactivo emita recetas.
    public function test_inactive_professionals_cannot_be_selected_or_register_prescriptions(): void
    {
        $patientId = DB::table('pacientes')->where('rut', '123456785')->value('rut');
        $professionalId = DB::table('personal_salud')->value('rut');
        $productId = DB::table('productos')->where('numero_serie', 'MED-001')->value('producto_id');
        DB::table('personal_salud')->where('rut', $professionalId)->update(['activo' => false]);

        $this->get(route('recipes.create'))
            ->assertOk()
            ->assertDontSee('value="'.$professionalId.'"', false);

        $this->from(route('recipes.create'))->post(route('recipes.store'), [
            'paciente_rut' => $patientId,
            'profesional_rut' => $professionalId,
            'numero_receta' => 'INACTIVE-PROFESSIONAL-REC',
            'fecha_emision' => now()->toDateString(),
            'fecha_vencimiento' => now()->addMonth()->toDateString(),
            'tipo_receta' => 'ESTANDAR',
            'detalles' => [[
                'producto_id' => $productId,
                'cantidad_indicada' => 1,
                'unidades_por_toma' => 1,
                'duracion_dias' => 1,
                'frecuencia' => 'Una vez al día',
            ]],
        ])
            ->assertRedirect(route('recipes.create'))
            ->assertSessionHasErrors('profesional_rut');

        $this->assertDatabaseMissing('recetas', ['numero_receta' => 'INACTIVE-PROFESSIONAL-REC']);
    }

    public function test_prescription_registration_saves_all_medicine_details(): void
    {
        $patientId = DB::table('pacientes')->where('rut', '123456785')->value('rut');
        $professionalId = DB::table('personal_salud')->value('rut');
        $productId = DB::table('productos')->where('numero_serie', 'MED-001')->value('producto_id');

        $page = $this->get(route('recipes.create'))
            ->assertOk()
            ->assertSee('data-patient-selected-rut-only="true"', false)
            ->assertSee('placeholder="Buscar por nombre o RUT"', false)
            ->assertSee('Tratamiento clínico vinculado')
            ->assertSee('name="indicar_tratamiento"', false)
            ->assertSee('id="prescription-treatment-fields"', false);
        $this->assertMatchesRegularExpression(
            '/<div(?=[^>]*data-prescription-treatment-fields)(?=[^>]*hidden)[^>]*>/',
            $page->getContent(),
        );

        $this->post(route('recipes.store'), [
            'paciente_rut' => $patientId,
            'profesional_rut' => $professionalId,
            'numero_receta' => 'REC-TEST-001',
            'fecha_emision' => now()->toDateString(),
            'fecha_vencimiento' => now()->addMonth()->toDateString(),
            'tipo_receta' => 'ESTANDAR',
            'detalles' => [[
                'producto_id' => $productId,
                'cantidad_indicada' => 30,
                'dosis' => '1 comprimido',
                'frecuencia' => 'Cada 8 horas',
                'unidades_por_toma' => 1,
                'duracion_dias' => 10,
            ]],
        ])->assertRedirect(route('recipes.index'));

        $this->assertDatabaseHas('recetas', [
            'numero_receta' => 'REC-TEST-001',
            'estado' => 'VIGENTE',
            'ficha_id' => DB::table('fichas_pacientes')->where('paciente_rut', $patientId)->value('ficha_id'),
        ]);
        $this->assertDatabaseHas('receta_detalle', ['producto_id' => $productId, 'cantidad_indicada' => 30]);
        $this->assertDatabaseHas('receta_detalle', [
            'producto_id' => $productId,
            'via_administracion' => 'ORAL',
            'unidades_por_toma' => 1,
            'duracion_dias' => 10,
            'es_sos' => false,
        ]);
        $this->assertDatabaseHas('auditoria', ['accion' => 'CREACION', 'entidad' => 'recetas']);
        $prescriptionId = DB::table('recetas')->where('numero_receta', 'REC-TEST-001')->value('receta_id');
        $this->get(route('recipes.index'))
            ->assertOk()
            ->assertSee('person-card', false)
            ->assertSee('María González López')
            ->assertSee('Ver medicamentos (1)')
            ->assertDontSee('<table class="data-table">', false)
            ->assertSee('data-print-prescription-url="'.route('recipes.print', $prescriptionId).'"', false)
            ->assertDontSee('target="_blank"', false);
        $this->get(route('recipes.index', ['buscar' => 'REC-TEST-001', 'estado' => 'VIGENTE']))
            ->assertOk()
            ->assertSee('REC-TEST-001')
            ->assertSee('name="buscar"', false)
            ->assertSee('<option value="VIGENTE" selected>VIGENTE</option>', false);
        $this->get(route('recipes.index', ['tipo_receta' => 'ESTANDAR']))
            ->assertOk()
            ->assertSee('REC-TEST-001')
            ->assertSee('<option value="ESTANDAR" selected>Estándar</option>', false)
            ->assertDontSee('<option value="RETENIDA" selected>', false);
        $this->get(route('recipes.print', $prescriptionId))
            ->assertOk()
            ->assertSee('Receta médica')
            ->assertSee('class="print-prescription"', false)
            ->assertSee('class="prescription-print-sheet"', false)
            ->assertSee(asset('logo.jpg'), false)
            ->assertSee('María González López')
            ->assertSee($patientId)
            ->assertSee('class="prescription-print-table"', false)
            ->assertSee('data-label="Indicaciones"', false)
            ->assertDontSee('class="table-responsive"', false)
            ->assertSee('Cantidad')
            ->assertSee('Indicaciones')
            ->assertSee('Cada 8 horas')
            ->assertSee('10 días')
            ->assertDontSee('Firma paciente');
    }

    public function test_prescription_form_can_register_a_linked_treatment_inline(): void
    {
        $patientRut = DB::table('pacientes')->where('rut', '123456785')->value('rut');
        $professionalRut = DB::table('personal_salud')->value('rut');
        $productId = DB::table('productos')->where('numero_serie', 'MED-001')->value('producto_id');
        $startDate = now()->toDateString();
        $endDate = now()->addMonth()->toDateString();

        $this->get(route('recipes.create'))
            ->assertOk()
            ->assertDontSee('name="tratamiento[fecha_inicio]"', false)
            ->assertDontSee('name="tratamiento[fecha_termino]"', false)
            ->assertSee('máximo dispensable por mes calendario');

        $this->post(route('recipes.store'), [
            'paciente_rut' => $patientRut,
            'profesional_rut' => $professionalRut,
            'numero_receta' => 'REC-INLINE-TREATMENT-001',
            'fecha_emision' => $startDate,
            'fecha_vencimiento' => $endDate,
            'tipo_receta' => 'ESTANDAR',
            'indicar_tratamiento' => '1',
            'tratamiento' => [
                'descripcion' => 'Indicación registrada desde la receta',
            ],
            'detalles' => [[
                'producto_id' => $productId,
                'cantidad_indicada' => 14,
                'unidades_por_toma' => 1,
                'duracion_dias' => 7,
                'frecuencia' => 'Cada 12 horas',
            ]],
        ])->assertRedirect(route('recipes.index'));

        $prescriptionId = DB::table('recetas')->where('numero_receta', 'REC-INLINE-TREATMENT-001')->value('receta_id');
        $this->assertDatabaseHas('tratamientos', [
            'receta_id' => $prescriptionId,
            'paciente_rut' => $patientRut,
            'descripcion' => 'Indicación registrada desde la receta',
            'fecha_inicio' => $startDate,
            'fecha_termino' => $endDate,
        ]);
        $this->assertDatabaseHas('auditoria', ['accion' => 'CREACION', 'entidad' => 'tratamientos']);
    }

    public function test_prescription_medicine_field_searches_catalog_without_requiring_stock(): void
    {
        $this->get(route('recipes.create'))
            ->assertOk()
            ->assertSee('data-product-search-url="'.route('recipes.products.search').'"', false)
            ->assertSee('id="prescription-product-0"', false)
            ->assertSee('Dosis por toma')
            ->assertDontSee('tomas_diarias')
            ->assertSee('role="combobox"', false)
            ->assertDontSee('data-product-availability', false);

        $productId = DB::table('productos')->where('numero_serie', 'MED-001')->value('producto_id');
        $response = $this->getJson(route('recipes.products.search', ['q' => 'Paracetamol']))
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonFragment([
                'producto_id' => $productId,
                'nombre' => 'Paracetamol 500 mg',
                'es_controlado' => false,
                'via_administracion' => 'ORAL',
                'unidad_base_codigo' => 'unidad',
            ]);

        $this->assertArrayNotHasKey('stock_disponible', $response->json()[0]);
    }

    public function test_recipes_do_not_offer_sos_and_keep_duration_and_frequency_required(): void
    {
        $patientId = DB::table('pacientes')->where('rut', '123456785')->value('rut');
        $professionalId = DB::table('personal_salud')->value('rut');
        $productId = DB::table('productos')->where('numero_serie', 'MED-001')->value('producto_id');
        $this->get(route('recipes.create'))
            ->assertOk()
            ->assertDontSee('Tratamiento SOS')
            ->assertDontSee('La cantidad prescrita será el máximo autorizado.')
            ->assertDontSee('data-sos-treatment', false);
        $basePayload = [
            'paciente_rut' => $patientId,
            'profesional_rut' => $professionalId,
            'fecha_emision' => now()->toDateString(),
            'fecha_vencimiento' => now()->addMonth()->toDateString(),
            'tipo_receta' => 'ESTANDAR',
            'detalles' => [[
                'producto_id' => $productId,
                'cantidad_indicada' => 10,
                'unidades_por_toma' => 1,
            ]],
        ];

        $this->from(route('recipes.index'))->post(route('recipes.store'), $basePayload + [
            'numero_receta' => 'REC-SOS-MISSING-001',
        ])->assertRedirect(route('recipes.index'))
            ->assertSessionHasErrors('detalles.0.duracion_dias');
        $this->assertDatabaseCount('recetas', 0);

        $this->from(route('recipes.index'))->post(route('recipes.store'), array_merge($basePayload, [
            'numero_receta' => 'REC-SOS-001',
            'detalles' => [[
                'producto_id' => $productId,
                'cantidad_indicada' => 10,
                'unidades_por_toma' => 1,
                'duracion_dias' => 10,
                'frecuencia' => 'Cada 8 horas',
                'es_sos' => true,
            ]],
        ]))->assertRedirect(route('recipes.index'))
            ->assertSessionHasErrors('detalles.0.es_sos');

        $this->post(route('recipes.store'), array_merge($basePayload, [
            'numero_receta' => 'REC-NO-SOS-001',
            'detalles' => [[
                'producto_id' => $productId,
                'cantidad_indicada' => 10,
                'unidades_por_toma' => 1,
                'duracion_dias' => 10,
                'frecuencia' => 'Cada 8 horas',
            ]],
        ]))->assertRedirect(route('recipes.index'));

        $this->assertDatabaseHas('receta_detalle', [
            'producto_id' => $productId,
            'cantidad_indicada' => 10,
            'frecuencia' => 'Cada 8 horas',
            'duracion_dias' => 10,
            'es_sos' => false,
        ]);
        $prescriptionId = DB::table('recetas')->where('numero_receta', 'REC-NO-SOS-001')->value('receta_id');
        $this->get(route('recipes.print', $prescriptionId))
            ->assertOk()
            ->assertSee('10 días')
            ->assertDontSee('SOS · máximo prescrito');
    }

    public function test_prescription_print_lists_all_dispensed_products_for_the_selected_order(): void
    {
        $patientId = DB::table('pacientes')->where('rut', '123456785')->value('rut');
        $professionalId = DB::table('personal_salud')->value('rut');
        $products = DB::table('productos')->where('activo', true)->limit(2)->get(['producto_id', 'nombre']);
        $this->assertCount(2, $products);

        $recipeId = DB::table('recetas')->insertGetId([
            'paciente_rut' => $patientId,
            'profesional_rut' => $professionalId,
            'numero_receta' => 'REC-PRINT-MULTI-001',
            'fecha_emision' => now()->toDateString(),
            'fecha_vencimiento' => now()->addMonth()->toDateString(),
            'tipo_receta' => 'ESTANDAR',
            'estado' => 'VIGENTE',
        ]);

        DB::table('receta_detalle')->insert([
            [
                'receta_id' => $recipeId,
                'producto_id' => $products[0]->producto_id,
                'cantidad_indicada' => 10,
                'dosis' => '1 comprimido',
                'frecuencia' => 'Cada 8 horas',
                'indicaciones' => 'Tomar con agua',
            ],
            [
                'receta_id' => $recipeId,
                'producto_id' => $products[1]->producto_id,
                'cantidad_indicada' => 20,
                'dosis' => '1 tableta',
                'frecuencia' => 'Cada 12 horas',
                'indicaciones' => 'Con alimentos',
            ],
        ]);

        $deliveryId = DB::table('entregas')->insertGetId([
            'paciente_rut' => $patientId,
            'receta_id' => $recipeId,
            'usuario_rut' => auth()->id(),
            'estado' => 'CONFIRMADA',
            'observacion' => 'Entrega confirmada',
        ]);

        $firstLotId = DB::table('lotes')->where('producto_id', $products[0]->producto_id)->value('lote_id');
        $secondLotId = DB::table('lotes')->where('producto_id', $products[1]->producto_id)->value('lote_id');

        DB::table('entrega_detalle')->insert([
            [
                'entrega_id' => $deliveryId,
                'lote_id' => $firstLotId,
                'producto_id' => $products[0]->producto_id,
                'cantidad' => 10,
                'dosis' => '1 comprimido',
                'horario' => 'Cada 8 horas',
                'indicaciones' => 'Tomar con agua',
            ],
            [
                'entrega_id' => $deliveryId,
                'lote_id' => $secondLotId,
                'producto_id' => $products[1]->producto_id,
                'cantidad' => 20,
                'dosis' => '1 tableta',
                'horario' => 'Cada 12 horas',
                'indicaciones' => 'Con alimentos',
            ],
        ]);

        $this->get(route('recipes.print', $recipeId))
            ->assertOk()
            ->assertSee($products[0]->nombre)
            ->assertSee($products[1]->nombre)
            ->assertSee('Cada 8 horas')
            ->assertSee('Cada 12 horas');
    }

    public function test_recent_delivery_list_includes_patient_and_responsible_names(): void
    {
        $patientId = DB::table('pacientes')->where('rut', '123456785')->value('rut');
        $userRut = DB::table('usuarios')->where('rut', '12345674')->value('rut');
        $deliveryId = DB::table('entregas')->insertGetId([
            'paciente_rut' => $patientId,
            'usuario_rut' => $userRut,
            'receta_id' => null,
            'estado' => 'CONFIRMADA',
            'fecha_hora' => now(),
        ]);

        $orderId = DB::table('ordenes_entrega')->insertGetId([
            'entrega_id' => $deliveryId,
            'numero_orden' => 'ORD-RECENT-001',
            'fecha_generacion' => now(),
            'plantilla' => 'standard',
        ]);

        $this->get(route('dispensations.deliveries'))
            ->assertOk()
            ->assertSee('person-card', false)
            ->assertSee('María González López')
            ->assertSee('Administración CESFAM Farmacia')
            ->assertSee('ORD-RECENT-001')
            ->assertSee('Ver comprobante')
            ->assertSee('href="'.route('dispensations.deliveries', ['comprobante' => $orderId]).'"', false)
            ->assertDontSee('<table class="data-table">', false);

        $this->get(route('dispensations.deliveries', ['comprobante' => $orderId]))
            ->assertOk()
            ->assertSee('data-dispensation-result-dialog', false)
            ->assertSee('ORD-RECENT-001')
            ->assertSee('id="entregas-recientes"', false)
            ->assertDontSee('id="registrar-entrega"', false);
    }

    public function test_authorized_person_authorization_is_registered_and_can_be_revoked(): void
    {
        $patientId = DB::table('pacientes')->where('rut', '123456785')->value('rut');

        $this->post(route('authorized-people.store'), [
            'paciente_rut' => $patientId,
            'rut' => '13.333.333-9',
            'nombre' => 'Representante',
            'apellidoPaterno' => 'De Prueba',
            'apellidoMaterno' => 'Apellido',
            'vinculo' => 'Familiar',
        ])->assertRedirect(route('authorized-people.index'));

        $authorizedPersonId = DB::table('personas_autorizadas')->where('rut', '133333339')->value('persona_autorizada_id');
        $this->assertDatabaseHas('personas_autorizadas', [
            'persona_autorizada_id' => $authorizedPersonId,
            'rut' => '133333339',
            'nombre' => 'Representante',
            'apellidoPaterno' => 'De Prueba',
            'apellidoMaterno' => 'Apellido',
        ]);
        $this->patch(route('authorized-people.toggle', $authorizedPersonId))->assertRedirect(route('authorized-people.index'));

        $this->assertDatabaseHas('personas_autorizadas', ['persona_autorizada_id' => $authorizedPersonId, 'activo' => false]);
        $this->assertDatabaseHas('auditoria', ['accion' => 'CAMBIO_ESTADO', 'entidad' => 'personas_autorizadas']);
    }

    public function test_shipment_receipt_adds_stock_and_movement_atomically(): void
    {
        $productId = DB::table('productos')->where('numero_serie', 'MED-001')->value('producto_id');
        $secondProductId = DB::table('productos')->where('numero_serie', 'MED-002')->value('producto_id');
        $warehouseId = DB::table('ubicaciones')->where('tipo', 'BODEGA')->value('ubicacion_id');
        $displayCaseId = DB::table('ubicaciones')->where('tipo', 'VITRINA')->value('ubicacion_id');

        $this->post(route('supplies.receive'), [
            'codigo_remesa' => 'REM-TEST-001',
            'origen' => 'CENABAST',
            'fecha_recepcion' => now()->format('Y-m-d H:i:s'),
            'detalles' => [[
                'producto_id' => $productId,
                'ubicacion_id' => $displayCaseId,
                'numero_lote' => 'REM-LOT-TEST',
                'fecha_vencimiento' => now()->addYear()->toDateString(),
                'cantidad' => 50,
            ], [
                'producto_id' => $secondProductId,
                'ubicacion_id' => $warehouseId,
                'numero_lote' => 'REM-LOT-TEST-2',
                'fecha_vencimiento' => now()->addYear()->toDateString(),
                'cantidad' => 25,
            ]],
        ])->assertRedirect(route('supplies.index'));

        $lotId = DB::table('lotes')->where('numero_lote', 'REM-LOT-TEST')->value('lote_id');
        $secondLotId = DB::table('lotes')->where('numero_lote', 'REM-LOT-TEST-2')->value('lote_id');
        $this->assertDatabaseHas('inventario', ['lote_id' => $lotId, 'ubicacion_id' => $displayCaseId, 'cantidad' => 50]);
        $this->assertDatabaseHas('inventario', ['lote_id' => $secondLotId, 'ubicacion_id' => $warehouseId, 'cantidad' => 25]);
        $this->assertDatabaseHas('movimientos_inventario', [
            'referencia_tipo' => 'REMESA',
            'tipo_movimiento' => 'RECEPCION',
            'lote_id' => $lotId,
            'ubicacion_id' => $displayCaseId,
        ]);
        $this->assertDatabaseHas('movimientos_inventario', [
            'referencia_tipo' => 'REMESA',
            'tipo_movimiento' => 'RECEPCION',
            'lote_id' => $secondLotId,
            'ubicacion_id' => $warehouseId,
        ]);
        $this->assertDatabaseHas('remesa_detalle', [
            'numero_lote' => 'REM-LOT-TEST',
            'cantidad' => 50,
            'cantidad_aceptada' => 50,
            'cantidad_rechazada' => 0,
        ]);
        $this->assertSame('ACTIVO', DB::table('lotes')->where('lote_id', $lotId)->value('estado'));
        $this->assertNotNull(DB::table('lotes')->where('lote_id', $lotId)->value('fecha_recepcion'));
    }

    public function test_shipment_receipt_rejects_dates_with_years_longer_than_four_digits(): void
    {
        $productId = DB::table('productos')->where('numero_serie', 'MED-001')->value('producto_id');
        $locationId = DB::table('ubicaciones')->where('tipo', 'VITRINA')->value('ubicacion_id');
        $dateError = 'Ingresa una fecha válida con día y mes de dos dígitos y año de cuatro dígitos.';

        $this->from(route('supplies.index'))->post(route('supplies.receive'), [
            'codigo_remesa' => 'REM-INVALID-YEAR-001',
            'origen' => 'CENABAST',
            'fecha_recepcion' => now()->format('Y-m-d H:i:s'),
            'detalles' => [[
                'producto_id' => $productId,
                'ubicacion_id' => $locationId,
                'numero_lote' => 'INVALID-YEAR-LOT',
                'fecha_vencimiento' => '2000000-11-21',
                'cantidad' => 1,
            ]],
        ])->assertRedirect(route('supplies.index'))
            ->assertSessionHasErrors(['detalles.0.fecha_vencimiento' => $dateError]);

        $this->assertDatabaseMissing('remesas', ['codigo_remesa' => 'REM-INVALID-YEAR-001']);
    }

    public function test_shipment_quality_control_stocks_only_accepted_quantity_and_requires_rejection_reason(): void
    {
        $productId = DB::table('productos')->where('numero_serie', 'MED-001')->value('producto_id');
        $warehouseId = DB::table('ubicaciones')->where('tipo', 'BODEGA')->value('ubicacion_id');
        $oldQuantity = (float) DB::table('inventario')
            ->join('lotes', 'lotes.lote_id', '=', 'inventario.lote_id')
            ->where('lotes.producto_id', $productId)
            ->where('ubicacion_id', $warehouseId)
            ->sum('inventario.cantidad');

        $this->post(route('supplies.receive'), [
            'codigo_remesa' => 'REM-QUALITY-001',
            'origen' => 'CENABAST',
            'fecha_recepcion' => now()->format('Y-m-d H:i:s'),
            'detalles' => [[
                'producto_id' => $productId,
                'ubicacion_id' => $warehouseId,
                'numero_lote' => 'REM-QUALITY-LOT',
                'fecha_vencimiento' => now()->addYear()->toDateString(),
                'cantidad' => 12,
                'cantidad_rechazada' => 4,
                'motivo_rechazo' => 'Envases dañados',
            ]],
        ])->assertRedirect(route('supplies.index'));

        $lotId = DB::table('lotes')->where('numero_lote', 'REM-QUALITY-LOT')->value('lote_id');
        $this->assertDatabaseHas('remesa_detalle', [
            'numero_lote' => 'REM-QUALITY-LOT',
            'cantidad' => 12,
            'cantidad_aceptada' => 8,
            'cantidad_rechazada' => 4,
            'motivo_rechazo' => 'Envases dañados',
        ]);
        $this->assertDatabaseHas('inventario', [
            'lote_id' => $lotId,
            'ubicacion_id' => $warehouseId,
            'cantidad' => 8,
        ]);
        $this->assertDatabaseHas('movimientos_inventario', [
            'lote_id' => $lotId,
            'tipo_movimiento' => 'RECEPCION',
            'cantidad' => 8,
        ]);

        $this->from(route('supplies.receive.form'))->post(route('supplies.receive'), [
            'codigo_remesa' => 'REM-QUALITY-002',
            'origen' => 'CENABAST',
            'fecha_recepcion' => now()->format('Y-m-d H:i:s'),
            'detalles' => [[
                'producto_id' => $productId,
                'ubicacion_id' => $warehouseId,
                'numero_lote' => 'REM-QUALITY-LOT-2',
                'fecha_vencimiento' => now()->addYear()->toDateString(),
                'cantidad' => 3,
                'cantidad_rechazada' => 1,
            ]],
        ])->assertSessionHasErrors('detalles.0.motivo_rechazo');

        $this->assertDatabaseMissing('remesas', ['codigo_remesa' => 'REM-QUALITY-002']);
        $this->assertSame(
            $oldQuantity + 8,
            (float) DB::table('inventario')
                ->join('lotes', 'lotes.lote_id', '=', 'inventario.lote_id')
                ->where('lotes.producto_id', $productId)
                ->where('ubicacion_id', $warehouseId)
                ->sum('inventario.cantidad')
        );
    }

    public function test_shipment_receipt_requires_a_registered_location_for_each_product(): void
    {
        $productId = DB::table('productos')->where('numero_serie', 'MED-001')->value('producto_id');

        $this->from(route('supplies.receive.form'))->post(route('supplies.receive'), [
            'codigo_remesa' => 'REM-INVALID-LOCATION',
            'origen' => 'CENABAST',
            'fecha_recepcion' => now()->format('Y-m-d H:i:s'),
            'detalles' => [[
                'producto_id' => $productId,
                'ubicacion_id' => 999999,
                'numero_lote' => 'REM-INVALID-LOCATION-LOT',
                'fecha_vencimiento' => now()->addYear()->toDateString(),
                'cantidad' => 10,
            ]],
        ])->assertRedirect(route('supplies.receive.form'))
            ->assertSessionHasErrors('detalles.0.ubicacion_id');

        $this->assertDatabaseMissing('remesas', ['codigo_remesa' => 'REM-INVALID-LOCATION']);
    }

    public function test_linked_shipment_requires_requested_products_but_stocks_actual_received_quantity(): void
    {
        $productId = DB::table('productos')->where('numero_serie', 'MED-001')->value('producto_id');
        $otherProductId = DB::table('productos')->where('numero_serie', 'MED-002')->value('producto_id');
        $warehouseId = DB::table('ubicaciones')->where('tipo', 'BODEGA')->value('ubicacion_id');
        $this->post(route('supplies.requests.store'), [
            'detalles' => [['producto_id' => $productId, 'cantidad_solicitada' => 100]],
        ])->assertRedirect(route('supplies.requests.index'));
        $requestId = DB::table('solicitudes_reposicion')->value('solicitud_id');
        $expiry = now()->addYear()->toDateString();
        $details = [
            ['producto_id' => $productId, 'ubicacion_id' => $warehouseId, 'numero_lote' => 'REQUEST-LOT-A', 'fecha_vencimiento' => $expiry, 'cantidad' => 40],
            ['producto_id' => $otherProductId, 'ubicacion_id' => $warehouseId, 'numero_lote' => 'REQUEST-LOT-B', 'fecha_vencimiento' => $expiry, 'cantidad' => 60],
        ];

        $this->from(route('supplies.receive.form'))->post(route('supplies.receive'), [
            'codigo_remesa' => 'REQUEST-MISMATCH',
            'origen' => 'CENABAST',
            'fecha_recepcion' => now()->format('Y-m-d H:i:s'),
            'solicitud_id' => $requestId,
            'detalles' => $details,
        ])->assertRedirect(route('supplies.receive.form'))->assertSessionHasErrors('detalles');
        $this->assertDatabaseMissing('remesas', ['codigo_remesa' => 'REQUEST-MISMATCH']);

        $details = [[
            'producto_id' => $productId,
            'ubicacion_id' => $warehouseId,
            'numero_lote' => 'REQUEST-LOT-RECEIVED',
            'fecha_vencimiento' => $expiry,
            'cantidad_envases' => 1000,
            'unidades_por_envase' => 2,
        ]];
        $this->post(route('supplies.receive'), [
            'codigo_remesa' => 'REQUEST-MATCH',
            'origen' => 'CENABAST',
            'fecha_recepcion' => now()->format('Y-m-d H:i:s'),
            'solicitud_id' => $requestId,
            'detalles' => $details,
        ])->assertRedirect(route('supplies.index'));
        $this->assertDatabaseHas('solicitudes_reposicion', ['solicitud_id' => $requestId, 'estado' => 'RECIBIDA']);
        $this->assertDatabaseHas('remesa_detalle', [
            'numero_lote' => 'REQUEST-LOT-RECEIVED',
            'cantidad' => 2000,
            'cantidad_envases' => 1000,
            'factor_conversion' => 2,
        ]);
        $lotId = DB::table('lotes')->where('numero_lote', 'REQUEST-LOT-RECEIVED')->value('lote_id');
        $this->assertDatabaseHas('inventario', [
            'lote_id' => $lotId,
            'ubicacion_id' => $warehouseId,
            'cantidad' => 2000,
        ]);
        $this->assertDatabaseCount('remesa_detalle', 1);

        $this->from(route('supplies.receive.form'))->post(route('supplies.receive'), [
            'codigo_remesa' => 'REQUEST-SECOND-RECEIPT',
            'origen' => 'CENABAST',
            'fecha_recepcion' => now()->format('Y-m-d H:i:s'),
            'solicitud_id' => $requestId,
            'detalles' => $details,
        ])->assertRedirect(route('supplies.receive.form'))->assertSessionHasErrors('solicitud_id');
        $this->assertDatabaseMissing('remesas', ['codigo_remesa' => 'REQUEST-SECOND-RECEIPT']);
    }

    public function test_shipments_and_reorder_requests_can_be_searched_and_filtered(): void
    {
        $productId = DB::table('productos')->where('numero_serie', 'MED-001')->value('producto_id');
        $requestId = DB::table('solicitudes_reposicion')->insertGetId([
            'estado' => 'RECIBIDA',
            'usuario_rut' => auth()->id(),
            'fecha_solicitud' => now()->subDay(),
        ]);
        DB::table('solicitud_reposicion_detalle')->insert([
            'solicitud_id' => $requestId,
            'producto_id' => $productId,
            'cantidad_solicitada' => 12,
        ]);
        DB::table('remesas')->insert([
            'codigo_remesa' => 'REM-FILTRO-01',
            'origen' => 'Proveedor de prueba',
            'fecha_recepcion' => now()->subDay(),
            'solicitud_id' => $requestId,
            'usuario_rut' => auth()->id(),
        ]);

        $this->get(route('supplies.index', [
            'buscar_remesa' => 'REM-FILTRO-01',
            'estado_solicitud' => 'RECIBIDA',
        ]))
            ->assertOk()
            ->assertSee('<div class="person-card-grid">', false)
            ->assertSee('name="buscar_remesa"', false)
            ->assertSee('name="estado_solicitud"', false)
            ->assertDontSee('<table class="data-table">', false)
            ->assertSee('REM-FILTRO-01')
            ->assertSee('Proveedor de prueba')
            ->assertSee('RECIBIDA');

        $this->get(route('supplies.requests.index', [
            'buscar_solicitud' => 'Paracetamol',
            'estado_solicitud_lista' => 'RECIBIDA',
        ]))
            ->assertOk()
            ->assertSee('<div class="person-card-grid">', false)
            ->assertSee('name="buscar_solicitud"', false)
            ->assertSee('name="estado_solicitud_lista"', false)
            ->assertDontSee('<table class="data-table">', false)
            ->assertSee('#'.$requestId)
            ->assertSee('Fecha')
            ->assertSee('12');
    }

    public function test_inventory_receipt_adds_to_existing_product_lot_and_location(): void
    {
        $productId = DB::table('productos')->where('numero_serie', 'MED-001')->value('producto_id');
        $lot = DB::table('lotes')->where('producto_id', $productId)->where('numero_lote', 'L250821')->first();
        $warehouseId = DB::table('ubicaciones')->where('tipo', 'BODEGA')->value('ubicacion_id');
        $inventoryId = DB::table('inventario')->where('lote_id', $lot->lote_id)->where('ubicacion_id', $warehouseId)->value('inventario_id');
        $oldQuantity = DB::table('inventario')->where('inventario_id', $inventoryId)->value('cantidad');
        $oldMinimum = DB::table('stock_minimos_ubicacion')
            ->where('producto_id', $productId)
            ->where('ubicacion_id', $warehouseId)
            ->value('stock_minimo');
        $movementCount = DB::table('movimientos_inventario')->where('lote_id', $lot->lote_id)->count();

        $this->post(route('inventory.receive'), [
            'producto_id' => $productId,
            'ubicacion_id' => $warehouseId,
            'numero_lote' => 'L250821',
            'fecha_vencimiento' => $lot->fecha_vencimiento,
            'cantidad' => 25,
            'origen' => 'Reposición de prueba',
            'documento_recepcion' => 'FAC-REC-001',
        ])->assertRedirect(route('inventory.index'));

        $this->assertDatabaseHas('inventario', [
            'inventario_id' => $inventoryId,
            'cantidad' => (float) $oldQuantity + 25,
        ]);
        $this->assertDatabaseHas('stock_minimos_ubicacion', [
            'producto_id' => $productId,
            'ubicacion_id' => $warehouseId,
            'stock_minimo' => $oldMinimum,
        ]);
        $this->assertSame($movementCount + 1, DB::table('movimientos_inventario')->where('lote_id', $lot->lote_id)->count());
    }

    public function test_reception_archives_empty_inventory_rows_to_kardex_for_both_receipt_flows(): void
    {
        $productId = DB::table('productos')->where('numero_serie', 'MED-001')->value('producto_id');
        $lot = DB::table('lotes')->where('producto_id', $productId)->where('numero_lote', 'L250821')->first();
        $warehouseId = DB::table('ubicaciones')->where('tipo', 'BODEGA')->value('ubicacion_id');
        $inventory = DB::table('inventario')
            ->where('lote_id', $lot->lote_id)
            ->where('ubicacion_id', $warehouseId)
            ->first();
        $previousLotId = DB::table('lotes')->insertGetId([
            'producto_id' => $productId,
            'numero_lote' => 'KARDEX-PREVIOUS-EMPTY-DIRECT',
            'fecha_vencimiento' => now()->addYear()->toDateString(),
            'estado' => 'ACTIVO',
        ]);
        $previousInventoryId = DB::table('inventario')->insertGetId([
            'lote_id' => $previousLotId,
            'ubicacion_id' => $warehouseId,
            'cantidad' => 0,
        ]);

        DB::table('inventario')->where('inventario_id', $inventory->inventario_id)->update(['cantidad' => 0]);
        $this->assertDatabaseHas('inventario', ['inventario_id' => $inventory->inventario_id, 'cantidad' => 0]);
        $this->assertDatabaseMissing('kardex_inventario', ['inventario_id_origen' => $inventory->inventario_id]);

        $this->post(route('inventory.receive'), [
            'producto_id' => $productId,
            'ubicacion_id' => $warehouseId,
            'numero_lote' => $lot->numero_lote,
            'fecha_vencimiento' => $lot->fecha_vencimiento,
            'cantidad' => 5,
            'origen' => 'Recepción de prueba',
            'documento_recepcion' => 'KARDEX-DIRECT-001',
        ])->assertRedirect(route('inventory.index'));

        $this->assertDatabaseMissing('inventario', ['inventario_id' => $inventory->inventario_id]);
        $this->assertDatabaseHas('inventario', [
            'lote_id' => $lot->lote_id,
            'ubicacion_id' => $warehouseId,
            'cantidad' => 5,
        ]);
        $this->assertDatabaseHas('kardex_inventario', [
            'inventario_id_origen' => $inventory->inventario_id,
            'motivo' => 'REINGRESO_MISMO_LOTE',
            'documento_recepcion' => 'KARDEX-DIRECT-001',
        ]);
        $this->assertDatabaseMissing('inventario', ['inventario_id' => $previousInventoryId]);
        $this->assertDatabaseHas('kardex_inventario', [
            'inventario_id_origen' => $previousInventoryId,
            'motivo' => 'RECEPCION_OTRO_LOTE',
            'referencia_tipo' => 'RECEPCION',
            'documento_recepcion' => 'KARDEX-DIRECT-001',
        ]);

        $reenteredInventory = DB::table('inventario')
            ->where('lote_id', $lot->lote_id)
            ->where('ubicacion_id', $warehouseId)
            ->first();
        DB::table('inventario')->where('inventario_id', $reenteredInventory->inventario_id)->update(['cantidad' => 0]);
        $shipmentPreviousLotId = DB::table('lotes')->insertGetId([
            'producto_id' => $productId,
            'numero_lote' => 'KARDEX-PREVIOUS-EMPTY-SHIPMENT',
            'fecha_vencimiento' => now()->addYear()->toDateString(),
            'estado' => 'ACTIVO',
        ]);
        $shipmentPreviousInventoryId = DB::table('inventario')->insertGetId([
            'lote_id' => $shipmentPreviousLotId,
            'ubicacion_id' => $warehouseId,
            'cantidad' => 0,
        ]);
        $this->assertDatabaseHas('inventario', ['inventario_id' => $reenteredInventory->inventario_id, 'cantidad' => 0]);
        $this->assertDatabaseMissing('kardex_inventario', ['inventario_id_origen' => $reenteredInventory->inventario_id]);

        $this->post(route('supplies.receive'), [
            'codigo_remesa' => 'KARDEX-SHIPMENT-001',
            'origen' => 'CENABAST',
            'fecha_recepcion' => now()->format('Y-m-d H:i:s'),
            'detalles' => [[
                'producto_id' => $productId,
                'ubicacion_id' => $warehouseId,
                'numero_lote' => $lot->numero_lote,
                'fecha_vencimiento' => $lot->fecha_vencimiento,
                'cantidad' => 7,
            ]],
        ])->assertRedirect(route('supplies.index'));

        $this->assertDatabaseMissing('inventario', ['inventario_id' => $reenteredInventory->inventario_id]);
        $this->assertDatabaseHas('inventario', [
            'lote_id' => $lot->lote_id,
            'ubicacion_id' => $warehouseId,
            'cantidad' => 7,
        ]);
        $this->assertDatabaseHas('kardex_inventario', [
            'inventario_id_origen' => $reenteredInventory->inventario_id,
            'referencia_tipo' => 'REMESA',
            'documento_recepcion' => 'KARDEX-SHIPMENT-001',
        ]);
        $this->assertDatabaseMissing('inventario', ['inventario_id' => $shipmentPreviousInventoryId]);
        $this->assertDatabaseHas('kardex_inventario', [
            'inventario_id_origen' => $shipmentPreviousInventoryId,
            'motivo' => 'RECEPCION_OTRO_LOTE',
            'referencia_tipo' => 'REMESA',
            'documento_recepcion' => 'KARDEX-SHIPMENT-001',
        ]);
        $this->get(route('reports.kardex'))
            ->assertOk()
            ->assertSee('Kardex de inventario histórico')
            ->assertSeeInOrder([
                'class="form-grid report-kardex-filters"',
                'for="buscar-kardex"',
                'for="desde"',
                'for="hasta"',
            ], false)
            ->assertSee('Imprimir CSV')
            ->assertDontSee('Imprimir / PDF')
            ->assertSee('KARDEX-DIRECT-001')
            ->assertSee('KARDEX-SHIPMENT-001')
            ->assertSee($lot->numero_lote);

        $export = $this->get(route('reports.export', ['tipo' => 'kardex', 'buscar_kardex' => 'KARDEX-SHIPMENT-001']));
        $export->assertDownload('reporte-kardex-historico.csv');
        $content = $export->streamedContent();
        $this->assertStringContainsString('KARDEX-SHIPMENT-001', $content);
        $this->assertStringNotContainsString('KARDEX-DIRECT-001', $content);
    }

    public function test_direct_inventory_receipt_records_only_accepted_quantity(): void
    {
        $productId = DB::table('productos')->where('numero_serie', 'MED-001')->value('producto_id');
        $warehouseId = DB::table('ubicaciones')->where('tipo', 'BODEGA')->value('ubicacion_id');

        $this->post(route('inventory.receive'), [
            'producto_id' => $productId,
            'ubicacion_id' => $warehouseId,
            'numero_lote' => 'DIRECT-QUALITY-LOT',
            'fecha_vencimiento' => now()->addYear()->toDateString(),
            'cantidad' => 10,
            'cantidad_rechazada' => 4,
            'motivo_rechazo' => 'Envases deteriorados',
            'origen' => 'Recepción directa',
            'documento_recepcion' => 'ACTA-REC-001',
        ])->assertRedirect(route('inventory.index'));

        $lotId = DB::table('lotes')->where('numero_lote', 'DIRECT-QUALITY-LOT')->value('lote_id');
        $this->assertDatabaseHas('inventario', [
            'lote_id' => $lotId,
            'ubicacion_id' => $warehouseId,
            'cantidad' => 6,
        ]);
        $this->assertDatabaseHas('movimientos_inventario', [
            'lote_id' => $lotId,
            'tipo_movimiento' => 'RECEPCION',
            'cantidad' => 6,
        ]);
        $this->assertDatabaseHas('auditoria', [
            'accion' => 'RECEPCION',
            'entidad' => 'inventario',
        ]);

        $this->from(route('inventory.index'))->post(route('inventory.receive'), [
            'producto_id' => $productId,
            'ubicacion_id' => $warehouseId,
            'numero_lote' => 'DIRECT-QUALITY-LOT-2',
            'fecha_vencimiento' => now()->addYear()->toDateString(),
            'cantidad' => 2,
            'cantidad_rechazada' => 1,
            'origen' => 'Recepción directa',
            'documento_recepcion' => 'ACTA-REC-002',
        ])->assertSessionHasErrors('motivo_rechazo');

        $this->assertDatabaseMissing('lotes', ['numero_lote' => 'DIRECT-QUALITY-LOT-2']);
    }

    public function test_direct_inventory_receipt_converts_packages_and_keeps_conversion_snapshot(): void
    {
        $productId = DB::table('productos')->where('numero_serie', 'MED-001')->value('producto_id');
        $warehouseId = DB::table('ubicaciones')->where('tipo', 'BODEGA')->value('ubicacion_id');
        $presentationId = DB::table('presentaciones_producto')->insertGetId([
            'producto_id' => $productId,
            'nombre' => 'Caja de 10 unidades',
            'factor_conversion' => 10,
            'activo' => true,
        ]);

        $this->post(route('inventory.receive'), [
            'producto_id' => $productId,
            'ubicacion_id' => $warehouseId,
            'numero_lote' => 'DIRECT-CONVERSION-LOT',
            'fecha_fabricacion' => now()->subMonths(3)->toDateString(),
            'fecha_vencimiento' => now()->addYear()->toDateString(),
            'presentacion_id' => $presentationId,
            'cantidad_envases' => 2,
            'unidades_sueltas' => 3,
            'cantidad_rechazada' => 4,
            'motivo_rechazo' => 'Cuatro unidades dañadas',
            'origen' => 'Recepción directa',
            'documento_recepcion' => 'ACTA-REC-003',
        ])->assertRedirect(route('inventory.index'));

        $lotId = DB::table('lotes')->where('numero_lote', 'DIRECT-CONVERSION-LOT')->value('lote_id');
        $this->assertDatabaseHas('lotes', [
            'lote_id' => $lotId,
            'fecha_fabricacion' => now()->subMonths(3)->toDateString(),
        ]);
        $this->assertDatabaseHas('inventario', ['lote_id' => $lotId, 'cantidad' => 19]);
        $this->assertDatabaseHas('movimientos_inventario', [
            'lote_id' => $lotId,
            'cantidad' => 19,
            'cantidad_original' => 2,
            'cantidad_envases' => 2,
            'unidades_sueltas' => 3,
            'factor_conversion' => 10,
            'presentacion_nombre' => 'Caja de 10 unidades',
        ]);

        DB::table('presentaciones_producto')->where('presentacion_id', $presentationId)
            ->update(['factor_conversion' => 20]);
        $this->assertDatabaseHas('movimientos_inventario', [
            'lote_id' => $lotId,
            'factor_conversion' => 10,
            'cantidad' => 19,
        ]);
    }

    public function test_shipment_receipt_converts_packages_before_quality_control(): void
    {
        $productId = DB::table('productos')->where('numero_serie', 'MED-002')->value('producto_id');
        $warehouseId = DB::table('ubicaciones')->where('tipo', 'BODEGA')->value('ubicacion_id');

        $this->get(route('supplies.receive.form'))
            ->assertOk()
            ->assertSee('name="detalles[0][fecha_fabricacion]"', false)
            ->assertSee('name="detalles[0][ubicacion_id]"', false);
        $this->post(route('supplies.receive'), [
            'codigo_remesa' => 'REM-CONVERSION-001',
            'origen' => 'Proveedor de prueba',
            'fecha_recepcion' => now()->format('Y-m-d\TH:i'),
            'detalles' => [[
                'producto_id' => $productId,
                'ubicacion_id' => $warehouseId,
                'numero_lote' => 'REM-CONVERSION-LOT',
                'fecha_fabricacion' => now()->subMonths(3)->toDateString(),
                'fecha_vencimiento' => now()->addYear()->toDateString(),
                'cantidad_envases' => 2,
                'unidades_por_envase' => 16,
                'cantidad_rechazada' => 3,
                'motivo_rechazo' => 'Tres unidades dañadas',
            ]],
        ])->assertRedirect(route('supplies.index'));

        $lotId = DB::table('lotes')->where('numero_lote', 'REM-CONVERSION-LOT')->value('lote_id');
        $this->assertDatabaseHas('lotes', [
            'lote_id' => $lotId,
            'fecha_fabricacion' => now()->subMonths(3)->toDateString(),
        ]);
        $this->assertDatabaseHas('inventario', ['lote_id' => $lotId, 'cantidad' => 29]);
        $this->assertDatabaseHas('remesa_detalle', [
            'numero_lote' => 'REM-CONVERSION-LOT',
            'fecha_fabricacion' => now()->subMonths(3)->toDateString(),
            'cantidad' => 32,
            'cantidad_aceptada' => 29,
            'cantidad_rechazada' => 3,
            'cantidad_envases' => 2,
            'unidades_sueltas' => 0,
            'factor_conversion' => 16,
            'presentacion_nombre' => 'Envase de 16 unidad',
        ]);
        $this->assertDatabaseHas('movimientos_inventario', [
            'lote_id' => $lotId,
            'cantidad' => 29,
            'factor_conversion' => 16,
            'cantidad_original' => 2,
        ]);
    }

    public function test_inventory_groups_products_and_shows_distinct_lots_in_fefo_order(): void
    {
        $productId = DB::table('productos')->where('numero_serie', 'MED-002')->value('producto_id');
        $warehouseId = DB::table('ubicaciones')->where('tipo', 'BODEGA')->value('ubicacion_id');

        $this->post(route('inventory.receive'), [
            'producto_id' => $productId,
            'ubicacion_id' => $warehouseId,
            'numero_lote' => 'AMOX-LOT-NUEVO',
            'fecha_vencimiento' => now()->addMonths(6)->toDateString(),
            'cantidad' => 30,
            'origen' => 'Reposición con nuevo vencimiento',
            'documento_recepcion' => 'FAC-REC-002',
        ])->assertRedirect(route('inventory.index'));

        $this->get(route('inventory.index'))
            ->assertOk()
            ->assertSee('Existencias por medicamento / insumo')
            ->assertSee('Amoxicilina 500 mg')
            ->assertSee('450');

        $response = $this->get(route('products.stock', $productId));
        $response->assertOk()
            ->assertSee('product-stock-navigation', false)
            ->assertSee('href="'.route('inventory.index').'"', false)
            ->assertSee('Volver al inventario')
            ->assertSee('product-stock-summary', false)
            ->assertSee('Stock disponible')
            ->assertSee('Stock registrado')
            ->assertSee('Lotes registrados')
            ->assertSee('Lotes y existencias por ubicación')
            ->assertSee('Stock agrupado por ubicación')
            ->assertSee('450')
            ->assertSee('AMOX-LOT-NUEVO')
            ->assertSee('L260201')
            ->assertSee('Carro de Paro')
            ->assertSee('Carro Unidosis')
            ->assertSee('Stock vencido')
            ->assertSee('Stock bloqueado')
            ->assertSee(now()->addMonths(18)->format('d/m/Y'));
        $this->assertLessThan(
            strpos($response->getContent(), 'Stock agrupado por ubicación'),
            strpos($response->getContent(), 'Lotes y existencias por ubicación'),
            'The FEFO lot table should appear before the location summary.'
        );

        $inventoryResponse = $this->getJson(route('inventory.api.index'))
            ->assertOk()
            ->assertJsonPath('per_page', 25)
            ->assertJsonPath('current_page', 1)
            ->assertJsonFragment([
                'medicamento_id' => $productId,
                'codigo' => 'MED-002',
                'nombre' => 'Amoxicilina 500 mg',
                'stock_total' => 450.0,
                'stock_bodega' => 430.0,
                'stock_vitrina' => 20.0,
                'cantidad_lotes' => 2,
            ]);
        self::assertLessThanOrEqual(25, count($inventoryResponse->json('data')));

        $lotResponse = $this->getJson(route('products.api.lots', $productId))
            ->assertOk()
            ->assertJsonPath('medicamento.medicamento_id', $productId)
            ->assertJsonPath('medicamento.nombre', 'Amoxicilina 500 mg')
            ->assertJsonPath('stock_total', 450)
            ->assertJsonPath('stock_fisico', 450)
            ->assertJsonCount(3, 'lotes')
            ->assertJsonFragment([
                'numero_lote' => 'AMOX-LOT-NUEVO',
                'ubicacion' => 'Bodega Central',
                'cantidad' => 30.0,
                'estado_lote' => 'ACTIVO',
            ]);
        $lotResponse->assertJsonFragment([
            'numero_lote' => 'L260201',
            'ubicacion' => 'Bodega Central',
            'cantidad' => 400.0,
        ])->assertJsonFragment([
            'numero_lote' => 'L260201',
            'ubicacion' => 'Vitrina Farmacia',
            'cantidad' => 20.0,
        ]);
        $this->assertDatabaseHas('lotes', [
            'producto_id' => $productId,
            'numero_lote' => 'AMOX-LOT-NUEVO',
            'estado' => 'ACTIVO',
        ]);
        $this->assertDatabaseHas('lotes', ['producto_id' => $productId, 'numero_lote' => 'L260201']);
        $this->assertSame(1, DB::table('productos')->where('numero_serie', 'MED-002')->count());
        $this->assertSame(2, DB::table('lotes')->where('producto_id', $productId)->count());

        $this->assertLessThan(
            strpos($response->getContent(), 'L260201'),
            strpos($response->getContent(), 'AMOX-LOT-NUEVO'),
            'The earlier-expiring lot should appear first in FEFO order.'
        );
    }

    public function test_receiving_same_lot_with_a_different_expiry_explains_new_lot_entry(): void
    {
        $productId = DB::table('productos')->where('numero_serie', 'MED-002')->value('producto_id');
        $this->from(route('inventory.index'))->post(route('inventory.receive'), [
            'producto_id' => $productId,
            'ubicacion_id' => DB::table('ubicaciones')->where('tipo', 'BODEGA')->value('ubicacion_id'),
            'numero_lote' => 'L260201',
            'fecha_vencimiento' => now()->addMonths(12)->toDateString(),
            'cantidad' => 10,
            'origen' => 'Reposición',
            'documento_recepcion' => 'FAC-REC-003',
        ])->assertRedirect(route('inventory.index'))
            ->assertSessionHasErrors([
                'numero_lote' => 'Ese número de lote ya está asociado a otra fecha de vencimiento. Ingresa el número de lote que aparece en el nuevo envase; cada lote con vencimiento distinto se registra por separado.',
            ]);
    }

    public function test_stock_limits_are_configured_per_product_and_location_outside_receiving(): void
    {
        $productId = DB::table('productos')->where('numero_serie', 'MED-002')->value('producto_id');
        $locationId = DB::table('ubicaciones')->where('nombre', 'Carro de Paro')->value('ubicacion_id');

        $this->put(route('products.stock-limits.update', $productId), [
            'ubicacion_id' => $locationId,
            'stock_minimo' => 12,
            'stock_maximo' => 40,
        ])->assertRedirect(route('products.stock', $productId).'#stock-ubicacion-'.$locationId);

        $this->assertDatabaseHas('stock_minimos_ubicacion', [
            'producto_id' => $productId,
            'ubicacion_id' => $locationId,
            'stock_minimo' => 12,
            'stock_maximo' => 40,
        ]);
        $this->get(route('products.stock', $productId))
            ->assertOk()
            ->assertSee('Carro de Paro')
            ->assertSee('12')
            ->assertSee('40')
            ->assertSee('FALTANTE AL MÍNIMO');

        $this->from(route('products.stock', $productId))
            ->put(route('products.stock-limits.update', $productId), [
                'ubicacion_id' => $locationId,
                'stock_minimo' => 41,
                'stock_maximo' => 40,
            ])
            ->assertRedirect(route('products.stock', $productId).'#stock-ubicacion-'.$locationId)
            ->assertSessionHasErrors('stock_maximo');

        $this->assertDatabaseHas('stock_minimos_ubicacion', [
            'producto_id' => $productId,
            'ubicacion_id' => $locationId,
            'stock_minimo' => 12,
            'stock_maximo' => 40,
        ]);
    }

    public function test_product_stock_separates_expiry_from_lot_availability_and_lists_empty_locations(): void
    {
        $productId = DB::table('productos')->where('numero_serie', 'MED-002')->value('producto_id');
        $cartLocationId = DB::table('ubicaciones')->where('nombre', 'Carro de Paro')->value('ubicacion_id');
        $unitLocationId = DB::table('ubicaciones')->where('nombre', 'Carro Unidosis')->value('ubicacion_id');
        $inactiveLotId = DB::table('lotes')->insertGetId([
            'producto_id' => $productId,
            'numero_lote' => 'BLOCKED-LOT',
            'fecha_vencimiento' => now()->addYear()->toDateString(),
            'estado' => 'INACTIVO',
        ]);
        DB::table('inventario')->insert([
            'lote_id' => $inactiveLotId,
            'ubicacion_id' => $cartLocationId,
            'cantidad' => 7,
        ]);
        $expiredLotId = DB::table('lotes')->insertGetId([
            'producto_id' => $productId,
            'numero_lote' => 'EXPIRED-UNIT-LOT',
            'fecha_vencimiento' => now()->subDay()->toDateString(),
        ]);
        DB::table('inventario')->insert([
            'lote_id' => $expiredLotId,
            'ubicacion_id' => $unitLocationId,
            'cantidad' => 3,
        ]);

        $this->get(route('products.stock', $productId))
            ->assertOk()
            ->assertSee('Carro de Paro')
            ->assertSee('Carro Unidosis')
            ->assertSee('Lote inactivo')
            ->assertSee('EXPIRED-UNIT-LOT')
            ->assertSee('Vencido')
            ->assertSee('No disponible');

        $this->getJson(route('products.api.lots', $productId))
            ->assertOk()
            ->assertJsonPath('stock_bloqueado', 7)
            ->assertJsonPath('stock_vencido', 3)
            ->assertJsonFragment([
                'numero_lote' => 'BLOCKED-LOT',
                'estado_disponibilidad' => 'BLOQUEADO',
            ])
            ->assertJsonFragment([
                'numero_lote' => 'EXPIRED-UNIT-LOT',
                'condicion_vencimiento' => 'VENCIDO',
                'estado_disponibilidad' => 'NO_DISPONIBLE',
            ]);
    }

    public function test_product_stock_lots_are_paginated_and_summary_covers_all_lots(): void
    {
        $productId = DB::table('productos')->where('numero_serie', 'MED-002')->value('producto_id');
        foreach (range(1, 25) as $index) {
            DB::table('lotes')->insert([
                'producto_id' => $productId,
                'numero_lote' => 'PAGE-STOCK-'.str_pad((string) $index, 2, '0', STR_PAD_LEFT),
                'fecha_vencimiento' => now()->addYears(5)->toDateString(),
            ]);
        }

        $this->get(route('products.stock', ['productId' => $productId, 'page' => 2]))
            ->assertOk()
            ->assertSee('27 registros')
            ->assertSee('PAGE-STOCK-25')
            ->assertSee('Página 2 de 2')
            ->assertSee('420');
    }

    public function test_inventory_adjustment_lines_are_grouped_and_authorized_atomically(): void
    {
        $this->get(route('inventory.adjust.form'))
            ->assertOk()
            ->assertSee('data-add-row="adjustment-details"', false)
            ->assertSee('id="adjustment-detail-template"', false)
            ->assertSee('Buscar medicamento')
            ->assertSee('data-adjustment-lot', false)
            ->assertSee('existencia');

        $inventories = DB::table('inventario')
            ->join('lotes', 'lotes.lote_id', '=', 'inventario.lote_id')
            ->join('productos', 'productos.producto_id', '=', 'lotes.producto_id')
            ->where('productos.activo', true)
            ->where('lotes.estado', 'ACTIVO')
            ->whereDate('lotes.fecha_vencimiento', '>=', today()->toDateString())
            ->where('inventario.cantidad', '>', 0)
            ->orderBy('productos.producto_id')
            ->get([
                'productos.producto_id',
                'inventario.lote_id',
                'inventario.ubicacion_id',
                'inventario.cantidad',
            ])
            ->unique('producto_id')
            ->take(2)
            ->values();
        $this->assertCount(2, $inventories);

        $makeDetails = fn (string $firstType, float $secondQuantity) => [
            [
                'producto_id' => $inventories[0]->producto_id,
                'lote_id' => $inventories[0]->lote_id,
                'ubicacion_id' => $inventories[0]->ubicacion_id,
                'tipo_ajuste' => $firstType,
                'cantidad' => 2,
                'motivo' => 'Diferencia de inventario línea 1',
            ],
            [
                'producto_id' => $inventories[1]->producto_id,
                'lote_id' => $inventories[1]->lote_id,
                'ubicacion_id' => $inventories[1]->ubicacion_id,
                'tipo_ajuste' => 'SALIDA',
                'cantidad' => $secondQuantity,
                'motivo' => 'Diferencia de inventario línea 2',
            ],
        ];

        $invalidDetails = $makeDetails('ENTRADA', 1);
        $invalidDetails[0]['producto_id'] = $inventories[1]->producto_id;
        $this->from(route('inventory.adjust.form'))
            ->post(route('inventory.adjust'), ['detalles' => $invalidDetails])
            ->assertRedirect(route('inventory.adjust.form'))
            ->assertSessionHasErrors('detalles.0.lote_id');
        $this->assertSame(0, DB::table('solicitudes_ajuste_grupos')->count());

        $initialStock = $inventories->map(fn ($inventory) => (float) $inventory->cantidad);
        $this->post(route('inventory.adjust'), ['detalles' => $makeDetails('ENTRADA', 1)])
            ->assertRedirect(route('inventory.adjustments.index'));
        $groupId = (int) DB::table('solicitudes_ajuste_grupos')->latest('grupo_ajuste_id')->value('grupo_ajuste_id');
        $requestIds = DB::table('solicitudes_ajuste_inventario')
            ->where('grupo_ajuste_id', $groupId)
            ->orderBy('solicitud_ajuste_id')
            ->pluck('solicitud_ajuste_id');
        $this->assertCount(2, $requestIds);
        $this->assertSame(2, DB::table('solicitudes_ajuste_inventario')
            ->where('grupo_ajuste_id', $groupId)
            ->where('estado', 'PENDIENTE')
            ->count());
        foreach ($inventories as $index => $inventory) {
            $this->assertSame($initialStock[$index], (float) DB::table('inventario')
                ->where('lote_id', $inventory->lote_id)
                ->where('ubicacion_id', $inventory->ubicacion_id)
                ->value('cantidad'));
        }
        $this->get(route('inventory.adjustments.index'))
            ->assertOk()
            ->assertSee('Diferencia de inventario línea 1')
            ->assertSee('Diferencia de inventario línea 2')
            ->assertSee('Ver solicitud')
            ->assertSee('Autorizar o rechazar afecta todas las líneas');
        $this->get(route('inventory.adjustments.show', $groupId))
            ->assertOk()
            ->assertSee('Solicitante:')
            ->assertSee('Fecha de solicitud:')
            ->assertSee('Medicamentos solicitados')
            ->assertSee('Diferencia de inventario línea 1')
            ->assertSee('Diferencia de inventario línea 2');

        $pharmacistRut = '104333333';
        DB::table('usuarios')->insert([
            'rut' => $pharmacistRut,
            'nombre' => 'QF',
            'apellidoPaterno' => 'Autorizador',
            'apellidoMaterno' => 'Grupo',
            'telefono' => '900000003',
            'password_hash' => Hash::make('UnaClaveSegura!2026'),
            'activo' => true,
        ]);
        DB::table('usuario_rol')->insert([
            'usuario_rut' => $pharmacistRut,
            'rol_id' => DB::table('roles')->where('nombre', 'Químico Farmacéutico')->value('rol_id'),
        ]);

        $this->actingAs(Usuario::where('rut', $pharmacistRut)->firstOrFail())
            ->get(route('inventory.adjustments.show', $groupId))
            ->assertOk()
            ->assertSee('Aprobar solicitud')
            ->assertSee('Rechazar solicitud')
            ->assertSee('Motivo del rechazo')
            ->assertSee('required', false);
        $this->actingAs(Usuario::where('rut', $pharmacistRut)->firstOrFail())
            ->patch(route('inventory.adjustments.resolve', $requestIds[0]), ['decision' => 'APROBAR'])
            ->assertRedirect(route('inventory.adjustments.index'));
        $this->assertDatabaseHas('solicitudes_ajuste_grupos', [
            'grupo_ajuste_id' => $groupId,
            'estado' => 'APROBADA',
        ]);
        $this->assertSame(2, DB::table('solicitudes_ajuste_inventario')
            ->where('grupo_ajuste_id', $groupId)
            ->where('estado', 'APROBADA')
            ->count());
        $this->assertSame(2, DB::table('movimientos_inventario')
            ->whereIn('referencia_id', $requestIds)
            ->where('referencia_tipo', 'SOLICITUD_AJUSTE')
            ->count());

        $this->actingAs(Usuario::where('rut', '12345674')->firstOrFail());
        $movementCount = DB::table('movimientos_inventario')->count();
        $firstInventoryBefore = (float) DB::table('inventario')
            ->where('lote_id', $inventories[0]->lote_id)
            ->where('ubicacion_id', $inventories[0]->ubicacion_id)
            ->value('cantidad');
        $this->post(route('inventory.adjust'), [
            'detalles' => $makeDetails('ENTRADA', (float) $inventories[1]->cantidad + 1),
        ])->assertRedirect(route('inventory.adjustments.index'));
        $secondGroupId = (int) DB::table('solicitudes_ajuste_grupos')->latest('grupo_ajuste_id')->value('grupo_ajuste_id');
        $secondRequestId = (int) DB::table('solicitudes_ajuste_inventario')
            ->where('grupo_ajuste_id', $secondGroupId)
            ->orderBy('solicitud_ajuste_id')
            ->value('solicitud_ajuste_id');

        $this->actingAs(Usuario::where('rut', $pharmacistRut)->firstOrFail())
            ->from(route('inventory.adjustments.index'))
            ->patch(route('inventory.adjustments.resolve', $secondRequestId), ['decision' => 'APROBAR'])
            ->assertRedirect(route('inventory.adjustments.index'))
            ->assertSessionHasErrors('cantidad');
        $this->assertSame($movementCount, DB::table('movimientos_inventario')->count());
        $this->assertSame($firstInventoryBefore, (float) DB::table('inventario')
            ->where('lote_id', $inventories[0]->lote_id)
            ->where('ubicacion_id', $inventories[0]->ubicacion_id)
            ->value('cantidad'));
        $this->assertDatabaseHas('solicitudes_ajuste_grupos', [
            'grupo_ajuste_id' => $secondGroupId,
            'estado' => 'PENDIENTE',
        ]);
        $this->from(route('inventory.adjustments.show', $secondGroupId))
            ->patch(route('inventory.adjustments.resolve', $secondRequestId), ['decision' => 'RECHAZAR'])
            ->assertRedirect(route('inventory.adjustments.show', $secondGroupId))
            ->assertSessionHasErrors('motivo_resolucion');
        $this->patch(route('inventory.adjustments.resolve', $secondRequestId), [
            'decision' => 'RECHAZAR',
            'motivo_resolucion' => 'Revisión física requerida',
        ])->assertRedirect(route('inventory.adjustments.index'));
        $this->assertDatabaseHas('solicitudes_ajuste_grupos', [
            'grupo_ajuste_id' => $secondGroupId,
            'estado' => 'RECHAZADA',
        ]);
        $this->assertSame(2, DB::table('solicitudes_ajuste_inventario')
            ->where('grupo_ajuste_id', $secondGroupId)
            ->where('estado', 'RECHAZADA')
            ->count());
        $this->assertSame($movementCount, DB::table('movimientos_inventario')->count());
    }

    public function test_inventory_adjustments_are_transactional_audited_and_cannot_create_negative_stock(): void
    {
        $inventory = DB::table('inventario')
            ->join('lotes', 'lotes.lote_id', '=', 'inventario.lote_id')
            ->where('inventario.cantidad', '>', 10)
            ->where('lotes.estado', 'ACTIVO')
            ->whereDate('lotes.fecha_vencimiento', '>=', today()->toDateString())
            ->first([
                'inventario.inventario_id',
                'inventario.lote_id',
                'inventario.ubicacion_id',
                'inventario.cantidad',
            ]);
        $this->assertNotNull($inventory);
        $movementCount = DB::table('movimientos_inventario')->count();
        $pharmacistRut = '104444444';
        DB::table('usuarios')->insert([
            'rut' => $pharmacistRut,
            'nombre' => 'QF',
            'apellidoPaterno' => 'Autorizador',
            'apellidoMaterno' => 'Prueba',
            'telefono' => '900000004',
            'password_hash' => Hash::make('UnaClaveSegura!2026'),
            'activo' => true,
        ]);
        DB::table('usuario_rol')->insert([
            'usuario_rut' => $pharmacistRut,
            'rol_id' => DB::table('roles')->where('nombre', 'Químico Farmacéutico')->value('rol_id'),
        ]);

        $this->post(route('inventory.adjust'), [
            'lote_id' => $inventory->lote_id,
            'ubicacion_id' => $inventory->ubicacion_id,
            'tipo_ajuste' => 'SALIDA',
            'cantidad' => 5,
            'motivo' => 'Diferencia de conteo físico',
        ])->assertRedirect(route('inventory.adjustments.index'));
        $firstRequestId = (int) DB::table('solicitudes_ajuste_inventario')->latest('solicitud_ajuste_id')->value('solicitud_ajuste_id');
        $this->assertDatabaseHas('inventario', [
            'inventario_id' => $inventory->inventario_id,
            'cantidad' => (float) $inventory->cantidad,
        ]);
        $this->assertDatabaseHas('solicitudes_ajuste_inventario', [
            'solicitud_ajuste_id' => $firstRequestId,
            'estado' => 'PENDIENTE',
            'usuario_solicita_rut' => auth()->id(),
        ]);
        $this->actingAs(Usuario::where('rut', $pharmacistRut)->firstOrFail())
            ->patch(route('inventory.adjustments.resolve', $firstRequestId), ['decision' => 'APROBAR'])
            ->assertRedirect(route('inventory.adjustments.index'));
        $this->assertDatabaseHas('inventario', [
            'inventario_id' => $inventory->inventario_id,
            'cantidad' => (float) $inventory->cantidad - 5,
        ]);
        $this->assertDatabaseHas('movimientos_inventario', [
            'lote_id' => $inventory->lote_id,
            'ubicacion_id' => $inventory->ubicacion_id,
            'tipo_movimiento' => 'AJUSTE_SALIDA',
            'cantidad' => 5,
            'observacion' => 'Diferencia de conteo físico',
            'referencia_tipo' => 'SOLICITUD_AJUSTE',
            'referencia_id' => $firstRequestId,
        ]);
        $this->assertDatabaseHas('solicitudes_ajuste_inventario', [
            'solicitud_ajuste_id' => $firstRequestId,
            'estado' => 'APROBADA',
            'usuario_autoriza_rut' => $pharmacistRut,
        ]);

        $this->actingAs(Usuario::where('rut', '12345674')->firstOrFail());
        $this->post(route('inventory.adjust'), [
            'lote_id' => $inventory->lote_id,
            'ubicacion_id' => $inventory->ubicacion_id,
            'tipo_ajuste' => 'ENTRADA',
            'cantidad' => 3,
            'motivo' => 'Corrección de registro',
        ])->assertRedirect(route('inventory.adjustments.index'));
        $secondRequestId = (int) DB::table('solicitudes_ajuste_inventario')->latest('solicitud_ajuste_id')->value('solicitud_ajuste_id');
        $this->actingAs(Usuario::where('rut', $pharmacistRut)->firstOrFail())
            ->patch(route('inventory.adjustments.resolve', $secondRequestId), ['decision' => 'APROBAR'])
            ->assertRedirect(route('inventory.adjustments.index'));
        $this->assertDatabaseHas('inventario', [
            'inventario_id' => $inventory->inventario_id,
            'cantidad' => (float) $inventory->cantidad - 2,
        ]);

        $this->actingAs(Usuario::where('rut', '12345674')->firstOrFail())
            ->post(route('inventory.adjust'), [
                'lote_id' => $inventory->lote_id,
                'ubicacion_id' => $inventory->ubicacion_id,
                'tipo_ajuste' => 'SALIDA',
                'cantidad' => (float) $inventory->cantidad,
                'motivo' => 'Intento de salida superior al saldo',
            ])
            ->assertRedirect(route('inventory.adjustments.index'));
        $thirdRequestId = (int) DB::table('solicitudes_ajuste_inventario')->latest('solicitud_ajuste_id')->value('solicitud_ajuste_id');
        $this->actingAs(Usuario::where('rut', $pharmacistRut)->firstOrFail())
            ->from(route('inventory.adjustments.index'))
            ->patch(route('inventory.adjustments.resolve', $thirdRequestId), ['decision' => 'APROBAR'])
            ->assertSessionHasErrors('cantidad');

        $this->assertSame($movementCount + 2, DB::table('movimientos_inventario')->count());
        $this->assertSame(2, DB::table('auditoria')->where('accion', 'AJUSTE_INVENTARIO')->count());
        $this->assertDatabaseHas('solicitudes_ajuste_inventario', [
            'solicitud_ajuste_id' => $thirdRequestId,
            'estado' => 'PENDIENTE',
        ]);
    }

    public function test_pharmacist_cannot_authorize_their_own_inventory_adjustment_request(): void
    {
        $inventory = DB::table('inventario')
            ->where('cantidad', '>', 0)
            ->first(['inventario_id', 'lote_id', 'ubicacion_id', 'cantidad']);
        $this->assertNotNull($inventory);
        $pharmacistRoleId = DB::table('roles')->where('nombre', 'Químico Farmacéutico')->value('rol_id');
        $pharmacistRuts = ['104555555', '104666666'];
        foreach ($pharmacistRuts as $index => $rut) {
            DB::table('usuarios')->insert([
                'rut' => $rut,
                'nombre' => 'QF '.$index,
                'apellidoPaterno' => 'Solicitud',
                'apellidoMaterno' => 'Prueba',
                'telefono' => '90000000'.($index + 5),
                'password_hash' => Hash::make('UnaClaveSegura!2026'),
                'activo' => true,
            ]);
            DB::table('usuario_rol')->insert(['usuario_rut' => $rut, 'rol_id' => $pharmacistRoleId]);
        }

        $this->actingAs(Usuario::where('rut', $pharmacistRuts[0])->firstOrFail())
            ->post(route('inventory.adjust'), [
                'lote_id' => $inventory->lote_id,
                'ubicacion_id' => $inventory->ubicacion_id,
                'tipo_ajuste' => 'ENTRADA',
                'cantidad' => 1,
                'motivo' => 'Corrección de conteo',
            ])
            ->assertRedirect(route('inventory.adjustments.index'));
        $requestId = (int) DB::table('solicitudes_ajuste_inventario')->latest('solicitud_ajuste_id')->value('solicitud_ajuste_id');
        $this->assertDatabaseHas('solicitudes_ajuste_inventario', [
            'solicitud_ajuste_id' => $requestId,
            'solicitante_es_qf' => true,
        ]);

        $this->from(route('inventory.adjustments.index'))
            ->patch(route('inventory.adjustments.resolve', $requestId), ['decision' => 'APROBAR'])
            ->assertSessionHasErrors('decision');
        $this->assertDatabaseHas('solicitudes_ajuste_inventario', [
            'solicitud_ajuste_id' => $requestId,
            'estado' => 'PENDIENTE',
        ]);

        $this->actingAs(Usuario::where('rut', $pharmacistRuts[1])->firstOrFail())
            ->patch(route('inventory.adjustments.resolve', $requestId), ['decision' => 'APROBAR'])
            ->assertRedirect(route('inventory.adjustments.index'));
        $this->assertDatabaseHas('solicitudes_ajuste_inventario', [
            'solicitud_ajuste_id' => $requestId,
            'estado' => 'APROBADA',
            'usuario_autoriza_rut' => $pharmacistRuts[1],
        ]);
    }

    public function test_inventory_json_endpoints_require_an_authenticated_session(): void
    {
        auth()->logout();

        $this->getJson('/api/inventario')->assertUnauthorized();
        $this->getJson('/api/medicamentos/1/lotes')->assertUnauthorized();
    }

    public function test_inventory_list_api_paginates_products_and_preserves_search(): void
    {
        $categoryId = DB::table('categorias_producto')->value('categoria_id');
        $baseUnitId = DB::table('unidades_medida')->where('codigo', 'unidad')->value('unidad_medida_id');
        foreach (range(1, 30) as $index) {
            DB::table('productos')->insert([
                'numero_serie' => 'PAGE-'.$index,
                'nombre' => 'Producto paginado '.str_pad((string) $index, 2, '0', STR_PAD_LEFT),
                'categoria_id' => $categoryId,
                'unidad_base_inventario_id' => $baseUnitId,
                'es_controlado' => false,
                'activo' => true,
            ]);
        }

        $this->getJson(route('inventory.api.index', ['page' => 2]))
            ->assertOk()
            ->assertJsonPath('per_page', 25)
            ->assertJsonPath('current_page', 2)
            ->assertJsonPath('last_page', 2);

        $this->getJson(route('inventory.api.index', ['buscar' => 'Producto paginado 01']))
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.codigo', 'PAGE-1');

        $this->getJson(route('inventory.api.index', [
            'buscar' => 'Producto',
            'buscar_global' => 'paginado 01',
        ]))
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.codigo', 'PAGE-1');
    }

    public function test_inventory_list_shows_the_soonest_active_lot_expiring_within_30_days(): void
    {
        $categoryId = DB::table('categorias_producto')->value('categoria_id');
        $baseUnitId = DB::table('unidades_medida')->where('codigo', 'unidad')->value('unidad_medida_id');
        $locationId = DB::table('ubicaciones')->value('ubicacion_id');
        $productId = DB::table('productos')->insertGetId([
            'numero_serie' => 'EXPIRY-INFO',
            'nombre' => 'Producto vencimiento cercano',
            'categoria_id' => $categoryId,
            'unidad_base_inventario_id' => $baseUnitId,
            'es_controlado' => false,
            'activo' => true,
        ]);
        $soonestExpiry = today()->addDays(2)->toDateString();
        $laterExpiry = today()->addDays(8)->toDateString();

        foreach ([
            ['numero_lote' => 'EXPIRY-LATER', 'fecha_vencimiento' => $laterExpiry, 'estado' => 'ACTIVO'],
            ['numero_lote' => 'EXPIRY-SOONEST', 'fecha_vencimiento' => $soonestExpiry, 'estado' => 'ACTIVO'],
            ['numero_lote' => 'EXPIRY-INACTIVE', 'fecha_vencimiento' => today()->addDay()->toDateString(), 'estado' => 'INACTIVO'],
        ] as $lot) {
            $lotId = DB::table('lotes')->insertGetId([
                'producto_id' => $productId,
                ...$lot,
            ]);
            DB::table('inventario')->insert([
                'lote_id' => $lotId,
                'ubicacion_id' => $locationId,
                'cantidad' => 5,
            ]);
        }

        $this->getJson(route('inventory.api.index', ['buscar' => 'EXPIRY-INFO']))
            ->assertOk()
            ->assertJsonPath('data.0.proximo_lote', 'EXPIRY-SOONEST')
            ->assertJsonPath('data.0.proximo_vencimiento', $soonestExpiry);

        $this->get(route('inventory.index', ['buscar' => 'EXPIRY-INFO']))
            ->assertOk()
            ->assertSee('PRÓXIMO A VENCER (30 DÍAS)')
            ->assertSee('EXPIRY-SOONEST')
            ->assertSee(today()->addDays(2)->format('d/m/Y'));
    }

    public function test_expired_lot_remains_visible_but_is_not_counted_as_available_stock(): void
    {
        $productId = DB::table('productos')->where('numero_serie', 'MED-001')->value('producto_id');
        $warehouseId = DB::table('ubicaciones')->where('tipo', 'BODEGA')->value('ubicacion_id');
        $expiredLotId = DB::table('lotes')->insertGetId([
            'producto_id' => $productId,
            'numero_lote' => 'EXPIRED-LOT',
            'fecha_vencimiento' => now()->subDay()->toDateString(),
        ]);
        DB::table('inventario')->insert([
            'lote_id' => $expiredLotId,
            'ubicacion_id' => $warehouseId,
            'cantidad' => 12,
        ]);

        $this->getJson(route('inventory.api.index'))
            ->assertOk()
            ->assertJsonFragment([
                'codigo' => 'MED-001',
                'stock_total' => 1380.0,
                'stock_fisico' => 1392.0,
                'stock_bodega' => 1200.0,
                'estado' => 'DISPONIBLE',
            ]);

        $this->getJson(route('products.api.lots', $productId))
            ->assertOk()
            ->assertJsonFragment([
                'lote_id' => $expiredLotId,
                'numero_lote' => 'EXPIRED-LOT',
                'cantidad' => 12.0,
                'estado' => 'VENCIDO',
            ]);
    }

    public function test_critical_stock_aggregates_eligible_lots_per_location(): void
    {
        $productId = DB::table('productos')->where('numero_serie', 'MED-002')->value('producto_id');
        $displayLocationId = DB::table('ubicaciones')->where('tipo', 'VITRINA')->value('ubicacion_id');
        $warehouseLocationId = DB::table('ubicaciones')->where('tipo', 'BODEGA')->value('ubicacion_id');
        $this->setProductLocationStock($productId, $displayLocationId, 60, [
            ['AGG-19', 19, now()->addYear()->toDateString(), 'ACTIVO'],
            ['AGG-40', 40, now()->addYear()->toDateString(), 'ACTIVO'],
            ['AGG-EXPIRED', 100, now()->subDay()->toDateString(), 'ACTIVO'],
            ['AGG-INACTIVE', 100, now()->addYear()->toDateString(), 'INACTIVO'],
        ]);
        $this->setProductLocationStock($productId, $warehouseLocationId, 49, [
            ['AGG-WAREHOUSE', 50, now()->addYear()->toDateString(), 'ACTIVO'],
        ]);

        $service = app(StockAlertService::class);
        $critical = $service->refreshConfiguredStockAlerts();
        $displayStock = $service->stockByLocationForProduct($productId)->firstWhere('ubicacion_id', $displayLocationId);

        $this->assertSame(59.0, (float) $displayStock->stock_disponible);
        $this->assertSame(60.0, (float) $displayStock->stock_minimo);
        $this->assertTrue($critical->contains(
            fn ($snapshot) => (int) $snapshot->producto_id === $productId
                && (int) $snapshot->ubicacion_id === $displayLocationId
        ));
        $this->assertDatabaseHas('alertas', [
            'tipo_alerta' => 'STOCK_CRITICO',
            'producto_id' => $productId,
            'ubicacion_id' => $displayLocationId,
            'lote_id' => null,
            'nivel_actual' => 59,
            'resuelta_at' => null,
        ]);
        $this->assertDatabaseMissing('alertas', [
            'tipo_alerta' => 'STOCK_CRITICO',
            'producto_id' => $productId,
            'ubicacion_id' => $warehouseLocationId,
            'resuelta_at' => null,
        ]);
        $this->get(route('alerts.map'))
            ->assertOk()
            ->assertSee('Amoxicilina 500 mg')
            ->assertSee('Vitrina Farmacia')
            ->assertDontSee('AGG-19');
    }

    public function test_critical_stock_uses_an_inclusive_configured_minimum_and_zero_stock(): void
    {
        $productId = DB::table('productos')->where('numero_serie', 'MED-002')->value('producto_id');
        $locationId = DB::table('ubicaciones')->where('tipo', 'VITRINA')->value('ubicacion_id');
        $service = app(StockAlertService::class);

        $this->setProductLocationStock($productId, $locationId, 58, [
            ['ABOVE-MINIMUM', 59, now()->addYear()->toDateString(), 'ACTIVO'],
        ]);
        $service->refreshConfiguredStockAlerts();
        $this->assertDatabaseMissing('alertas', [
            'tipo_alerta' => 'STOCK_CRITICO',
            'producto_id' => $productId,
            'ubicacion_id' => $locationId,
            'resuelta_at' => null,
        ]);

        $this->setProductLocationStock($productId, $locationId, 59, [
            ['EQUAL-MINIMUM', 59, now()->addYear()->toDateString(), 'ACTIVO'],
        ]);
        $service->refreshConfiguredStockAlerts();
        $this->assertDatabaseHas('alertas', [
            'tipo_alerta' => 'STOCK_CRITICO',
            'producto_id' => $productId,
            'ubicacion_id' => $locationId,
            'nivel_actual' => 59,
            'resuelta_at' => null,
        ]);

        $this->setProductLocationStock($productId, $locationId, 59, [
            ['BELOW-MINIMUM', 58, now()->addYear()->toDateString(), 'ACTIVO'],
        ]);
        $service->refreshConfiguredStockAlerts();
        $this->assertDatabaseHas('alertas', [
            'tipo_alerta' => 'STOCK_CRITICO',
            'producto_id' => $productId,
            'ubicacion_id' => $locationId,
            'nivel_actual' => 58,
            'resuelta_at' => null,
        ]);

        $this->setProductLocationStock($productId, $locationId, 1, []);
        $snapshots = $service->refreshConfiguredStockAlerts();
        $zeroStock = $service->stockByLocationForProduct($productId)->firstWhere('ubicacion_id', $locationId);

        $this->assertSame(0.0, (float) $zeroStock->stock_disponible);
        $this->assertTrue($snapshots->contains(
            fn ($snapshot) => (int) $snapshot->producto_id === $productId
                && (int) $snapshot->ubicacion_id === $locationId
                && (float) $snapshot->stock_disponible === 0.0
        ));
        $this->assertDatabaseHas('alertas', [
            'tipo_alerta' => 'SIN_EXISTENCIAS',
            'producto_id' => $productId,
            'ubicacion_id' => $locationId,
            'nivel_actual' => 0,
            'resuelta_at' => null,
        ]);
    }

    public function test_expiry_alert_remains_per_lot_even_when_aggregated_stock_is_sufficient(): void
    {
        $productId = DB::table('productos')->where('numero_serie', 'MED-002')->value('producto_id');
        $locationId = DB::table('ubicaciones')->where('tipo', 'VITRINA')->value('ubicacion_id');
        $lotIds = $this->setProductLocationStock($productId, $locationId, 5, [
            ['EXPIRY-NEAR', 2, now()->addDays(15)->toDateString(), 'ACTIVO'],
            ['EXPIRY-OTHER', 8, now()->addYear()->toDateString(), 'ACTIVO'],
        ]);

        app(StockAlertService::class)->check($lotIds[0], $locationId);

        $this->assertDatabaseHas('alertas', [
            'tipo_alerta' => 'VENCIMIENTO',
            'producto_id' => $productId,
            'lote_id' => $lotIds[0],
            'ubicacion_id' => $locationId,
            'resuelta_at' => null,
        ]);
        $this->assertDatabaseMissing('alertas', [
            'tipo_alerta' => 'STOCK_CRITICO',
            'producto_id' => $productId,
            'ubicacion_id' => $locationId,
            'resuelta_at' => null,
        ]);
    }

    public function test_expired_lot_is_removed_only_after_the_assigned_user_requests_and_qf_approves_the_withdrawal(): void
    {
        $technicianRoleId = DB::table('roles')->where('nombre', 'Técnico Farmacéutico')->value('rol_id');
        DB::table('usuario_rol')->where('usuario_rut', Auth::id())->update(['rol_id' => $technicianRoleId]);

        $productId = DB::table('productos')->where('numero_serie', 'MED-002')->value('producto_id');
        $locationId = DB::table('ubicaciones')->where('tipo', 'VITRINA')->value('ubicacion_id');
        $lotNumber = 'EXPIRED-WITHDRAWAL';
        $lotId = DB::table('lotes')->insertGetId([
            'producto_id' => $productId,
            'numero_lote' => $lotNumber,
            'fecha_vencimiento' => now()->subDay()->toDateString(),
        ]);
        DB::table('inventario')->insert([
            'lote_id' => $lotId,
            'ubicacion_id' => $locationId,
            'cantidad' => 6,
        ]);
        $alertId = DB::table('alertas')->insertGetId([
            'tipo_alerta' => 'VENCIDO',
            'prioridad' => 'CRITICA',
            'producto_id' => $productId,
            'lote_id' => $lotId,
            'ubicacion_id' => $locationId,
            'responsable_rut' => Auth::id(),
            'estado' => 'ACTIVA',
            'nivel_actual' => 6,
            'mensaje' => 'Lote vencido para prueba de retiro.',
            'atendida' => false,
        ]);

        app(StockAlertService::class)->refreshExpiringAlerts();

        $this->assertSame(6.0, (float) DB::table('inventario')
            ->where('lote_id', $lotId)
            ->where('ubicacion_id', $locationId)
            ->value('cantidad'));
        $this->assertNull(DB::table('alertas')->where('alerta_id', $alertId)->value('resuelta_at'));
        $this->post(route('alerts.replenish', $alertId))->assertUnprocessable();
        $this->patch(route('alerts.assign', $alertId), ['responsable_rut' => Auth::id()])->assertForbidden();
        $this->patch(route('alerts.acknowledge', $alertId))->assertForbidden();
        $this->post(route('alerts.follow', $alertId), ['observacion' => 'Seguimiento no permitido'])->assertForbidden();

        $reason = "Retiro de medicamento vencido: lote {$lotNumber} (alerta #{$alertId}).";
        $adjustmentUrl = route('inventory.adjust.form', [
            'lote_id' => $lotId,
            'ubicacion_id' => $locationId,
            'tipo_ajuste' => 'SALIDA',
            'cantidad' => 6,
            'motivo' => $reason,
        ]);

        $this->get(route('alerts.show', $alertId))
            ->assertOk()
            ->assertSee('Solicitar retiro por vencimiento')
            ->assertSee('El retiro se registra mediante una solicitud de salida')
            ->assertSee('Realizar solicitud')
            ->assertSee('href="'.route('inventory.adjust.form').'"', false)
            ->assertSee('Consultar lote en Inventario')
            ->assertSee('href="'.route('products.stock', $productId).'"', false)
            ->assertSee('Volver al mapa operativo')
            ->assertDontSee('Asignar responsable')
            ->assertDontSee('Asignar / reasignar')
            ->assertDontSee('Reconocer')
            ->assertDontSee('Registrar seguimiento');
        $this->get(route('alerts.expiry-withdrawal', $alertId))->assertRedirect($adjustmentUrl);
        $this->get($adjustmentUrl)
            ->assertOk()
            ->assertSee('value="SALIDA" selected', false)
            ->assertSee('value="6"', false)
            ->assertSee($reason);
        $this->get(route('inventory.adjust.form'))->assertOk();

        $this->post(route('inventory.adjust'), [
            'lote_id' => $lotId,
            'ubicacion_id' => $locationId,
            'tipo_ajuste' => 'SALIDA',
            'cantidad' => 6,
            'motivo' => $reason,
        ])->assertRedirect(route('inventory.adjustments.index'));

        $requestId = (int) DB::table('solicitudes_ajuste_inventario')
            ->orderByDesc('solicitud_ajuste_id')
            ->value('solicitud_ajuste_id');
        $this->assertDatabaseHas('solicitudes_ajuste_inventario', [
            'solicitud_ajuste_id' => $requestId,
            'estado' => 'PENDIENTE',
            'tipo_ajuste' => 'SALIDA',
        ]);
        $this->assertSame(6.0, (float) DB::table('inventario')->where('lote_id', $lotId)->value('cantidad'));

        $qfRut = '104777777';
        DB::table('usuarios')->insert([
            'rut' => $qfRut,
            'nombre' => 'QF',
            'apellidoPaterno' => 'Retiro',
            'apellidoMaterno' => 'Prueba',
            'password_hash' => Hash::make('UnaClaveSegura!2026'),
            'activo' => true,
        ]);
        DB::table('usuario_rol')->insert([
            'usuario_rut' => $qfRut,
            'rol_id' => DB::table('roles')->where('nombre', 'Químico Farmacéutico')->value('rol_id'),
        ]);

        $this->actingAs(Usuario::where('rut', $qfRut)->firstOrFail())
            ->patch(route('inventory.adjustments.resolve', $requestId), ['decision' => 'APROBAR'])
            ->assertRedirect(route('inventory.adjustments.index'));

        $this->assertSame(0.0, (float) DB::table('inventario')->where('lote_id', $lotId)->value('cantidad'));
        $this->assertNotNull(DB::table('alertas')->where('alerta_id', $alertId)->value('resuelta_at'));
    }

    public function test_critical_alerts_are_deduplicated_resolved_and_reopened_with_history(): void
    {
        $productId = DB::table('productos')->where('numero_serie', 'MED-002')->value('producto_id');
        $locationId = DB::table('ubicaciones')->where('tipo', 'VITRINA')->value('ubicacion_id');
        $service = app(StockAlertService::class);
        $lotIds = $this->setProductLocationStock($productId, $locationId, 10, [
            ['ALERT-LOW', 5, now()->addYear()->toDateString(), 'ACTIVO'],
        ]);
        $legacyAlertId = DB::table('alertas')->insertGetId([
            'tipo_alerta' => 'STOCK_CRITICO',
            'lote_id' => $lotIds[0],
            'ubicacion_id' => $locationId,
            'nivel_actual' => 5,
            'mensaje' => 'Alerta anterior asociada a un lote',
            'atendida' => false,
        ]);

        $service->refreshConfiguredStockAlerts();
        $service->refreshConfiguredStockAlerts();
        $this->assertDatabaseHas('alertas', [
            'alerta_id' => $legacyAlertId,
            'producto_id' => $productId,
            'lote_id' => null,
            'ubicacion_id' => $locationId,
            'resuelta_at' => null,
        ]);
        $this->assertSame(1, DB::table('alertas')
            ->where('tipo_alerta', 'STOCK_CRITICO')
            ->where('producto_id', $productId)
            ->where('ubicacion_id', $locationId)
            ->whereNull('resuelta_at')
            ->count());
        $alertId = DB::table('alertas')
            ->where('tipo_alerta', 'STOCK_CRITICO')
            ->where('producto_id', $productId)
            ->where('ubicacion_id', $locationId)
            ->whereNull('resuelta_at')
            ->value('alerta_id');
        $attendedAt = now()->subMinute();
        DB::table('alertas')->where('alerta_id', $alertId)->update([
            'atendida' => true,
            'atendida_por_rut' => auth()->id(),
            'atendida_at' => $attendedAt,
        ]);

        $this->setProductLocationStock($productId, $locationId, 10, [
            ['ALERT-RECOVERED', 15, now()->addYear()->toDateString(), 'ACTIVO'],
        ]);
        $service->refreshConfiguredStockAlerts();
        $this->assertSame(0, DB::table('alertas')
            ->where('tipo_alerta', 'STOCK_CRITICO')
            ->where('producto_id', $productId)
            ->where('ubicacion_id', $locationId)
            ->whereNull('resuelta_at')
            ->count());
        $this->assertDatabaseHas('alertas', [
            'alerta_id' => $alertId,
            'tipo_alerta' => 'STOCK_CRITICO',
            'producto_id' => $productId,
            'ubicacion_id' => $locationId,
            'atendida' => true,
            'atendida_por_rut' => auth()->id(),
        ]);
        $this->assertSame(
            $attendedAt->toDateTimeString(),
            DB::table('alertas')->where('alerta_id', $alertId)->value('atendida_at')
        );
        $this->assertNotNull(DB::table('alertas')->where('alerta_id', $alertId)->value('resuelta_at'));

        $this->setProductLocationStock($productId, $locationId, 10, [
            ['ALERT-RETURNED', 4, now()->addYear()->toDateString(), 'ACTIVO'],
        ]);
        $service->refreshConfiguredStockAlerts();
        $this->assertSame(1, DB::table('alertas')
            ->where('tipo_alerta', 'STOCK_CRITICO')
            ->where('producto_id', $productId)
            ->where('ubicacion_id', $locationId)
            ->whereNull('resuelta_at')
            ->count());
        $this->assertDatabaseHas('alertas', [
            'tipo_alerta' => 'STOCK_CRITICO',
            'producto_id' => $productId,
            'ubicacion_id' => $locationId,
            'atendida' => false,
            'resuelta_at' => null,
        ]);
        $this->assertSame(2, DB::table('alertas')
            ->where('tipo_alerta', 'STOCK_CRITICO')
            ->where('producto_id', $productId)
            ->where('ubicacion_id', $locationId)
            ->count());
    }

    public function test_stock_alert_notifies_user_and_redirects_to_manual_replenishment_flow(): void
    {
        $categoryId = DB::table('categorias_producto')->where('nombre', 'Medicamentos')->value('categoria_id');
        $baseUnitId = DB::table('unidades_medida')->where('codigo', 'unidad')->value('unidad_medida_id');
        $productId = DB::table('productos')->insertGetId([
            'numero_serie' => 'MED-ALERTA-REPO',
            'nombre' => 'Medicamento para alerta de reposición',
            'descripcion' => 'Producto de prueba del flujo de alertas.',
            'categoria_id' => $categoryId,
            'unidad_base_inventario_id' => $baseUnitId,
            'es_controlado' => false,
            'activo' => true,
        ]);
        $locationId = DB::table('ubicaciones')->where('tipo', 'VITRINA')->value('ubicacion_id');
        DB::table('stock_minimos_ubicacion')->insert([
            'producto_id' => $productId,
            'ubicacion_id' => $locationId,
            'stock_minimo' => 30,
        ]);
        $alertId = DB::table('alertas')->insertGetId([
            'tipo_alerta' => 'STOCK_CRITICO',
            'producto_id' => $productId,
            'ubicacion_id' => $locationId,
            'nivel_actual' => 0,
            'mensaje' => 'Stock crítico para prueba de reposición',
            'atendida' => false,
        ]);

        $requestCountBefore = DB::table('solicitudes_reposicion')->count();

        $this->from(route('alerts.map'))
            ->post(route('alerts.replenish', $alertId))
            ->assertRedirect(route('supplies.requests.index', [
                'buscar_solicitud' => 'Medicamento para alerta de reposición',
                'estado_solicitud_lista' => 'PENDIENTE',
            ]));

        $this->assertSame($requestCountBefore, DB::table('solicitudes_reposicion')->count());
        $this->assertDatabaseMissing('solicitud_reposicion_detalle', [
            'producto_id' => $productId,
        ]);
        $this->assertDatabaseHas('alertas', [
            'alerta_id' => $alertId,
            'atendida' => false,
            'atendida_por_rut' => null,
        ]);
    }

    public function test_replenishment_request_is_saved_with_its_product_lines(): void
    {
        $productId = DB::table('productos')->where('numero_serie', 'MED-002')->value('producto_id');

        $this->get(route('supplies.requests.create'))
            ->assertOk()
            ->assertSee('data-product-search-input', false)
            ->assertSee('name="detalles[0][producto_id]"', false)
            ->assertSee('data-name="producto_id"', false)
            ->assertSee('Buscar producto por nombre')
            ->assertSee('unidad base indicada para el producto')
            ->assertSee('data-product-search-url="'.route('supplies.requests.products.search').'"', false);

        $this->post(route('supplies.requests.store'), [
            'detalles' => [[
                'producto_id' => $productId,
                'cantidad_solicitada' => 100,
            ]],
        ])->assertRedirect(route('supplies.requests.index'));

        $requestId = DB::table('solicitudes_reposicion')->value('solicitud_id');
        $this->assertDatabaseHas('solicitudes_reposicion', ['solicitud_id' => $requestId, 'estado' => 'PENDIENTE']);
        $this->assertDatabaseHas('solicitud_reposicion_detalle', [
            'solicitud_id' => $requestId,
            'producto_id' => $productId,
            'cantidad_solicitada' => 100,
        ]);
    }

    public function test_replenishment_product_search_shows_stock_by_location(): void
    {
        $this->getJson(route('supplies.requests.products.search', ['q' => 'Paracetamol']))
            ->assertOk()
            ->assertJsonPath('0.nombre', 'Paracetamol 500 mg')
            ->assertJsonPath('0.ubicaciones.0.nombre', 'Bodega Central')
            ->assertJsonPath('0.ubicaciones.0.cantidad', 1200)
            ->assertJsonPath('0.ubicaciones.1.nombre', 'Vitrina Farmacia')
            ->assertJsonPath('0.ubicaciones.1.cantidad', 180);
    }

    public function test_replenishment_request_explains_duplicate_products(): void
    {
        $productId = DB::table('productos')->where('numero_serie', 'MED-002')->value('producto_id');
        $requestCount = DB::table('solicitudes_reposicion')->count();

        $this->from(route('supplies.requests.create'))
            ->post(route('supplies.requests.store'), [
                'detalles' => [
                    ['producto_id' => $productId, 'cantidad_solicitada' => 100],
                    ['producto_id' => $productId, 'cantidad_solicitada' => 50],
                ],
            ])
            ->assertRedirect(route('supplies.requests.create'))
            ->assertSessionHasErrors([
                'detalles' => 'Cada producto debe aparecer una sola vez por solicitud. Elimina la línea repetida y suma su cantidad en una sola línea.',
            ]);

        $this->assertSame($requestCount, DB::table('solicitudes_reposicion')->count());
    }

    public function test_inventory_alert_can_be_acknowledged(): void
    {
        $productId = DB::table('productos')->where('numero_serie', 'MED-002')->value('producto_id');
        $locationId = DB::table('ubicaciones')->where('tipo', 'VITRINA')->value('ubicacion_id');
        $lotId = DB::table('lotes')->where('producto_id', $productId)->value('lote_id');
        $alertId = DB::table('alertas')->insertGetId([
            'tipo_alerta' => 'STOCK_CRITICO',
            'lote_id' => $lotId,
            'ubicacion_id' => $locationId,
            'mensaje' => 'Prueba de atención',
            'atendida' => false,
        ]);

        $this->patch(route('alerts.acknowledge', $alertId))->assertRedirect(route('alerts.show', $alertId));

        $this->assertDatabaseHas('alertas', [
            'alerta_id' => $alertId,
            'reconocida_por_rut' => auth()->id(),
        ]);
        $auditCount = DB::table('auditoria')->where('accion', 'RECONOCIMIENTO_ALERTA')->where('registro_id', $alertId)->count();
        $this->patch(route('alerts.acknowledge', $alertId))->assertRedirect(route('alerts.show', $alertId));
        $this->assertSame(
            $auditCount,
            DB::table('auditoria')->where('accion', 'RECONOCIMIENTO_ALERTA')->where('registro_id', $alertId)->count()
        );
    }

    // Verifica que el mapa solo muestre alertas activas y que el historial incluya sus acciones.
    public function test_alert_map_separates_active_alerts_from_action_history(): void
    {
        $product = DB::table('productos')->where('numero_serie', 'MED-002')->firstOrFail();
        $locationId = DB::table('ubicaciones')->where('tipo', 'VITRINA')->value('ubicacion_id');
        $expiryProductId = DB::table('productos')->insertGetId([
            'numero_serie' => 'ALERT-EXPIRY-TEST',
            'nombre' => 'Producto vencimiento',
            'categoria_id' => DB::table('categorias_producto')->value('categoria_id'),
            'unidad_base_inventario_id' => DB::table('unidades_medida')->where('codigo', 'unidad')->value('unidad_medida_id'),
            'es_controlado' => false,
            'activo' => true,
        ]);
        $expiryLotId = DB::table('lotes')->insertGetId([
            'producto_id' => $expiryProductId,
            'numero_lote' => 'ALERT-EXPIRY-LOT',
            'fecha_vencimiento' => today()->addDays(2)->toDateString(),
        ]);
        DB::table('inventario')->insert([
            'lote_id' => $expiryLotId,
            'ubicacion_id' => $locationId,
            'cantidad' => 5,
        ]);
        $activeAlertId = DB::table('alertas')->insertGetId([
            'tipo_alerta' => 'DESCUADRE_CONTROLADO',
            'producto_id' => $product->producto_id,
            'ubicacion_id' => $locationId,
            'mensaje' => 'Alerta activa de prueba',
            'fecha_generacion' => now(),
            'atendida' => false,
        ]);
        DB::table('alertas')->insert([
            'tipo_alerta' => 'DESCUADRE_CONTROLADO',
            'mensaje' => 'Alerta resuelta que no debe estar en el mapa',
            'fecha_generacion' => now(),
            'atendida' => true,
            'resuelta_at' => now(),
            'estado' => 'RESUELTA',
        ]);
        DB::table('historial_alertas')->insert([
            'alerta_id' => $activeAlertId,
            'usuario_rut' => Auth::id(),
            'tipo_evento' => 'SEGUIMIENTO',
            'estado_anterior' => 'ACTIVA',
            'estado_nuevo' => 'EN_SEGUIMIENTO',
            'descripcion' => 'Seguimiento de prueba con responsable y hora.',
            'created_at' => now(),
        ]);

        $this->get(route('alerts.map'))
            ->assertOk()
            ->assertSee('Mapa operativo')
            ->assertSee('Producto vencimiento')
            ->assertSee('VENCIMIENTO · Lote ALERT-EXPIRY-LOT')
            ->assertSee('Ingresar')
            ->assertSee('Alerta activa de prueba')
            ->assertSee('<h3>'.$product->nombre.'</h3>', false)
            ->assertSee(DB::table('ubicaciones')->where('ubicacion_id', $locationId)->value('nombre'))
            ->assertSee('href="'.route('alerts.show', $activeAlertId).'"', false)
            ->assertDontSee('Producto sin identificación')
            ->assertDontSee('Sin ubicación')
            ->assertDontSee('Alerta resuelta que no debe estar en el mapa')
            ->assertDontSee('alert-section-grid', false)
            ->assertDontSee('Seguimiento de prueba con responsable y hora.')
            ->assertSee('href="'.route('alerts.history').'"', false);

        $this->get(route('alerts.show', $activeAlertId))
            ->assertOk()
            ->assertSee($product->nombre)
            ->assertSee('Asignar / reasignar')
            ->assertSee('Reconocer')
            ->assertSee('Registrar seguimiento')
            ->assertSee('Volver al mapa operativo');

        $this->get(route('alerts.history'))
            ->assertOk()
            ->assertSee('Seguimiento de prueba con responsable y hora.')
            ->assertSee('FECHA Y HORA')
            ->assertDontSee('alert-section-grid', false)
            ->assertDontSee('Alerta activa de prueba');
    }

    public function test_technicians_only_see_their_assigned_alerts(): void
    {
        $technicianRut = auth()->id();
        $technicianRoleId = DB::table('roles')->where('nombre', 'Técnico Farmacéutico')->value('rol_id');
        $otherUserRut = DB::table('usuarios')->where('rut', '!=', $technicianRut)->value('rut');
        DB::table('usuario_rol')->where('usuario_rut', $technicianRut)->update(['rol_id' => $technicianRoleId]);

        $assignedAlertId = DB::table('alertas')->insertGetId([
            'tipo_alerta' => 'DESCUADRE_CONTROLADO',
            'responsable_rut' => $technicianRut,
            'mensaje' => 'Alerta asignada solo al técnico',
            'fecha_generacion' => now(),
            'atendida' => false,
        ]);
        $otherAlertId = DB::table('alertas')->insertGetId([
            'tipo_alerta' => 'DESCUADRE_CONTROLADO',
            'responsable_rut' => $otherUserRut,
            'mensaje' => 'Alerta asignada a otra persona',
            'fecha_generacion' => now(),
            'atendida' => false,
        ]);

        $this->get(route('alerts.index'))
            ->assertRedirect(route('alerts.map'));
        $this->get(route('alerts.map'))
            ->assertOk()
            ->assertSee('Mis alertas asignadas')
            ->assertSee('Alerta asignada solo al técnico')
            ->assertDontSee('Historial de Alertas')
            ->assertDontSee('Alerta asignada a otra persona');
        $this->get(route('alerts.history'))
            ->assertForbidden();
        $this->get(route('alerts.show', $assignedAlertId))->assertOk();
        $this->get(route('alerts.show', $otherAlertId))->assertNotFound();
    }

    // Verifica que el historial distinga al ejecutor, al responsable designado y al actual.
    public function test_alert_history_identifies_who_assigned_the_responsible_person(): void
    {
        $responsible = DB::table('usuarios')
            ->join('usuario_rol', 'usuario_rol.usuario_rut', '=', 'usuarios.rut')
            ->join('roles', 'roles.rol_id', '=', 'usuario_rol.rol_id')
            ->whereIn('roles.nombre', ['Administrador', 'Químico Farmacéutico', 'Técnico Farmacéutico'])
            ->where('usuarios.activo', true)
            ->first(['usuarios.rut', 'usuarios.nombre', 'usuarios.apellidoPaterno', 'usuarios.apellidoMaterno']);
        $this->assertNotNull($responsible);

        $alertId = DB::table('alertas')->insertGetId([
            'tipo_alerta' => 'STOCK_CRITICO',
            'mensaje' => 'Alerta para validar reasignación en el historial.',
            'fecha_generacion' => now(),
            'atendida' => false,
        ]);

        $this->patch(route('alerts.assign', $alertId), [
            'responsable_rut' => $responsible->rut,
        ])->assertRedirect(route('alerts.show', $alertId));

        $historyEvent = DB::table('historial_alertas')->where('alerta_id', $alertId)->first();
        $this->assertSame((string) Auth::id(), $historyEvent->usuario_rut);
        $this->assertNull($historyEvent->responsable_anterior_rut);
        $this->assertSame($responsible->rut, $historyEvent->responsable_nuevo_rut);
        $this->assertDatabaseHas('notificaciones', [
            'usuario_rut' => $responsible->rut,
            'tipo' => 'ALERTA_ASIGNADA',
            'titulo' => 'Alerta asignada',
            'accion_url' => route('alerts.show', $alertId, false),
        ]);

        $actor = DB::table('usuarios')->where('rut', Auth::id())->first();
        $actorName = trim(implode(' ', array_filter([$actor->nombre, $actor->apellidoPaterno, $actor->apellidoMaterno])));
        $responsibleName = trim(implode(' ', array_filter([$responsible->nombre, $responsible->apellidoPaterno, $responsible->apellidoMaterno])));

        $this->get(route('alerts.history'))
            ->assertOk()
            ->assertSee('Asignación / reasignación')
            ->assertSee('Responsable anterior')
            ->assertSee('Designado a')
            ->assertSee('RESPONSABLE ACTUAL')
            ->assertSee($actorName)
            ->assertSee($responsibleName);
    }

    public function test_notification_menu_counts_notifications_and_scopes_read_actions_to_the_recipient(): void
    {
        $otherUserRut = '98765431';
        DB::table('usuarios')->insert([
            'rut' => $otherUserRut,
            'nombre' => 'Usuario',
            'apellidoPaterno' => 'Sin',
            'apellidoMaterno' => 'Acceso',
            'password_hash' => Hash::make('PruebaSegura123!'),
            'activo' => true,
        ]);

        $notificationId = DB::table('notificaciones')->insertGetId([
            'usuario_rut' => Auth::id(),
            'tipo' => 'ALERTA_ASIGNADA',
            'titulo' => 'Alerta asignada',
            'mensaje' => 'Se te asignó el seguimiento de una alerta.',
            'accion_texto' => 'Revisar alerta',
            'accion_url' => route('alerts.index', [], false),
            'created_at' => now(),
        ]);
        $otherNotificationId = DB::table('notificaciones')->insertGetId([
            'usuario_rut' => $otherUserRut,
            'tipo' => 'ALERTA_ASIGNADA',
            'titulo' => 'Notificación privada',
            'mensaje' => 'Solo visible para su destinatario.',
            'accion_texto' => 'Revisar alerta',
            'accion_url' => route('alerts.index', [], false),
            'created_at' => now(),
        ]);

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Todas <span>1</span>', false)
            ->assertSee('No leídas <span>1</span>', false)
            ->assertSee('notification-badge" aria-hidden="true">1', false)
            ->assertSee('Alerta asignada');

        $this->get(route('notifications.open', $notificationId))
            ->assertRedirect(route('alerts.index', [], false));
        $this->assertNotNull(DB::table('notificaciones')->where('notificacion_id', $notificationId)->value('leida_at'));

        $this->get(route('notifications.open', $otherNotificationId))->assertNotFound();
        $this->assertNull(DB::table('notificaciones')->where('notificacion_id', $otherNotificationId)->value('leida_at'));

        $unreadId = DB::table('notificaciones')->insertGetId([
            'usuario_rut' => Auth::id(),
            'tipo' => 'ALERTA_RESUELTA',
            'titulo' => 'Alerta resuelta',
            'mensaje' => 'La alerta se cerró automáticamente.',
            'accion_texto' => 'Consultar alerta',
            'accion_url' => route('alerts.index', [], false),
            'created_at' => now(),
        ]);

        $this->post(route('notifications.read-all'))->assertRedirect();
        $this->assertNotNull(DB::table('notificaciones')->where('notificacion_id', $unreadId)->value('leida_at'));
        $this->assertNull(DB::table('notificaciones')->where('notificacion_id', $otherNotificationId)->value('leida_at'));
    }

    public function test_replenishment_request_notifies_the_responsible_for_the_critical_alert(): void
    {
        $productId = DB::table('productos')->where('numero_serie', 'MED-002')->value('producto_id');
        $locationId = DB::table('ubicaciones')->where('tipo', 'VITRINA')->value('ubicacion_id');
        DB::table('alertas')->insert([
            'tipo_alerta' => 'STOCK_CRITICO',
            'producto_id' => $productId,
            'ubicacion_id' => $locationId,
            'responsable_rut' => Auth::id(),
            'estado' => 'ACTIVA',
            'nivel_actual' => 0,
            'mensaje' => 'Stock crítico para notificación.',
            'atendida' => false,
        ]);

        $this->post(route('supplies.requests.store'), [
            'detalles' => [['producto_id' => $productId, 'cantidad_solicitada' => 300]],
        ])->assertRedirect(route('supplies.requests.index'));

        $requestId = (int) DB::table('solicitudes_reposicion')->orderByDesc('solicitud_id')->value('solicitud_id');
        $requestCode = 'RE-'.now()->format('Y').'-'.str_pad((string) $requestId, 3, '0', STR_PAD_LEFT);
        $notification = DB::table('notificaciones')
            ->where('usuario_rut', Auth::id())
            ->where('tipo', 'REPOSICION_PENDIENTE')
            ->first();

        $this->assertNotNull($notification);
        $this->assertStringContainsString($requestCode, $notification->mensaje);
        $this->assertStringContainsString('300 unidades', $notification->mensaje);
        $this->assertSame(
            route('supplies.requests.index', ['buscar_solicitud' => (string) $requestId], false),
            $notification->accion_url
        );
    }

    public function test_resolving_an_expiry_alert_notifies_its_assigned_responsible(): void
    {
        $productId = DB::table('productos')->where('numero_serie', 'MED-002')->value('producto_id');
        $locationId = DB::table('ubicaciones')->where('tipo', 'VITRINA')->value('ubicacion_id');
        $lotId = DB::table('lotes')->insertGetId([
            'producto_id' => $productId,
            'numero_lote' => 'NOTIFICATION-EXPIRY',
            'fecha_vencimiento' => now()->addDays(60)->toDateString(),
        ]);
        $alertId = DB::table('alertas')->insertGetId([
            'tipo_alerta' => 'VENCIMIENTO',
            'producto_id' => $productId,
            'lote_id' => $lotId,
            'ubicacion_id' => $locationId,
            'responsable_rut' => Auth::id(),
            'estado' => 'ACTIVA',
            'mensaje' => 'Alerta de vencimiento para prueba.',
            'atendida' => false,
        ]);

        $this->getJson('/api/v1/alerts/map')->assertOk();

        $this->assertNotNull(DB::table('alertas')->where('alerta_id', $alertId)->value('resuelta_at'));
        $this->assertDatabaseHas('notificaciones', [
            'usuario_rut' => Auth::id(),
            'tipo' => 'ALERTA_RESUELTA',
            'titulo' => 'Alerta resuelta',
            'accion_url' => '/alertas/'.$alertId,
        ]);
    }

    public function test_alert_list_filters_and_paginates_beyond_the_old_result_cap(): void
    {
        foreach (array_chunk(range(1, 210), 100) as $batch) {
            DB::table('alertas')->insert(array_map( fn ($index) => [
                'tipo_alerta' => 'DESCUADRE_CONTROLADO',
                'mensaje' => 'alert-page-'.str_pad((string) $index, 3, '0', STR_PAD_LEFT),
                'atendida' => false,
            ], $batch));
        }

        $this->get(route('alerts.map', [
            'tipo_alerta' => 'DESCUADRE_CONTROLADO',
            'estado' => 'pendientes',
            'page' => 9,
        ]))
            ->assertOk()
            ->assertSee('210 alertas')
            ->assertSee('alert-page-001')
            ->assertDontSee('alert-page-210')
            ->assertSee('aria-label="Paginación de registros"', false)
            ->assertSee('Página 9 de 9');
    }

    public function test_alert_map_can_filter_by_product_or_lot_priority_type_location_and_alert_date(): void
    {
        $product = DB::table('productos')->where('numero_serie', 'MED-002')->first();
        $lot = DB::table('lotes')->where('producto_id', $product->producto_id)->first();
        $locationId = DB::table('ubicaciones')->where('tipo', 'VITRINA')->value('ubicacion_id');
        $otherLocationId = DB::table('ubicaciones')->where('ubicacion_id', '<>', $locationId)->value('ubicacion_id');
        $alertDate = now()->subDay()->toDateString();
        $marker = 'alert-filter-target-'.uniqid();

        DB::table('alertas')->insert([
            'tipo_alerta' => 'DESCUADRE_CONTROLADO',
            'producto_id' => $product->producto_id,
            'lote_id' => $lot->lote_id,
            'ubicacion_id' => $locationId,
            'mensaje' => $marker,
            'fecha_generacion' => $alertDate.' 12:00:00',
            'atendida' => false,
        ]);

        $filters = [
            'prioridad' => 'CONCILIACION',
            'tipo_alerta' => 'DESCUADRE_CONTROLADO',
            'ubicacion_id' => $locationId,
            'fecha_desde' => $alertDate,
            'fecha_hasta' => $alertDate,
        ];

        $this->get(route('alerts.map', ['buscar_alerta' => $product->nombre, ...$filters]))
            ->assertOk()
            ->assertSee($marker);
        $this->get(route('alerts.map', ['buscar_alerta' => $lot->numero_lote, ...$filters]))
            ->assertOk()
            ->assertSee($marker);

        foreach ([
            ['buscar_alerta' => 'no-matching-product-or-lot'],
            ['prioridad' => 'CRITICA'],
            ['tipo_alerta' => 'VENCIMIENTO'],
            ['ubicacion_id' => $otherLocationId],
            ['fecha_desde' => now()->addDay()->toDateString(), 'fecha_hasta' => now()->addDay()->toDateString()],
        ] as $overrides) {
            $this->get(route('alerts.map', array_merge($filters, $overrides)))
                ->assertOk()
                ->assertDontSee($marker);
        }

        $this->get(route('alerts.map'))
            ->assertSee('name="buscar_alerta"', false)
            ->assertSee('name="prioridad"', false)
            ->assertSee('name="tipo_alerta"', false)
            ->assertSee('name="ubicacion_id"', false)
            ->assertSee('name="fecha_desde"', false)
            ->assertSee('name="fecha_hasta"', false);
    }

    public function test_administrator_can_export_inventory_report_as_csv(): void
    {
        $this->get(route('reports.export'))->assertDownload('reporte-inventario.csv');
    }

    public function test_report_exports_cover_deliveries_stock_controlled_movements_and_expiring_lots(): void
    {
        $pharmacistRoleId = DB::table('roles')->where('nombre', 'Químico Farmacéutico')->value('rol_id');
        DB::table('usuario_rol')->where('usuario_rut', '12345674')->update(['rol_id' => $pharmacistRoleId]);
        $this->actingAs(Usuario::query()->findOrFail('12345674'));

        foreach ([
            'entregas' => ['reporte-entregas.csv', 'Paciente'],
            'existencias' => ['reporte-existencias.csv', 'Stock mínimo'],
            'controlados' => ['reporte-medicamentos-controlados.csv', 'Autoriza'],
            'vencimientos' => ['reporte-vencimientos.csv', 'Vencimiento'],
        ] as $type => [$filename, $header]) {
            $response = $this->get(route('reports.export', ['tipo' => $type]));
            $response->assertDownload($filename);
            $this->assertStringContainsString($header, $response->streamedContent());
        }

        $this->get(route('reports.controlled'))
            ->assertOk()
            ->assertSee('Exportar a Excel (CSV)')
            ->assertDontSee('Imprimir / PDF');
    }

    public function test_delivery_report_date_range_includes_the_entire_end_date(): void
    {
        $date = now()->addDays(2)->toDateString();
        $nextDate = now()->addDays(3)->toDateString();
        $patientRut = DB::table('pacientes')->value('rut');
        $productId = DB::table('productos')->where('numero_serie', 'MED-001')->value('producto_id');
        $lotId = DB::table('lotes')->where('producto_id', $productId)->value('lote_id');

        foreach ([$date.' 23:59:59', $nextDate.' 00:00:00'] as $deliveryDate) {
            $deliveryId = DB::table('entregas')->insertGetId([
                'paciente_rut' => $patientRut,
                'usuario_rut' => auth()->id(),
                'fecha_hora' => $deliveryDate,
                'estado' => 'CONFIRMADA',
            ],
            );
            DB::table('entrega_detalle')->insert([
                'entrega_id' => $deliveryId,
                'lote_id' => $lotId,
                'producto_id' => $productId,
                'cantidad' => 1,
            ]);
        }

        $this->getJson('/api/v1/reports/deliveries?desde='.$date.'&hasta='.$date)
            ->assertOk()
            ->assertJsonPath('data.deliveries.total', 1);
    }

    public function test_delivery_report_defaults_to_all_records_and_orders_search_before_dates(): void
    {
        $patientRut = DB::table('pacientes')->value('rut');
        $productId = DB::table('productos')->where('numero_serie', 'MED-001')->value('producto_id');
        $lotId = DB::table('lotes')->where('producto_id', $productId)->value('lote_id');
        $deliveryIds = [];

        foreach ([now()->subYears(2), now()->addYears(2)] as $date) {
            $deliveryId = DB::table('entregas')->insertGetId([
                'paciente_rut' => $patientRut,
                'usuario_rut' => auth()->id(),
                'fecha_hora' => $date,
                'estado' => 'CONFIRMADA',
            ]);
            DB::table('entrega_detalle')->insert([
                'entrega_id' => $deliveryId,
                'lote_id' => $lotId,
                'producto_id' => $productId,
                'cantidad' => 1,
            ]);
            $deliveryIds[] = $deliveryId;
        }

        $this->get(route('reports.deliveries'))
            ->assertOk()
            ->assertDontSee('Filtrar período')
            ->assertDontSee('Imprimir / PDF')
            ->assertSee('Descargar CSV')
            ->assertSeeInOrder([
                'id="entregas-dispensaciones"',
                'for="buscar-entrega"',
                'for="desde"',
                'for="hasta"',
            ], false)
            ->assertSee('2 líneas confirmadas')
            ->assertViewHas('deliveries', fn ($deliveries) => $deliveries->total() === 2
                && $deliveries->getCollection()->pluck('entrega_id')->all() === $deliveryIds);
    }

    public function test_delivery_csv_export_applies_search_and_date_filters(): void
    {
        $patients = DB::table('pacientes')->orderBy('rut')->limit(3)->get(['rut']);
        $this->assertCount(3, $patients);
        foreach ([
            [$patients[0]->rut, 'CSV Incluido'],
            [$patients[1]->rut, 'CSV Excluido por producto'],
            [$patients[2]->rut, 'CSV Excluido por fecha'],
        ] as [$patientRut, $name]) {
            DB::table('pacientes')->where('rut', $patientRut)->update([
                'nombre' => $name,
                'apellidoPaterno' => 'Paciente',
                'apellidoMaterno' => 'Filtro',
            ]);
        }

        $productIds = DB::table('productos')
            ->whereIn('numero_serie', ['MED-001', 'MED-002'])
            ->pluck('producto_id', 'numero_serie');
        $lotIds = DB::table('lotes')
            ->whereIn('producto_id', $productIds->values())
            ->pluck('lote_id', 'producto_id');
        $date = now()->subDays(5)->toDateString();
        $outsideDate = now()->subDays(6)->toDateString();
        $deliveries = [
            [$patients[0]->rut, $productIds['MED-001'], $date],
            [$patients[1]->rut, $productIds['MED-002'], $date],
            [$patients[2]->rut, $productIds['MED-001'], $outsideDate],
        ];

        foreach ($deliveries as [$patientRut, $productId, $deliveryDate]) {
            $deliveryId = DB::table('entregas')->insertGetId([
                'paciente_rut' => $patientRut,
                'usuario_rut' => auth()->id(),
                'fecha_hora' => $deliveryDate.' 12:00:00',
                'estado' => 'CONFIRMADA',
            ]);
            DB::table('entrega_detalle')->insert([
                'entrega_id' => $deliveryId,
                'lote_id' => $lotIds[$productId],
                'producto_id' => $productId,
                'cantidad' => 1,
            ]);
        }

        $csv = $this->get(route('reports.export', [
            'tipo' => 'entregas',
            'desde' => $date,
            'hasta' => $date,
            'buscar_entrega' => 'Paracetamol',
        ]))
            ->assertDownload('reporte-entregas.csv')
            ->streamedContent();

        $this->assertStringContainsString('CSV Incluido', $csv);
        $this->assertStringNotContainsString('CSV Excluido por producto', $csv);
        $this->assertStringNotContainsString('CSV Excluido por fecha', $csv);
    }

    public function test_report_category_stock_can_be_searched_and_filtered_by_availability(): void
    {
        $baseUnitId = DB::table('unidades_medida')->where('codigo', 'unidad')->value('unidad_medida_id');
        $categoryWithStockId = DB::table('categorias_producto')->insertGetId([
            'nombre' => 'Categoría filtro disponible',
            'descripcion' => 'Prueba con existencias',
        ]);
        $categoryWithoutStockId = DB::table('categorias_producto')->insertGetId([
            'nombre' => 'Categoría filtro agotada',
            'descripcion' => 'Prueba sin existencias',
        ]);
        $productWithStockId = DB::table('productos')->insertGetId([
            'numero_serie' => 'FILTER-STOCK-001',
            'nombre' => 'Producto filtro disponible',
            'categoria_id' => $categoryWithStockId,
            'unidad_base_inventario_id' => $baseUnitId,
            'activo' => true,
        ]);
        DB::table('productos')->insert([
            'numero_serie' => 'FILTER-STOCK-002',
            'nombre' => 'Producto filtro agotado',
            'categoria_id' => $categoryWithoutStockId,
            'unidad_base_inventario_id' => $baseUnitId,
            'activo' => true,
        ]);
        $lotId = DB::table('lotes')->insertGetId([
            'producto_id' => $productWithStockId,
            'numero_lote' => 'FILTER-STOCK-LOT',
            'fecha_vencimiento' => now()->addYear()->toDateString(),
        ]);
        DB::table('inventario')->insert([
            'lote_id' => $lotId,
            'ubicacion_id' => DB::table('ubicaciones')->where('tipo', 'BODEGA')->value('ubicacion_id'),
            'cantidad' => 7,
        ]);

        $this->get(route('reports.stock', [
            'buscar_categoria' => 'filtro disponible',
            'disponibilidad' => 'con_existencias',
        ]))
            ->assertOk()
            ->assertSee('Categoría filtro disponible')
            ->assertSee('1')
            ->assertDontSee('Categoría filtro agotada');

        $this->get(route('reports.stock', [
            'buscar_categoria' => 'filtro agotada',
            'disponibilidad' => 'sin_existencias',
        ]))
            ->assertOk()
            ->assertSee('Categoría filtro agotada')
            ->assertSee('Sin existencias');
    }

    public function test_report_category_stock_can_filter_active_and_inactive_products(): void
    {
        $baseUnitId = DB::table('unidades_medida')->where('codigo', 'unidad')->value('unidad_medida_id');
        $categoryId = DB::table('categorias_producto')->insertGetId([
            'nombre' => 'Categoría producto inactivo',
        ]);
        $productId = DB::table('productos')->insertGetId([
            'numero_serie' => 'INACTIVE-CATEGORY-001',
            'nombre' => 'Producto categoría inactivo',
            'categoria_id' => $categoryId,
            'unidad_base_inventario_id' => $baseUnitId,
            'activo' => false,
        ]);
        $lotId = DB::table('lotes')->insertGetId([
            'producto_id' => $productId,
            'numero_lote' => 'INACTIVE-CATEGORY-LOT',
            'fecha_vencimiento' => now()->addYear()->toDateString(),
        ]);
        DB::table('inventario')->insert([
            'lote_id' => $lotId,
            'ubicacion_id' => DB::table('ubicaciones')->where('tipo', 'BODEGA')->value('ubicacion_id'),
            'cantidad' => 9,
        ]);

        $this->get(route('reports.stock', [
            'buscar_categoria' => 'producto inactivo',
            'estado_producto' => 'inactivo',
            'disponibilidad' => 'con_existencias',
        ]))
            ->assertOk()
            ->assertSee('Categoría producto inactivo')
            ->assertSee('1');

        $this->get(route('reports.stock', [
            'buscar_categoria' => 'producto inactivo',
            'estado_producto' => 'activo',
            'disponibilidad' => 'con_existencias',
        ]))
            ->assertOk()
            ->assertDontSee('Categoría producto inactivo');
    }

    public function test_report_movements_can_be_searched_and_filtered_by_type(): void
    {
        $productId = DB::table('productos')->where('numero_serie', 'MED-001')->value('producto_id');
        $lotId = DB::table('lotes')->where('producto_id', $productId)->value('lote_id');
        $locationId = DB::table('ubicaciones')->where('tipo', 'BODEGA')->value('ubicacion_id');
        foreach (['SALIDA', 'RECEPCION'] as $type) {
            DB::table('movimientos_inventario')->insert([
                'lote_id' => $lotId,
                'ubicacion_id' => $locationId,
                'tipo_movimiento' => $type,
                'cantidad' => 1,
                'usuario_rut' => auth()->id(),
                'observacion' => 'Movimiento filtro especializado',
            ]);
        }

        $this->get(route('reports.movements', [
            'buscar_movimiento' => 'filtro especializado',
            'tipo_movimiento' => 'SALIDA',
        ]))
            ->assertOk()
            ->assertDontSee('Filtrar período')
            ->assertSeeInOrder([
                'id="movimientos-inventario"',
                'for="buscar-movimiento"',
                'for="tipo-movimiento"',
                'for="desde"',
                'for="hasta"',
            ], false)
            ->assertSee('Paracetamol 500 mg')
            ->assertSee('SALIDA')
            ->assertViewHas('movements',  fn ($movements) => $movements->getCollection()
                ->every( fn ($movement) => $movement->tipo_movimiento === 'SALIDA'));
    }

    public function test_low_stock_automatically_creates_one_replenishment_request_for_the_deficit(): void
    {
        $productId = DB::table('productos')->where('numero_serie', 'MED-002')->value('producto_id');
        $lotIds = DB::table('lotes')->where('producto_id', $productId)->pluck('lote_id');
        $inventoryId = DB::table('inventario')
            ->join('lotes', 'lotes.lote_id', '=', 'inventario.lote_id')
            ->where('lotes.producto_id', $productId)
            ->where('lotes.estado', 'ACTIVO')
            ->whereDate('lotes.fecha_vencimiento', '>=', now()->toDateString())
            ->orderBy('inventario_id')
            ->value('inventario_id');

        DB::table('inventario')->whereIn('lote_id', $lotIds)->update([
            'cantidad' => 0,
        ]);
        DB::table('inventario')->where('inventario_id', $inventoryId)->update([
            'cantidad' => 3,
        ]);
        DB::table('stock_minimos_ubicacion')->where('producto_id', $productId)->update(['stock_minimo' => 0]);
        $locationId = DB::table('inventario')->where('inventario_id', $inventoryId)->value('ubicacion_id');
        DB::table('stock_minimos_ubicacion')->updateOrInsert(
            ['producto_id' => $productId, 'ubicacion_id' => $locationId],
            ['stock_minimo' => 10]
        );
        DB::table('alertas')->insert([
            'tipo_alerta' => 'STOCK_CRITICO',
            'producto_id' => $productId,
            'ubicacion_id' => $locationId,
            'responsable_rut' => Auth::id(),
            'estado' => 'ACTIVA',
            'nivel_actual' => 3,
            'mensaje' => 'Stock crítico para reposición automática.',
            'atendida' => false,
        ]);

        $alerts = app(StockAlertService::class);
        $alerts->checkForReplenishment($productId, (string) Auth::id());
        $alerts->checkForReplenishment($productId, (string) Auth::id());

        $this->assertDatabaseCount('solicitudes_reposicion', 1);
        $this->assertDatabaseHas('solicitud_reposicion_detalle', [
            'solicitud_id' => DB::table('solicitudes_reposicion')->value('solicitud_id'),
            'producto_id' => $productId,
            'cantidad_solicitada' => 7,
        ]);
        $this->assertDatabaseHas('auditoria', [
            'accion' => 'REPOSICION_AUTOMATICA',
            'resultado' => 'EXITOSO',
        ]);
        $this->assertDatabaseHas('notificaciones', [
            'usuario_rut' => Auth::id(),
            'tipo' => 'REPOSICION_PENDIENTE',
            'titulo' => 'Reposición pendiente',
        ]);
    }

    public function test_dispensing_triggers_replenishment_when_available_stock_falls_below_minimum(): void
    {
        $patientId = DB::table('pacientes')->where('rut', '123456785')->value('rut');
        $productId = DB::table('productos')->where('numero_serie', 'MED-001')->value('producto_id');
        $locationId = DB::table('ubicaciones')->where('tipo', 'VITRINA')->value('ubicacion_id');
        $recipeId = $this->createPrescription($patientId, $productId, 1);
        $lotIds = DB::table('lotes')->where('producto_id', $productId)->pluck('lote_id');
        DB::table('inventario')->whereIn('lote_id', $lotIds)->update([
            'cantidad' => 0,
        ]);
        DB::table('stock_minimos_ubicacion')->where('producto_id', $productId)->update(['stock_minimo' => 0]);
        $lotId = DB::table('lotes')->insertGetId([
            'producto_id' => $productId,
            'numero_lote' => 'AUTO-REPLENISHMENT',
            'fecha_vencimiento' => now()->addMonths(6)->toDateString(),
        ]);
        DB::table('inventario')->insert([
            'lote_id' => $lotId,
            'ubicacion_id' => $locationId,
            'cantidad' => 1,
        ]);
        $otherLocationId = DB::table('ubicaciones')
            ->where('ubicacion_id', '!=', $locationId)
            ->value('ubicacion_id');
        $this->assertNotNull($otherLocationId);
        $otherLocationLotId = DB::table('lotes')->insertGetId([
            'producto_id' => $productId,
            'numero_lote' => 'AUTO-REPLENISHMENT-OTHER-LOCATION',
            'fecha_vencimiento' => now()->addYear()->toDateString(),
            'estado' => 'ACTIVO',
        ]);
        DB::table('inventario')->insert([
            'lote_id' => $otherLocationLotId,
            'ubicacion_id' => $otherLocationId,
            'cantidad' => 20,
        ]);
        DB::table('stock_minimos_ubicacion')->updateOrInsert(
            ['producto_id' => $productId, 'ubicacion_id' => $locationId],
            ['stock_minimo' => 5]
        );

        $this->post(route('dispensations.store'), [
            'paciente_rut' => $patientId,
            'producto_id' => $productId,
            'receta_id' => $recipeId,
            'cantidad' => 1,
        ])->assertRedirectContains(route('dispensations.index'))
            ->assertRedirectContains('comprobante=');

        $this->assertDatabaseHas('inventario', [
            'lote_id' => $lotId,
            'ubicacion_id' => $locationId,
            'cantidad' => 0,
        ]);
        $this->assertDatabaseMissing('kardex_inventario', ['lote_id' => $lotId]);
        $this->assertDatabaseHas('alertas', [
            'producto_id' => $productId,
            'ubicacion_id' => $locationId,
            'tipo_alerta' => 'SIN_EXISTENCIAS',
            'estado' => 'ACTIVA',
        ]);

        $requestId = DB::table('solicitudes_reposicion')->value('solicitud_id');
        $this->assertDatabaseHas('solicitud_reposicion_detalle', [
            'solicitud_id' => $requestId,
            'producto_id' => $productId,
            'cantidad_solicitada' => 5,
        ]);

        $this->post(route('inventory.receive'), [
            'producto_id' => $productId,
            'ubicacion_id' => $locationId,
            'numero_lote' => 'AUTO-REPLENISHMENT-NEW-LOT',
            'fecha_vencimiento' => now()->addYear()->toDateString(),
            'cantidad' => 4,
            'origen' => 'Recepción de reposición',
            'documento_recepcion' => 'AUTO-REPLENISHMENT-RECEIPT',
        ])->assertRedirect(route('inventory.index'));

        $this->assertDatabaseMissing('inventario', ['lote_id' => $lotId]);
        $this->assertDatabaseHas('kardex_inventario', [
            'lote_id' => $lotId,
            'ubicacion_id' => $locationId,
            'motivo' => 'RECEPCION_OTRO_LOTE',
            'referencia_tipo' => 'RECEPCION',
            'documento_recepcion' => 'AUTO-REPLENISHMENT-RECEIPT',
        ]);
    }

    public function test_dispensing_archives_exhausted_lot_when_other_lots_at_location_keep_stock_sufficient(): void
    {
        $patientId = DB::table('pacientes')->where('rut', '123456785')->value('rut');
        $productId = (int) DB::table('productos')->where('numero_serie', 'MED-001')->value('producto_id');
        $locationId = (int) DB::table('ubicaciones')->where('tipo', 'VITRINA')->value('ubicacion_id');
        $recipeId = $this->createPrescription($patientId, $productId, 1);
        [$depletedLotId, $availableLotId] = $this->setProductLocationStock($productId, $locationId, 5, [
            ['AUTO-ARCHIVE-EMPTY', 1, now()->addMonths(3)->toDateString(), 'ACTIVO'],
            ['AUTO-ARCHIVE-AVAILABLE', 10, now()->addMonths(6)->toDateString(), 'ACTIVO'],
        ]);
        $replenishmentCount = DB::table('solicitudes_reposicion')->count();
        $notificationCount = DB::table('notificaciones')->count();

        $response = $this->post(route('dispensations.store'), [
            'paciente_rut' => $patientId,
            'producto_id' => $productId,
            'receta_id' => $recipeId,
            'cantidad' => 1,
        ]);

        $response->assertSessionHas('delivery_order_id');
        $this->assertDatabaseMissing('inventario', ['lote_id' => $depletedLotId]);
        $this->assertDatabaseHas('inventario', [
            'lote_id' => $availableLotId,
            'ubicacion_id' => $locationId,
            'cantidad' => 10,
        ]);
        $deliveryId = DB::table('entregas')->value('entrega_id');
        $this->assertDatabaseHas('kardex_inventario', [
            'lote_id' => $depletedLotId,
            'ubicacion_id' => $locationId,
            'cantidad_final' => 0,
            'motivo' => 'DISPENSACION_LOTE_AGOTADO',
            'referencia_tipo' => 'ENTREGA',
            'referencia_id' => $deliveryId,
        ]);
        $this->assertSame($replenishmentCount, DB::table('solicitudes_reposicion')->count());
        $this->assertSame($notificationCount, DB::table('notificaciones')->count());
        $this->assertSame(
            0,
            DB::table('alertas')
                ->where('producto_id', $productId)
                ->where('ubicacion_id', $locationId)
                ->whereIn('tipo_alerta', ['SIN_EXISTENCIAS', 'STOCK_CRITICO', 'STOCK_BAJO'])
                ->whereNull('resuelta_at')
                ->count()
        );
    }

    public function test_dispensing_archives_empty_lot_and_requests_replenishment_when_location_stock_is_critical(): void
    {
        $patientId = DB::table('pacientes')->where('rut', '123456785')->value('rut');
        $productId = (int) DB::table('productos')->where('numero_serie', 'MED-001')->value('producto_id');
        $locationId = (int) DB::table('ubicaciones')->where('tipo', 'VITRINA')->value('ubicacion_id');
        $recipeId = $this->createPrescription((int) $patientId, $productId, 1);
        [$depletedLotId, $remainingLotId] = $this->setProductLocationStock($productId, $locationId, 5, [
            ['AUTO-KEEP-EMPTY', 1, now()->addMonths(3)->toDateString(), 'ACTIVO'],
            ['AUTO-KEEP-INSUFFICIENT', 2, now()->addMonths(6)->toDateString(), 'ACTIVO'],
        ]);
        $replenishmentCount = DB::table('solicitudes_reposicion')->count();

        $response = $this->post(route('dispensations.store'), [
            'paciente_rut' => $patientId,
            'producto_id' => $productId,
            'receta_id' => $recipeId,
            'cantidad' => 1,
        ]);

        $response->assertSessionHas('delivery_order_id');
        $this->assertDatabaseMissing('inventario', ['lote_id' => $depletedLotId]);
        $this->assertDatabaseHas('inventario', [
            'lote_id' => $remainingLotId,
            'ubicacion_id' => $locationId,
            'cantidad' => 2,
        ]);
        $this->assertDatabaseHas('kardex_inventario', [
            'lote_id' => $depletedLotId,
            'ubicacion_id' => $locationId,
            'motivo' => 'DISPENSACION_LOTE_AGOTADO',
        ]);
        $this->assertDatabaseHas('alertas', [
            'producto_id' => $productId,
            'ubicacion_id' => $locationId,
            'tipo_alerta' => 'STOCK_CRITICO',
            'estado' => 'ACTIVA',
        ]);
        $this->assertDatabaseHas('solicitud_reposicion_detalle', ['producto_id' => $productId]);
        $this->assertGreaterThan($replenishmentCount, DB::table('solicitudes_reposicion')->count());
    }

    public function test_quimico_farmaceutico_can_register_signed_controlled_movement(): void
    {
        $categoryId = DB::table('categorias_producto')->where('nombre', 'Medicamentos')->value('categoria_id');
        $baseUnitId = DB::table('unidades_medida')->where('codigo', 'unidad')->value('unidad_medida_id');
        $productId = DB::table('productos')->insertGetId([
            'numero_serie' => 'CTRL-TEST',
            'nombre' => 'Medicamento controlado prueba',
            'categoria_id' => $categoryId,
            'unidad_base_inventario_id' => $baseUnitId,
            'es_controlado' => true,
            'activo' => true,
        ]);
        $lotId = DB::table('lotes')->insertGetId([
            'producto_id' => $productId,
            'numero_lote' => 'CTRL-LOT-01',
            'fecha_vencimiento' => now()->addYear()->toDateString(),
        ]);
        $locationId = DB::table('ubicaciones')->where('tipo', 'BODEGA')->value('ubicacion_id');
        DB::table('inventario')->insert([
            'lote_id' => $lotId,
            'ubicacion_id' => $locationId,
            'cantidad' => 5,
        ]);
        $pharmacistId = '102222222';
        DB::table('usuarios')->insert([
            'rut' => $pharmacistId,
            'nombre' => 'Químico',
            'apellidoPaterno' => 'De Prueba',
            'apellidoMaterno' => 'Farmacéutico',
            'password_hash' => bcrypt('UnaClaveSegura!2026'),
            'activo' => true,
        ]);
        $roleId = DB::table('roles')->where('nombre', 'Químico Farmacéutico')->value('rol_id');
        DB::table('usuario_rol')->insert(['usuario_rut' => $pharmacistId, 'rol_id' => $roleId]);

        $this->actingAs(Usuario::findOrFail($pharmacistId))
            ->post(route('controlled-ledger.store'), [
                'producto_id' => $productId,
                'lote_id' => $lotId,
                'ubicacion_id' => $locationId,
                'tipo_movimiento' => 'SALIDA',
                'cantidad' => 2,
                'observacion' => 'Entrega controlada de prueba',
                'password' => 'UnaClaveSegura!2026',
            ])->assertRedirect(route('controlled-ledger.index'));

        $this->assertDatabaseHas('movimientos_controlados', [
            'producto_id' => $productId,
            'tipo_movimiento' => 'SALIDA',
            'cantidad' => 2,
            'firmado' => true,
            'usuario_autoriza_rut' => $pharmacistId,
        ]);
        $this->assertDatabaseHas('inventario', ['lote_id' => $lotId, 'cantidad' => 3]);
    }

    public function test_controlled_medicine_dispensing_requires_qf_identity_and_password_and_tracks_both_users(): void
    {
        $categoryId = DB::table('categorias_producto')->where('nombre', 'Medicamentos')->value('categoria_id');
        $baseUnitId = DB::table('unidades_medida')->where('codigo', 'unidad')->value('unidad_medida_id');
        $productId = DB::table('productos')->insertGetId([
            'numero_serie' => 'CTRL-DISPENSE-TEST', 'nombre' => 'Controlado dispensación prueba',
            'categoria_id' => $categoryId, 'unidad_base_inventario_id' => $baseUnitId, 'es_controlado' => true, 'activo' => true,
        ]);
        $locationId = DB::table('ubicaciones')->where('tipo', 'VITRINA')->value('ubicacion_id');
        $lotId = DB::table('lotes')->insertGetId([
            'producto_id' => $productId, 'numero_lote' => 'CTRL-DISP-LOT',
            'fecha_vencimiento' => now()->addYear()->toDateString(),
        ]);
        DB::table('inventario')->insert(['lote_id' => $lotId, 'ubicacion_id' => $locationId, 'cantidad' => 5]);
        $patientId = DB::table('pacientes')->where('rut', '123456785')->value('rut');
        $recipeId = $this->createPrescription($patientId, $productId, 2);
        DB::table('receta_detalle')->where('receta_id', $recipeId)->update(['dosis' => '1 comprimido', 'frecuencia' => '12', 'indicaciones' => 'Uso indicado']);
        $submission = [
            'paciente_rut' => $patientId, 'receta_id' => $recipeId,
            'detalles' => [[
                'producto_id' => $productId, 'cantidad' => 2, 'dosis' => '1 comprimido',
                'frecuencia' => '12', 'indicaciones' => 'Uso indicado',
            ]],
        ];

        $this->post(route('dispensations.store'), $submission)->assertSessionHasErrors('autorizacion_qf');

        $pharmacistId = '103333334';
        DB::table('usuarios')->insert([
            'rut' => $pharmacistId, 'nombre' => 'QF', 'apellidoPaterno' => 'Dispensa',
            'apellidoMaterno' => 'Prueba', 'password_hash' => Hash::make('ClaveQF-2026'), 'activo' => true,
        ]);
        DB::table('usuario_rol')->insert([
            'usuario_rut' => $pharmacistId,
            'rol_id' => DB::table('roles')->where('nombre', 'Químico Farmacéutico')->value('rol_id'),
        ]);
        $technicianId = '104444444';
        DB::table('usuarios')->insert([
            'rut' => $technicianId, 'nombre' => 'Técnico', 'apellidoPaterno' => 'Dispensa',
            'apellidoMaterno' => 'Prueba', 'password_hash' => Hash::make('ClaveTecnico-2026'), 'activo' => true,
        ]);
        DB::table('usuario_rol')->insert([
            'usuario_rut' => $technicianId,
            'rol_id' => DB::table('roles')->where('nombre', 'Técnico Farmacéutico')->value('rol_id'),
        ]);
        $this->actingAs(Usuario::findOrFail($technicianId))
            ->post(route('dispensations.store'), $submission + [
                'autorizador_rut' => $technicianId,
                'autorizador_password' => 'ClaveTecnico-2026',
            ])
            ->assertSessionHasErrors('autorizacion_qf');
        $this->post(route('dispensations.store'), $submission + [
            'autorizador_rut' => $pharmacistId,
            'autorizador_password' => 'incorrecta',
        ])
            ->assertSessionHasErrors('autorizacion_qf');
        $this->assertArrayNotHasKey('autorizador_rut', session()->getOldInput());
        $this->assertArrayNotHasKey('autorizador_password', session()->getOldInput());
        $this->assertDatabaseMissing('entregas', ['receta_id' => $recipeId]);

        $this->post(route('dispensations.store'), $submission + [
            'autorizador_rut' => '10.333.333-4',
            'autorizador_password' => 'ClaveQF-2026',
        ])
            ->assertRedirectContains(route('dispensations.index'))
            ->assertRedirectContains('comprobante=');

        $this->assertDatabaseHas('entregas', ['receta_id' => $recipeId, 'usuario_rut' => $technicianId]);
        $this->assertDatabaseHas('inventario', ['lote_id' => $lotId, 'cantidad' => 3]);
        $this->assertDatabaseHas('movimientos_controlados', [
            'producto_id' => $productId, 'lote_id' => $lotId, 'tipo_movimiento' => 'SALIDA',
            'cantidad' => 2, 'referencia_tipo' => 'ENTREGA', 'firmado' => true,
            'usuario_responsable_rut' => $technicianId,
            'usuario_autoriza_rut' => $pharmacistId,
        ]);
        $this->assertDatabaseHas('auditoria', [
            'usuario_rut' => $technicianId,
            'accion' => 'FIRMA_CONTROLADO_VALIDADA',
            'resultado' => 'EXITOSO',
        ]);
    }

    public function test_web_dispensation_requires_confirmation_that_the_physical_recipe_matches(): void
    {
        $patientRut = '123456785';
        $productId = DB::table('productos')->where('numero_serie', 'MED-001')->value('producto_id');
        $recipeId = $this->createPrescription((int) $patientRut, $productId, 1);

        $this->from(route('dispensations.index'))->post(route('dispensations.store'), [
            'paciente_rut' => $patientRut,
            'receta_id' => $recipeId,
            'transcripcion_manual' => 1,
            'detalles' => [[
                'producto_id' => $productId,
                'cantidad' => 1,
                'unidades_por_toma' => 1,
                'cantidad_indicada' => 1,
                'frecuencia' => '24',
            ]],
        ])->assertRedirect(route('dispensations.index'))
            ->assertSessionHasErrors('confirmacion_receta');

        $this->assertDatabaseCount('entregas', 0);
    }

    public function test_new_authorized_person_is_saved_with_the_confirmed_dispensation(): void
    {
        config(['labels.width_mm' => 80, 'labels.height_mm' => 50]);

        $patientRut = '123456785';
        $productId = DB::table('productos')->where('numero_serie', 'MED-001')->value('producto_id');
        $locationId = DB::table('ubicaciones')->where('tipo', 'VITRINA')->value('ubicacion_id');
        $this->setProductLocationStock($productId, $locationId, 0, [
            ['NEW-AUTHORIZED-PERSON', 2, now()->addYear()->toDateString(), 'ACTIVO'],
        ]);
        $recipeId = $this->createPrescription((int) $patientRut, $productId, 2);
        $this->getJson(route('dispensations.patient-options', ['patientRut' => $patientRut]))
            ->assertOk()
            ->assertJsonStructure([
                'recetas' => [[
                    'receta_id',
                    'numero_receta',
                    'profesional_rut',
                    'fecha_emision',
                    'fecha_vencimiento',
                    'profesional',
                    'observacion',
                    'detalles' => [[
                        'producto',
                        'unidad_base_codigo',
                        'dosis',
                        'unidades_por_toma',
                        'frecuencia',
                        'duracion_dias',
                        'cantidad_indicada',
                        'indicaciones',
                        'es_sos',
                    ]],
                ]],
                'personas_autorizadas',
            ])
            ->assertJsonFragment([
                'receta_id' => $recipeId,
                'fecha_emision' => now()->toDateString(),
                'fecha_vencimiento' => now()->addMonth()->toDateString(),
            ]);

        $submission = [
            'paciente_rut' => $patientRut,
            'receta_id' => $recipeId,
            'nueva_persona_autorizada' => [
                'rut' => '10.333.333-4',
                'nombre' => 'Ana',
                'apellidoPaterno' => 'Ejemplo',
                'apellidoMaterno' => 'Prueba',
                'vinculo' => 'Hija',
            ],
            'detalles' => [[
                'producto_id' => $productId,
                'cantidad' => 1,
            ]],
        ];
        $invalidSubmission = $submission;
        $invalidSubmission['detalles'][0]['cantidad'] = 3;
        $this->postJson(route('api.v1.dispensations.store'), $invalidSubmission)->assertUnprocessable();
        $this->assertDatabaseMissing('personas_autorizadas', [
            'paciente_rut' => $patientRut,
            'rut' => '103333334',
        ]);

        $this->postJson(route('api.v1.dispensations.store'), $submission)
            ->assertCreated()
            ->assertJsonPath('data.message', 'Entrega confirmada; el stock y la trazabilidad fueron actualizados.');

        $authorizedPersonId = DB::table('personas_autorizadas')
            ->where('paciente_rut', $patientRut)
            ->where('rut', '103333334')
            ->value('persona_autorizada_id');
        $this->assertNotNull($authorizedPersonId);
        $this->assertDatabaseHas('entregas', [
            'receta_id' => $recipeId,
            'persona_autorizada_id' => $authorizedPersonId,
        ]);
        $this->assertDatabaseHas('auditoria', [
            'accion' => 'CREACION',
            'entidad' => 'personas_autorizadas',
            'registro_id' => $authorizedPersonId,
            'resultado' => 'EXITOSO',
        ]);

        $deliveryId = DB::table('entregas')->where('receta_id', $recipeId)->value('entrega_id');
        $orderId = DB::table('ordenes_entrega')->where('entrega_id', $deliveryId)->value('orden_entrega_id');
        $this->assertDatabaseHas('entregas', [
            'entrega_id' => $deliveryId,
            'paciente_nombre_snapshot' => \App\Support\People\PersonName::format(
                DB::table('pacientes')->where('rut', $patientRut)->first()
            ),
            'persona_autorizada_nombre_snapshot' => 'Ana Ejemplo Prueba',
            'persona_autorizada_rut_snapshot' => '103333334',
        ]);
        DB::table('personas_autorizadas')->where('persona_autorizada_id', $authorizedPersonId)
            ->update(['nombre' => 'Nombre Modificado']);

        $this->get(route('delivery-orders.labels', $orderId))
            ->assertOk()
            ->assertSee('Retira: Ana Ejemplo Prueba (RUT 103333334)')
            ->assertDontSee('Nombre Modificado');
    }

    public function test_quimico_farmaceutico_reconciles_controlled_stock_and_resolves_discrepancy(): void
    {
        $categoryId = DB::table('categorias_producto')->where('nombre', 'Medicamentos')->value('categoria_id');
        $baseUnitId = DB::table('unidades_medida')->where('codigo', 'unidad')->value('unidad_medida_id');
        $productId = DB::table('productos')->insertGetId([
            'numero_serie' => 'CTRL-COUNT-TEST',
            'nombre' => 'Controlado para conteo',
            'categoria_id' => $categoryId,
            'unidad_base_inventario_id' => $baseUnitId,
            'es_controlado' => true,
            'activo' => true,
        ]);
        $lotId = DB::table('lotes')->insertGetId([
            'producto_id' => $productId,
            'numero_lote' => 'CTRL-COUNT-LOT',
            'fecha_vencimiento' => now()->addYear()->toDateString(),
        ]);
        $locationId = DB::table('ubicaciones')->where('tipo', 'BODEGA')->value('ubicacion_id');
        DB::table('inventario')->insert([
            'lote_id' => $lotId,
            'ubicacion_id' => $locationId,
            'cantidad' => 5,
        ]);

        $pharmacistId = '104444444';
        DB::table('usuarios')->insert([
            'rut' => $pharmacistId,
            'nombre' => 'QF',
            'apellidoPaterno' => 'De Conciliación',
            'apellidoMaterno' => 'Farmacéutico',
            'password_hash' => Hash::make('UnaClaveSegura!2026'),
            'activo' => true,
        ]);
        $roleId = DB::table('roles')->where('nombre', 'Químico Farmacéutico')->value('rol_id');
        DB::table('usuario_rol')->insert(['usuario_rut' => $pharmacistId, 'rol_id' => $roleId]);
        $this->actingAs(Usuario::findOrFail($pharmacistId));
        $this->get(route('controlled-ledger.reconcile.form'))
            ->assertOk()
            ->assertSee('Conciliar stock físico de controlados');

        $payload = [
            'producto_id' => $productId,
            'lote_id' => $lotId,
            'ubicacion_id' => $locationId,
            'cantidad_contada' => 3,
            'observacion' => 'Conteo mensual documentado',
            'password' => 'UnaClaveSegura!2026',
        ];
        $this->post(route('controlled-ledger.reconcile'), $payload)
            ->assertRedirect(route('controlled-ledger.reconcile.form'))
            ->assertSessionHas('error');
        $this->assertDatabaseHas('alertas', [
            'tipo_alerta' => 'DESCUADRE_CONTROLADO',
            'lote_id' => $lotId,
            'ubicacion_id' => $locationId,
            'nivel_actual' => 3,
            'atendida' => false,
        ]);
        $this->assertDatabaseHas('inventario', ['lote_id' => $lotId, 'cantidad' => 5]);

        $payload['cantidad_contada'] = 5;
        $this->post(route('controlled-ledger.reconcile'), $payload)
            ->assertRedirect(route('controlled-ledger.reconcile.form'))
            ->assertSessionHas('status');
        $this->assertDatabaseHas('alertas', [
            'tipo_alerta' => 'DESCUADRE_CONTROLADO',
            'lote_id' => $lotId,
            'atendida' => true,
            'atendida_por_rut' => $pharmacistId,
        ]);
        $this->assertDatabaseCount('alertas', 1);
        $this->assertDatabaseHas('auditoria', [
            'accion' => 'CONTEO_CONTROLADO',
            'resultado' => 'DESCUADRE_DETECTADO',
        ]);
    }

    public function test_cart_stock_and_emergency_use_can_be_searched_and_filtered(): void
    {
        $productId = DB::table('productos')->where('numero_serie', 'MED-001')->value('producto_id');
        $lotId = DB::table('lotes')->where('producto_id', $productId)->value('lote_id');
        $locationId = DB::table('ubicaciones')->where('tipo', 'CARRO_PARO')->value('ubicacion_id');
        DB::table('inventario')->insert([
            'lote_id' => $lotId,
            'ubicacion_id' => $locationId,
            'cantidad' => 5,
        ]);
        DB::table('movimientos_inventario')->insert([
            'lote_id' => $lotId,
            'ubicacion_id' => $locationId,
            'tipo_movimiento' => 'SALIDA',
            'cantidad' => 2,
            'usuario_rut' => auth()->id(),
            'referencia_tipo' => 'CARRO_PARO',
            'observacion' => 'Motivo filtro carro',
        ]);

        $this->get(route('carts.index', [
            'buscar_stock' => 'MED-001',
            'tipo_carro' => 'PARO',
        ]))
            ->assertOk()
            ->assertSee('L250821')
            ->assertSee('Consultar existencias en carros');

        $inactiveProductId = DB::table('productos')->insertGetId([
            'numero_serie' => 'INACTIVE-CART-001',
            'nombre' => 'Producto inactivo carro',
            'categoria_id' => DB::table('categorias_producto')->value('categoria_id'),
            'unidad_base_inventario_id' => DB::table('unidades_medida')->where('codigo', 'unidad')->value('unidad_medida_id'),
            'activo' => false,
        ]);
        $inactiveLotId = DB::table('lotes')->insertGetId([
            'producto_id' => $inactiveProductId,
            'numero_lote' => 'INACTIVE-CART-LOT',
            'fecha_vencimiento' => now()->addYear()->toDateString(),
        ]);
        DB::table('inventario')->insert([
            'lote_id' => $inactiveLotId,
            'ubicacion_id' => $locationId,
            'cantidad' => 3,
        ]);

        $this->get(route('carts.index', [
            'buscar_stock' => 'INACTIVE-CART-001',
            'estado_producto' => 'inactivo',
        ]))
            ->assertOk()
            ->assertSee('Producto inactivo carro')
            ->assertSee('Inactivo');

        $this->get(route('carts.index', ['buscar_stock' => 'INACTIVE-CART-001']))
            ->assertOk()
            ->assertDontSee('Producto inactivo carro');

        $this->get(route('carts.usage.index', ['buscar_uso' => 'Motivo filtro carro']))
            ->assertOk()
            ->assertSee('Motivo filtro carro')
            ->assertSee('Consultar uso del Carro de Paro');
    }

    // Verifica que las fechas del formulario filtren las preparaciones Unidosis.
    public function test_unidosis_preparations_can_be_filtered_by_date_range(): void
    {
        $patientRut = DB::table('pacientes')->where('rut', '123456785')->value('rut');
        $cartId = DB::table('carros')->where('tipo_carro', 'UNIDOSIS')->value('carro_id');
        $treatmentId = DB::table('tratamientos')->insertGetId([
            'paciente_rut' => $patientRut,
            'descripcion' => 'Tratamiento para filtro de fechas',
            'fecha_inicio' => now()->subDays(10)->toDateString(),
            'activo' => true,
        ]);

        foreach ([
            now()->subDay()->toDateString() => 'Preparación anterior',
            now()->toDateString() => 'Preparación actual',
        ] as $date => $label) {
            DB::table('preparaciones_unidosis')->insert([
                'tratamiento_id' => $treatmentId,
                'carro_id' => $cartId,
                'fecha_preparacion' => $date,
                'periodo' => $label,
                'usuario_prepara_rut' => auth()->id(),
                'estado' => 'PREPARANDO',
            ]);
        }

        $this->get(route('carts.unidosis.index', [
            'preparacion_desde' => now()->toDateString(),
            'preparacion_hasta' => now()->toDateString(),
        ]))
            ->assertOk()
            ->assertSee('Preparación actual')
            ->assertDontSee('Preparación anterior');
    }

    public function test_unidosis_preparation_allocates_fefo_stock_and_generates_labels(): void
    {
        $patientId = DB::table('pacientes')->where('rut', '123456785')->value('rut');
        $productId = DB::table('productos')->where('numero_serie', 'MED-001')->value('producto_id');
        $locationId = DB::table('ubicaciones')->where('tipo', 'CARRO_UNIDOSIS')->value('ubicacion_id');
        $cartId = DB::table('carros')->where('tipo_carro', 'UNIDOSIS')->value('carro_id');
        $treatmentId = DB::table('tratamientos')->insertGetId([
            'paciente_rut' => $patientId,
            'descripcion' => 'Tratamiento de prueba',
            'fecha_inicio' => now()->toDateString(),
            'activo' => true,
        ]);
        $firstLotId = DB::table('lotes')->insertGetId([
            'producto_id' => $productId,
            'numero_lote' => 'UNI-FEFO-01',
            'fecha_vencimiento' => now()->addMonths(2)->toDateString(),
        ]);
        DB::table('inventario')->insert([
            'lote_id' => $firstLotId,
            'ubicacion_id' => $locationId,
            'cantidad' => 1,
        ]);
        DB::table('inventario')->insert([
            'lote_id' => DB::table('lotes')->where('producto_id', $productId)->where('numero_lote', 'L250821')->value('lote_id'),
            'ubicacion_id' => $locationId,
            'cantidad' => 4,
        ]);

        $this->getJson(route('carts.unidosis.products.search', ['q' => 'Paracetamol']))
            ->assertOk()
            ->assertJsonFragment(['producto_id' => $productId]);

        $this->post(route('carts.unidosis.prepare'), [
            'tratamiento_id' => $treatmentId,
            'fecha_preparacion' => now()->toDateString(),
            'periodo' => 'MANANA',
            'detalles' => [[
                'producto_id' => $productId,
                'cantidad' => 2,
                'horario' => '08:00',
            ]],
        ])->assertRedirect(route('carts.unidosis.index'));

        $preparationId = DB::table('preparaciones_unidosis')->value('preparacion_id');
        $this->assertDatabaseHas('preparacion_unidosis_detalle', ['preparacion_id' => $preparationId, 'lote_id' => $firstLotId, 'cantidad' => 1]);
        $this->assertDatabaseHas('rotulos_unidosis', ['identificador' => 'UNI-'.$preparationId.'-1']);
        $labelContent = DB::table('rotulos_unidosis')
            ->where('preparacion_detalle_id', DB::table('preparacion_unidosis_detalle')->where('preparacion_id', $preparationId)->value('preparacion_detalle_id'))
            ->value('contenido_generado');
        $this->assertStringNotContainsString('Dosis:', $labelContent);
        $this->assertStringContainsString('Horario: 08:00', $labelContent);
        $this->assertDatabaseHas('inventario', ['lote_id' => $firstLotId, 'cantidad' => 0]);

        $this->from(route('carts.unidosis.create'))->post(route('carts.unidosis.prepare'), [
            'tratamiento_id' => $treatmentId,
            'fecha_preparacion' => now()->toDateString(),
            'periodo' => 'TARDE',
            'detalles' => [[
                'producto_id' => $productId,
                'cantidad' => 1,
                'horario' => '8:00 PM',
            ]],
        ])->assertRedirect(route('carts.unidosis.create'))
            ->assertSessionHasErrors('detalles.0.horario');

        $pharmacistId = '103333333';
        DB::table('usuarios')->insert([
            'rut' => $pharmacistId,
            'nombre' => 'Química',
            'apellidoPaterno' => 'Confirmadora',
            'apellidoMaterno' => 'Farmacéutica',
            'password_hash' => bcrypt('UnaClaveSegura!2026'),
            'activo' => true,
        ]);
        $roleId = DB::table('roles')->where('nombre', 'Químico Farmacéutico')->value('rol_id');
        DB::table('usuario_rol')->insert(['usuario_rut' => $pharmacistId, 'rol_id' => $roleId]);
        $this->actingAs(Usuario::findOrFail($pharmacistId))
            ->patch(route('carts.unidosis.confirm', $preparationId))
            ->assertRedirect(route('carts.unidosis.index'));
        $this->assertDatabaseHas('preparaciones_unidosis', [
            'preparacion_id' => $preparationId,
            'estado' => 'CONFIRMADA',
            'usuario_confirma_rut' => $pharmacistId,
        ]);
    }

    public function test_dispensing_consumes_stock_by_expiry_order_and_writes_audit(): void
    {
        config(['labels.width_mm' => 80, 'labels.height_mm' => 50]);

        $productId = DB::table('productos')->where('numero_serie', 'MED-001')->value('producto_id');
        $locationId = DB::table('ubicaciones')->where('tipo', 'VITRINA')->value('ubicacion_id');
        $patientId = DB::table('pacientes')->where('rut', '123456785')->value('rut');
        $recipeId = $this->createPrescription($patientId, $productId, 3);
        DB::table('receta_detalle')->where('receta_id', $recipeId)->update([
            'dosis' => '1 comprimido',
            'frecuencia' => '8',
            'indicaciones' => 'Tomar con agua',
        ]);
        $nearLotId = DB::table('lotes')->insertGetId([
            'producto_id' => $productId,
            'numero_lote' => 'FEFO-TEST',
            'fecha_vencimiento' => now()->addMonths(3)->toDateString(),
        ]);
        DB::table('inventario')->insert([
            'lote_id' => $nearLotId,
            'ubicacion_id' => $locationId,
            'cantidad' => 2,
        ]);
        $this->getJson(route('products.api.fefo', $productId))
            ->assertOk()
            ->assertJsonPath('lotes.0.numero_lote', 'FEFO-TEST')
            ->assertJsonPath('lotes.0.cantidad_disponible', 2);
        DB::table('stock_minimos_ubicacion')->updateOrInsert(
            ['producto_id' => $productId, 'ubicacion_id' => $locationId],
            ['stock_minimo' => 180]
        );

        $dispensationResponse = $this->post(route('dispensations.store'), [
            'paciente_rut' => $patientId,
            'producto_id' => $productId,
            'receta_id' => $recipeId,
            'cantidad' => 3,
            'dosis' => '1 comprimido',
            'frecuencia' => '8',
            'indicaciones' => 'Tomar con agua',
        ]);
        $dispensationResponse->assertSessionHas('delivery_order_id');

        $this->assertDatabaseMissing('inventario', ['lote_id' => $nearLotId]);
        $this->assertDatabaseHas('kardex_inventario', [
            'lote_id' => $nearLotId,
            'ubicacion_id' => $locationId,
            'motivo' => 'DISPENSACION_LOTE_AGOTADO',
            'referencia_tipo' => 'ENTREGA',
        ]);
        $this->assertDatabaseHas('entrega_detalle', ['lote_id' => $nearLotId, 'cantidad' => 2]);
        $deliveryId = DB::table('entregas')->value('entrega_id');
        $this->assertDatabaseHas('entregas', [
            'entrega_id' => $deliveryId,
            'ficha_id' => DB::table('fichas_pacientes')->where('paciente_rut', $patientId)->value('ficha_id'),
        ]);
        $orderId = DB::table('ordenes_entrega')->where('entrega_id', $deliveryId)->value('orden_entrega_id');
        $this->assertNotNull($orderId);
        $dispensationResponse->assertRedirect(route('dispensations.index', ['comprobante' => $orderId]));
        $detailId = DB::table('entrega_detalle')->where('entrega_id', $deliveryId)->value('entrega_detalle_id');
        $this->app['session.store']->forget('delivery_order_id');
        $this->get(route('dispensations.index', ['comprobante' => $orderId]))
            ->assertOk()
            ->assertSee('data-dispensation-result-dialog', false)
            ->assertSee('data-open-on-load="true"', false)
            ->assertSee('>Receta Médica</h2>', false)
            ->assertSee('src="'.asset('logo.jpg').'"', false)
            ->assertDontSee('<th>PDF</th>', false)
            ->assertSee('FEFO-TEST')
            ->assertSee('<th>Etiqueta</th>', false)
            ->assertSee('data-print-label-url="'.route('delivery-orders.label', [$orderId, $detailId]).'"', false)
            ->assertSee('Imprimir etiqueta')
            ->assertSee('Imprimir etiquetas')
            ->assertSee('Imprimir Orden')
            ->assertSee(route('delivery-orders.labels', $orderId), false);
        $this->get(route('delivery-orders.receipt', $orderId))
            ->assertOk()
            ->assertSee('class="print-delivery-order"', false)
            ->assertSee('<h1>Receta Médica</h1>', false)
            ->assertSee('src="'.asset('logo.jpg').'"', false)
            ->assertSee('Paracetamol 500 mg')
            ->assertSee('FEFO-TEST')
            ->assertSee('Tomar con agua');
        $this->get(route('delivery-orders.show', $orderId))
            ->assertOk()
            ->assertSee('Detalle de dispensación')
            ->assertSee('Paracetamol 500 mg')
            ->assertSee('FEFO-TEST')
            ->assertSee('Retira</span><strong>Paciente</strong>', false)
            ->assertSee('Dispensado por')
            ->assertDontSee('Responsable')
            ->assertSee('data-print-label-url', false)
            ->assertSee('Imprimir etiquetas')
            ->assertSee('Imprimir');
        $this->get(route('delivery-orders.label', [$orderId, $detailId]))
            ->assertOk()
            ->assertSee('class="print-delivery-label"', false)
            ->assertSee('Etiquetas farmacéuticas')
            ->assertSee('Imprimir etiquetas')
            ->assertSee('@page', false)
            ->assertSee('80.00mm 50.00mm', false)
            ->assertSee('Paracetamol 500 mg')
            ->assertSee('LOTE FEFO-TEST')
            ->assertSee('Entregado:')
            ->assertSee('Vence lote:')
            ->assertSee('RUT:')
            ->assertSee('Dosis:')
            ->assertSee('Frecuencia:')
            ->assertSee('Cada 8 horas')
            ->assertSee('Indicaciones:');
        $this->get(route('delivery-orders.labels', $orderId))
            ->assertOk()
            ->assertSee('Vista previa')
            ->assertSee('1 copia por etiqueta');
        $this->assertDatabaseHas('auditoria', ['accion' => 'DISPENSACION', 'resultado' => 'EXITOSO']);
        $this->assertDatabaseHas('alertas', [
            'tipo_alerta' => 'STOCK_CRITICO',
            'producto_id' => $productId,
            'lote_id' => null,
            'ubicacion_id' => $locationId,
            'nivel_actual' => 179,
            'resuelta_at' => null,
        ]);
    }

    public function test_label_reprints_use_snapshots_group_by_product_and_lot_and_do_not_change_stock(): void
    {
        $patientRut = '123456785';
        $productId = DB::table('productos')->where('numero_serie', 'MED-001')->value('producto_id');
        $lot = DB::table('lotes')->where('producto_id', $productId)->first();
        $deliveryId = DB::table('entregas')->insertGetId([
            'paciente_rut' => $patientRut,
            'usuario_rut' => auth()->id(),
            'estado' => 'CONFIRMADA',
            'fecha_hora' => now(),
            'paciente_nombre_snapshot' => 'Paciente Histórico',
        ]);
        $orderId = DB::table('ordenes_entrega')->insertGetId([
            'entrega_id' => $deliveryId,
            'numero_orden' => 'ORD-LABEL-SNAPSHOT',
            'plantilla' => 'COMPROBANTE_FARMACIA_V1',
        ]);

        foreach ([2, 3] as $quantity) {
            DB::table('entrega_detalle')->insert([
                'entrega_id' => $deliveryId,
                'lote_id' => $lot->lote_id,
                'producto_id' => $productId,
                'cantidad' => $quantity,
                'dosis' => '1 comprimido',
                'horario' => '24',
                'indicaciones' => 'Tomar en la mañana con agua.',
                'producto_nombre_snapshot' => 'Medicamento Histórico 50 mg',
                'unidad_base_codigo_snapshot' => 'comp.',
                'numero_lote_snapshot' => 'LOTE-HISTORICO',
                'fecha_vencimiento_lote_snapshot' => $lot->fecha_vencimiento,
            ]);
        }

        DB::table('pacientes')->where('rut', $patientRut)->update(['nombre' => 'Paciente Actualizado']);
        DB::table('productos')->where('producto_id', $productId)->update(['nombre' => 'Medicamento Actualizado']);
        $inventoryBefore = DB::table('inventario')->sum('cantidad');
        $deliveryCountBefore = DB::table('entregas')->count();
        $detailCountBefore = DB::table('entrega_detalle')->count();

        config(['labels.width_mm' => null, 'labels.height_mm' => null]);
        $this->get(route('delivery-orders.labels', $orderId))
            ->assertOk()
            ->assertSee('Configura LABEL_WIDTH_MM y LABEL_HEIGHT_MM')
            ->assertDontSee('Imprimir etiquetas');
        $this->assertDatabaseMissing('auditoria', [
            'entidad' => 'ordenes_entrega',
            'registro_id' => $orderId,
            'accion' => 'SOLICITUD_ETIQUETAS',
        ]);

        config(['labels.width_mm' => 72.5, 'labels.height_mm' => 38]);
        $response = $this->get(route('delivery-orders.labels', $orderId))
            ->assertOk()
            ->assertSee('72.50mm 38.00mm', false)
            ->assertSee('Paciente Histórico')
            ->assertDontSee('Paciente Actualizado')
            ->assertSee('Medicamento Histórico 50 mg')
            ->assertDontSee('Medicamento Actualizado')
            ->assertSee('LOTE LOTE-HISTORICO')
            ->assertSee('5 comp.')
            ->assertSee('Retira: Titular')
            ->assertSee('Cada 24 horas')
            ->assertSee('Tomar en la mañana con agua.');

        $this->assertSame(1, substr_count($response->getContent(), '<article class="dispensed-label">'));
        $this->assertSame($inventoryBefore, DB::table('inventario')->sum('cantidad'));
        $this->assertSame($deliveryCountBefore, DB::table('entregas')->count());
        $this->assertSame($detailCountBefore, DB::table('entrega_detalle')->count());
        $this->assertDatabaseHas('auditoria', [
            'entidad' => 'ordenes_entrega',
            'registro_id' => $orderId,
            'accion' => 'SOLICITUD_ETIQUETAS',
            'resultado' => 'EXITOSO',
        ]);

        $this->get(route('delivery-orders.labels', $orderId))->assertOk();
        $this->assertDatabaseHas('auditoria', [
            'entidad' => 'ordenes_entrega',
            'registro_id' => $orderId,
            'accion' => 'REIMPRESION_ETIQUETAS',
            'resultado' => 'EXITOSO',
        ]);

        $otherProductId = DB::table('productos')->where('numero_serie', 'MED-002')->value('producto_id');
        DB::table('entrega_detalle')->where('entrega_id', $deliveryId)->update(['producto_id' => $otherProductId]);
        $this->get(route('delivery-orders.labels', $orderId))
            ->assertStatus(409);

        DB::table('entregas')->where('entrega_id', $deliveryId)->update(['estado' => 'PENDIENTE']);
        $this->get(route('delivery-orders.labels', $orderId))->assertNotFound();
    }

    public function test_dispensing_registers_multiple_prescription_medicines_in_one_delivery(): void
    {
        $patientId = DB::table('pacientes')->where('rut', '123456785')->value('rut');
        $firstProductId = DB::table('productos')->where('numero_serie', 'MED-001')->value('producto_id');
        $secondProductId = DB::table('productos')->where('numero_serie', 'MED-002')->value('producto_id');
        $locationId = DB::table('ubicaciones')->where('tipo', 'VITRINA')->value('ubicacion_id');
        $recipeId = $this->createPrescription($patientId, $firstProductId, 4);
        DB::table('receta_detalle')->where('receta_id', $recipeId)->where('producto_id', $firstProductId)->update([
            'dosis' => '1 comprimido',
            'unidades_por_toma' => 1,
            'duracion_dias' => 2,
            'es_sos' => false,
            'frecuencia' => '8',
            'indicaciones' => 'Con agua',
        ]);
        DB::table('receta_detalle')->insert([
            'receta_id' => $recipeId,
            'producto_id' => $secondProductId,
            'cantidad_indicada' => 3,
            'dosis' => '1 cápsula',
            'unidades_por_toma' => 1,
            'duracion_dias' => 2,
            'es_sos' => false,
            'frecuencia' => '12',
            'indicaciones' => 'Tomar después de comer',
        ]);

        foreach ([[$firstProductId, 'MULTI-001'], [$secondProductId, 'MULTI-002']] as [$productId, $lotNumber]) {
            $lotId = DB::table('lotes')->insertGetId([
                'producto_id' => $productId,
                'numero_lote' => $lotNumber,
                'fecha_vencimiento' => now()->addYear()->toDateString(),
            ]);
            DB::table('inventario')->insert([
                'lote_id' => $lotId,
                'ubicacion_id' => $locationId,
                'cantidad' => 10,
            ]);
        }

        $this->post(route('dispensations.store'), [
            'paciente_rut' => $patientId,
            'receta_id' => $recipeId,
            'transcripcion_manual' => 1,
            'confirmacion_receta' => 1,
            'observacion' => 'Receta física cotejada',
            'detalles' => [
                [
                    'producto_id' => $firstProductId,
                    'cantidad' => 4,
                    'unidades_por_toma' => 1,
                    'duracion_dias' => 2,
                    'cantidad_indicada' => 4,
                    'es_sos' => 0,
                    'frecuencia' => '8',
                    'indicaciones' => 'Con agua',
                ],
                [
                    'producto_id' => $secondProductId,
                    'cantidad' => 3,
                    'unidades_por_toma' => 1,
                    'duracion_dias' => 2,
                    'cantidad_indicada' => 3,
                    'es_sos' => 0,
                    'frecuencia' => '12',
                    'indicaciones' => 'Tomar después de comer',
                ],
            ],
        ])->assertRedirectContains(route('dispensations.index'))
            ->assertRedirectContains('comprobante=');

        $deliveryId = DB::table('entregas')->where('receta_id', $recipeId)->value('entrega_id');
        $this->assertDatabaseHas('entregas', [
            'entrega_id' => $deliveryId,
            'observacion' => 'Receta física cotejada',
        ]);
        $this->assertDatabaseHas('auditoria', [
            'accion' => 'DISPENSACION',
            'registro_id' => $deliveryId,
            'resultado' => 'EXITOSO',
        ]);
        $this->assertSame(2, DB::table('entrega_detalle')->where('entrega_id', $deliveryId)->distinct('producto_id')->count('producto_id'));
        $this->assertDatabaseHas('entrega_detalle', [
            'entrega_id' => $deliveryId,
            'producto_id' => $firstProductId,
            'cantidad' => 4,
            'horario' => '8',
            'indicaciones' => 'Con agua',
        ]);
        $this->assertDatabaseHas('entrega_detalle', [
            'entrega_id' => $deliveryId,
            'producto_id' => $secondProductId,
            'cantidad' => 3,
            'horario' => '12',
            'dosis' => '1 cápsula',
            'indicaciones' => 'Tomar después de comer',
        ]);
        $this->assertDatabaseHas('recetas', ['receta_id' => $recipeId, 'estado' => 'UTILIZADA']);

        $orderId = DB::table('ordenes_entrega')->where('entrega_id', $deliveryId)->value('orden_entrega_id');
        $this->get(route('delivery-orders.show', $orderId))
            ->assertOk()
            ->assertSee('Cada 8 horas')
            ->assertSee('Cada 12 horas')
            ->assertSee('Imprimir');
    }

    public function test_dispensing_validates_every_entered_field_against_the_prescription_before_creating_delivery(): void
    {
        $patientId = DB::table('pacientes')->where('rut', '123456785')->value('rut');
        $prescribedProductId = DB::table('productos')->where('numero_serie', 'MED-001')->value('producto_id');
        $otherProductId = DB::table('productos')->where('numero_serie', 'MED-002')->value('producto_id');
        $recipeId = $this->createPrescription($patientId, $prescribedProductId, 5);
        DB::table('receta_detalle')->where('receta_id', $recipeId)->update([
            'dosis' => '1 comprimido',
            'unidades_por_toma' => 1,
            'duracion_dias' => 5,
            'es_sos' => false,
            'frecuencia' => 'Cada 8 horas',
            'indicaciones' => 'Tomar con agua',
        ]);

        $validDetail = [
            'producto_id' => $prescribedProductId,
            'cantidad' => 5,
            'unidades_por_toma' => 1,
            'duracion_dias' => 5,
            'frecuencia' => 'Cada 8 horas',
            'cantidad_indicada' => 5,
            'es_sos' => 0,
            'indicaciones' => 'Tomar con agua',
        ];
        $invalidDetails = [
            ['producto_id' => $otherProductId] + array_diff_key($validDetail, ['producto_id' => true]),
            ['cantidad' => 6] + array_diff_key($validDetail, ['cantidad' => true]),
            ['unidades_por_toma' => 2] + array_diff_key($validDetail, ['unidades_por_toma' => true]),
            ['duracion_dias' => 6] + array_diff_key($validDetail, ['duracion_dias' => true]),
            ['frecuencia' => 'Cada 12 horas'] + array_diff_key($validDetail, ['frecuencia' => true]),
            ['cantidad_indicada' => 6] + array_diff_key($validDetail, ['cantidad_indicada' => true]),
            ['indicaciones' => 'Tomar sin agua'] + array_diff_key($validDetail, ['indicaciones' => true]),
        ];
        $errorFields = [
            'producto_id',
            'cantidad',
            'unidades_por_toma',
            'duracion_dias',
            'frecuencia',
            'cantidad_indicada',
            'indicaciones',
        ];

        foreach ($invalidDetails as $index => $detail) {
            $this->from(route('dispensations.index'))->post(route('dispensations.store'), [
                'paciente_rut' => $patientId,
                'receta_id' => $recipeId,
                'transcripcion_manual' => 1,
                'confirmacion_receta' => 1,
                'detalles' => [$detail],
            ])->assertRedirect(route('dispensations.index'))
                ->assertSessionHasErrors('detalles.0.'.$errorFields[$index]);
        }

        $this->from(route('dispensations.index'))->post(route('dispensations.store'), [
            'paciente_rut' => $patientId,
            'receta_id' => $recipeId,
            'transcripcion_manual' => 1,
            'confirmacion_receta' => 1,
            'detalles' => [[
                'producto_id' => $prescribedProductId,
                'cantidad' => 5,
            ]],
        ])->assertSessionHasErrors([
            'detalles.0.unidades_por_toma',
            'detalles.0.cantidad_indicada',
        ]);

        $this->assertDatabaseCount('entregas', 0);
        $this->assertDatabaseCount('movimientos_inventario', 0);
    }

    public function test_recent_deliveries_can_be_searched_and_filtered_by_exact_date(): void
    {
        $patients = DB::table('pacientes')->orderBy('rut')->limit(2)->get();
        $productId = DB::table('productos')->where('numero_serie', 'MED-001')->value('producto_id');
        $lotId = DB::table('lotes')->where('producto_id', $productId)->value('lote_id');
        $firstDeliveryDate = now()->subDay()->setTime(10, 30);
        $secondDeliveryDate = now()->setTime(11, 30);

        foreach ([
            [$patients[0]->rut, $firstDeliveryDate->toDateTimeString(), 'ORD-SEARCH-1001'],
            [$patients[1]->rut, $secondDeliveryDate->toDateTimeString(), 'ORD-SEARCH-1002'],
        ] as [$patientId, $date, $orderNumber]) {
            $deliveryId = DB::table('entregas')->insertGetId([
                'paciente_rut' => $patientId,
                'usuario_rut' => auth()->id(),
                'fecha_hora' => $date,
                'estado' => 'CONFIRMADA',
            ]);
            DB::table('entrega_detalle')->insert([
                'entrega_id' => $deliveryId,
                'lote_id' => $lotId,
                'producto_id' => $productId,
                'cantidad' => 2,
            ]);
            DB::table('ordenes_entrega')->insert([
                'entrega_id' => $deliveryId,
                'numero_orden' => $orderNumber,
                'plantilla' => 'COMPROBANTE_FARMACIA_V1',
            ]);
        }

        $this->get(route('dispensations.deliveries', [
            'buscar' => 'Juan',
            'fecha' => $secondDeliveryDate->toDateString(),
        ]))
            ->assertOk()
            ->assertSee('Juan Pérez Morales')
            ->assertSee('Paciente')
            ->assertSee('ORD-SEARCH-1002')
            ->assertDontSee('ORD-SEARCH-1001');
    }

    public function test_dispensing_allows_partial_deliveries_and_keeps_the_prescription_open_until_complete(): void
    {
        $patientId = DB::table('pacientes')->where('rut', '123456785')->value('rut');
        $firstProductId = DB::table('productos')->where('numero_serie', 'MED-001')->value('producto_id');
        $secondProductId = DB::table('productos')->where('numero_serie', 'MED-002')->value('producto_id');
        $locationId = DB::table('ubicaciones')->where('tipo', 'VITRINA')->value('ubicacion_id');
        $recipeId = $this->createPrescription($patientId, $firstProductId, 2);
        DB::table('receta_detalle')->insert([
            'receta_id' => $recipeId,
            'producto_id' => $secondProductId,
            'cantidad_indicada' => 1,
        ]);
        $todayLotId = DB::table('lotes')->insertGetId([
            'producto_id' => $firstProductId,
            'numero_lote' => 'EXPIRES-TODAY',
            'fecha_vencimiento' => now()->toDateString(),
        ]);
        DB::table('inventario')->insert([
            'lote_id' => $todayLotId,
            'ubicacion_id' => $locationId,
            'cantidad' => 1,
        ]);

        $stockBefore = DB::table('inventario')->whereIn('lote_id', DB::table('lotes')->whereIn('producto_id', [$firstProductId, $secondProductId])->select('lote_id'))
            ->where('ubicacion_id', $locationId)->sum('cantidad');

        $this->post(route('dispensations.store'), [
            'paciente_rut' => $patientId,
            'receta_id' => $recipeId,
            'detalles' => [['producto_id' => $firstProductId, 'cantidad' => 3]],
        ])->assertSessionHasErrors('detalles.0.cantidad');
        $this->assertDatabaseCount('entregas', 0);
        $this->assertSame((float) $stockBefore, (float) DB::table('inventario')
            ->whereIn('lote_id', DB::table('lotes')->whereIn('producto_id', [$firstProductId, $secondProductId])->select('lote_id'))
            ->where('ubicacion_id', $locationId)->sum('cantidad'));

        $this->post(route('dispensations.store'), [
            'paciente_rut' => $patientId,
            'receta_id' => $recipeId,
            'detalles' => [['producto_id' => $firstProductId, 'cantidad' => 1]],
        ])->assertRedirectContains(route('dispensations.index'))
            ->assertRedirectContains('comprobante=');
        $this->assertDatabaseHas('entrega_detalle', ['lote_id' => $todayLotId, 'cantidad' => 1]);
        $this->assertDatabaseHas('recetas', ['receta_id' => $recipeId, 'estado' => 'VIGENTE']);
        $firstDeliveryId = DB::table('entregas')->where('receta_id', $recipeId)->value('entrega_id');
        $firstOrderId = DB::table('ordenes_entrega')->where('entrega_id', $firstDeliveryId)->value('orden_entrega_id');
        $unitCode = DB::table('productos')
            ->join('unidades_medida', 'unidades_medida.unidad_medida_id', '=', 'productos.unidad_base_inventario_id')
            ->where('productos.producto_id', $firstProductId)
            ->value('unidades_medida.codigo');
        $this->get(route('delivery-orders.show', $firstOrderId))
            ->assertOk()
            ->assertSee('CANTIDAD PRESCRITA')
            ->assertSee('SALDO PENDIENTE')
            ->assertSee('1 '.$unitCode);

        $this->post(route('dispensations.store'), [
            'paciente_rut' => $patientId,
            'receta_id' => $recipeId,
            'detalles' => [
                ['producto_id' => $firstProductId, 'cantidad' => 1],
                ['producto_id' => $secondProductId, 'cantidad' => 1],
            ],
        ])->assertRedirectContains(route('dispensations.index'))
            ->assertRedirectContains('comprobante=');
        $this->assertDatabaseHas('recetas', ['receta_id' => $recipeId, 'estado' => 'UTILIZADA']);
    }

    public function test_long_term_treatment_caps_dispensing_per_calendar_month_and_allows_next_month_refill(): void
    {
        $patientRut = '123456785';
        $patientId = (int) $patientRut;
        $productId = DB::table('productos')->where('numero_serie', 'MED-001')->value('producto_id');
        $locationId = DB::table('ubicaciones')->where('tipo', 'VITRINA')->value('ubicacion_id');
        $issuedAt = now();
        $expiresAt = $issuedAt->copy()->addMonths(6);
        $recipeId = $this->createPrescription($patientId, $productId, 3);
        DB::table('recetas')->where('receta_id', $recipeId)->update([
            'fecha_vencimiento' => $expiresAt->toDateString(),
        ]);
        DB::table('tratamientos')->insert([
            'receta_id' => $recipeId,
            'paciente_rut' => $patientRut,
            'descripcion' => 'Tratamiento prolongado de prueba',
            'fecha_inicio' => $issuedAt->toDateString(),
            'fecha_termino' => $expiresAt->toDateString(),
            'activo' => true,
        ]);
        $this->setProductLocationStock($productId, $locationId, 0, [
            ['LONG-TERM-MONTHLY', 30, $expiresAt->toDateString(), 'ACTIVO'],
        ]);

        $this->post(route('dispensations.store'), [
            'paciente_rut' => $patientRut,
            'receta_id' => $recipeId,
            'detalles' => [['producto_id' => $productId, 'cantidad' => 2]],
        ])->assertRedirectContains(route('dispensations.index'))
            ->assertRedirectContains('comprobante=');

        $options = $this->getJson(route('dispensations.patient-options', ['patientRut' => $patientRut]))
            ->assertOk()
            ->assertJsonPath('recetas.0.detalles.0.es_tratamiento_prolongado', true);
        $this->assertEquals(1, $options->json('recetas.0.detalles.0.cantidad_pendiente'));

        $this->from(route('dispensations.index'))->post(route('dispensations.store'), [
            'paciente_rut' => $patientRut,
            'receta_id' => $recipeId,
            'detalles' => [['producto_id' => $productId, 'cantidad' => 2]],
        ])->assertRedirect(route('dispensations.index'))
            ->assertSessionHasErrors('detalles.0.cantidad');
        $this->assertSame(1, DB::table('entregas')->where('receta_id', $recipeId)->count());

        $this->travelTo($issuedAt->copy()->addMonthNoOverflow()->startOfMonth()->addDay());
        $nextMonthOptions = $this->getJson(route('dispensations.patient-options', ['patientRut' => $patientRut]))
            ->assertOk()
            ->assertJsonPath('recetas.0.detalles.0.es_tratamiento_prolongado', true);
        $this->assertEquals(3, $nextMonthOptions->json('recetas.0.detalles.0.cantidad_pendiente'));

        $this->post(route('dispensations.store'), [
            'paciente_rut' => $patientRut,
            'receta_id' => $recipeId,
            'detalles' => [['producto_id' => $productId, 'cantidad' => 3]],
        ])->assertRedirectContains(route('dispensations.index'))
            ->assertRedirectContains('comprobante=');
        $this->assertSame(2, DB::table('entregas')->where('receta_id', $recipeId)->count());
        $this->assertDatabaseHas('recetas', ['receta_id' => $recipeId, 'estado' => 'VIGENTE']);
    }

    public function test_expired_prescriptions_are_hidden_and_cannot_be_dispensed(): void
    {
        $patientRut = '123456785';
        $productId = DB::table('productos')->where('numero_serie', 'MED-001')->value('producto_id');
        $recipeId = $this->createPrescription((int) $patientRut, $productId, 2);
        DB::table('recetas')->where('receta_id', $recipeId)->update([
            'fecha_emision' => now()->subDays(2)->toDateString(),
            'fecha_vencimiento' => now()->subDay()->toDateString(),
        ]);

        $this->getJson(route('dispensations.patient-options', ['patientRut' => $patientRut]))
            ->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertJsonMissing(['receta_id' => $recipeId]);

        $this->from(route('dispensations.index'))->post(route('dispensations.store'), [
            'paciente_rut' => $patientRut,
            'receta_id' => $recipeId,
            'detalles' => [['producto_id' => $productId, 'cantidad' => 1]],
        ])->assertRedirect(route('dispensations.index'))
            ->assertSessionHasErrors('receta_id');

        $this->assertDatabaseCount('entregas', 0);
    }

    public function test_dispensing_shortage_rolls_back_stock_and_delivery_records(): void
    {
        $patientId = DB::table('pacientes')->where('rut', '123456785')->value('rut');
        $productId = DB::table('productos')->where('numero_serie', 'MED-001')->value('producto_id');
        $stockBefore = DB::table('inventario')
            ->join('lotes', 'lotes.lote_id', '=', 'inventario.lote_id')
            ->where('lotes.producto_id', $productId)
            ->where('inventario.ubicacion_id', DB::table('ubicaciones')->where('tipo', 'VITRINA')->value('ubicacion_id'))
            ->sum('inventario.cantidad');
        $requestedQuantity = (int) $stockBefore + 40;
        $recipeId = $this->createPrescription($patientId, $productId, $requestedQuantity);

        $this->from(route('dispensations.index'))->post(route('dispensations.store'), [
            'paciente_rut' => $patientId,
            'receta_id' => $recipeId,
            'transcripcion_manual' => 1,
            'confirmacion_receta' => 1,
            'detalles' => [[
                'producto_id' => $productId,
                'cantidad' => $requestedQuantity,
                'unidades_por_toma' => 1,
                'duracion_dias' => 1,
                'frecuencia' => '24',
                'cantidad_indicada' => $requestedQuantity,
                'es_sos' => 0,
            ]],
        ])->assertRedirect(route('dispensations.index'))
            ->assertSessionHasErrors([
                'detalles.0.cantidad' => 'Stock insuficiente para la dispensación.',
            ]);

        $this->get(route('dispensations.index'))
            ->assertOk()
            ->assertSee('notification-toast-error', false)
            ->assertSee('data-notification-persistent="true"', false)
            ->assertSee('Dispensación no realizada.')
            ->assertSee('Stock insuficiente para la dispensación.')
            ->assertDontSee('Stock insuficiente: faltan')
            ->assertDontSee('Stock insuficiente en vitrina: hay')
            ->assertDontSee('Pendiente según la receta:')
            ->assertDontSee('La transcripción coincide con la receta seleccionada.')
            ->assertDontSee('class="flash flash-error"', false);

        $this->assertDatabaseCount('entregas', 0);
        $stockAfter = DB::table('inventario')
            ->join('lotes', 'lotes.lote_id', '=', 'inventario.lote_id')
            ->where('lotes.producto_id', $productId)
            ->where('inventario.ubicacion_id', DB::table('ubicaciones')->where('tipo', 'VITRINA')->value('ubicacion_id'))
            ->sum('inventario.cantidad');
        $this->assertEquals($stockBefore, $stockAfter);
    }

    public function test_dispensing_requires_a_valid_prescription(): void
    {
        $patientId = DB::table('pacientes')->where('rut', '123456785')->value('rut');
        $productId = DB::table('productos')->where('numero_serie', 'MED-001')->value('producto_id');

        $this->from(route('dispensations.index'))->post(route('dispensations.store'), [
            'paciente_rut' => $patientId,
            'producto_id' => $productId,
            'cantidad' => 1,
        ])->assertRedirect(route('dispensations.index'))->assertSessionHasErrors('receta_id');

        $this->assertDatabaseCount('entregas', 0);
    }

    public function test_sos_dispensing_can_be_recorded_without_a_prescription(): void
    {
        $patientId = DB::table('pacientes')->where('rut', '123456785')->value('rut');
        $productId = DB::table('productos')->where('numero_serie', 'MED-001')->value('producto_id');
        $locationId = DB::table('ubicaciones')->where('tipo', 'VITRINA')->value('ubicacion_id');
        $this->get(route('dispensations.index'))
            ->assertOk()
            ->assertSee('data-toggle-sos-treatment', false)
            ->assertSee('id="receta_id" name="receta_id" data-selected-recipe="" required disabled', false)
            ->assertSee('id="persona_autorizada_id" name="persona_autorizada_id" disabled', false)
            ->assertSee('data-toggle-authorized-person aria-controls="new-authorized-person" aria-expanded="false" disabled', false);
        $lotId = DB::table('lotes')->insertGetId([
            'producto_id' => $productId,
            'numero_lote' => 'SOS-'.uniqid(),
            'fecha_vencimiento' => now()->addYear()->toDateString(),
        ]);
        DB::table('inventario')->insert([
            'lote_id' => $lotId,
            'ubicacion_id' => $locationId,
            'cantidad' => 5,
        ]);

        $this->post(route('dispensations.store'), [
            'paciente_rut' => $patientId,
            'tratamiento_sos' => 1,
            'transcripcion_manual' => 1,
            'detalles' => [[
                'producto_id' => $productId,
                'cantidad' => 2,
            ]],
        ])->assertRedirectContains(route('dispensations.index'))
            ->assertRedirectContains('comprobante=');

        $delivery = DB::table('entregas')->first();
        $this->assertNotNull($delivery);
        $this->assertNull($delivery->receta_id);
        $this->assertTrue((bool) $delivery->es_sos);
        $this->assertDatabaseHas('entrega_detalle', [
            'entrega_id' => $delivery->entrega_id,
            'producto_id' => $productId,
            'cantidad' => 2,
            'dosis' => null,
            'horario' => 'SOS',
            'indicaciones' => 'Tratamiento SOS',
            'cantidad_indicada_snapshot' => null,
            'duracion_dias_snapshot' => null,
        ]);

        $orderId = DB::table('ordenes_entrega')->where('entrega_id', $delivery->entrega_id)->value('orden_entrega_id');
        $this->get(route('dispensations.index', ['comprobante' => $orderId]))
            ->assertOk()
            ->assertSee('<strong>SOS</strong>', false)
            ->assertSee('Dispensación de emergencia SOS');
        $this->get(route('delivery-orders.receipt', $orderId))
            ->assertOk()
            ->assertSee('Comprobante de Tratamiento SOS')
            ->assertSee('TRATAMIENTO SOS')
            ->assertSee('<strong>SOS</strong>', false)
            ->assertSee('Paracetamol 500 mg');
    }

    public function test_dispensing_is_blocked_when_the_patient_has_no_quota_left(): void
    {
        $patientId = DB::table('pacientes')->where('rut', '123456785')->value('rut');
        $productId = DB::table('productos')->where('numero_serie', 'MED-001')->value('producto_id');
        $recipeId = $this->createPrescription($patientId, $productId, 1);
        $programId = DB::table('programas_salud')->value('programa_id');
        $enrollmentId = DB::table('paciente_programa')->insertGetId([
            'paciente_rut' => $patientId,
            'programa_id' => $programId,
            'fecha_inicio' => now()->subMonth()->toDateString(),
            'fecha_termino' => now()->addMonth()->toDateString(),
            'cobertura' => 'Tratamiento mensual',
            'activo' => true,
        ]);
        DB::table('cuotas_paciente')->insert([
            'paciente_programa_id' => $enrollmentId,
            'producto_id' => $productId,
            'periodo_inicio' => now()->startOfMonth()->toDateString(),
            'periodo_termino' => now()->endOfMonth()->toDateString(),
            'cantidad_autorizada' => 2,
            'cantidad_utilizada' => 2,
        ]);

        $this->from(route('dispensations.index'))->post(route('dispensations.store'), [
            'paciente_rut' => $patientId,
            'producto_id' => $productId,
            'receta_id' => $recipeId,
            'cantidad' => 1,
        ])->assertRedirect(route('dispensations.index'))->assertSessionHasErrors('detalles.0.cantidad');

        $this->assertDatabaseCount('entregas', 0);
    }

    public function test_transfer_rejects_more_stock_than_available(): void
    {
        $lotId = DB::table('lotes')->where('numero_lote', 'L250821')->value('lote_id');
        $warehouseId = DB::table('ubicaciones')->where('tipo', 'BODEGA')->value('ubicacion_id');
        $displayId = DB::table('ubicaciones')->where('tipo', 'VITRINA')->value('ubicacion_id');

        $this->from(route('inventory.index'))->post(route('inventory.transfer'), [
            'lote_id' => $lotId,
            'origen_id' => $warehouseId,
            'destino_id' => $displayId,
            'cantidad' => 5000,
        ])->assertRedirect(route('inventory.index'))->assertSessionHasErrors('cantidad');
    }

    // Impide que el servicio registre un traspaso entre una ubicación y sí misma.
    public function test_inventory_operation_service_rejects_self_transfers(): void
    {
        $lotId = DB::table('lotes')->where('numero_lote', 'L250821')->value('lote_id');
        $locationId = DB::table('ubicaciones')->where('tipo', 'BODEGA')->value('ubicacion_id');

        $this->expectException(ValidationException::class);
        app(InventoryOperationService::class)->transfer([
            'lote_id' => $lotId,
            'origen_id' => $locationId,
            'destino_id' => $locationId,
            'cantidad' => 1,
            'observacion' => 'No debe registrarse',
        ], (string) Auth::id());
    }

    public function test_technician_is_denied_administration_and_the_attempt_is_audited(): void
    {
        $technicianId = '101111111';
        DB::table('usuarios')->insert([
            'rut' => $technicianId,
            'nombre' => 'Técnico',
            'apellidoPaterno' => 'De Prueba',
            'apellidoMaterno' => 'Técnico',
            'password_hash' => bcrypt('UnaClaveSegura!2026'),
            'activo' => true,
        ]);
        $roleId = DB::table('roles')->where('nombre', 'Técnico Farmacéutico')->value('rol_id');
        DB::table('usuario_rol')->insert(['usuario_rut' => $technicianId, 'rol_id' => $roleId]);

        $this->actingAs(Usuario::findOrFail($technicianId))
            ->get(route('modules.show', 'administracion'))
            ->assertForbidden()
            ->assertSee('ACCESO NO AUTORIZADO');

        $this->assertDatabaseHas('auditoria', [
            'usuario_rut' => $technicianId,
            'accion' => 'ACCESO_DENEGADO',
            'resultado' => 'RECHAZADO',
        ]);
    }

    private function createPrescription(int $patientId, int $productId, int $quantity): int
    {
        $prescriptionId = DB::table('recetas')->insertGetId([
            'paciente_rut' => $patientId,
            'profesional_rut' => DB::table('personal_salud')->value('rut'),
            'numero_receta' => 'REC-DELIVERY-'.uniqid(),
            'fecha_emision' => now()->toDateString(),
            'fecha_vencimiento' => now()->addMonth()->toDateString(),
            'tipo_receta' => 'ESTANDAR',
            'estado' => 'VIGENTE',
        ]);
        DB::table('receta_detalle')->insert([
            'receta_id' => $prescriptionId,
            'producto_id' => $productId,
            'cantidad_indicada' => $quantity,
        ]);

        return $prescriptionId;
    }

    /**
     * @param  array<int, array{0: string, 1: int, 2: string, 3: string}>  $lots
     * @return array<int, int>
     */
    private function setProductLocationStock(int $productId, int $locationId, int $minimum, array $lots): array
    {
        $existingLotIds = DB::table('lotes')->where('producto_id', $productId)->pluck('lote_id');
        DB::table('inventario')
            ->where('ubicacion_id', $locationId)
            ->whereIn('lote_id', $existingLotIds)
            ->update(['cantidad' => 0]);
        DB::table('stock_minimos_ubicacion')->updateOrInsert(
            ['producto_id' => $productId, 'ubicacion_id' => $locationId],
            ['stock_minimo' => $minimum]
        );

        $createdLotIds = [];
        foreach ($lots as [$lotNumber, $quantity, $expiry, $status]) {
            $lotId = DB::table('lotes')->insertGetId([
                'producto_id' => $productId,
                'numero_lote' => $lotNumber,
                'fecha_vencimiento' => $expiry,
                'estado' => $status,
            ]);
            $createdLotIds[] = $lotId;
            if ($quantity > 0) {
                DB::table('inventario')->insert([
                    'lote_id' => $lotId,
                    'ubicacion_id' => $locationId,
                    'cantidad' => $quantity,
                ]);
            }
        }

        return $createdLotIds;
    }
}
