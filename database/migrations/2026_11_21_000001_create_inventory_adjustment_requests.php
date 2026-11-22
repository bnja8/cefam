<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('solicitudes_ajuste_inventario')) {
            return;
        }

        Schema::create('solicitudes_ajuste_inventario', function (Blueprint $table) {
            $table->engine('InnoDB');
            $table->bigIncrements('solicitud_ajuste_id');
            $table->bigInteger('lote_id');
            $table->bigInteger('ubicacion_id');
            $table->string('tipo_ajuste', 10);
            $table->decimal('cantidad', 12, 2);
            $table->text('motivo');
            $table->string('estado', 20)->default('PENDIENTE');
            $table->string('usuario_solicita_rut', 12);
            $table->boolean('solicitante_es_qf')->default(false);
            $table->string('usuario_autoriza_rut', 12)->nullable();
            $table->bigInteger('movimiento_id')->nullable();
            $table->text('motivo_resolucion')->nullable();
            $table->timestamp('fecha_solicitud')->useCurrent();
            $table->timestamp('fecha_resolucion')->nullable();
            $table->index(['estado', 'fecha_solicitud'], 'solicitudes_ajuste_estado_fecha_idx');
            $table->index(['usuario_solicita_rut', 'fecha_solicitud'], 'solicitudes_ajuste_solicitante_fecha_idx');
            $table->foreign('lote_id', 'solicitudes_ajuste_lote_fk')->references('lote_id')->on('lotes');
            $table->foreign('ubicacion_id', 'solicitudes_ajuste_ubicacion_fk')->references('ubicacion_id')->on('ubicaciones');
            $table->foreign('usuario_solicita_rut', 'solicitudes_ajuste_solicitante_fk')->references('rut')->on('usuarios');
            $table->foreign('usuario_autoriza_rut', 'solicitudes_ajuste_autorizador_fk')->references('rut')->on('usuarios');
            $table->foreign('movimiento_id', 'solicitudes_ajuste_movimiento_fk')->references('movimiento_id')->on('movimientos_inventario');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('solicitudes_ajuste_inventario');
    }
};
