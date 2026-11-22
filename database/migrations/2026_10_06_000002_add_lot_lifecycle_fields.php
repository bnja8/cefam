<?php
// Actualiza el esquema de datos: 2026 10 06 000002 add lot lifecycle fields.


use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Aplica esta migración al esquema de la base de datos.
    public function up(): void
    {
        if (! Schema::hasColumn('lotes', 'fecha_recepcion')) {
            Schema::table('lotes', /* Actualiza la estructura de la tabla lotes. */ function (Blueprint $table) {
                $table->timestamp('fecha_recepcion')->nullable();
            });
        }

        $receiptDates = collect();
        if (Schema::hasTable('remesa_detalle') && Schema::hasTable('remesas')) {
            $receiptDates = DB::table('remesa_detalle')
                ->join('remesas', 'remesas.remesa_id', '=', 'remesa_detalle.remesa_id')
                ->join('lotes', /* Actualiza la estructura de la tabla lotes. */ function ($join) {
                    $join->on('lotes.producto_id', '=', 'remesa_detalle.producto_id')
                        ->on('lotes.numero_lote', '=', 'remesa_detalle.numero_lote')
                        ->on('lotes.fecha_vencimiento', '=', 'remesa_detalle.fecha_vencimiento');
                })
                ->whereNull('lotes.fecha_recepcion')
                ->groupBy('lotes.lote_id')
                ->select('lotes.lote_id')
                ->selectRaw('MIN(remesas.fecha_recepcion) as fecha_recepcion')
                ->pluck('fecha_recepcion', 'lote_id');
        }

        foreach ($receiptDates as $lotId => $receivedAt) {
            DB::table('lotes')->where('lote_id', $lotId)->update(['fecha_recepcion' => $receivedAt]);
        }

        if (Schema::hasTable('movimientos_inventario')) {
            $directReceiptDates = DB::table('movimientos_inventario')
                ->join('inventario', 'inventario.inventario_id', '=', 'movimientos_inventario.referencia_id')
                ->where('movimientos_inventario.referencia_tipo', 'RECEPCION')
                ->where('movimientos_inventario.tipo_movimiento', 'RECEPCION')
                ->whereNull('lotes.fecha_recepcion')
                ->join('lotes', 'lotes.lote_id', '=', 'inventario.lote_id')
                ->groupBy('lotes.lote_id')
                ->select('lotes.lote_id')
                ->selectRaw('MIN(movimientos_inventario.fecha_hora) as fecha_recepcion')
                ->pluck('fecha_recepcion', 'lote_id');

            foreach ($directReceiptDates as $lotId => $receivedAt) {
                DB::table('lotes')->where('lote_id', $lotId)->update(['fecha_recepcion' => $receivedAt]);
            }
        }

        $addedState = ! Schema::hasColumn('lotes', 'estado');
        if ($addedState) {
            Schema::table('lotes', /* Actualiza la estructura de la tabla lotes. */ function (Blueprint $table) {
                $table->string('estado', 20)->default('ACTIVO');
            });
            if (DB::getDriverName() === 'mysql') {
                DB::statement("ALTER TABLE lotes ADD CONSTRAINT chk_lotes_estado CHECK (estado IN ('ACTIVO', 'INACTIVO'))");
            }
        }
    }

    // Revierte los cambios de esta migración.
    public function down(): void
    {
        // These fields are part of the base schema; rolling back must not remove user data.
    }
};
