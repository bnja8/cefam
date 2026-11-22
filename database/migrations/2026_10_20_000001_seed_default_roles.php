<?php
// Actualiza el esquema de datos: 2026 10 20 000001 seed default roles.


use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    // Aplica esta migración al esquema de la base de datos.
    public function up(): void
    {
        DB::table('roles')->insertOrIgnore([
            ['nombre' => 'Administrador', 'descripcion' => 'Gestión global del sistema'],
            ['nombre' => 'Técnico Farmacéutico', 'descripcion' => 'Operación de farmacia e inventario'],
            ['nombre' => 'Químico Farmacéutico', 'descripcion' => 'Supervisión y validación farmacéutica'],
        ]);
    }

    // Revierte los cambios de esta migración.
    public function down(): void
    {
        // Roles pueden estar asignados a usuarios; no se eliminan al revertir la migración.
    }
};
