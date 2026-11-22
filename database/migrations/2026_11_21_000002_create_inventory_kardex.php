<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('kardex_inventario')) {
            Schema::create('kardex_inventario', function (Blueprint $table) {
                $table->engine('InnoDB');
                $table->bigIncrements('kardex_id');
                $table->bigInteger('inventario_id_origen')->unique('kardex_inventario_origen_unique');
                $table->bigInteger('lote_id');
                $table->bigInteger('ubicacion_id');
                $table->decimal('cantidad_final', 12, 2)->default(0);
                $table->string('motivo', 50);
                $table->string('referencia_tipo', 50)->nullable();
                $table->bigInteger('referencia_id')->nullable();
                $table->string('documento_recepcion', 100)->nullable();
                $table->text('observacion')->nullable();
                $table->string('usuario_rut', 12)->nullable();
                $table->timestamp('fecha_hora')->useCurrent();
                $table->index(['lote_id', 'ubicacion_id', 'fecha_hora'], 'kardex_lote_ubicacion_fecha_idx');
                $table->index('fecha_hora', 'kardex_fecha_idx');
                $table->foreign('lote_id', 'fk_kardex_lote')->references('lote_id')->on('lotes');
                $table->foreign('ubicacion_id', 'fk_kardex_ubicacion')->references('ubicacion_id')->on('ubicaciones');
                $table->foreign('usuario_rut', 'fk_kardex_usuario')->references('rut')->on('usuarios');
            });
        }

    }

    public function down(): void
    {
        Schema::dropIfExists('kardex_inventario');
    }
};
