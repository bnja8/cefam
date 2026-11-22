<?php
// Actualiza el esquema de datos: 2026 11 19 000001 remove tomas diarias from prescription details.


use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Aplica esta migración al esquema de la base de datos.
    public function up(): void
    {
        if (Schema::hasColumn('receta_detalle', 'tomas_diarias')) {
            Schema::table('receta_detalle', /* Actualiza la estructura de la tabla receta_detalle. */ function (Blueprint $table) {
                $table->dropColumn('tomas_diarias');
            });
        }
    }

    // Revierte los cambios de esta migración.
    public function down(): void
    {
        if (! Schema::hasColumn('receta_detalle', 'tomas_diarias')) {
            Schema::table('receta_detalle', /* Actualiza la estructura de la tabla receta_detalle. */ function (Blueprint $table) {
                $table->unsignedSmallInteger('tomas_diarias')->nullable();
            });
        }
    }
};
