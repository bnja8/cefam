<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('tratamientos')) {
            Schema::create('tratamientos', function (Blueprint $table): void {
                $table->bigInteger('tratamiento_id')->autoIncrement();
                $table->string('paciente_rut', 12);
                $table->text('descripcion');
                $table->date('fecha_inicio');
                $table->date('fecha_termino')->nullable();
                $table->boolean('activo')->default(true);
                $table->index(['paciente_rut'], 'fk_tratamientos_paciente_rut');
                $table->foreign('paciente_rut', 'fk_tratamientos_paciente_rut')->references('rut')->on('pacientes');
            });
            if (DB::getDriverName() === 'mysql') {
                DB::statement('ALTER TABLE `tratamientos` ADD CONSTRAINT `tratamientos_chk_1` CHECK (((`fecha_termino` is null) or (`fecha_termino` >= `fecha_inicio`)))');
            }
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('farmacia_migration_ownership')
            || ! DB::table('farmacia_migration_ownership')->where('migration', '2026_09_08_000000_prepare_farmacia_schema')->exists()) {
            return;
        }

        Schema::dropIfExists('tratamientos');
    }
};
