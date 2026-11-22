<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('rotulos_unidosis')) {
            Schema::create('rotulos_unidosis', function (Blueprint $table): void {
                $table->bigInteger('rotulo_id')->autoIncrement();
                $table->bigInteger('preparacion_detalle_id');
                $table->string('identificador', 100);
                $table->text('contenido_generado');
                $table->timestamp('fecha_generacion')->useCurrent();
                $table->unique(['preparacion_detalle_id'], 'preparacion_detalle_id');
                $table->unique(['identificador'], 'identificador');
                $table->foreign('preparacion_detalle_id', 'fk_rotulos_unidosis_preparacion_detalle_id')->references('preparacion_detalle_id')->on('preparacion_unidosis_detalle');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('farmacia_migration_ownership')
            || ! DB::table('farmacia_migration_ownership')->where('migration', '2026_09_08_000000_prepare_farmacia_schema')->exists()) {
            return;
        }

        Schema::dropIfExists('rotulos_unidosis');
    }
};
