<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const MIGRATION = '2026_09_08_000000_prepare_farmacia_schema';

    private const TABLES = [
        'categorias_producto',
        'pacientes',
        'profesionales_salud',
        'programas_salud',
        'roles',
        'ubicaciones',
        'usuarios',
        'auditoria',
        'carros',
        'paciente_programa',
        'personas_autorizadas',
        'productos',
        'solicitudes_reposicion',
        'traspasos_inventario',
        'usuario_rol',
        'recetas',
        'tratamientos',
        'cuotas_paciente',
        'lotes',
        'productos_codigo_barra',
        'remesas',
        'solicitud_reposicion_detalle',
        'stock_minimos_ubicacion',
        'entregas',
        'receta_detalle',
        'preparaciones_unidosis',
        'alertas',
        'inventario',
        'movimientos_controlados',
        'movimientos_inventario',
        'remesa_detalle',
        'traspaso_detalle',
        'entrega_detalle',
        'ordenes_entrega',
        'preparacion_unidosis_detalle',
        'rotulos_unidosis',
    ];

    public function up(): void
    {
        $existingTables = array_values(array_filter(
            self::TABLES,
            static fn (string $table): bool => Schema::hasTable($table)
        ));
        if (count($existingTables) === count(self::TABLES)) {
            return;
        }

        $ownsSchema = Schema::hasTable('farmacia_migration_ownership')
            && DB::table('farmacia_migration_ownership')->where('migration', self::MIGRATION)->exists();
        if ($existingTables !== [] && ! $ownsSchema) {
            throw new RuntimeException('La base contiene solo parte del esquema de farmacia. Restaura el esquema completo o usa una base vacia.');
        }

        if (! Schema::hasTable('farmacia_migration_ownership')) {
            Schema::create('farmacia_migration_ownership', function (Blueprint $table): void {
                $table->string('migration')->primary();
            });
        }
        if (! $ownsSchema) {
            DB::table('farmacia_migration_ownership')->insert(['migration' => self::MIGRATION]);
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('farmacia_migration_ownership')
            || ! DB::table('farmacia_migration_ownership')->where('migration', self::MIGRATION)->exists()) {
            return;
        }

        DB::table('farmacia_migration_ownership')->where('migration', self::MIGRATION)->delete();
        Schema::dropIfExists('farmacia_migration_ownership');
    }
};
