<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('entregas')) {
            Schema::create('entregas', function (Blueprint $table): void {
                $table->bigInteger('entrega_id')->autoIncrement();
                $table->string('paciente_rut', 12);
                $table->bigInteger('persona_autorizada_id')->nullable();
                $table->bigInteger('receta_id')->nullable();
                $table->string('usuario_rut', 12);
                $table->timestamp('fecha_hora')->useCurrent();
                $table->string('estado', 20)->default('CONFIRMADA');
                $table->text('observacion')->nullable();
                $table->string('receptor_tipo', 20)->default('PACIENTE');
                $table->string('receptor_eventual_rut', 12)->nullable();
                $table->string('receptor_eventual_nombre', 50)->nullable();
                $table->string('receptor_eventual_apellido_paterno', 50)->nullable();
                $table->string('receptor_eventual_apellido_materno', 50)->nullable();
                $table->string('receptor_eventual_vinculo', 100)->nullable();
                $table->index(['persona_autorizada_id'], 'fk_entregas_persona_autorizada_id');
                $table->index(['receta_id'], 'fk_entregas_receta_id');
                $table->index(['usuario_rut'], 'fk_entregas_usuario_rut');
                $table->index(['paciente_rut', 'fecha_hora'], 'idx_entregas_paciente_fecha');
                $table->foreign('paciente_rut', 'fk_entregas_paciente_rut')->references('rut')->on('pacientes');
                $table->foreign('receta_id', 'fk_entregas_receta_id')->references('receta_id')->on('recetas');
                $table->foreign('persona_autorizada_id', 'fk_entregas_persona_autorizada_id')->references('persona_autorizada_id')->on('personas_autorizadas');
                $table->foreign('usuario_rut', 'fk_entregas_usuario_rut')->references('rut')->on('usuarios');
            });
            if (DB::getDriverName() === 'mysql') {
                DB::statement('ALTER TABLE `entregas` ADD CONSTRAINT `entregas_chk_1` CHECK ((`estado` in (\'PENDIENTE\',\'CONFIRMADA\',\'RECHAZADA\',\'ANULADA\')))');
            }
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('farmacia_migration_ownership')
            || ! DB::table('farmacia_migration_ownership')->where('migration', '2026_09_08_000000_prepare_farmacia_schema')->exists()) {
            return;
        }

        Schema::dropIfExists('entregas');
    }
};
