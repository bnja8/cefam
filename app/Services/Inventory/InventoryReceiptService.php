<?php

namespace App\Services\Inventory;

use App\Services\Alerts\StockAlertService;
use App\Services\Audit\AuditLogger;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InventoryReceiptService
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly StockAlertService $alerts,
        private readonly InventoryQuantityConverter $quantityConverter,
        private readonly InventoryKardexService $kardex
    ) {}

    public function receive(array $data, string $userRut): void
    {
        $rawRejectedQuantity = $data['cantidad_rechazada'] ?? 0;
        if (! is_numeric($rawRejectedQuantity)
            || ! is_finite((float) $rawRejectedQuantity)
            || (float) $rawRejectedQuantity < 0
        ) {
            throw ValidationException::withMessages([
                'cantidad_rechazada' => 'La cantidad rechazada debe ser igual o mayor que cero.',
            ]);
        }
        $rejectedQuantity = (float) $rawRejectedQuantity;

        $manufacturingDate = $data['fecha_fabricacion'] ?? null;
        $receiptDate = Carbon::parse($data['fecha_recepcion'] ?? now())->toDateTimeString();

        DB::transaction(function () use ($data, $manufacturingDate, $receiptDate, $rejectedQuantity, $userRut): void {
            $productIsActive = DB::table('productos')->where('producto_id', $data['producto_id'])
                ->where('activo', true)->lockForUpdate()->first(['producto_id']);
            if (! $productIsActive) {
                throw ValidationException::withMessages(['producto_id' => 'No se puede recibir stock de un producto inactivo.']);
            }

            $locationExists = DB::table('ubicaciones')
                ->where('ubicacion_id', $data['ubicacion_id'])
                ->exists();
            if (! $locationExists) {
                throw new ModelNotFoundException('No se encontró la ubicación solicitada.');
            }

            $quantitySnapshot = $this->quantityConverter->resolve($data);
            if ($rejectedQuantity > $quantitySnapshot['base_quantity']) {
                throw ValidationException::withMessages([
                    'cantidad_rechazada' => 'La cantidad rechazada no puede superar la cantidad total convertida a unidad base.',
                ]);
            }
            $acceptedQuantity = $quantitySnapshot['base_quantity'] - $rejectedQuantity;
            $lot = DB::table('lotes')->where('producto_id', $data['producto_id'])
                ->where('numero_lote', $data['numero_lote'])->lockForUpdate()->first();

            if ($lot) {
                $lotId = $lot->lote_id;
                if ($lot->estado !== 'ACTIVO') {
                    throw ValidationException::withMessages([
                        'numero_lote' => 'El lote existe, pero está inactivo y no puede recibir nuevas existencias.',
                    ]);
                }
                if ($lot->fecha_vencimiento !== $data['fecha_vencimiento']) {
                    throw ValidationException::withMessages([
                        'numero_lote' => 'Ese número de lote ya está asociado a otra fecha de vencimiento. Ingresa el número de lote que aparece en el nuevo envase; cada lote con vencimiento distinto se registra por separado.',
                    ]);
                }
                if ($lot->fecha_fabricacion && $manufacturingDate && $lot->fecha_fabricacion !== $manufacturingDate) {
                    throw ValidationException::withMessages([
                        'fecha_fabricacion' => 'La fecha de fabricación no coincide con la registrada para este lote.',
                    ]);
                }
                if (! $lot->fecha_fabricacion && $manufacturingDate) {
                    DB::table('lotes')->where('lote_id', $lotId)->update([
                        'fecha_fabricacion' => $manufacturingDate,
                    ]);
                }
            } else {
                $lotId = DB::table('lotes')->insertGetId([
                    'producto_id' => $data['producto_id'],
                    'numero_lote' => $data['numero_lote'],
                    'fecha_fabricacion' => $manufacturingDate,
                    'fecha_vencimiento' => $data['fecha_vencimiento'],
                    'fecha_recepcion' => $receiptDate,
                    'estado' => 'ACTIVO',
                ]);
            }

            $inventoryId = null;
            if ($acceptedQuantity > 0) {
                $inventory = DB::table('inventario')
                    ->where('lote_id', $lotId)
                    ->where('ubicacion_id', $data['ubicacion_id'])
                    ->lockForUpdate()
                    ->first(['inventario_id', 'cantidad']);

                if ($inventory && (float) $inventory->cantidad <= 0) {
                    $this->kardex->archiveEmptyInventory(
                        (int) $inventory->inventario_id,
                        $userRut,
                        'RECEPCION',
                        null,
                        $data['documento_recepcion']
                    );
                    $inventory = null;
                }

                if ($inventory) {
                    $inventoryId = $inventory->inventario_id;
                    DB::table('inventario')->where('inventario_id', $inventoryId)->increment('cantidad', $acceptedQuantity);
                } else {
                    $inventoryId = DB::table('inventario')->insertGetId([
                        'lote_id' => $lotId,
                        'ubicacion_id' => $data['ubicacion_id'],
                        'cantidad' => $acceptedQuantity,
                    ]);
                }

                DB::table('movimientos_inventario')->insert([
                    'lote_id' => $lotId,
                    'ubicacion_id' => $data['ubicacion_id'],
                    'tipo_movimiento' => 'RECEPCION',
                    'cantidad' => $acceptedQuantity,
                    ...$this->quantityConverter->movementSnapshot($quantitySnapshot),
                    'usuario_rut' => $userRut,
                    'referencia_tipo' => 'RECEPCION',
                    'referencia_id' => $inventoryId,
                    'observacion' => 'Proveedor: '.$data['origen'].' · Documento: '.$data['documento_recepcion'],
                ]);
                $this->kardex->archiveEmptyInventoryRowsForProductAtLocation(
                    (int) $data['producto_id'],
                    (int) $data['ubicacion_id'],
                    $userRut,
                    'RECEPCION',
                    (int) $inventoryId,
                    $data['documento_recepcion'],
                    'RECEPCION_OTRO_LOTE',
                    'Fila de inventario agotada archivada al ingresar otro lote en la misma ubicación.'
                );
                $this->alerts->check($lotId, $data['ubicacion_id']);
            }

            $this->audit->record('RECEPCION', $inventoryId ? 'inventario' : 'lotes', $inventoryId ?? $lotId, 'EXITOSO', [
                'producto_id' => $data['producto_id'],
                'lote' => $data['numero_lote'],
                'fecha_fabricacion' => $manufacturingDate,
                'cantidad_recibida' => $quantitySnapshot['base_quantity'],
                'conversion' => $quantitySnapshot,
                'cantidad_aceptada' => $acceptedQuantity,
                'cantidad_rechazada' => $rejectedQuantity,
                'motivo_rechazo' => $rejectedQuantity > 0 ? trim($data['motivo_rechazo']) : null,
                'origen' => $data['origen'],
                'documento_recepcion' => $data['documento_recepcion'],
                'fecha_recepcion' => $receiptDate,
            ]);
        });
    }
}
