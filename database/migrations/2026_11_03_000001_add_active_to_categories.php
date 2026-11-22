<?php
// Actualiza el esquema de datos: 2026 11 03 000001 add active to categories.


use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Aplica esta migración al esquema de la base de datos.
    public function up(): void
    {
        if (! Schema::hasColumn('categorias_producto', 'activo')) {
            Schema::table('categorias_producto', function (Blueprint $table): void {
                $table->boolean('activo')->default(true);
            });
        }
    }

    // Revierte los cambios de esta migración.
    public function down(): void
    {
        if (Schema::hasColumn('categorias_producto', 'activo')) {
            Schema::table('categorias_producto', function (Blueprint $table): void {
                $table->dropColumn('activo');
            });
        }
    }
};
