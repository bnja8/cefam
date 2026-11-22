<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('paciente_programa')) {
            Schema::create('paciente_programa', function (Blueprint $table): void {
                $table->bigInteger('paciente_programa_id')->autoIncrement();
                $table->string('paciente_rut', 12);
                $table->bigInteger('programa_id');
                $table->date('fecha_inicio');
                $table->date('fecha_termino')->nullable();
                $table->string('cobertura', 255)->nullable();
                $table->boolean('activo')->default(true);
                $table->unique(['paciente_rut', 'programa_id', 'fecha_inicio'], 'paciente_programa_paciente_rut_unique');
                $table->index(['programa_id'], 'fk_paciente_programa_programa_id');
                $table->foreign('paciente_rut', 'fk_paciente_programa_paciente_rut')->references('rut')->on('pacientes');
                $table->foreign('programa_id', 'fk_paciente_programa_programa_id')->references('programa_id')->on('programas_salud');
            });
            if (DB::getDriverName() === 'mysql') {
                DB::statement('ALTER TABLE `paciente_programa` ADD CONSTRAINT `paciente_programa_chk_1` CHECK (((`fecha_termino` is null) or (`fecha_termino` >= `fecha_inicio`)))');
            }
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('farmacia_migration_ownership')
            || ! DB::table('farmacia_migration_ownership')->where('migration', '2026_09_08_000000_prepare_farmacia_schema')->exists()) {
            return;
        }

        Schema::dropIfExists('paciente_programa');
    }
};
