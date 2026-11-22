<?php
// Actualiza el esquema de datos: 2026 11 16 000001 add manufacturing dates to lots and receipts.


use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Aplica esta migración al esquema de la base de datos.
    public function up(): void
    {
        foreach (['lotes', 'remesa_detalle'] as $tableName) {
            if (! Schema::hasColumn($tableName, 'fecha_fabricacion')) {
                Schema::table($tableName, /* Callback que completa un paso de la operación actual. */ function (Blueprint $table) {
                    $table->date('fecha_fabricacion')->nullable()->after('numero_lote');
                });
            }
        }
    }

    // Revierte los cambios de esta migración.
    public function down(): void
    {
        foreach (['remesa_detalle', 'lotes'] as $tableName) {
            if (Schema::hasColumn($tableName, 'fecha_fabricacion')) {
                Schema::table($tableName, /* Callback que completa un paso de la operación actual. */ function (Blueprint $table) {
                    $table->dropColumn('fecha_fabricacion');
                });
            }
        }
    }
};
