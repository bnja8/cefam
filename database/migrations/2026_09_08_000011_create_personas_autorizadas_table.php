<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('personas_autorizadas')) {
            Schema::create('personas_autorizadas', function (Blueprint $table): void {
                $table->bigInteger('persona_autorizada_id')->autoIncrement();
                $table->string('paciente_rut', 12);
                $table->string('rut', 12);
                $table->string('nombre', 50);
                $table->string('apellidoPaterno', 50);
                $table->string('apellidoMaterno', 50);
                $table->string('vinculo', 100);
                $table->boolean('activo')->default(true);
                $table->unique(['paciente_rut', 'rut'], 'personas_autorizadas_paciente_rut_unique');
                $table->foreign('paciente_rut', 'fk_personas_autorizadas_paciente_rut')->references('rut')->on('pacientes');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('farmacia_migration_ownership')
            || ! DB::table('farmacia_migration_ownership')->where('migration', '2026_09_08_000000_prepare_farmacia_schema')->exists()) {
            return;
        }

        Schema::dropIfExists('personas_autorizadas');
    }
};
