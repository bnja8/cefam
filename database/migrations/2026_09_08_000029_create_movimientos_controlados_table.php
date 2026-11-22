<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('movimientos_controlados')) {
            Schema::create('movimientos_controlados', function (Blueprint $table): void {
                $table->bigInteger('movimiento_controlado_id')->autoIncrement();
                $table->bigInteger('producto_id');
                $table->bigInteger('lote_id');
                $table->string('tipo_movimiento', 20);
                $table->decimal('cantidad', 12, 2);
                $table->timestamp('fecha_hora')->useCurrent();
                $table->string('usuario_responsable_rut', 12);
                $table->string('usuario_autoriza_rut', 12)->nullable();
                $table->text('firma_autorizacion')->nullable();
                $table->string('referencia_tipo', 50)->nullable();
                $table->bigInteger('referencia_id')->nullable();
                $table->text('observacion')->nullable();
                $table->boolean('firmado')->default(false);
                $table->index(['producto_id'], 'fk_movimientos_controlados_producto_id');
                $table->index(['lote_id'], 'fk_movimientos_controlados_lote_id');
                $table->index(['usuario_responsable_rut'], 'fk_movimientos_controlados_usuario_responsable_rut');
                $table->index(['usuario_autoriza_rut'], 'fk_movimientos_controlados_usuario_autoriza_rut');
                $table->foreign('lote_id', 'fk_movimientos_controlados_lote_id')->references('lote_id')->on('lotes');
                $table->foreign('producto_id', 'fk_movimientos_controlados_producto_id')->references('producto_id')->on('productos');
                $table->foreign('usuario_autoriza_rut', 'fk_movimientos_controlados_usuario_autoriza_rut')->references('rut')->on('usuarios');
                $table->foreign('usuario_responsable_rut', 'fk_movimientos_controlados_usuario_responsable_rut')->references('rut')->on('usuarios');
            });
            if (DB::getDriverName() === 'mysql') {
                DB::statement('ALTER TABLE `movimientos_controlados` ADD CONSTRAINT `movimientos_controlados_chk_1` CHECK ((`tipo_movimiento` in (\'INGRESO\',\'SALIDA\',\'AJUSTE\')))');
            }
            if (DB::getDriverName() === 'mysql') {
                DB::statement('ALTER TABLE `movimientos_controlados` ADD CONSTRAINT `movimientos_controlados_chk_2` CHECK ((`cantidad` > 0))');
            }
            if (DB::getDriverName() === 'mysql') {
                DB::statement('ALTER TABLE `movimientos_controlados` ADD CONSTRAINT `movimientos_controlados_chk_3` CHECK (((`firmado` = false) or ((`usuario_autoriza_rut` is not null) and (`firma_autorizacion` is not null))))');
            }
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('farmacia_migration_ownership')
            || ! DB::table('farmacia_migration_ownership')->where('migration', '2026_09_08_000000_prepare_farmacia_schema')->exists()) {
            return;
        }

        Schema::dropIfExists('movimientos_controlados');
    }
};
