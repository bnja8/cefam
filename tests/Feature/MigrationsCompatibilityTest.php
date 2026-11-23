<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Tests\TestCase;

class MigrationsCompatibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_initial_migration_creates_only_the_historical_baseline(): void
    {
        $connectionName = 'migration_baseline_test';
        $connection = config('database.connections.sqlite');
        $connection['database'] = ':memory:';
        config(["database.connections.{$connectionName}" => $connection]);

        try {
            $exitCode = Artisan::call('migrate:fresh', [
                '--database' => $connectionName,
                '--path' => array_map(
                    static fn (string $path): string => 'database'.DIRECTORY_SEPARATOR.'migrations'.DIRECTORY_SEPARATOR.basename($path),
                    glob(database_path('migrations/2026_09_08_*.php'))
                ),
                '--force' => true,
            ]);

            $this->assertSame(0, $exitCode, Artisan::output());

            $schema = Schema::connection($connectionName);
            $this->assertTrue($schema->hasTable('roles'));
            $this->assertTrue($schema->hasTable('personas_autorizadas'));
            $this->assertFalse($schema->hasTable('unidades_medida'));
            $this->assertFalse($schema->hasTable('fichas_pacientes'));
            $this->assertFalse($schema->hasView('vw_stock_producto_ubicacion'));
            $this->assertFalse($schema->hasColumn('lotes', 'estado'));
            $this->assertFalse($schema->hasColumn('alertas', 'resuelta_at'));
            $this->assertFalse($schema->hasColumn('categorias_producto', 'activo'));
            $this->assertTrue($schema->hasColumn('preparacion_unidosis_detalle', 'dosis'));
            $this->assertTrue($schema->hasColumn('profesionales_salud', 'registro_profesional'));
        } finally {
            DB::purge($connectionName);
        }
    }

    public function test_fresh_migrations_create_the_pharmacy_schema_without_sql_imports(): void
    {
        foreach ([
            'alertas',
            'auditoria',
            'entregas',
            'entrega_detalle',
            'fichas_pacientes',
            'historial_alertas',
            'inventario',
            'kardex_inventario',
            'lotes',
            'notificaciones',
            'pacientes',
            'personal_salud',
            'personas_autorizadas',
            'presentaciones_producto',
            'productos',
            'receta_detalle',
            'recetas',
            'remesas',
            'solicitudes_ajuste_inventario',
            'solicitudes_ajuste_grupos',
            'tratamientos',
            'unidades_medida',
            'usuarios',
        ] as $table) {
            $this->assertTrue(Schema::hasTable($table), "Missing migrated table: {$table}");
        }

        $this->assertTrue(Schema::hasView('vw_stock_producto_ubicacion'));
        $this->assertFalse(Schema::hasTable('profesionales_salud'));
        $this->assertTrue(Schema::hasColumn('personal_salud', 'usuario_rut'));
        $this->assertTrue(Schema::hasTable('permissions'));
        $this->assertTrue(Schema::hasColumn('solicitudes_ajuste_inventario', 'grupo_ajuste_id'));
        $this->assertDatabaseMissing('roles', ['nombre' => 'Administrador']);
        $this->assertDatabaseMissing('categorias_producto', ['nombre' => 'Medicamentos']);
        $this->assertDatabaseCount('formas_farmaceuticas', 0);
        $this->assertDatabaseCount('unidades_medida', 0);
    }

    public function test_migrations_can_run_over_the_latest_schema_without_duplicate_objects(): void
    {
        DB::table('migrations')->delete();

        $exitCode = Artisan::call('migrate', ['--force' => true]);

        $this->assertSame(0, $exitCode, Artisan::output());
        $this->assertTrue(Schema::hasTable('personas_autorizadas'));
        $this->assertTrue(Schema::hasColumn('entregas', 'paciente_nombre_snapshot'));
        $this->assertTrue(Schema::hasTable('jobs'));
    }

    public function test_legacy_staff_records_survive_the_table_rename(): void
    {
        $connectionName = 'staff_rename_test';
        $connection = config('database.connections.sqlite');
        $connection['database'] = ':memory:';
        config(["database.connections.{$connectionName}" => $connection]);

        try {
            $baselinePath = array_map(
                static fn (string $path): string => 'database'.DIRECTORY_SEPARATOR.'migrations'.DIRECTORY_SEPARATOR.basename($path),
                glob(database_path('migrations/2026_09_08_*.php'))
            );
            $this->assertSame(0, Artisan::call('migrate:fresh', [
                '--database' => $connectionName,
                '--path' => $baselinePath,
                '--force' => true,
            ]), Artisan::output());

            DB::connection($connectionName)->table('profesionales_salud')->insert([
                'rut' => '123456785',
                'nombre' => 'Camila',
                'apellidoPaterno' => 'Fuentes',
                'apellidoMaterno' => 'Soto',
                'tipo_profesional' => 'Médico Cirujano',
            ]);

            $this->assertSame(0, Artisan::call('migrate', [
                '--database' => $connectionName,
                '--force' => true,
            ]), Artisan::output());

            $this->assertDatabaseHas('personal_salud', ['rut' => '123456785', 'nombre' => 'Camila'], $connectionName);
            $this->assertFalse(Schema::connection($connectionName)->hasTable('profesionales_salud'));
        } finally {
            DB::purge($connectionName);
        }
    }

    public function test_legacy_staff_rows_merge_when_an_imported_schema_already_has_the_new_table(): void
    {
        $connectionName = 'staff_merge_test';
        $connection = config('database.connections.sqlite');
        $connection['database'] = ':memory:';
        config(["database.connections.{$connectionName}" => $connection]);

        try {
            $baselinePath = array_map(
                static fn (string $path): string => 'database'.DIRECTORY_SEPARATOR.'migrations'.DIRECTORY_SEPARATOR.basename($path),
                glob(database_path('migrations/2026_09_08_*.php'))
            );
            $this->assertSame(0, Artisan::call('migrate:fresh', [
                '--database' => $connectionName,
                '--path' => $baselinePath,
                '--force' => true,
            ]), Artisan::output());

            Schema::connection($connectionName)->create('personal_salud', function (Blueprint $table): void {
                $table->string('rut', 12)->primary();
                $table->string('nombre', 50)->nullable();
                $table->string('apellidoPaterno', 50);
                $table->string('apellidoMaterno', 50);
                $table->string('tipo_profesional', 50);
                $table->boolean('activo')->default(true);
            });
            DB::connection($connectionName)->table('personal_salud')->insert([
                'rut' => '111111111',
                'nombre' => 'Imported',
                'apellidoPaterno' => 'Staff',
                'apellidoMaterno' => 'Member',
                'tipo_profesional' => 'Médico Cirujano',
            ]);
            DB::connection($connectionName)->table('profesionales_salud')->insert([
                'rut' => '222222222',
                'nombre' => 'Legacy',
                'apellidoPaterno' => 'Staff',
                'apellidoMaterno' => 'Member',
                'tipo_profesional' => 'Enfermera',
            ]);

            $this->assertSame(0, Artisan::call('migrate', [
                '--database' => $connectionName,
                '--force' => true,
            ]), Artisan::output());

            $this->assertDatabaseHas('personal_salud', ['rut' => '111111111'], $connectionName);
            $this->assertDatabaseHas('personal_salud', ['rut' => '222222222'], $connectionName);
            $this->assertFalse(Schema::connection($connectionName)->hasTable('profesionales_salud'));
            $recipeForeignKeys = DB::connection($connectionName)->select("PRAGMA foreign_key_list('recetas')");
            $this->assertTrue(collect($recipeForeignKeys)->contains(
                static fn (object $foreignKey): bool => $foreignKey->table === 'personal_salud'
            ));
        } finally {
            DB::purge($connectionName);
        }
    }
}
