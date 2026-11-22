<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('tratamientos', 'receta_id')) {
            return;
        }

        Schema::table('tratamientos', function (Blueprint $table) {
            $table->bigInteger('receta_id')->nullable()->unique('tratamientos_receta_id_unique');
            $table->foreign('receta_id', 'fk_tratamientos_receta_id')
                ->references('receta_id')
                ->on('recetas')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('tratamientos', 'receta_id')) {
            return;
        }

        Schema::table('tratamientos', function (Blueprint $table) {
            $table->dropForeign('fk_tratamientos_receta_id');
            $table->dropUnique('tratamientos_receta_id_unique');
            $table->dropColumn('receta_id');
        });
    }
};
