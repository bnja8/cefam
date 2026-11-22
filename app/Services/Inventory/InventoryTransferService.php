<?php

namespace App\Services\Inventory;

use App\Services\Alerts\StockAlertService;
use App\Services\Audit\AuditLogger;
use App\Support\Inventory\InventoryQuantitySnapshot;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InventoryTransferService
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly StockAlertService $alerts
    ) {}

    public function transfer(array $data, string $userRut): void
    {
        $quantity = $this->positiveQuantity($data['cantidad'] ?? null);
        if ((int) ($data['origen_id'] ?? 0) === (int) ($data['destino_id'] ?? 0)) {
            throw ValidationException::withMessages([
                'destino_id' => 'La ubicación de destino debe ser distinta de la ubicación de origen.',
            ]);
        }

        DB::transaction(function () use ($data, $quantity, $userRut): void {
            $locationCount = DB::table('ubicaciones')
                ->whereIn('ubicacion_id', [$data['origen_id'], $data['destino_id']])
                ->distinct()
                ->count('ubicacion_id');
            if ($locationCount !== 2) {
                throw new ModelNotFoundException('No se encontró una de las ubicaciones del traspaso.');
            }

            $source = DB::table('inventario')
                ->join('lotes', 'lotes.lote_id', '=', 'inventario.lote_id')
                ->join('productos', 'productos.producto_id', '=', 'lotes.producto_id')
                ->where('inventario.lote_id', $data['lote_id'])
                ->where('inventario.ubicacion_id', $data['origen_id'])
                ->where('productos.activo', true)
                ->where('lotes.estado', 'ACTIVO')
                ->whereDate('lotes.fecha_vencimiento', '>=', now()->toDateString())
                ->lockForUpdate()
                ->first([
                    'inventario.inventario_id',
                    'inventario.lote_id',
                    'inventario.cantidad',
                    'lotes.producto_id',
                ]);

            if (! $source || (float) $source->cantidad < $quantity) {
                throw ValidationException::withMessages(['cantidad' => 'La ubicación de origen no tiene stock suficiente.']);
            }

            $destination = DB::table('inventario')->where('lote_id', $data['lote_id'])
                ->where('ubicacion_id', $data['destino_id'])->lockForUpdate()->first();
            if ($destination) {
                DB::table('inventario')->where('inventario_id', $destination->inventario_id)->increment('cantidad', $quantity);
            } else {
                $destinationId = DB::table('inventario')->insertGetId([
                    'lote_id' => $data['lote_id'],
                    'ubicacion_id' => $data['destino_id'],
                    'cantidad' => $quantity,
                ]);
                $destination = (object) ['inventario_id' => $destinationId];
            }

            DB::table('inventario')->where('inventario_id', $source->inventario_id)->decrement('cantidad', $quantity);
            $transferId = DB::table('traspasos_inventario')->insertGetId([
                'origen_id' => $data['origen_id'],
                'destino_id' => $data['destino_id'],
                'usuario_rut' => $userRut,
                'observacion' => $data['observacion'],
            ]);
            DB::table('traspaso_detalle')->insert([
                'traspaso_id' => $transferId,
                'lote_id' => $data['lote_id'],
                'cantidad' => $quantity,
            ]);
            foreach ([
                [$data['origen_id'], 'TRASPASO_SALIDA'],
                [$data['destino_id'], 'TRASPASO_ENTRADA'],
            ] as [$locationId, $movementType]) {
                DB::table('movimientos_inventario')->insert([
                    'lote_id' => $data['lote_id'],
                    'ubicacion_id' => $locationId,
                    'tipo_movimiento' => $movementType,
                    'cantidad' => $quantity,
                    ...InventoryQuantitySnapshot::forBaseQuantity((int) $source->producto_id, $quantity),
                    'usuario_rut' => $userRut,
                    'referencia_tipo' => 'TRASPASO',
                    'referencia_id' => $transferId,
                    'observacion' => $data['observacion'],
                ]);
            }
            $this->alerts->check($data['lote_id'], $data['origen_id']);
            $this->alerts->check($data['lote_id'], $data['destino_id']);
            $this->audit->record('TRASPASO', 'inventario', $transferId, 'EXITOSO', $data);
        });
    }

    // Guarda los límites de stock configurados para un producto y ubicación.

    public function updateStockLimits(int $productId, array $data): void
    {
        $minimum = $this->nonNegativeQuantity($data['stock_minimo'] ?? null, 'stock_minimo');
        $maximum = isset($data['stock_maximo'])
            ? $this->nonNegativeQuantity($data['stock_maximo'], 'stock_maximo')
            : null;
        $locationId = $data['ubicacion_id'] ?? null;
        if (! is_numeric($locationId) || (int) $locationId <= 0) {
            throw ValidationException::withMessages([
                'ubicacion_id' => 'Selecciona una ubicación válida.',
            ]);
        }
        if ($maximum !== null && $maximum < $minimum) {
            throw ValidationException::withMessages([
                'stock_maximo' => 'El stock máximo no puede ser menor que el mínimo.',
            ]);
        }

        DB::transaction(function () use ($locationId, $productId, $minimum, $maximum): void {
            $product = DB::table('productos')->where('producto_id', $productId)
                ->lockForUpdate()->first(['producto_id']);
            if (! $product) {
                throw new ModelNotFoundException('No se encontró el producto solicitado.');
            }

            $location = DB::table('ubicaciones')->where('ubicacion_id', $locationId)
                ->lockForUpdate()->first(['ubicacion_id']);
            if (! $location) {
                throw new ModelNotFoundException('No se encontró la ubicación solicitada.');
            }

            DB::table('stock_minimos_ubicacion')->updateOrInsert(
                ['producto_id' => $productId, 'ubicacion_id' => $locationId],
                [
                    'stock_minimo' => $minimum,
                    'stock_maximo' => $maximum,
                ]
            );
            $this->audit->record('CONFIGURACION_STOCK', 'stock_minimos_ubicacion', $productId, 'EXITOSO', [
                'ubicacion_id' => $locationId,
                'stock_minimo' => $minimum,
                'stock_maximo' => $maximum,
            ]);
        });

        $this->alerts->refreshConfiguredStockAlerts();
    }

    // Valida cantidades positivas usadas en ajustes y traspasos.

    private function positiveQuantity(mixed $quantity, string $field = 'cantidad'): float
    {
        $quantity = $this->nonNegativeQuantity($quantity, $field);
        if ($quantity <= 0) {
            throw ValidationException::withMessages([
                $field => 'La cantidad debe ser mayor que cero.',
            ]);
        }

        return $quantity;
    }

    // Valida cantidades finitas que no pueden ser negativas.

    private function nonNegativeQuantity(mixed $quantity, string $field): float
    {
        if (! is_numeric($quantity) || ! is_finite((float) $quantity) || (float) $quantity < 0) {
            throw ValidationException::withMessages([
                $field => 'La cantidad debe ser un número igual o mayor que cero.',
            ]);
        }

        return (float) $quantity;
    }
}
