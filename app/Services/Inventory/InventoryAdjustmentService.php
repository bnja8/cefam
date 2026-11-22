<?php

namespace App\Services\Inventory;

use App\Services\Alerts\StockAlertService;
use App\Services\Audit\AuditLogger;
use App\Support\Inventory\InventoryQuantitySnapshot;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InventoryAdjustmentService
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly StockAlertService $alerts
    ) {}

    public function requestAdjustment(array $data, string $requesterRut): int
    {
        $quantity = $this->positiveQuantity($data['cantidad'] ?? null);
        if (! in_array($data['tipo_ajuste'] ?? null, ['ENTRADA', 'SALIDA'], true)) {
            throw ValidationException::withMessages([
                'tipo_ajuste' => 'El tipo de ajuste no es válido.',
            ]);
        }

        return DB::transaction(function () use ($data, $quantity, $requesterRut): int {
            $requesterIsPharmacist = DB::table('usuario_rol')
                ->join('roles', 'roles.rol_id', '=', 'usuario_rol.rol_id')
                ->where('usuario_rol.usuario_rut', $requesterRut)
                ->where('roles.nombre', 'Químico Farmacéutico')
                ->exists();
            $requestId = DB::table('solicitudes_ajuste_inventario')->insertGetId([
                'lote_id' => $data['lote_id'],
                'ubicacion_id' => $data['ubicacion_id'],
                'tipo_ajuste' => $data['tipo_ajuste'],
                'cantidad' => $quantity,
                'motivo' => trim($data['motivo']),
                'estado' => 'PENDIENTE',
                'usuario_solicita_rut' => $requesterRut,
                'solicitante_es_qf' => $requesterIsPharmacist,
            ]);
            $this->audit->record('SOLICITUD_AJUSTE_INVENTARIO', 'solicitudes_ajuste_inventario', $requestId, 'PENDIENTE', [
                'lote_id' => $data['lote_id'],
                'ubicacion_id' => $data['ubicacion_id'],
                'tipo_ajuste' => $data['tipo_ajuste'],
                'cantidad' => $quantity,
                'motivo' => trim($data['motivo']),
            ]);

            return $requestId;
        });
    }

    public function resolveAdjustmentRequest(
        int $requestId,
        string $approverRut,
        string $decision,
        ?string $resolutionReason = null
    ): void {
        if (! in_array($decision, ['APROBAR', 'RECHAZAR'], true)) {
            throw ValidationException::withMessages(['decision' => 'La decisión de autorización no es válida.']);
        }
        if ($decision === 'RECHAZAR' && trim((string) $resolutionReason) === '') {
            throw ValidationException::withMessages(['motivo_resolucion' => 'Indica el motivo del rechazo.']);
        }

        DB::transaction(function () use ($requestId, $approverRut, $decision, $resolutionReason): void {
            $adjustmentRequest = DB::table('solicitudes_ajuste_inventario')
                ->where('solicitud_ajuste_id', $requestId)
                ->lockForUpdate()
                ->first();
            if (! $adjustmentRequest) {
                throw new ModelNotFoundException('No se encontró la solicitud de ajuste.');
            }
            if ($adjustmentRequest->estado !== 'PENDIENTE') {
                throw ValidationException::withMessages([
                    'decision' => 'Esta solicitud ya fue resuelta y no puede modificarse.',
                ]);
            }

            if ($adjustmentRequest->solicitante_es_qf && $adjustmentRequest->usuario_solicita_rut === $approverRut) {
                throw ValidationException::withMessages([
                    'decision' => 'La solicitud del Químico Farmacéutico debe autorizarla otro Químico Farmacéutico.',
                ]);
            }

            $movementId = null;
            if ($decision === 'APROBAR') {
                $movementId = $this->adjust([
                    'lote_id' => $adjustmentRequest->lote_id,
                    'ubicacion_id' => $adjustmentRequest->ubicacion_id,
                    'tipo_ajuste' => $adjustmentRequest->tipo_ajuste,
                    'cantidad' => $adjustmentRequest->cantidad,
                    'motivo' => $adjustmentRequest->motivo,
                ], $approverRut, $requestId, $adjustmentRequest->usuario_solicita_rut);
            }

            $newStatus = $decision === 'APROBAR' ? 'APROBADA' : 'RECHAZADA';
            DB::table('solicitudes_ajuste_inventario')
                ->where('solicitud_ajuste_id', $requestId)
                ->where('estado', 'PENDIENTE')
                ->update([
                    'estado' => $newStatus,
                    'usuario_autoriza_rut' => $approverRut,
                    'movimiento_id' => $movementId,
                    'motivo_resolucion' => $resolutionReason ? trim($resolutionReason) : null,
                    'fecha_resolucion' => now(),
                ]);
            $this->audit->record(
                $decision === 'APROBAR' ? 'AUTORIZACION_AJUSTE_INVENTARIO' : 'RECHAZO_AJUSTE_INVENTARIO',
                'solicitudes_ajuste_inventario',
                $requestId,
                $newStatus,
                [
                    'usuario_solicita_rut' => $adjustmentRequest->usuario_solicita_rut,
                    'usuario_autoriza_rut' => $approverRut,
                    'movimiento_id' => $movementId,
                    'motivo_resolucion' => $resolutionReason ? trim($resolutionReason) : null,
                ]
            );
        });
    }

    public function adjust(
        array $data,
        string $userRut,
        ?int $adjustmentRequestId = null,
        ?string $requesterRut = null
    ): int {
        $quantity = $this->positiveQuantity($data['cantidad'] ?? null);
        if (! in_array($data['tipo_ajuste'] ?? null, ['ENTRADA', 'SALIDA'], true)) {
            throw ValidationException::withMessages([
                'tipo_ajuste' => 'El tipo de ajuste no es válido.',
            ]);
        }

        return DB::transaction(function () use ($data, $quantity, $userRut, $adjustmentRequestId, $requesterRut): int {
            $lot = DB::table('lotes')
                ->join('productos', 'productos.producto_id', '=', 'lotes.producto_id')
                ->where('lotes.lote_id', $data['lote_id'])
                ->lockForUpdate()
                ->first([
                    'lotes.lote_id',
                    'lotes.producto_id',
                    'lotes.estado',
                    'lotes.fecha_vencimiento',
                    'productos.activo as producto_activo',
                ]);
            if (! $lot) {
                throw ValidationException::withMessages(['lote_id' => 'No se encontró el lote seleccionado.']);
            }

            if (! DB::table('ubicaciones')->where('ubicacion_id', $data['ubicacion_id'])->exists()) {
                throw new ModelNotFoundException('No se encontró la ubicación solicitada.');
            }

            $inventory = DB::table('inventario')
                ->where('lote_id', $lot->lote_id)
                ->where('ubicacion_id', $data['ubicacion_id'])
                ->lockForUpdate()
                ->first();

            if ($data['tipo_ajuste'] === 'ENTRADA') {
                if (! $lot->producto_activo || $lot->estado !== 'ACTIVO' || $lot->fecha_vencimiento < today()->toDateString()) {
                    throw ValidationException::withMessages([
                        'lote_id' => 'Solo se puede agregar stock a un lote activo, vigente y de un producto activo.',
                    ]);
                }

                if ($inventory) {
                    DB::table('inventario')
                        ->where('inventario_id', $inventory->inventario_id)
                        ->increment('cantidad', $quantity);
                    $inventoryId = $inventory->inventario_id;
                } else {
                    $inventoryId = DB::table('inventario')->insertGetId([
                        'lote_id' => $lot->lote_id,
                        'ubicacion_id' => $data['ubicacion_id'],
                        'cantidad' => $quantity,
                    ]);
                }
                $movementType = 'AJUSTE_ENTRADA';
            } else {
                if (! $inventory || (float) $inventory->cantidad < $quantity) {
                    throw ValidationException::withMessages([
                        'cantidad' => 'El ajuste de salida no puede superar las existencias físicas de esa ubicación.',
                    ]);
                }
                DB::table('inventario')
                    ->where('inventario_id', $inventory->inventario_id)
                    ->decrement('cantidad', $quantity);
                $inventoryId = $inventory->inventario_id;
                $movementType = 'AJUSTE_SALIDA';
            }

            $movementId = DB::table('movimientos_inventario')->insertGetId([
                'lote_id' => $lot->lote_id,
                'ubicacion_id' => $data['ubicacion_id'],
                'tipo_movimiento' => $movementType,
                'cantidad' => $quantity,
                ...InventoryQuantitySnapshot::forBaseQuantity((int) $lot->producto_id, $quantity),
                'usuario_rut' => $userRut,
                'referencia_tipo' => $adjustmentRequestId ? 'SOLICITUD_AJUSTE' : 'AJUSTE_MANUAL',
                'referencia_id' => $adjustmentRequestId,
                'observacion' => $data['motivo'],
            ]);
            $this->alerts->check($lot->lote_id, $data['ubicacion_id']);
            $this->audit->record('AJUSTE_INVENTARIO', 'movimientos_inventario', $movementId, 'EXITOSO', [
                'producto_id' => $lot->producto_id,
                'lote_id' => $lot->lote_id,
                'ubicacion_id' => $data['ubicacion_id'],
                'inventario_id' => $inventoryId,
                'tipo_movimiento' => $movementType,
                'cantidad' => $quantity,
                'motivo' => $data['motivo'],
                'solicitud_ajuste_id' => $adjustmentRequestId,
                'usuario_solicita_rut' => $requesterRut,
            ]);

            return $movementId;
        });
    }

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
