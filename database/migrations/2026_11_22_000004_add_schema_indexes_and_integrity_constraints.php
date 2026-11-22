<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const INDEXES = [
        ['alertas', 'idx_alertas_resuelta_fecha', ['resuelta_at', 'fecha_generacion']],
        ['alertas', 'idx_alertas_resuelta_responsable_fecha', ['resuelta_at', 'responsable_rut', 'fecha_generacion']],
        ['alertas', 'idx_alertas_tipo_resuelta_atendida', ['tipo_alerta', 'resuelta_at', 'atendida']],
        ['entregas', 'idx_entregas_estado_fecha', ['estado', 'fecha_hora']],
        ['lotes', 'idx_lotes_estado_vencimiento', ['estado', 'fecha_vencimiento']],
        ['movimientos_controlados', 'idx_movimientos_controlados_fecha', ['fecha_hora']],
        ['preparaciones_unidosis', 'idx_preparaciones_unidosis_fecha_estado', ['fecha_preparacion', 'estado']],
        ['recetas', 'idx_recetas_fecha_emision', ['fecha_emision']],
        ['solicitudes_reposicion', 'idx_solicitudes_reposicion_fecha_estado', ['fecha_solicitud', 'estado']],
    ];

    private const FOREIGN_KEYS = [
        ['historial_alertas', 'historial_alertas_alerta_id_foreign', 'alerta_id', 'alertas', 'alerta_id', true],
        ['historial_alertas', 'historial_alertas_usuario_rut_foreign', 'usuario_rut', 'usuarios', 'rut', false],
        ['historial_alertas', 'historial_alertas_responsable_anterior_rut_foreign', 'responsable_anterior_rut', 'usuarios', 'rut', false],
        ['historial_alertas', 'historial_alertas_responsable_nuevo_rut_foreign', 'responsable_nuevo_rut', 'usuarios', 'rut', false],
        ['notificaciones', 'notificaciones_usuario_rut_foreign', 'usuario_rut', 'usuarios', 'rut', true],
        ['presentaciones_producto', 'presentaciones_producto_producto_id_foreign', 'producto_id', 'productos', 'producto_id', true],
    ];

    public function up(): void
    {
        if (! DB::connection()->pretending()) {
            foreach (array_unique(array_column(self::INDEXES, 0)) as $table) {
                if (! Schema::hasTable($table)) {
                    throw new RuntimeException("No existe la tabla requerida para los índices: {$table}.");
                }
            }
        }

        if (DB::getDriverName() === 'mysql') {
            $this->normalizeMysqlTables();
        }

        foreach (self::INDEXES as [$tableName, $indexName, $columns]) {
            if (! Schema::hasIndex($tableName, $indexName)) {
                Schema::table($tableName, function (Blueprint $table) use ($columns, $indexName) {
                    $table->index($columns, $indexName);
                });
            }
        }

        if (Schema::hasIndex('inventario', 'idx_inventario_lote')) {
            Schema::table('inventario', function (Blueprint $table) {
                $table->dropIndex('idx_inventario_lote');
            });
        }

        if (DB::getDriverName() === 'mysql') {
            $this->addForeignKeys();
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            foreach (self::FOREIGN_KEYS as [$tableName, $constraintName]) {
                if (Schema::hasForeignKey($tableName, $constraintName)) {
                    Schema::table($tableName, function (Blueprint $table) use ($constraintName) {
                        $table->dropForeign($constraintName);
                    });
                }
            }
        }

        foreach (self::INDEXES as [$tableName, $indexName]) {
            if (Schema::hasIndex($tableName, $indexName)) {
                Schema::table($tableName, function (Blueprint $table) use ($indexName) {
                    $table->dropIndex($indexName);
                });
            }
        }

        if (! Schema::hasIndex('inventario', 'idx_inventario_lote')) {
            Schema::table('inventario', function (Blueprint $table) {
                $table->index('lote_id', 'idx_inventario_lote');
            });
        }
    }

    private function normalizeMysqlTables(): void
    {
        foreach (['historial_alertas', 'notificaciones', 'presentaciones_producto'] as $table) {
            $engine = DB::selectOne(
                'SELECT ENGINE FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?',
                [$table]
            )?->ENGINE;

            if ($engine !== 'InnoDB') {
                DB::statement("ALTER TABLE `{$table}` ENGINE=InnoDB");
            }
        }

        foreach ([
            ['historial_alertas', 'alerta_id'],
            ['presentaciones_producto', 'producto_id'],
        ] as [$table, $column]) {
            $type = DB::selectOne(
                'SELECT COLUMN_TYPE FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?',
                [$table, $column]
            )?->COLUMN_TYPE;

            if (is_string($type) && str_contains(strtolower($type), 'unsigned')) {
                DB::statement("ALTER TABLE `{$table}` MODIFY `{$column}` BIGINT NOT NULL");
            }
        }
    }

    private function addForeignKeys(): void
    {
        foreach (self::FOREIGN_KEYS as [$tableName, $constraintName, $column, $referencedTable, $referencedColumn, $cascade]) {
            if (Schema::hasForeignKey($tableName, $constraintName)) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) use ($column, $constraintName, $referencedColumn, $referencedTable, $cascade) {
                $foreign = $table->foreign($column, $constraintName)
                    ->references($referencedColumn)
                    ->on($referencedTable);

                if ($cascade) {
                    $foreign->cascadeOnDelete();
                }
            });
        }
    }
};
