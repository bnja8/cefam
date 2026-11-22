<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('preparacion_unidosis_detalle')) {
            Schema::create('preparacion_unidosis_detalle', function (Blueprint $table): void {
                $table->bigInteger('preparacion_detalle_id')->autoIncrement();
                $table->bigInteger('preparacion_id');
                $table->bigInteger('lote_id');
                $table->decimal('cantidad', 12, 2);
                $table->string('horario', 100)->nullable();
                $table->string('dosis', 100)->nullable();
                $table->index(['preparacion_id'], 'fk_preparacion_unidosis_detalle_preparacion_id');
                $table->index(['lote_id'], 'fk_preparacion_unidosis_detalle_lote_id');
                $table->foreign('lote_id', 'fk_preparacion_unidosis_detalle_lote_id')->references('lote_id')->on('lotes');
                $table->foreign('preparacion_id', 'fk_preparacion_unidosis_detalle_preparacion_id')->references('preparacion_id')->on('preparaciones_unidosis');
            });
            if (DB::getDriverName() === 'mysql') {
                DB::statement('ALTER TABLE `preparacion_unidosis_detalle` ADD CONSTRAINT `preparacion_unidosis_detalle_chk_1` CHECK ((`cantidad` > 0))');
            }
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('farmacia_migration_ownership')
            || ! DB::table('farmacia_migration_ownership')->where('migration', '2026_09_08_000000_prepare_farmacia_schema')->exists()) {
            return;
        }

        Schema::dropIfExists('preparacion_unidosis_detalle');
    }
};
