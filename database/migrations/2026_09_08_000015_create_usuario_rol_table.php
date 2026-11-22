<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('usuario_rol')) {
            Schema::create('usuario_rol', function (Blueprint $table): void {
                $table->string('usuario_rut', 12);
                $table->bigInteger('rol_id');
                $table->timestamp('asignado_at')->useCurrent();
                $table->primary(['usuario_rut', 'rol_id']);
                $table->index(['rol_id'], 'fk_usuario_rol_rol_id');
                $table->foreign('rol_id', 'fk_usuario_rol_rol_id')->references('rol_id')->on('roles');
                $table->foreign('usuario_rut', 'fk_usuario_rol_usuario_rut')->references('rut')->on('usuarios');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('farmacia_migration_ownership')
            || ! DB::table('farmacia_migration_ownership')->where('migration', '2026_09_08_000000_prepare_farmacia_schema')->exists()) {
            return;
        }

        Schema::dropIfExists('usuario_rol');
    }
};
