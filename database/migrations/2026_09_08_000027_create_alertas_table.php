<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('alertas')) {
            Schema::create('alertas', function (Blueprint $table): void {
                $table->bigInteger('alerta_id')->autoIncrement();
                $table->string('tipo_alerta', 30);
                $table->bigInteger('producto_id')->nullable();
                $table->bigInteger('lote_id')->nullable();
                $table->bigInteger('ubicacion_id')->nullable();
                $table->timestamp('fecha_generacion')->useCurrent();
                $table->decimal('nivel_actual', 12, 2)->nullable();
                $table->text('mensaje');
                $table->boolean('atendida')->default(false);
                $table->string('atendida_por_rut', 12)->nullable();
                $table->timestamp('atendida_at')->nullable();
                $table->index(['producto_id'], 'fk_alertas_producto_id');
                $table->index(['lote_id'], 'fk_alertas_lote_id');
                $table->index(['ubicacion_id'], 'fk_alertas_ubicacion_id');
                $table->index(['atendida_por_rut'], 'fk_alertas_atendida_por_rut');
                $table->index(['tipo_alerta', 'producto_id', 'ubicacion_id', 'atendida'], 'idx_alertas_producto_ubicacion_estado');
                $table->foreign('atendida_por_rut', 'fk_alertas_atendida_por_rut')->references('rut')->on('usuarios');
                $table->foreign('lote_id', 'fk_alertas_lote_id')->references('lote_id')->on('lotes');
                $table->foreign('producto_id', 'fk_alertas_producto_id')->references('producto_id')->on('productos');
                $table->foreign('ubicacion_id', 'fk_alertas_ubicacion_id')->references('ubicacion_id')->on('ubicaciones');
            });
            if (DB::getDriverName() === 'mysql') {
                DB::statement("ALTER TABLE `alertas` ADD CONSTRAINT `alertas_tipo_alerta_check` CHECK (`tipo_alerta` IN ('STOCK_CRITICO', 'VENCIMIENTO', 'DESCUADRE_CONTROLADO'))");
            }
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('farmacia_migration_ownership')
            || ! DB::table('farmacia_migration_ownership')->where('migration', '2026_09_08_000000_prepare_farmacia_schema')->exists()) {
            return;
        }

        Schema::dropIfExists('alertas');
    }
};
