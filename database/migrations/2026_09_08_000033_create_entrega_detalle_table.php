<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('entrega_detalle')) {
            Schema::create('entrega_detalle', function (Blueprint $table): void {
                $table->bigInteger('entrega_detalle_id')->autoIncrement();
                $table->bigInteger('entrega_id');
                $table->bigInteger('lote_id');
                $table->bigInteger('producto_id');
                $table->decimal('cantidad', 12, 2);
                $table->bigInteger('cuota_id')->nullable();
                $table->string('dosis', 100)->nullable();
                $table->string('horario', 100)->nullable();
                $table->index(['entrega_id'], 'fk_entrega_detalle_entrega_id');
                $table->index(['lote_id'], 'fk_entrega_detalle_lote_id');
                $table->index(['cuota_id'], 'fk_entrega_detalle_cuota_id');
                $table->index(['producto_id'], 'idx_entrega_detalle_producto');
                $table->foreign('cuota_id', 'fk_entrega_detalle_cuota_id')->references('cuota_id')->on('cuotas_paciente');
                $table->foreign('entrega_id', 'fk_entrega_detalle_entrega_id')->references('entrega_id')->on('entregas');
                $table->foreign('lote_id', 'fk_entrega_detalle_lote_id')->references('lote_id')->on('lotes');
                $table->foreign('producto_id', 'fk_entrega_detalle_producto_id')->references('producto_id')->on('productos');
            });
            if (DB::getDriverName() === 'mysql') {
                DB::statement('ALTER TABLE `entrega_detalle` ADD CONSTRAINT `entrega_detalle_chk_1` CHECK ((`cantidad` > 0))');
            }
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('farmacia_migration_ownership')
            || ! DB::table('farmacia_migration_ownership')->where('migration', '2026_09_08_000000_prepare_farmacia_schema')->exists()) {
            return;
        }

        Schema::dropIfExists('entrega_detalle');
    }
};
