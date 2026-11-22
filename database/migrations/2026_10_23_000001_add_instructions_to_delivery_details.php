<?php
// Actualiza el esquema de datos: 2026 10 23 000001 add instructions to delivery details.


use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Aplica esta migración al esquema de la base de datos.
    public function up(): void
    {
        if (! Schema::hasColumn('entrega_detalle', 'indicaciones')) {
            Schema::table('entrega_detalle', /* Actualiza la estructura de la tabla entrega_detalle. */ function (Blueprint $table) {
                $table->text('indicaciones')->nullable();
            });
        }
    }

    // Revierte los cambios de esta migración.
    public function down(): void
    {
        if (Schema::hasColumn('entrega_detalle', 'indicaciones')) {
            Schema::table('entrega_detalle', /* Actualiza la estructura de la tabla entrega_detalle. */ function (Blueprint $table) {
                $table->dropColumn('indicaciones');
            });
        }
    }
};
