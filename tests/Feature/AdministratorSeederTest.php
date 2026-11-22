<?php

namespace Tests\Feature;

use Database\Seeders\AdministratorSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdministratorSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_initial_administrator_creation_requires_a_minimum_length_secret(): void
    {
        config(['app.administrator_initial_password' => null]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage(
            'Define ADMINISTRATOR_INITIAL_PASSWORD con al menos 16 caracteres antes de crear la cuenta administradora.',
        );

        $this->seed(AdministratorSeeder::class);
    }

    public function test_initial_administrator_secret_length_counts_characters_not_bytes(): void
    {
        config(['app.administrator_initial_password' => str_repeat('á', 8)]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage(
            'Define ADMINISTRATOR_INITIAL_PASSWORD con al menos 16 caracteres antes de crear la cuenta administradora.',
        );

        $this->seed(AdministratorSeeder::class);
    }

    public function test_new_administrator_uses_the_configured_initial_secret(): void
    {
        $password = 'test-only-initial-secret-2026';
        config(['app.administrator_initial_password' => $password]);

        $this->seed(AdministratorSeeder::class);

        $admin = DB::table('usuarios')->where('rut', '12345674')->first();

        $this->assertNotNull($admin);
        $this->assertTrue(Hash::check($password, $admin->password_hash));
    }
}
