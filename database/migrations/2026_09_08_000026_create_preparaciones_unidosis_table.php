<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('preparaciones_unidosis')) {
            Schema::create('preparaciones_unidosis', function (Blueprint $table): void {
                $table->bigInteger('preparacion_id')->autoIncrement();
                $table->bigInteger('tratamiento_id');
                $table->bigInteger('carro_id');
                $table->date('fecha_preparacion');
                $table->string('periodo', 50);
                $table->string('usuario_prepara_rut', 12);
                $table->string('usuario_confirma_rut', 12)->nullable();
                $table->timestamp('fecha_confirmacion')->nullable();
                $table->string('estado', 20)->default('PREPARANDO');
                $table->unique(['tratamiento_id', 'fecha_preparacion', 'periodo'], 'tratamiento_id');
                $table->index(['carro_id'], 'fk_preparaciones_unidosis_carro_id');
                $table->index(['usuario_prepara_rut'], 'fk_preparaciones_unidosis_usuario_prepara_rut');
                $table->index(['usuario_confirma_rut'], 'fk_preparaciones_unidosis_usuario_confirma_rut');
                $table->foreign('carro_id', 'fk_preparaciones_unidosis_carro_id')->references('carro_id')->on('carros');
                $table->foreign('tratamiento_id', 'fk_preparaciones_unidosis_tratamiento_id')->references('tratamiento_id')->on('tratamientos');
                $table->foreign('usuario_confirma_rut', 'fk_preparaciones_unidosis_usuario_confirma_rut')->references('rut')->on('usuarios');
                $table->foreign('usuario_prepara_rut', 'fk_preparaciones_unidosis_usuario_prepara_rut')->references('rut')->on('usuarios');
            });
            if (DB::getDriverName() === 'mysql') {
                DB::statement('ALTER TABLE `preparaciones_unidosis` ADD CONSTRAINT `preparaciones_unidosis_chk_1` CHECK ((`estado` in (\'PREPARANDO\',\'CONFIRMADA\',\'ANULADA\')))');
            }
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('farmacia_migration_ownership')
            || ! DB::table('farmacia_migration_ownership')->where('migration', '2026_09_08_000000_prepare_farmacia_schema')->exists()) {
            return;
        }

        Schema::dropIfExists('preparaciones_unidosis');
    }
};
