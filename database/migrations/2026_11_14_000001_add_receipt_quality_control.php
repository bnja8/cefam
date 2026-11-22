<?php
// Actualiza el esquema de datos: 2026 11 14 000001 add receipt quality control.


use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Aplica esta migración al esquema de la base de datos.
    public function up(): void
    {
        Schema::table('remesa_detalle', /* Actualiza la estructura de la tabla remesa_detalle. */ function (Blueprint $table) {
            if (! Schema::hasColumn('remesa_detalle', 'cantidad_aceptada')) {
                $table->decimal('cantidad_aceptada', 12, 2)->nullable();
            }
            if (! Schema::hasColumn('remesa_detalle', 'cantidad_rechazada')) {
                $table->decimal('cantidad_rechazada', 12, 2)->default(0);
            }
            if (! Schema::hasColumn('remesa_detalle', 'motivo_rechazo')) {
                $table->string('motivo_rechazo', 500)->nullable();
            }
        });

        DB::table('remesa_detalle')
            ->whereNull('cantidad_aceptada')
            ->update(['cantidad_aceptada' => DB::raw('cantidad')]);
    }

    // Revierte los cambios de esta migración.
    public function down(): void
    {
        Schema::table('remesa_detalle', /* Actualiza la estructura de la tabla remesa_detalle. */ function (Blueprint $table) {
            $table->dropColumn(['cantidad_aceptada', 'cantidad_rechazada', 'motivo_rechazo']);
        });
    }
};
