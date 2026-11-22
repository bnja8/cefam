<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('receta_detalle')) {
            Schema::create('receta_detalle', function (Blueprint $table): void {
                $table->bigInteger('receta_detalle_id')->autoIncrement();
                $table->bigInteger('receta_id');
                $table->bigInteger('producto_id');
                $table->string('dosis', 100)->nullable();
                $table->string('frecuencia', 100)->nullable();
                $table->decimal('cantidad_indicada', 12, 2);
                $table->text('indicaciones')->nullable();
                $table->index(['receta_id'], 'fk_receta_detalle_receta_id');
                $table->index(['producto_id'], 'fk_receta_detalle_producto_id');
                $table->foreign('producto_id', 'fk_receta_detalle_producto_id')->references('producto_id')->on('productos');
                $table->foreign('receta_id', 'fk_receta_detalle_receta_id')->references('receta_id')->on('recetas');
            });
            if (DB::getDriverName() === 'mysql') {
                DB::statement('ALTER TABLE `receta_detalle` ADD CONSTRAINT `receta_detalle_chk_1` CHECK ((`cantidad_indicada` > 0))');
            }
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('farmacia_migration_ownership')
            || ! DB::table('farmacia_migration_ownership')->where('migration', '2026_09_08_000000_prepare_farmacia_schema')->exists()) {
            return;
        }

        Schema::dropIfExists('receta_detalle');
    }
};
