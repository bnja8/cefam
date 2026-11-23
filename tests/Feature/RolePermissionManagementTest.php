<?php

namespace Tests\Feature;

use App\Models\Usuario;
use App\Services\Search\GlobalSearchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class RolePermissionManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_administrators_can_manage_roles_and_locked_permissions_cannot_be_granted(): void
    {
        $admin = $this->createUserWithRole('12345674', 'Administrador');
        $this->actingAs($admin)
            ->get('/permisos')
            ->assertOk()
            ->assertSee('Roles y permisos')
            ->assertSee('Registrar rol');
        $this->actingAs($admin)
            ->get(route('roles.create'))
            ->assertOk()
            ->assertSee('Nombre del rol *')
            ->assertSee('Descripción')
            ->assertSee('Permisos del rol')
            ->assertSee('Asigna solo los permisos necesarios')
            ->assertSee('Protegido')
            ->assertSee('data-permission-confirmation', false)
            ->assertSee('Confirmar y guardar')
            ->assertSee('data-permission-form', false);
        $this->actingAs($admin)
            ->get(route('roles.create'))
            ->assertSee('No hay cambios pendientes.')
            ->assertDontSee('Permisos seleccionados')
            ->assertDontSee('data-selected-permissions', false);
        $adminRoleId = DB::table('roles')->where('nombre', 'Administrador')->value('rol_id');
        $this->actingAs($admin)->patch(route('roles.toggle', $adminRoleId))->assertForbidden();
        $this->assertDatabaseHas('roles', ['rol_id' => $adminRoleId, 'activo' => true]);

        $this->post('/roles', [
            'nombre' => 'Auxiliar',
            'descripcion' => 'Acceso acotado',
            'permissions' => ['patients.view', 'patients.create', 'users.manage'],
        ]);
        $roleId = DB::table('roles')->where('nombre', 'Auxiliar')->value('rol_id');
        $this->assertDatabaseHas('roles', ['rol_id' => $roleId, 'activo' => true]);
        $this->assertDatabaseHas('permission_role', [
            'rol_id' => $roleId,
            'permission_id' => DB::table('permissions')->where('slug', 'patients.view')->value('permission_id'),
        ]);
        $this->assertDatabaseMissing('permission_role', [
            'rol_id' => $roleId,
            'permission_id' => DB::table('permissions')->where('slug', 'users.manage')->value('permission_id'),
        ]);
        $this->get(route('roles.permissions.show', $roleId))
            ->assertOk()
            ->assertSee('Permisos de Auxiliar')
            ->assertSee('Guardar permisos')
            ->assertSee('Principio de mínimo privilegio')
            ->assertSee('Consulta')
            ->assertSee('Acción')
            ->assertSee('No hay cambios pendientes.')
            ->assertDontSee('Permisos seleccionados')
            ->assertDontSee('data-selected-permissions', false)
            ->assertSee('data-permission-toggle', false)
            ->assertSee('Volver a revisar')
            ->assertSee('data-permission-confirmation', false);

        $this->actingAs($admin)->put(route('roles.permissions.update', $roleId), [
            'permissions' => ['patients.view', 'patients.create', 'users.manage', 'permissions.manage'],
        ])->assertRedirect(route('roles.permissions.show', $roleId));

        $patientViewId = DB::table('permissions')->where('slug', 'patients.view')->value('permission_id');
        $patientCreateId = DB::table('permissions')->where('slug', 'patients.create')->value('permission_id');
        $usersManageId = DB::table('permissions')->where('slug', 'users.manage')->value('permission_id');
        $permissionsManageId = DB::table('permissions')->where('slug', 'permissions.manage')->value('permission_id');

        $this->assertDatabaseHas('permission_role', ['rol_id' => $roleId, 'permission_id' => $patientViewId]);
        $this->assertDatabaseHas('permission_role', ['rol_id' => $roleId, 'permission_id' => $patientCreateId]);
        $this->assertDatabaseMissing('permission_role', ['rol_id' => $roleId, 'permission_id' => $usersManageId]);
        $this->assertDatabaseMissing('permission_role', ['rol_id' => $roleId, 'permission_id' => $permissionsManageId]);

        $user = $this->createUserWithRole('158765438', 'Auxiliar', $roleId);
        $this->actingAs($user)->get('/pacientes')->assertOk();
        $this->actingAs($admin)->put(route('roles.permissions.update', $roleId), [
            'permissions' => ['patients.view'],
        ])->assertRedirect(route('roles.permissions.show', $roleId))
            ->assertSessionHas('status', 'Permisos actualizados correctamente.');
        $this->actingAs($admin)
            ->get(route('roles.permissions.show', $roleId))
            ->assertSee('Operación realizada con éxito.')
            ->assertSee('Permisos actualizados correctamente.')
            ->assertDontSee('Permisos del rol actualizados.');

        $this->actingAs(Usuario::query()->findOrFail($user->rut))
            ->get('/pacientes/crear')
            ->assertForbidden();

        $this->actingAs($user)->get('/permisos')->assertForbidden();
    }

    public function test_deactivating_a_role_revokes_permissions_and_prevents_new_assignments(): void
    {
        $admin = $this->createUserWithRole('12345674', 'Administrador');
        $this->actingAs($admin)->post(route('roles.store'), [
            'nombre' => 'Rol temporal',
            'descripcion' => 'Acceso temporal',
            'permissions' => ['patients.view'],
        ])->assertRedirect();
        $roleId = DB::table('roles')->where('nombre', 'Rol temporal')->value('rol_id');
        $user = $this->createUserWithRole('158765438', 'Rol temporal', $roleId);

        $this->actingAs($user)->get(route('patients.index'))->assertOk();
        $this->actingAs($admin)
            ->patch(route('roles.toggle', $roleId))
            ->assertRedirect(route('permissions.index'));
        $this->assertDatabaseHas('roles', ['rol_id' => $roleId, 'activo' => false]);
        $this->actingAs($user)->get(route('patients.index'))->assertForbidden();

        $this->actingAs($admin)
            ->get(route('users.create'))
            ->assertOk()
            ->assertDontSee('Rol temporal');
        $this->actingAs($admin)
            ->patch(route('roles.toggle', $roleId))
            ->assertRedirect(route('permissions.index'));
        $this->actingAs($user)->get(route('patients.index'))->assertOk();
    }

    public function test_account_can_be_linked_to_and_unlinked_from_staff(): void
    {
        $admin = $this->createUserWithRole('12345674', 'Administrador');
        $roleId = DB::table('roles')->insertGetId([
            'nombre' => 'Técnico Farmacéutico',
            'descripcion' => 'Operación de farmacia',
            'slug' => 'tecnico-farmaceutico',
            'permissions_initialized' => true,
        ]);
        DB::table('personal_salud')->insert([
            'rut' => '123456785',
            'nombre' => 'Camila',
            'apellidoPaterno' => 'Fuentes',
            'apellidoMaterno' => 'Soto',
            'tipo_profesional' => 'Técnico Farmacéutico',
            'activo' => true,
        ]);

        $this->actingAs($admin)->post('/usuarios', [
            'rut' => '15.876.543-8',
            'nombre' => 'Ana',
            'apellidoPaterno' => 'Pérez',
            'apellidoMaterno' => 'Rojas',
            'rol_id' => $roleId,
            'personal_rut' => '123456785',
            'password' => 'temporary-password-2026',
        ])->assertRedirect(route('users.index'));

        $this->assertDatabaseHas('personal_salud', [
            'rut' => '123456785',
            'usuario_rut' => '158765438',
        ]);

        $this->put(route('users.update', '158765438'), [
            'rut' => '158765438',
            'nombre' => 'Ana',
            'apellidoPaterno' => 'Pérez',
            'apellidoMaterno' => 'Rojas',
            'rol_id' => $roleId,
            'personal_rut' => '',
        ])->assertRedirect(route('users.index'));

        $this->assertDatabaseHas('personal_salud', [
            'rut' => '123456785',
            'usuario_rut' => null,
        ]);
    }

    public function test_view_only_permission_does_not_grant_access_to_creation_screens(): void
    {
        $roleId = DB::table('roles')->insertGetId([
            'nombre' => 'Solo lectura',
            'descripcion' => 'Consulta de dispensaciones',
            'slug' => 'solo-lectura',
            'permissions_initialized' => true,
        ]);
        $this->createUserWithRole('158765438', 'Solo lectura', $roleId);
        $viewPermissionId = DB::table('permissions')->where('slug', 'dispensations.view')->value('permission_id');
        DB::table('permission_role')->insert([
            'rol_id' => $roleId,
            'permission_id' => $viewPermissionId,
        ]);

        $user = Usuario::query()->findOrFail('158765438');
        $this->actingAs($user)->get(route('dispensations.index'))->assertForbidden();
        $this->actingAs($user)->get(route('dispensations.deliveries'))->assertOk();
    }

    public function test_sidebar_and_program_routes_follow_granted_permissions(): void
    {
        $roleId = DB::table('roles')->insertGetId([
            'nombre' => 'Consulta de programas',
            'descripcion' => 'Solo lectura de programas',
            'slug' => 'consulta-programas',
            'permissions_initialized' => true,
        ]);
        $this->createUserWithRole('158765438', 'Consulta de programas', $roleId);
        $permissionId = DB::table('permissions')->where('slug', 'programs.view')->value('permission_id');
        DB::table('permission_role')->insert([
            'rol_id' => $roleId,
            'permission_id' => $permissionId,
        ]);

        $user = Usuario::query()->findOrFail('158765438');
        $this->actingAs($user)
            ->get(route('programs.index'))
            ->assertOk()
            ->assertSee('Consultar programas')
            ->assertDontSee('Inicio / Panel de Control')
            ->assertDontSee('Pacientes inscritos')
            ->assertDontSee('Afiliar paciente');
        $this->actingAs($user)->get(route('programs.affiliations.index'))->assertForbidden();

        $affiliationsPermissionId = DB::table('permissions')->where('slug', 'programs.affiliations.view')->value('permission_id');
        DB::table('permission_role')->insert([
            'rol_id' => $roleId,
            'permission_id' => $affiliationsPermissionId,
        ]);
        $this->actingAs($user)
            ->get(route('programs.affiliations.index'))
            ->assertOk()
            ->assertSee('afiliaciones-recientes');
    }

    public function test_dashboard_sections_and_receipts_follow_granted_permissions(): void
    {
        $roleId = DB::table('roles')->insertGetId([
            'nombre' => 'Panel limitado',
            'descripcion' => 'Panel de control sin módulos operativos',
            'slug' => 'panel-limitado',
            'permissions_initialized' => true,
        ]);
        $this->createUserWithRole('158765438', 'Panel limitado', $roleId);
        $dashboardPermissionId = DB::table('permissions')->where('slug', 'dashboard.view')->value('permission_id');
        DB::table('permission_role')->insert([
            'rol_id' => $roleId,
            'permission_id' => $dashboardPermissionId,
        ]);
        $user = Usuario::query()->findOrFail('158765438');

        $this->actingAs($user)
            ->get(route('dashboard', ['comprobante' => 1]))
            ->assertOk()
            ->assertSee('Panel de Control')
            ->assertDontSee('Accesos rápidos')
            ->assertDontSee('Últimas entregas de hoy')
            ->assertDontSee('delivery-order-dialog');

        $patientViewPermissionId = DB::table('permissions')->where('slug', 'patients.view')->value('permission_id');
        DB::table('permission_role')->insert([
            'rol_id' => $roleId,
            'permission_id' => $patientViewPermissionId,
        ]);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Buscar y gestionar pacientes')
            ->assertDontSee('Consultar y registrar recetas')
            ->assertDontSee('Últimas entregas de hoy')
            ->assertDontSee('Alertas operativas')
            ->assertDontSee('Próximos vencimientos')
            ->assertDontSee('Salud del inventario')
            ->assertDontSee('Productos más dispensados');
    }

    public function test_read_permissions_do_not_expose_other_submodule_lists_or_actions(): void
    {
        $roleId = DB::table('roles')->insertGetId([
            'nombre' => 'Consulta de catálogos',
            'descripcion' => 'Solo lectura de catálogos y remesas',
            'slug' => 'consulta-catalogos',
            'permissions_initialized' => true,
        ]);
        $this->createUserWithRole('158765438', 'Consulta de catálogos', $roleId);
        $permissionIds = DB::table('permissions')
            ->whereIn('slug', [
                'inventory.view',
                'inventory.products.view',
                'inventory.catalog.view',
                'supplies.view',
            ])
            ->pluck('permission_id');
        DB::table('permission_role')->insert(
            $permissionIds->map(static fn (int $permissionId): array => [
                'rol_id' => $roleId,
                'permission_id' => $permissionId,
            ])->all()
        );
        $user = Usuario::query()->findOrFail('158765438');

        $this->actingAs($user)
            ->get(route('inventory.catalog'))
            ->assertOk()
            ->assertSee('Productos del catálogo')
            ->assertDontSee('Nuevo producto')
            ->assertDontSee('Editar');
        $this->actingAs($user)
            ->get(route('categories.index'))
            ->assertOk()
            ->assertSee('Categorías de productos')
            ->assertDontSee('Nueva categoría')
            ->assertDontSee('Editar');
        $this->actingAs($user)
            ->get(route('locations.index'))
            ->assertOk()
            ->assertDontSee('Agregar ubicación')
            ->assertDontSee('Editar');
        $this->actingAs($user)
            ->get(route('supplies.index'))
            ->assertOk()
            ->assertDontSee('Registrar recepción');
        $this->actingAs($user)->get(route('supplies.requests.index'))->assertForbidden();
        $requestsPermissionId = DB::table('permissions')->where('slug', 'supplies.requests.view')->value('permission_id');
        DB::table('permission_role')->insert([
            'rol_id' => $roleId,
            'permission_id' => $requestsPermissionId,
        ]);
        $this->actingAs($user)
            ->get(route('supplies.requests.index'))
            ->assertOk()
            ->assertDontSee('Solicitar reposición');
        $this->actingAs($user)->get(route('supplies.requests.create'))->assertForbidden();
    }

    public function test_reports_view_and_export_do_not_grant_controlled_movements_access(): void
    {
        $roleId = DB::table('roles')->insertGetId([
            'nombre' => 'Reportes generales',
            'descripcion' => 'Reportes no controlados',
            'slug' => 'reportes-generales',
            'permissions_initialized' => true,
        ]);
        $this->createUserWithRole('158765438', 'Reportes generales', $roleId);
        $permissionIds = DB::table('permissions')
            ->whereIn('slug', ['reports.view', 'reports.export'])
            ->pluck('permission_id');
        DB::table('permission_role')->insert(
            $permissionIds->map(static fn (int $permissionId): array => [
                'rol_id' => $roleId,
                'permission_id' => $permissionId,
            ])->all()
        );
        $user = Usuario::query()->findOrFail('158765438');

        $this->actingAs($user)
            ->get(route('reports.deliveries'))
            ->assertOk()
            ->assertDontSee('Movimientos controlados')
            ->assertDontSee('Medicamentos controlados');
        $this->actingAs($user)->get(route('reports.controlled'))->assertForbidden();
        $this->actingAs($user)
            ->get(route('reports.export', ['tipo' => 'controlados']))
            ->assertForbidden();
        $this->actingAs($user)->get(route('reports.export'))->assertDownload('reporte-inventario.csv');
    }

    public function test_global_search_returns_only_results_the_user_can_open(): void
    {
        $roleId = DB::table('roles')->insertGetId([
            'nombre' => 'Solo programas',
            'descripcion' => 'Consulta limitada a programas',
            'slug' => 'solo-programas',
            'permissions_initialized' => true,
        ]);
        $this->createUserWithRole('158765438', 'Solo programas', $roleId);
        $permissionId = DB::table('permissions')->where('slug', 'programs.view')->value('permission_id');
        DB::table('permission_role')->insert([
            'rol_id' => $roleId,
            'permission_id' => $permissionId,
        ]);
        $searchResults = [
            ['type' => 'patient', 'title' => 'Test Patient', 'detail' => '123456785', 'identifier' => '123456785'],
            ['type' => 'product', 'title' => 'Test Product', 'detail' => 'MED-001', 'identifier' => 'MED-001'],
            ['type' => 'lot', 'title' => 'LOT-001 · Test Product', 'detail' => 'MED-001 · Vence 01/01/2027', 'identifier' => 'LOT-001'],
        ];
        $this->mock(GlobalSearchService::class)
            ->shouldReceive('search')
            ->with('Camila')
            ->twice()
            ->andReturn($searchResults);

        $user = Usuario::query()->findOrFail('158765438');
        $this->actingAs($user)->getJson(route('search', ['q' => 'Camila']))->assertOk()->assertExactJson([]);

        $permissionId = DB::table('permissions')->where('slug', 'patients.view')->value('permission_id');
        DB::table('permission_role')->insert([
            'rol_id' => $roleId,
            'permission_id' => $permissionId,
        ]);
        $this->actingAs($user)
            ->getJson(route('search', ['q' => 'Camila']))
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.tipo', 'Paciente')
            ->assertJsonPath('0.url', route('patients.file', '123456785'));
    }

    public function test_prescription_api_aliases_require_the_matching_recipe_permissions(): void
    {
        $roleId = DB::table('roles')->insertGetId([
            'nombre' => 'Consulta de recetas',
            'descripcion' => 'Solo lectura de recetas',
            'slug' => 'consulta-recetas',
            'permissions_initialized' => true,
        ]);
        $this->createUserWithRole('158765438', 'Consulta de recetas', $roleId);
        $permissionId = DB::table('permissions')->where('slug', 'recipes.view')->value('permission_id');
        DB::table('permission_role')->insert([
            'rol_id' => $roleId,
            'permission_id' => $permissionId,
        ]);
        $user = Usuario::query()->findOrFail('158765438');

        $this->actingAs($user)->getJson(route('api.v1.prescriptions.index'))->assertOk();
        $this->actingAs($user)->getJson(route('api.v1.prescriptions.options'))->assertForbidden();
        $this->actingAs($user)->getJson(route('api.v1.prescriptions.products', ['q' => 'am']))->assertForbidden();
        $this->actingAs($user)->postJson(route('api.v1.prescriptions.store'), [])->assertForbidden();
    }

    private function createUserWithRole(string $rut, string $roleName, ?int $roleId = null): Usuario
    {
        $roleId ??= DB::table('roles')->insertGetId([
            'nombre' => $roleName,
            'descripcion' => $roleName,
            'slug' => strtolower(str_replace(' ', '-', $roleName)),
            'permissions_initialized' => true,
        ]);

        DB::table('usuarios')->insert([
            'rut' => $rut,
            'nombre' => 'Test',
            'apellidoPaterno' => 'User',
            'apellidoMaterno' => 'Account',
            'password_hash' => Hash::make('test-password-2026'),
            'activo' => true,
        ]);
        DB::table('usuario_rol')->insert(['usuario_rut' => $rut, 'rol_id' => $roleId]);

        return Usuario::query()->findOrFail($rut);
    }
}
