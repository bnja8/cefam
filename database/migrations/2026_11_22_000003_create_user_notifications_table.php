<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('notificaciones')) {
            Schema::create('notificaciones', function (Blueprint $table) {
                $table->engine('InnoDB');
                $table->bigIncrements('notificacion_id');
                $table->string('usuario_rut', 12);
                $table->string('tipo', 40);
                $table->string('titulo', 120);
                $table->text('mensaje');
                $table->string('accion_texto', 100)->nullable();
                $table->string('accion_url', 500)->nullable();
                $table->timestamp('leida_at')->nullable();
                $table->timestamp('created_at')->useCurrent();

                $table->index(['usuario_rut', 'leida_at', 'created_at'], 'notificaciones_usuario_lectura_fecha_idx');
                $table->foreign('usuario_rut')->references('rut')->on('usuarios')->cascadeOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('notificaciones');
    }
};
