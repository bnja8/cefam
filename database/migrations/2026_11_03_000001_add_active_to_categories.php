<?php
// Actualiza el esquema de datos: 2026 11 03 000001 add active to categories.


use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Aplica esta migración al esquema de la base de datos.
    public function up(): void
    {
        if (! Schema::hasColumn('categorias_producto', 'activo')) {
            DB::statement('ALTER TABLE categorias_producto ADD COLUMN activo BOOLEAN NOT NULL DEFAULT 1');
        }
    }

    // Revierte los cambios de esta migración.
    public function down(): void
    {
        if (Schema::hasColumn('categorias_producto', 'activo')) {
            DB::statement('ALTER TABLE categorias_producto DROP COLUMN activo');
        }
    }
};
