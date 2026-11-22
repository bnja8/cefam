<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('solicitudes_reposicion')) {
            Schema::create('solicitudes_reposicion', function (Blueprint $table): void {
                $table->bigInteger('solicitud_id')->autoIncrement();
                $table->timestamp('fecha_solicitud')->useCurrent();
                $table->string('estado', 30)->default('PENDIENTE');
                $table->string('usuario_rut', 12);
                $table->index(['usuario_rut'], 'fk_solicitudes_reposicion_usuario_rut');
                $table->foreign('usuario_rut', 'fk_solicitudes_reposicion_usuario_rut')->references('rut')->on('usuarios');
            });
            if (DB::getDriverName() === 'mysql') {
                DB::statement('ALTER TABLE `solicitudes_reposicion` ADD CONSTRAINT `solicitudes_reposicion_chk_1` CHECK ((`estado` in (\'PENDIENTE\',\'SOLICITADA\',\'RECIBIDA\',\'CANCELADA\')))');
            }
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('farmacia_migration_ownership')
            || ! DB::table('farmacia_migration_ownership')->where('migration', '2026_09_08_000000_prepare_farmacia_schema')->exists()) {
            return;
        }

        Schema::dropIfExists('solicitudes_reposicion');
    }
};
