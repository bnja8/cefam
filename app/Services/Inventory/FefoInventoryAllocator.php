<?php
// Asigna existencias por fecha de vencimiento con el criterio FEFO.


namespace App\Services\Inventory;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use LogicException;

class FefoInventoryAllocator
{
    // Descuenta las unidades disponibles respetando el orden FEFO.
    public function consume(
        int $productId,
        array $locationIds,
        float $quantity,
        string $asOfDate,
        string $errorMessage,
        string $errorField = 'cantidad'
    ): Collection {
        if (DB::transactionLevel() === 0) {
            throw new LogicException('FEFO inventory allocation must run inside a database transaction.');
        }
        if ($quantity <= 0 || ! is_finite($quantity)) {
            throw ValidationException::withMessages([
                $errorField => 'La cantidad a descontar debe ser mayor que cero.',
            ]);
        }
        if ($locationIds === []) {
            throw ValidationException::withMessages([
                $errorField => 'No hay ubicaciones habilitadas para descontar existencias.',
            ]);
        }

        $lots = DB::table('inventario')
            ->join('lotes', 'lotes.lote_id', '=', 'inventario.lote_id')
            ->where('lotes.producto_id', $productId)
            ->where('lotes.estado', 'ACTIVO')
            ->whereIn('inventario.ubicacion_id', $locationIds)
            ->where('inventario.cantidad', '>', 0)
            ->whereDate('lotes.fecha_vencimiento', '>=', $asOfDate)
            ->orderBy('lotes.fecha_vencimiento')
            ->orderBy('lotes.lote_id')
            ->orderBy('inventario.ubicacion_id')
            ->lockForUpdate()
            ->get([
                'inventario.inventario_id',
                'inventario.lote_id',
                'inventario.ubicacion_id',
                'inventario.cantidad',
                'lotes.numero_lote',
                'lotes.fecha_vencimiento',
            ]);

        $remaining = $quantity;
        $allocations = collect();
        foreach ($lots as $lot) {
            if ($remaining <= 0) {
                break;
            }

            $allocated = min($remaining, (float) $lot->cantidad);
            DB::table('inventario')->where('inventario_id', $lot->inventario_id)->decrement('cantidad', $allocated);
            $lot->cantidad_asignada = $allocated;
            $allocations->push($lot);
            $remaining -= $allocated;
        }

        if ($remaining > 0.00001) {
            throw ValidationException::withMessages([$errorField => $errorMessage]);
        }

        return $allocations;
    }
}
