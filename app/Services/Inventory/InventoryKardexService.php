<?php

namespace App\Services\Inventory;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InventoryKardexService
{
    public function archiveEmptyInventoryIfAnotherLotHasStock(
        int $inventoryId,
        string $userRut,
        string $referenceType,
        ?int $referenceId,
        ?string $receiptDocument,
        string $reason,
        string $observation
    ): bool {
        if (DB::transactionLevel() === 0) {
            throw new \LogicException('Inventory archival must run inside a database transaction.');
        }

        $inventory = DB::table('inventario')
            ->join('lotes', 'lotes.lote_id', '=', 'inventario.lote_id')
            ->where('inventario.inventario_id', $inventoryId)
            ->lockForUpdate()
            ->first([
                'inventario.cantidad',
                'inventario.lote_id',
                'inventario.ubicacion_id',
                'lotes.producto_id',
            ]);

        if (! $inventory || (float) $inventory->cantidad > 0) {
            return false;
        }

        $hasAvailableOtherLot = DB::table('inventario')
            ->join('lotes', 'lotes.lote_id', '=', 'inventario.lote_id')
            ->where('lotes.producto_id', $inventory->producto_id)
            ->where('lotes.estado', 'ACTIVO')
            ->whereDate('lotes.fecha_vencimiento', '>=', today()->toDateString())
            ->where('inventario.ubicacion_id', $inventory->ubicacion_id)
            ->where('inventario.inventario_id', '!=', $inventoryId)
            ->where('inventario.cantidad', '>', 0)
            ->exists();

        if (! $hasAvailableOtherLot) {
            return false;
        }

        return $this->archiveEmptyInventory(
            $inventoryId,
            $userRut,
            $referenceType,
            $referenceId,
            $receiptDocument,
            $reason,
            $observation
        );
    }

    public function archiveEmptyInventoryRowsForProductAtLocation(
        int $productId,
        int $locationId,
        string $userRut,
        string $referenceType,
        ?int $referenceId,
        ?string $receiptDocument,
        string $reason,
        string $observation
    ): int {
        if (DB::transactionLevel() === 0) {
            throw new \LogicException('Inventory archival must run inside a database transaction.');
        }

        $emptyInventoryIds = DB::table('inventario')
            ->join('lotes', 'lotes.lote_id', '=', 'inventario.lote_id')
            ->where('lotes.producto_id', $productId)
            ->where('inventario.ubicacion_id', $locationId)
            ->where('inventario.cantidad', '<=', 0)
            ->orderBy('inventario.inventario_id')
            ->lockForUpdate()
            ->pluck('inventario.inventario_id');

        $archivedCount = 0;
        foreach ($emptyInventoryIds as $inventoryId) {
            if ($this->archiveEmptyInventoryIfAnotherLotHasStock(
                (int) $inventoryId,
                $userRut,
                $referenceType,
                $referenceId,
                $receiptDocument,
                $reason,
                $observation
            )) {
                $archivedCount++;
            }
        }

        return $archivedCount;
    }

    public function archiveEmptyInventory(
        int $inventoryId,
        string $userRut,
        string $referenceType,
        ?int $referenceId,
        ?string $receiptDocument,
        string $reason = 'REINGRESO_MISMO_LOTE',
        string $observation = 'Fila de inventario sin stock archivada al reingresar el mismo lote en la misma ubicación.'
    ): bool {
        if (DB::transactionLevel() === 0) {
            throw new \LogicException('Inventory archival must run inside a database transaction.');
        }

        $inventory = DB::table('inventario')
            ->where('inventario_id', $inventoryId)
            ->lockForUpdate()
            ->first();

        if (! $inventory || (float) $inventory->cantidad > 0) {
            return false;
        }

        DB::table('kardex_inventario')->insert([
            'inventario_id_origen' => $inventory->inventario_id,
            'lote_id' => $inventory->lote_id,
            'ubicacion_id' => $inventory->ubicacion_id,
            'cantidad_final' => $inventory->cantidad,
            'motivo' => $reason,
            'referencia_tipo' => $referenceType,
            'referencia_id' => $referenceId,
            'documento_recepcion' => $receiptDocument,
            'observacion' => $observation,
            'usuario_rut' => $userRut,
            'fecha_hora' => now(),
        ]);

        $deleted = DB::table('inventario')
            ->where('inventario_id', $inventoryId)
            ->where('cantidad', '<=', 0)
            ->delete();

        if ($deleted !== 1) {
            throw ValidationException::withMessages([
                'inventario' => 'No fue posible archivar el registro de inventario agotado.',
            ]);
        }

        return true;
    }
}
