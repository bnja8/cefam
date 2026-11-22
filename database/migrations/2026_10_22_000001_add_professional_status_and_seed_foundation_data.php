<?php
// Actualiza el esquema de datos: 2026 10 22 000001 add professional status and seed foundation data.


use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Aplica esta migración al esquema de la base de datos.
    public function up(): void
    {
        if (! Schema::hasColumn('profesionales_salud', 'activo')) {
            Schema::table('profesionales_salud', /* Actualiza la estructura de la tabla profesionales_salud. */ function (Blueprint $table) {
                $table->boolean('activo')->default(true);
            });
        }
    }

    // Revierte los cambios de esta migración.
    public function down(): void
    {
        if (Schema::hasColumn('profesionales_salud', 'activo')) {
            Schema::table('profesionales_salud', /* Actualiza la estructura de la tabla profesionales_salud. */ function (Blueprint $table) {
                $table->dropColumn('activo');
            });
        }
    }
};
