<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use App\Models\Usuario;
use Tests\TestCase;

class WebAndApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_browser_routes_render_blade_pages_and_protect_authenticated_pages(): void
    {
        $this->get('/login')->assertOk()->assertSee('Bienvenido');
        $this->from('/login')->post('/login', [
            'rut' => 'invalid',
            'password' => 'incorrecta',
        ])->assertRedirect('/login')->assertSessionHasErrors('rut');

        $this->get('/')->assertRedirect('/login');
        $this->get('/pacientes')->assertRedirect('/login');
    }

    public function test_browser_login_renders_dashboard_and_logout_returns_to_login(): void
    {
        $password = 'test-only-admin-secret-2026';
        config(['app.administrator_initial_password' => $password]);
        $this->seed();

        $this->post('/login', [
            'rut' => '1.234.567-4',
            'password' => $password,
        ])->assertRedirect(route('dashboard'));

        $this->get('/')
            ->assertOk()
            ->assertSee('Panel de Control');

        $this->post('/logout')->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_api_routes_are_available_and_require_authentication(): void
    {
        $this->get('/api/v1/csrf-cookie')->assertNoContent();

        $this->get('/api/v1/user')
            ->assertUnauthorized()
            ->assertJsonStructure(['message']);
    }

    public function test_pharmacy_module_api_routes_require_authentication(): void
    {
        foreach ([
            '/api/v1/prescriptions',
            '/api/v1/prescriptions/options',
            '/api/v1/dispensations',
            '/api/v1/dispensations/deliveries',
            '/api/v1/carts/stock',
            '/api/v1/carts/usage',
            '/api/v1/carts/preparations',
            '/api/v1/controlled-ledger',
            '/api/v1/controlled-ledger/options',
            '/api/v1/delivery-orders/1',
            '/api/v1/delivery-orders/1/details/1/label',
        ] as $path) {
            $this->getJson($path)->assertUnauthorized()->assertJsonStructure(['message']);
        }

        foreach ([
            '/api/v1/prescriptions',
            '/api/v1/dispensations',
            '/api/v1/carts/paro/dispense',
            '/api/v1/carts/unidosis',
            '/api/v1/controlled-ledger',
            '/api/v1/controlled-ledger/reconcile',
        ] as $path) {
            $this->postJson($path, [])->assertUnauthorized()->assertJsonStructure(['message']);
        }
    }

    public function test_api_can_authenticate_and_fetch_dashboard_json_with_existing_roles(): void
    {
        $password = 'test-only-admin-secret-2026';
        config(['app.administrator_initial_password' => $password]);
        $this->seed();

        $this->postJson('/api/v1/login', [
            'rut' => '1.234.567-4',
            'password' => $password,
        ])->assertOk()
            ->assertJsonPath('data.rut', '12345674')
            ->assertJsonPath('data.roles.0', 'Administrador');

        $this->getJson('/api/v1/dashboard')
            ->assertOk()
            ->assertJsonStructure(['data' => ['stockCount', 'dispensationActivity', 'stockByCategory']]);

        $this->getJson('/api/v1/prescriptions')
            ->assertOk()
            ->assertJsonStructure(['data' => ['prescriptions' => ['data']]]);
        $this->get('/api/v1/prescriptions')
            ->assertOk()
            ->assertHeader('content-type', 'application/json');
        $this->getJson('/api/v1/dispensations/deliveries')
            ->assertOk()
            ->assertJsonStructure(['data' => ['deliveries' => ['data']]]);
        $this->getJson('/api/v1/carts/stock')
            ->assertOk()
            ->assertJsonStructure(['data' => ['stock' => ['data']]]);
        $this->getJson('/api/v1/controlled-ledger')
            ->assertForbidden();

        $this->postJson('/api/v1/logout')
            ->assertOk()
            ->assertJsonPath('data.message', 'Sesión cerrada');

        $this->getJson('/api/v1/user')->assertUnauthorized();
    }

    public function test_api_login_throttles_repeated_attempts(): void
    {
        $credentials = ['rut' => '1.234.567-4', 'password' => 'incorrecta'];

        for ($attempt = 0; $attempt < 10; $attempt++) {
            $this->postJson('/api/v1/login', $credentials)->assertUnprocessable();
        }

        $this->postJson('/api/v1/login', $credentials)->assertTooManyRequests();
    }

    public function test_alert_report_search_and_notification_endpoints_return_json_for_authenticated_users(): void
    {
        config(['app.administrator_initial_password' => 'test-only-admin-secret-2026']);
        $this->seed();
        $this->actingAs(Usuario::query()->findOrFail('12345674'));

        $this->getJson('/api/v1/alerts/map')
            ->assertOk()
            ->assertJsonStructure(['data' => ['alerts' => ['data', 'current_page', 'last_page', 'total'], 'locations', 'alertSummary']]);

        $this->getJson('/api/v1/reports/deliveries')
            ->assertOk()
            ->assertJsonPath('data.screen', 'deliveries')
            ->assertJsonStructure(['data' => ['deliveries' => ['data', 'current_page', 'total']]]);

        $this->getJson('/api/v1/reports/movements?desde=2026-01-01')
            ->assertOk()
            ->assertJsonPath('data.screen', 'movements')
            ->assertJsonStructure(['data' => ['movements' => ['data', 'current_page', 'total']]]);

        $this->getJson('/api/v1/search?q=am')
            ->assertOk()
            ->assertJsonIsArray();

        DB::table('notificaciones')->insert([
            'usuario_rut' => '12345674',
            'tipo' => 'ALERTA_REASIGNADA',
            'titulo' => 'Nueva asignación',
            'mensaje' => 'Tienes una alerta asignada.',
            'accion_texto' => 'Ver alerta',
            'accion_url' => '/alertas/1',
            'created_at' => now(),
        ]);

        $notificationId = (int) DB::table('notificaciones')->value('notificacion_id');
        $this->getJson('/api/v1/notifications')
            ->assertOk()
            ->assertJsonPath('data.total', 1)
            ->assertJsonPath('data.unread', 1);

        $this->postJson("/api/v1/notifications/{$notificationId}/open")
            ->assertOk()
            ->assertJsonPath('data.action_url', '/alertas/1');
        $this->assertDatabaseHas('notificaciones', [
            'notificacion_id' => $notificationId,
            'usuario_rut' => '12345674',
        ]);
        $this->assertNotNull(DB::table('notificaciones')->where('notificacion_id', $notificationId)->value('leida_at'));
    }

    public function test_reports_api_keeps_role_restrictions_and_alert_history_is_forbidden_to_technicians(): void
    {
        config(['app.administrator_initial_password' => 'test-only-admin-secret-2026']);
        $this->seed();
        $technician = Usuario::query()->findOrFail('12345674');
        $technicianRole = DB::table('roles')->where('nombre', 'Técnico Farmacéutico')->value('rol_id');
        DB::table('usuario_rol')->where('usuario_rut', $technician->rut)->delete();
        DB::table('usuario_rol')->insert(['usuario_rut' => $technician->rut, 'rol_id' => $technicianRole]);
        $this->actingAs($technician);

        $this->getJson('/api/v1/reports/deliveries')->assertForbidden();
        $this->getJson('/api/v1/alerts/history')->assertForbidden();
    }
}
