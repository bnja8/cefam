<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('recetas')) {
            Schema::create('recetas', function (Blueprint $table): void {
                $table->bigInteger('receta_id')->autoIncrement();
                $table->string('paciente_rut', 12);
                $table->string('profesional_rut', 12);
                $table->string('numero_receta', 100);
                $table->date('fecha_emision');
                $table->date('fecha_vencimiento');
                $table->string('tipo_receta', 20);
                $table->string('estado', 20)->default('VIGENTE');
                $table->text('observacion')->nullable();
                $table->unique(['numero_receta'], 'numero_receta');
                $table->index(['profesional_rut'], 'fk_recetas_profesional_rut');
                $table->index(['paciente_rut'], 'idx_recetas_paciente');
                $table->foreign('paciente_rut', 'fk_recetas_paciente_rut')->references('rut')->on('pacientes');
                $table->foreign('profesional_rut', 'fk_recetas_profesional_rut')->references('rut')->on('profesionales_salud');
            });
            if (DB::getDriverName() === 'mysql') {
                DB::statement('ALTER TABLE `recetas` ADD CONSTRAINT `recetas_chk_1` CHECK ((`tipo_receta` in (\'ESTANDAR\',\'RETENIDA\')))');
            }
            if (DB::getDriverName() === 'mysql') {
                DB::statement('ALTER TABLE `recetas` ADD CONSTRAINT `recetas_chk_2` CHECK ((`estado` in (\'VIGENTE\',\'VENCIDA\',\'ANULADA\',\'UTILIZADA\')))');
            }
            if (DB::getDriverName() === 'mysql') {
                DB::statement('ALTER TABLE `recetas` ADD CONSTRAINT `recetas_chk_3` CHECK ((`fecha_vencimiento` >= `fecha_emision`))');
            }
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('farmacia_migration_ownership')
            || ! DB::table('farmacia_migration_ownership')->where('migration', '2026_09_08_000000_prepare_farmacia_schema')->exists()) {
            return;
        }

        Schema::dropIfExists('recetas');
    }
};
