<?php

namespace App\Services\Alerts;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class StockAlertService
{
    public function __construct(
        private readonly StockThresholdAlertService $stockAlerts,
        private readonly StockExpiryAlertService $expiryAlerts,
        private readonly StockReplenishmentService $replenishment
    ) {}

    public function check(int $lotId, int $locationId): void
    {
        DB::transaction(function () use ($lotId, $locationId): void {
            $lot = DB::table('lotes')
                ->join('productos', 'productos.producto_id', '=', 'lotes.producto_id')
                ->where('lotes.lote_id', $lotId)
                ->lockForUpdate()
                ->first([
                    'lotes.producto_id',
                    'productos.nombre as producto',
                    'lotes.numero_lote',
                    'lotes.fecha_vencimiento',
                ]);
            if (! $lot) {
                throw new ModelNotFoundException('No se encontr? el lote solicitado.');
            }

            $location = DB::table('ubicaciones')
                ->where('ubicacion_id', $locationId)
                ->lockForUpdate()
                ->first(['nombre']);
            if (! $location) {
                throw new ModelNotFoundException('No se encontr? la ubicaci?n solicitada.');
            }

            $inventory = DB::table('inventario')
                ->where('lote_id', $lotId)
                ->where('ubicacion_id', $locationId)
                ->lockForUpdate()
                ->first(['cantidad']);
            $lot->cantidad = $inventory ? $inventory->cantidad : 0;
            $lot->ubicacion_id = $locationId;
            $lot->ubicacion = $location->nombre;

            $this->stockAlerts->refreshProductLocation($lot->producto_id, $locationId);
            $this->expiryAlerts->syncExpiryAlert($lotId, $locationId, $lot);
        });
    }

    public function refreshConfiguredStockAlerts(): Collection
    {
        return $this->stockAlerts->refreshConfiguredStockAlerts();
    }

    public function stockByLocationForProduct(int $productId): Collection
    {
        return DB::table('vw_stock_producto_ubicacion')
            ->where('producto_id', $productId)
            ->orderBy('ubicacion')
            ->get();
    }

    public function refreshExpiringAlerts(): void
    {
        $this->expiryAlerts->refreshExpiringAlerts();
    }

    public function checkForReplenishment(int $productId, string $userRut, ?int $locationId = null): void
    {
        $this->replenishment->checkForReplenishment($productId, $userRut, $locationId);
    }
}
