<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('remesas')) {
            Schema::create('remesas', function (Blueprint $table): void {
                $table->bigInteger('remesa_id')->autoIncrement();
                $table->string('codigo_remesa', 100);
                $table->string('origen', 150);
                $table->timestamp('fecha_recepcion');
                $table->bigInteger('solicitud_id')->nullable();
                $table->string('usuario_rut', 12);
                $table->unique(['codigo_remesa'], 'codigo_remesa');
                $table->index(['solicitud_id'], 'fk_remesas_solicitud_id');
                $table->index(['usuario_rut'], 'fk_remesas_usuario_rut');
                $table->index(['fecha_recepcion'], 'idx_remesas_fecha');
                $table->foreign('solicitud_id', 'fk_remesas_solicitud_id')->references('solicitud_id')->on('solicitudes_reposicion');
                $table->foreign('usuario_rut', 'fk_remesas_usuario_rut')->references('rut')->on('usuarios');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('farmacia_migration_ownership')
            || ! DB::table('farmacia_migration_ownership')->where('migration', '2026_09_08_000000_prepare_farmacia_schema')->exists()) {
            return;
        }

        Schema::dropIfExists('remesas');
    }
};
