<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('movimientos_inventario')) {
            Schema::create('movimientos_inventario', function (Blueprint $table): void {
                $table->bigInteger('movimiento_id')->autoIncrement();
                $table->bigInteger('lote_id');
                $table->bigInteger('ubicacion_id');
                $table->string('tipo_movimiento', 30);
                $table->decimal('cantidad', 12, 2);
                $table->timestamp('fecha_hora')->useCurrent();
                $table->string('usuario_rut', 12);
                $table->string('referencia_tipo', 50)->nullable();
                $table->bigInteger('referencia_id')->nullable();
                $table->text('observacion')->nullable();
                $table->index(['lote_id'], 'fk_movimientos_inventario_lote_id');
                $table->index(['ubicacion_id'], 'fk_movimientos_inventario_ubicacion_id');
                $table->index(['usuario_rut'], 'fk_movimientos_inventario_usuario_rut');
                $table->index(['fecha_hora'], 'idx_movimientos_inventario_fecha');
                $table->foreign('lote_id', 'fk_movimientos_inventario_lote_id')->references('lote_id')->on('lotes');
                $table->foreign('ubicacion_id', 'fk_movimientos_inventario_ubicacion_id')->references('ubicacion_id')->on('ubicaciones');
                $table->foreign('usuario_rut', 'fk_movimientos_inventario_usuario_rut')->references('rut')->on('usuarios');
            });
            if (DB::getDriverName() === 'mysql') {
                DB::statement('ALTER TABLE `movimientos_inventario` ADD CONSTRAINT `movimientos_inventario_chk_1` CHECK ((`tipo_movimiento` in (\'RECEPCION\',\'SALIDA\',\'TRASPASO_ENTRADA\',\'TRASPASO_SALIDA\',\'AJUSTE_ENTRADA\',\'AJUSTE_SALIDA\')))');
            }
            if (DB::getDriverName() === 'mysql') {
                DB::statement('ALTER TABLE `movimientos_inventario` ADD CONSTRAINT `movimientos_inventario_chk_2` CHECK ((`cantidad` > 0))');
            }
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('farmacia_migration_ownership')
            || ! DB::table('farmacia_migration_ownership')->where('migration', '2026_09_08_000000_prepare_farmacia_schema')->exists()) {
            return;
        }

        Schema::dropIfExists('movimientos_inventario');
    }
};
