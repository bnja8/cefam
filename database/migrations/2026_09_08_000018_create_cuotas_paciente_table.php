<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('cuotas_paciente')) {
            Schema::create('cuotas_paciente', function (Blueprint $table): void {
                $table->bigInteger('cuota_id')->autoIncrement();
                $table->bigInteger('paciente_programa_id');
                $table->bigInteger('producto_id');
                $table->date('periodo_inicio');
                $table->date('periodo_termino');
                $table->decimal('cantidad_autorizada', 12, 2);
                $table->decimal('cantidad_utilizada', 12, 2)->default('0.00');
                $table->index(['paciente_programa_id'], 'fk_cuotas_paciente_paciente_programa_id');
                $table->index(['producto_id'], 'fk_cuotas_paciente_producto_id');
                $table->foreign('paciente_programa_id', 'fk_cuotas_paciente_paciente_programa_id')->references('paciente_programa_id')->on('paciente_programa');
                $table->foreign('producto_id', 'fk_cuotas_paciente_producto_id')->references('producto_id')->on('productos');
            });
            if (DB::getDriverName() === 'mysql') {
                DB::statement('ALTER TABLE `cuotas_paciente` ADD CONSTRAINT `cuotas_paciente_chk_1` CHECK ((`periodo_termino` >= `periodo_inicio`))');
            }
            if (DB::getDriverName() === 'mysql') {
                DB::statement('ALTER TABLE `cuotas_paciente` ADD CONSTRAINT `cuotas_paciente_chk_2` CHECK ((`cantidad_autorizada` >= 0))');
            }
            if (DB::getDriverName() === 'mysql') {
                DB::statement('ALTER TABLE `cuotas_paciente` ADD CONSTRAINT `cuotas_paciente_chk_3` CHECK ((`cantidad_utilizada` >= 0))');
            }
            if (DB::getDriverName() === 'mysql') {
                DB::statement('ALTER TABLE `cuotas_paciente` ADD CONSTRAINT `cuotas_paciente_chk_4` CHECK ((`cantidad_utilizada` <= `cantidad_autorizada`))');
            }
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('farmacia_migration_ownership')
            || ! DB::table('farmacia_migration_ownership')->where('migration', '2026_09_08_000000_prepare_farmacia_schema')->exists()) {
            return;
        }

        Schema::dropIfExists('cuotas_paciente');
    }
};
