<?php
// Actualiza el esquema de datos: 2026 10 11 000001 add alert resolution timestamp.


use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Aplica esta migración al esquema de la base de datos.
    public function up(): void
    {
        if (! Schema::hasColumn('alertas', 'resuelta_at')) {
            Schema::table('alertas', /* Actualiza la estructura de la tabla alertas. */ function (Blueprint $table) {
                $table->timestamp('resuelta_at')->nullable()->default(null);
            });
        }
    }

    // Revierte los cambios de esta migración.
    public function down(): void
    {
        if (Schema::hasColumn('alertas', 'resuelta_at')) {
            Schema::table('alertas', /* Actualiza la estructura de la tabla alertas. */ function (Blueprint $table) {
                $table->dropColumn('resuelta_at');
            });
        }
    }
};
