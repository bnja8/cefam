<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
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
            'personas_autorizadas',
            'presentaciones_producto',
            'productos',
            'receta_detalle',
            'recetas',
            'remesas',
            'solicitudes_ajuste_inventario',
            'tratamientos',
            'unidades_medida',
            'usuarios',
        ] as $table) {
            $this->assertTrue(Schema::hasTable($table), "Missing migrated table: {$table}");
        }

        $this->assertTrue(Schema::hasView('vw_stock_producto_ubicacion'));
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
}
