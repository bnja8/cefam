<?php
// Actualiza el esquema de datos: 2026 10 22 000001 add professional status and seed foundation data.


use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
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

        if (Schema::hasTable('roles')) {
            foreach ([
                ['Administrador', 'Gestión global del sistema'],
                ['Técnico Farmacéutico', 'Operación de farmacia e inventario'],
                ['Químico Farmacéutico', 'Supervisión y validación farmacéutica'],
            ] as [$nombre, $descripcion]) {
                DB::table('roles')->insertOrIgnore([
                    'nombre' => $nombre,
                    'descripcion' => $descripcion,
                ]);
            }
        }

        if (Schema::hasTable('ubicaciones')) {
            foreach ([
                ['Bodega Central', 'BODEGA'],
                ['Vitrina Farmacia', 'VITRINA'],
                ['Carro de Paro', 'CARRO_PARO'],
                ['Carro Unidosis', 'CARRO_UNIDOSIS'],
            ] as [$nombre, $tipo]) {
                DB::table('ubicaciones')->insertOrIgnore([
                    'nombre' => $nombre,
                    'tipo' => $tipo,
                ]);
            }
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
