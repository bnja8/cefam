<?php

namespace Tests\Feature;

use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ModuleApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.administrator_initial_password' => 'Module-api-test-secret-2026']);
        $this->seed();
    }

    public function test_inventory_and_supply_json_routes_require_authentication(): void
    {
        $this->getJson('/api/v1/inventory')->assertUnauthorized();
        $this->getJson('/api/v1/supplies/shipments')->assertUnauthorized();
    }

    public function test_inventory_transfer_options_remain_role_restricted(): void
    {
        $user = Usuario::where('rut', '12345674')->firstOrFail();
        $this->actingAs($user);
        $technicianRoleId = DB::table('roles')->where('nombre', 'Técnico Farmacéutico')->value('rol_id');
        DB::table('usuario_rol')->where('usuario_rut', $user->getAuthIdentifier())->update(['rol_id' => $technicianRoleId]);

        $this->getJson('/api/v1/inventory/options')
            ->assertOk()
            ->assertJsonPath('puede_traspasar', false)
            ->assertJsonCount(0, 'traspasos');
    }

    public function test_inventory_list_and_product_detail_are_json(): void
    {
        $this->actingAs(Usuario::where('rut', '12345674')->firstOrFail());
        $productId = DB::table('productos')->where('numero_serie', 'MED-001')->value('producto_id');

        $this->getJson('/api/v1/inventory?buscar=Paracetamol')
            ->assertOk()
            ->assertJsonStructure(['data', 'current_page', 'last_page'])
            ->assertJsonPath('data.0.medicamento_id', (int) $productId);

        $this->getJson("/api/v1/inventory/{$productId}")
            ->assertOk()
            ->assertJsonStructure(['data' => ['producto', 'lotes', 'stock_por_ubicacion', 'stock_disponible']])
            ->assertJsonPath('data.producto.producto_id', $productId);
    }

    public function test_prescription_can_register_a_treatment_for_the_same_patient_atomically(): void
    {
        $this->actingAs(Usuario::where('rut', '12345674')->firstOrFail());
        $patientRut = DB::table('pacientes')->where('rut', '123456785')->value('rut');
        $professionalRut = DB::table('profesionales_salud')->value('rut');
        $productId = DB::table('productos')->where('numero_serie', 'MED-001')->value('producto_id');
        $startDate = now()->toDateString();
        $endDate = now()->addMonth()->toDateString();

        $response = $this->postJson('/api/v1/prescriptions', [
            'paciente_rut' => $patientRut,
            'profesional_rut' => $professionalRut,
            'numero_receta' => 'REC-TREATMENT-API-001',
            'fecha_emision' => $startDate,
            'fecha_vencimiento' => $endDate,
            'tipo_receta' => 'ESTANDAR',
            'indicar_tratamiento' => true,
            'tratamiento' => [
                'descripcion' => 'Tratamiento indicado desde la receta',
            ],
            'detalles' => [[
                'producto_id' => $productId,
                'cantidad_indicada' => 14,
                'unidades_por_toma' => 1,
                'duracion_dias' => 7,
                'frecuencia' => 'Cada 12 horas',
            ]],
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.message', 'Receta registrada y tratamiento vinculado al paciente.')
            ->assertJsonStructure(['data' => ['receta_id', 'tratamiento_id']]);
        $this->assertDatabaseHas('tratamientos', [
            'tratamiento_id' => $response->json('data.tratamiento_id'),
            'paciente_rut' => $patientRut,
            'descripcion' => 'Tratamiento indicado desde la receta',
            'fecha_inicio' => $startDate,
            'fecha_termino' => $endDate,
        ]);
    }

    public function test_inventory_catalog_product_edit_categories_and_locations_use_json(): void
    {
        $this->actingAs(Usuario::where('rut', '12345674')->firstOrFail());
        $productId = DB::table('productos')->where('numero_serie', 'MED-001')->value('producto_id');

        $this->getJson('/api/v1/inventory/catalog')
            ->assertOk()
            ->assertJsonStructure(['data' => ['data']]);
        $this->getJson('/api/v1/inventory/catalog/options')
            ->assertOk()
            ->assertJsonStructure(['data' => ['categorias', 'dosageForms', 'measurementUnits']]);
        $this->getJson("/api/v1/inventory/catalog/{$productId}")
            ->assertOk()
            ->assertJsonStructure(['data' => ['product', 'categories', 'ingredients', 'presentations']]);
        $catalogCategoryId = DB::table('categorias_producto')->value('categoria_id');
        $baseUnitId = DB::table('unidades_medida')->where('codigo', 'unidad')->value('unidad_medida_id');
        $this->postJson('/api/v1/inventory/catalog', [
            'codigo_producto' => 'API-CATALOG-ITEM',
            'nombre' => 'Insumo de catálogo API',
            'categoria_id' => $catalogCategoryId,
            'tipo_producto' => 'INSUMO_CLINICO',
            'unidad_base_inventario_id' => $baseUnitId,
            'presentaciones' => [],
        ])->assertCreated()
            ->assertJsonStructure(['message', 'producto_id']);
        $this->assertDatabaseHas('productos', ['numero_serie' => 'API-CATALOG-ITEM']);

        $categoryResponse = $this->postJson('/api/v1/inventory/categories', [
            'nombre_categoria' => 'Categoría API',
            'descripcion_categoria' => 'Prueba de catálogo',
        ])->assertCreated();
        $categoryId = $categoryResponse->json('categoria_id');
        $this->getJson('/api/v1/inventory/categories')->assertOk()->assertJsonStructure(['data' => ['data']]);
        $this->getJson("/api/v1/inventory/categories/{$categoryId}")
            ->assertOk()
            ->assertJsonPath('data.nombre', 'Categoría API');
        $this->putJson("/api/v1/inventory/categories/{$categoryId}", [
            'nombre_categoria' => 'Categoría API editada',
            'descripcion_categoria' => 'Actualizada',
        ])->assertOk();

        $locationResponse = $this->postJson('/api/v1/inventory/locations', [
            'nombre' => 'Ubicación API',
            'tipo' => 'BODEGA',
            'descripcion' => 'Prueba de ubicaciones',
        ])->assertCreated();
        $locationId = $locationResponse->json('ubicacion_id');
        $this->getJson('/api/v1/inventory/locations')->assertOk()->assertJsonStructure(['data' => ['data'], 'types']);
        $this->getJson("/api/v1/inventory/locations/{$locationId}")
            ->assertOk()
            ->assertJsonPath('data.typeLocked', false);
        $this->putJson("/api/v1/inventory/locations/{$locationId}", [
            'nombre' => 'Ubicación API editada',
            'tipo' => 'BODEGA',
            'descripcion' => 'Actualizada',
        ])->assertOk();
    }

    public function test_supply_list_and_request_operation_return_json(): void
    {
        $this->actingAs(Usuario::where('rut', '12345674')->firstOrFail());
        $productId = DB::table('productos')->where('numero_serie', 'MED-002')->value('producto_id');

        $this->getJson('/api/v1/supplies/shipments')->assertOk()->assertJsonStructure(['data' => ['data']]);
        $this->getJson('/api/v1/supplies/requests')->assertOk()->assertJsonStructure(['data' => ['data']]);

        $response = $this->postJson('/api/v1/supplies/requests', [
            'detalles' => [['producto_id' => $productId, 'cantidad_solicitada' => 6]],
        ])->assertCreated()
            ->assertJsonStructure(['message', 'solicitud_id']);
        $requestId = $response->json('solicitud_id');

        $this->getJson("/api/v1/supplies/requests/{$requestId}")
            ->assertOk()
            ->assertJsonStructure(['data' => ['solicitud', 'detalles']])
            ->assertJsonPath('data.detalles.0.producto_id', $productId);

        $this->assertDatabaseHas('solicitud_reposicion_detalle', [
            'producto_id' => $productId,
            'cantidad_solicitada' => 6,
        ]);
    }

    public function test_inventory_adjustment_operation_uses_server_validation_and_returns_json(): void
    {
        $this->actingAs(Usuario::where('rut', '12345674')->firstOrFail());
        $inventory = DB::table('inventario')->first();

        $this->postJson('/api/v1/inventory/adjustments', [
            'lote_id' => $inventory->lote_id,
            'ubicacion_id' => $inventory->ubicacion_id,
            'tipo_ajuste' => 'ENTRADA',
            'cantidad' => 2,
            'motivo' => 'Conteo de prueba',
        ])->assertCreated()
            ->assertJsonStructure(['message', 'solicitud_ajuste_id']);

        $this->postJson('/api/v1/inventory/adjustments', [
            'lote_id' => $inventory->lote_id,
            'ubicacion_id' => $inventory->ubicacion_id,
            'tipo_ajuste' => 'INVALIDO',
            'cantidad' => 0,
            'motivo' => '',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['tipo_ajuste', 'cantidad', 'motivo']);
    }

    public function test_inventory_receipt_operation_returns_json_and_updates_stock(): void
    {
        $this->actingAs(Usuario::where('rut', '12345674')->firstOrFail());
        $productId = DB::table('productos')->where('numero_serie', 'MED-002')->value('producto_id');
        $locationId = DB::table('ubicaciones')->value('ubicacion_id');
        $lotNumber = 'API-RECEIPT-'.uniqid();

        $this->postJson('/api/v1/inventory/receipts', [
            'producto_id' => $productId,
            'ubicacion_id' => $locationId,
            'numero_lote' => $lotNumber,
            'fecha_fabricacion' => now()->subMonth()->toDateString(),
            'fecha_vencimiento' => now()->addYear()->toDateString(),
            'cantidad' => 4,
            'origen' => 'Proveedor de prueba',
            'documento_recepcion' => 'API-TEST',
        ])->assertCreated()
            ->assertJsonPath('message', 'Recepción registrada.');

        $lotId = DB::table('lotes')->where('numero_lote', $lotNumber)->value('lote_id');
        $this->assertDatabaseHas('inventario', [
            'lote_id' => $lotId,
            'ubicacion_id' => $locationId,
            'cantidad' => 4,
        ]);
    }
}
