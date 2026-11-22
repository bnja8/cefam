<?php
// Actualiza el esquema de datos: 2026 09 08 000000 create farmacia schema.


use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const OWNERSHIP_TABLE = 'farmacia_migration_ownership';

    // Aplica esta migración al esquema de la base de datos.
    public function up(): void
    {
        $schemaPath = database_path('schema/database_1.1.sql');
        $schema = file_get_contents($schemaPath);
        if ($schema === false) {
            throw new RuntimeException('No se pudo leer database/schema/database_1.1.sql.');
        }

        preg_match_all('/CREATE TABLE\s+([a-z_]+)/i', $schema, $matches);
        $schemaTables = $matches[1];
        if ($schemaTables === []) {
            throw new RuntimeException('database/schema/database_1.1.sql no contiene tablas para crear.');
        }

        $existingTables = array_values(array_filter(
            $schemaTables,
            fn (string $table) => Schema::hasTable($table)
        ));

        if (count($existingTables) === count($schemaTables)) {
            return;
        }

        if ($existingTables !== []) {
            throw new RuntimeException(
                'La base contiene solo parte de database/schema/database_1.1.sql. Completa la importación del esquema antes de ejecutar las migraciones.'
            );
        }

        if (DB::getDriverName() === 'sqlite') {
            $schema = preg_replace('/CREATE DATABASE IF NOT EXISTS.*?;\s*/is', '', $schema);
            $schema = preg_replace('/^\s*USE\s+[^;]+;\s*$/im', '', $schema);
            $schema = preg_replace('/^\s*SET NAMES\s+[^;]+;\s*$/im', '', $schema);
            $schema = preg_replace(
                '/BIGINT\s+NOT\s+NULL\s+AUTO_INCREMENT\s+PRIMARY\s+KEY/i',
                'INTEGER PRIMARY KEY AUTOINCREMENT',
                $schema
            );
            $schema = preg_replace('/UNIQUE\s+KEY\s+[a-z0-9_]+\s*\(([^)]+)\)/i', 'UNIQUE ($1)', $schema);
            $schema = preg_replace('/\)\s+ENGINE=InnoDB\s+DEFAULT\s+CHARSET=utf8mb4\s+COLLATE=utf8mb4_[a-z0-9_]+;/i', ');', $schema);
            $schema = preg_replace('/CREATE\s+OR\s+REPLACE\s+VIEW/i', 'CREATE VIEW', $schema);
            $schema = preg_replace('/\bCURDATE\(\)/i', "date('now')", $schema);
        }

        DB::unprepared($schema);

        Schema::create(self::OWNERSHIP_TABLE, /* Callback que completa un paso de la operación actual. */ function (Blueprint $table) {
            $table->string('migration')->primary();
        });
        DB::table(self::OWNERSHIP_TABLE)->insert([
            'migration' => '2026_09_08_000000_create_farmacia_schema',
        ]);
    }

    // Revierte los cambios de esta migración.
    public function down(): void
    {
        $migration = '2026_09_08_000000_create_farmacia_schema';
        if (! Schema::hasTable(self::OWNERSHIP_TABLE)
            || ! DB::table(self::OWNERSHIP_TABLE)->where('migration', $migration)->exists()) {
            return;
        }

        Schema::disableForeignKeyConstraints();

        try {
            foreach ([
                'alertas',
                'auditoria',
                'movimientos_controlados',
                'rotulos_unidosis',
                'preparacion_unidosis_detalle',
                'preparaciones_unidosis',
                'tratamientos',
                'carros',
                'ordenes_entrega',
                'entrega_detalle',
                'entregas',
                'cuotas_paciente',
                'personas_autorizadas',
                'receta_detalle',
                'recetas',
                'traspaso_detalle',
                'traspasos_inventario',
                'movimientos_inventario',
                'remesa_detalle',
                'remesas',
                'solicitud_reposicion_detalle',
                'solicitudes_reposicion',
                'stock_minimos_ubicacion',
                'inventario',
                'lotes',
                'ubicaciones',
                'productos_codigo_barra',
                'productos',
                'categorias_producto',
                'paciente_programa',
                'programas_salud',
                'profesionales_salud',
                'pacientes',
                'usuario_rol',
                'usuarios',
                'roles',
            ] as $table) {
                Schema::dropIfExists($table);
            }
        } finally {
            Schema::enableForeignKeyConstraints();
        }

        DB::table(self::OWNERSHIP_TABLE)->where('migration', $migration)->delete();
        if (DB::table(self::OWNERSHIP_TABLE)->doesntExist()) {
            Schema::drop(self::OWNERSHIP_TABLE);
        }
    }
};
