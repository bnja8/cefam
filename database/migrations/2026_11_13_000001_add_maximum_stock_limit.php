<?php
// Actualiza el esquema de datos: 2026 11 13 000001 add maximum stock limit.


use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Aplica esta migración al esquema de la base de datos.
    public function up(): void
    {
        if (! Schema::hasColumn('stock_minimos_ubicacion', 'stock_maximo')) {
            Schema::table('stock_minimos_ubicacion', /* Actualiza la estructura de la tabla stock_minimos_ubicacion. */ function (Blueprint $table) {
                $table->decimal('stock_maximo', 12, 2)->nullable();
            });
        }
    }

    // Revierte los cambios de esta migración.
    public function down(): void
    {
        if (Schema::hasColumn('stock_minimos_ubicacion', 'stock_maximo')) {
            Schema::table('stock_minimos_ubicacion', /* Actualiza la estructura de la tabla stock_minimos_ubicacion. */ function (Blueprint $table) {
                $table->dropColumn('stock_maximo');
            });
        }
    }
};
