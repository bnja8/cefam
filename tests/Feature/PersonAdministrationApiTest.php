<?php

namespace Tests\Feature;

use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PersonAdministrationApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.administrator_initial_password' => 'Module-migration-test-secret-2026']);
        $this->seed();
    }

    public function test_module_json_endpoints_require_authentication_and_return_json(): void
    {
        foreach (['patients', 'professionals', 'authorized-people', 'programs', 'users'] as $module) {
            $this->getJson("/api/v1/{$module}")
                ->assertUnauthorized()
                ->assertJsonStructure(['message']);
        }

        $this->actingAs(Usuario::query()->findOrFail('12345674'));

        foreach (['patients', 'professionals', 'authorized-people', 'programs', 'users'] as $module) {
            $this->getJson("/api/v1/{$module}")
                ->assertOk()
                ->assertJsonStructure(['data', 'meta']);
        }
    }

    public function test_personnel_program_and_user_crud_uses_json_and_keeps_user_credentials_private(): void
    {
        $this->actingAs(Usuario::query()->findOrFail('12345674'));

        $patient = $this->postJson('/api/v1/patients', [
            'rut' => '22.222.222-2',
            'nombre' => 'Paciente API',
            'apellidoPaterno' => 'Prueba',
            'apellidoMaterno' => 'CESFAM',
            'fecha_nacimiento' => '1990-01-01',
            'telefono' => '912345678',
        ])->assertCreated()
            ->assertJsonPath('data.rut', '222222222')
            ->assertJsonPath('data.telefono', '+56 9 12345678')
            ->json('data');

        $this->putJson('/api/v1/patients/222222222', $patient + ['activo' => false])
            ->assertOk()
            ->assertJsonPath('data.activo', false);
        $this->getJson('/api/v1/patients/222222222')->assertOk()->assertJsonPath('data.rut', '222222222');

        $professional = $this->postJson('/api/v1/professionals', [
            'rut' => '22.222.222-2',
            'nombre' => 'Profesional API',
            'apellidoPaterno' => 'Prueba',
            'apellidoMaterno' => 'CESFAM',
            'tipo_profesional' => 'Enfermera',
        ])->assertCreated()->json('data');
        $this->assertArrayNotHasKey('registro_profesional', $professional);

        $this->putJson('/api/v1/professionals/222222222', $professional + ['activo' => false])
            ->assertOk();
        $this->getJson('/api/v1/professionals/222222222')->assertOk()->assertJsonPath('data.rut', '222222222');
        $this->patchJson('/api/v1/professionals/222222222/status')
            ->assertOk()
            ->assertJsonPath('data.activo', true);

        $authorizedPerson = $this->postJson('/api/v1/authorized-people', [
            'paciente_rut' => '123456785',
            'rut' => '11.111.111-1',
            'nombre' => 'Persona API',
            'apellidoPaterno' => 'Prueba',
            'apellidoMaterno' => 'CESFAM',
            'vinculo' => 'Cuidador',
        ])->assertCreated()->json('data');

        $this->putJson('/api/v1/authorized-people/'.$authorizedPerson['persona_autorizada_id'], $authorizedPerson + ['activo' => false])
            ->assertOk();
        $this->getJson('/api/v1/authorized-people/'.$authorizedPerson['persona_autorizada_id'])
            ->assertOk()
            ->assertJsonPath('data.persona_autorizada_id', $authorizedPerson['persona_autorizada_id']);
        $this->patchJson('/api/v1/authorized-people/'.$authorizedPerson['persona_autorizada_id'].'/status')
            ->assertOk()
            ->assertJsonPath('data.activo', true);

        $program = $this->postJson('/api/v1/programs', [
            'nombre' => 'Programa API de prueba',
            'descripcion' => 'Cobertura para pruebas de API.',
        ])->assertCreated()->json('data');

        $this->putJson('/api/v1/programs/'.$program['programa_id'], [
            'nombre' => 'Programa API actualizado',
            'descripcion' => 'Descripción actualizada.',
        ])->assertOk();
        $this->getJson('/api/v1/programs/'.$program['programa_id'])
            ->assertOk()
            ->assertJsonPath('data.programa_id', $program['programa_id']);
        $this->patchJson('/api/v1/programs/'.$program['programa_id'].'/status')
            ->assertOk()
            ->assertJsonPath('data.activo', false);

        $roleId = DB::table('roles')->where('nombre', 'Técnico Farmacéutico')->value('rol_id');
        $this->postJson('/api/v1/users', [
            'rut' => '11.111.111-1',
            'nombre' => 'Usuario API',
            'apellidoPaterno' => 'Prueba',
            'apellidoMaterno' => 'CESFAM',
            'telefono' => '912345678',
            'password' => 'Temporary-user-secret-2026',
            'rol_id' => $roleId,
        ])->assertCreated()
            ->assertJsonMissingPath('data.password_hash');

        $this->getJson('/api/v1/users/111111111')
            ->assertOk()
            ->assertJsonMissingPath('data.password_hash');
        $this->patchJson('/api/v1/users/111111111/status')
            ->assertOk()
            ->assertJsonPath('data.activo', false);

        $this->assertDatabaseHas('pacientes', ['rut' => '222222222', 'activo' => false]);
        $this->assertDatabaseHas('usuarios', ['rut' => '111111111', 'telefono' => '+56 9 12345678']);
        $this->assertDatabaseHas('personal_salud', ['rut' => '222222222', 'activo' => true]);
        $this->assertDatabaseHas('personas_autorizadas', ['persona_autorizada_id' => $authorizedPerson['persona_autorizada_id'], 'activo' => true]);
        $this->assertDatabaseHas('programas_salud', ['programa_id' => $program['programa_id'], 'nombre' => 'Programa API actualizado', 'activo' => false]);
        $this->assertDatabaseHas('usuarios', ['rut' => '111111111', 'activo' => false]);
    }

    public function test_existing_role_rules_and_validation_errors_are_preserved(): void
    {
        $user = Usuario::query()->findOrFail('12345674');
        $technicianRole = DB::table('roles')->where('nombre', 'Técnico Farmacéutico')->value('rol_id');
        DB::table('usuario_rol')->where('usuario_rut', $user->rut)->delete();
        DB::table('usuario_rol')->insert(['usuario_rut' => $user->rut, 'rol_id' => $technicianRole]);
        $this->actingAs($user);

        $this->postJson('/api/v1/patients', [])->assertForbidden();
        $this->getJson('/api/v1/users')->assertForbidden();
        $this->postJson('/api/v1/programs', [])->assertForbidden();

        $admin = Usuario::query()->findOrFail('12345674');
        DB::table('usuario_rol')->where('usuario_rut', $admin->rut)->delete();
        DB::table('usuario_rol')->insert([
            'usuario_rut' => $admin->rut,
            'rol_id' => DB::table('roles')->where('nombre', 'Administrador')->value('rol_id'),
        ]);
        $this->actingAs($admin);

        $this->postJson('/api/v1/patients', ['rut' => 'bad-rut'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['rut', 'nombre', 'apellidoPaterno', 'apellidoMaterno']);
    }

    public function test_program_affiliation_options_listing_and_enrollment_are_json(): void
    {
        $this->actingAs(Usuario::query()->findOrFail('12345674'));

        $this->getJson('/api/v1/programs/affiliations/options')
            ->assertOk()
            ->assertJsonStructure(['data', 'options' => ['programs', 'products', 'patients']]);

        $this->getJson('/api/v1/programs/affiliations')
            ->assertOk()
            ->assertJsonStructure(['data', 'meta', 'options' => ['allPrograms']]);

        $programId = DB::table('programas_salud')->where('activo', true)->value('programa_id');
        $productId = DB::table('productos')->where('numero_serie', 'MED-001')->value('producto_id');
        $start = now()->toDateString();
        $end = now()->addDay()->toDateString();

        $response = $this->postJson('/api/v1/programs/affiliations', [
            'paciente_rut' => '123456785',
            'programa_id' => $programId,
            'fecha_inicio' => $start,
            'cobertura' => 'Cobertura API',
            'detalles' => [[
                'producto_id' => $productId,
                'periodo_inicio' => $start,
                'periodo_termino' => $end,
                'cantidad_autorizada' => '5.00',
            ]],
        ])->assertCreated()
            ->assertJsonStructure(['message', 'data' => ['paciente_programa_id']]);

        $enrollmentId = $response->json('data.paciente_programa_id');
        $this->assertDatabaseHas('paciente_programa', [
            'paciente_programa_id' => $enrollmentId,
            'paciente_rut' => '123456785',
            'programa_id' => $programId,
        ]);
        $this->assertDatabaseHas('cuotas_paciente', [
            'paciente_programa_id' => $enrollmentId,
            'producto_id' => $productId,
            'cantidad_autorizada' => 5,
        ]);

        $this->getJson('/api/v1/programs/affiliations')
            ->assertOk()
            ->assertJsonPath('data.0.paciente_programa_id', $enrollmentId)
            ->assertJsonPath('data.0.estado', 'VIGENTE');
    }
}
