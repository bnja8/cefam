<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('auditoria')) {
            Schema::create('auditoria', function (Blueprint $table): void {
                $table->bigInteger('auditoria_id')->autoIncrement();
                $table->string('usuario_rut', 12)->nullable();
                $table->timestamp('fecha_hora')->useCurrent();
                $table->string('accion', 50);
                $table->string('entidad', 100);
                $table->bigInteger('registro_id')->nullable();
                $table->string('resultado', 30);
                $table->json('detalle')->nullable();
                $table->string('ip_origen', 45)->nullable();
                $table->index(['usuario_rut'], 'fk_auditoria_usuario_rut');
                $table->index(['fecha_hora'], 'idx_auditoria_fecha');
                $table->foreign('usuario_rut', 'fk_auditoria_usuario_rut')->references('rut')->on('usuarios');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('farmacia_migration_ownership')
            || ! DB::table('farmacia_migration_ownership')->where('migration', '2026_09_08_000000_prepare_farmacia_schema')->exists()) {
            return;
        }

        Schema::dropIfExists('auditoria');
    }
};
