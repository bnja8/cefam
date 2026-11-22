<?php

namespace App\Actions\Supplies;

use App\Rules\FourDigitDate;
use App\Rules\FourDigitDateTime;
use App\Models\Usuario;
use App\Services\Audit\AuditLogger;
use App\Services\Inventory\InventoryKardexService;
use App\Services\Inventory\InventoryQuantityConverter;
use App\Services\Alerts\StockAlertService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SupplyReceiptAction
{
    private const SUPPLY_OPERATORS = ['Administrador', 'Químico Farmacéutico', 'Técnico Farmacéutico'];

    public function receive(
        Request $request,
        AuditLogger $audit,
        StockAlertService $alerts,
        InventoryQuantityConverter $quantityConverter,
        InventoryKardexService $kardex
    ): RedirectResponse|JsonResponse {
        $this->authorizeSupplyOperation($audit, 'remesas');
        $data = $request->validate([
            'codigo_remesa' => ['required', 'string', 'max:100', Rule::unique('remesas', 'codigo_remesa')],
            'origen' => ['required', 'string', 'max:150'],
            'fecha_recepcion' => ['required', 'bail', new FourDigitDateTime(), 'before_or_equal:now'],
            'solicitud_id' => ['nullable', 'integer', 'exists:solicitudes_reposicion,solicitud_id'],
            'detalles' => ['required', 'array', 'min:1', 'max:100'],
            'detalles.*.producto_id' => ['required', 'integer', 'exists:productos,producto_id,activo,1'],
            'detalles.*.ubicacion_id' => ['required', 'integer', 'exists:ubicaciones,ubicacion_id'],
            'detalles.*.numero_lote' => ['required', 'string', 'max:100'],
            'detalles.*.fecha_fabricacion' => ['nullable', 'bail', new FourDigitDate(), 'before_or_equal:today', 'before:detalles.*.fecha_vencimiento'],
            'detalles.*.fecha_vencimiento' => ['required', 'bail', new FourDigitDate(), 'after_or_equal:today'],
            'detalles.*.cantidad' => ['nullable', 'numeric', 'decimal:0,2', 'gt:0', 'required_without_all:detalles.*.cantidad_envases,detalles.*.unidades_sueltas'],
            'detalles.*.presentacion_id' => ['nullable', 'integer', 'exists:presentaciones_producto,presentacion_id'],
            'detalles.*.cantidad_envases' => ['nullable', 'integer', 'gte:0'],
            'detalles.*.unidades_por_envase' => ['nullable', 'numeric', 'decimal:0,4', 'gt:0'],
            'detalles.*.unidades_sueltas' => ['nullable', 'numeric', 'decimal:0,2', 'gte:0'],
            'detalles.*.cantidad_rechazada' => ['nullable', 'numeric', 'decimal:0,2', 'gte:0'],
            'detalles.*.motivo_rechazo' => ['nullable', 'string', 'max:500'],
        ]);

        $lineKeys = array_map(
            // Transforma cada elemento de la colección.
            fn ($detail) => $detail['producto_id'].'|'.mb_strtolower(trim($detail['numero_lote'])),
            $data['detalles']
        );
        if (count($lineKeys) !== count(array_unique($lineKeys))) {
            throw ValidationException::withMessages(['detalles' => 'Un mismo producto y lote solo puede aparecer una vez por remesa.']);
        }
        foreach ($data['detalles'] as $index => $detail) {
            $rejectedQuantity = (float) ($detail['cantidad_rechazada'] ?? 0);
            if ($rejectedQuantity > 0 && trim((string) ($detail['motivo_rechazo'] ?? '')) === '') {
                throw ValidationException::withMessages([
                    "detalles.$index.motivo_rechazo" => 'Indica el motivo de rechazo para dejar trazabilidad del control de calidad.',
                ]);
            }
        }

        $shipmentId = DB::transaction(function () use ($data, $audit, $alerts, $quantityConverter, $kardex) {
            foreach ($data['detalles'] as $index => $detail) {
                $snapshot = $quantityConverter->resolve($detail, "detalles.$index");
                $rejectedQuantity = (float) ($detail['cantidad_rechazada'] ?? 0);
                if ($rejectedQuantity > $snapshot['base_quantity']) {
                    throw ValidationException::withMessages([
                        "detalles.$index.cantidad_rechazada" => 'La cantidad rechazada no puede superar la cantidad total convertida a unidad base.',
                    ]);
                }
                $data['detalles'][$index]['quantity_snapshot'] = $snapshot;
            }

            $reorderRequest = null;
            if (! empty($data['solicitud_id'])) {
                $reorderRequest = DB::table('solicitudes_reposicion')
                    ->where('solicitud_id', $data['solicitud_id'])
                    ->lockForUpdate()
                    ->first(['solicitud_id', 'estado']);
                if (! $reorderRequest || ! in_array($reorderRequest->estado, ['PENDIENTE', 'SOLICITADA'], true)) {
                    throw ValidationException::withMessages([
                        'solicitud_id' => 'La solicitud ya fue recibida, cancelada o no está disponible.',
                    ]);
                }

                $requestedLines = DB::table('solicitud_reposicion_detalle')
                    ->where('solicitud_id', $reorderRequest->solicitud_id)
                    ->lockForUpdate()
                    ->get(['producto_id', 'cantidad_solicitada'])
                    ->keyBy('producto_id');
                $requestedProductIds = $requestedLines->keys()
                    ->map(fn ($productId) => (int) $productId)
                    ->sort()
                    ->values()
                    ->all();
                $receivedProductIds = collect($data['detalles'])
                    ->pluck('producto_id')
                    ->map(fn ($productId) => (int) $productId)
                    ->unique()
                    ->sort()
                    ->values()
                    ->all();
                if ($requestedProductIds !== $receivedProductIds) {
                    throw ValidationException::withMessages([
                        'detalles' => 'Los productos de la remesa deben coincidir con los de la solicitud seleccionada.',
                    ]);
                }
            }

            $shipmentId = DB::table('remesas')->insertGetId([
                'codigo_remesa' => $data['codigo_remesa'],
                'origen' => $data['origen'],
                'fecha_recepcion' => $data['fecha_recepcion'],
                'solicitud_id' => $data['solicitud_id'] ?? null,
                'usuario_rut' => Auth::id(),
            ]);

            foreach ($data['detalles'] as $detail) {
                $rejectedQuantity = (float) ($detail['cantidad_rechazada'] ?? 0);
                $quantitySnapshot = $detail['quantity_snapshot'];
                $acceptedQuantity = $quantitySnapshot['base_quantity'] - $rejectedQuantity;
                $location = DB::table('ubicaciones')
                    ->where('ubicacion_id', $detail['ubicacion_id'])
                    ->lockForUpdate()
                    ->first(['ubicacion_id']);
                if (! $location) {
                    throw ValidationException::withMessages([
                        'detalles' => 'Una de las ubicaciones seleccionadas ya no está disponible.',
                    ]);
                }
                $locationId = (int) $location->ubicacion_id;
                $productIsActive = DB::table('productos')->where('producto_id', $detail['producto_id'])
                    ->where('activo', true)->lockForUpdate()->first(['producto_id']);
                if (! $productIsActive) {
                    throw ValidationException::withMessages([
                        'detalles' => 'No se puede recibir stock de un producto inactivo.',
                    ]);
                }

                $lotId = DB::table('lotes')
                    ->where('producto_id', $detail['producto_id'])
                    ->where('numero_lote', $detail['numero_lote'])
                    ->lockForUpdate()
                    ->value('lote_id');
                if ($lotId) {
                    $existingLot = DB::table('lotes')->where('lote_id', $lotId)
                        ->first(['fecha_fabricacion', 'fecha_vencimiento', 'estado']);
                    if ($existingLot->estado !== 'ACTIVO') {
                        throw ValidationException::withMessages([
                            'detalles' => "El lote {$detail['numero_lote']} está inactivo y no puede recibir nuevas existencias.",
                        ]);
                    }
                    if ($existingLot->fecha_vencimiento !== $detail['fecha_vencimiento']) {
                        throw ValidationException::withMessages([
                            'detalles' => "El lote {$detail['numero_lote']} ya existe con otra fecha de vencimiento.",
                        ]);
                    }
                    $manufacturingDate = $detail['fecha_fabricacion'] ?? null;
                    if ($existingLot->fecha_fabricacion && $manufacturingDate && $existingLot->fecha_fabricacion !== $manufacturingDate) {
                        throw ValidationException::withMessages([
                            "detalles.$index.fecha_fabricacion" => 'La fecha de fabricación no coincide con la registrada para este lote.',
                        ]);
                    }
                    $manufacturingDate = $manufacturingDate ?? $existingLot->fecha_fabricacion;
                    if (! $existingLot->fecha_fabricacion && $manufacturingDate) {
                        DB::table('lotes')->where('lote_id', $lotId)->update([
                            'fecha_fabricacion' => $manufacturingDate,
                        ]);
                    }
                } else {
                    $manufacturingDate = $detail['fecha_fabricacion'] ?? null;
                    $lotId = DB::table('lotes')->insertGetId([
                        'producto_id' => $detail['producto_id'],
                        'numero_lote' => $detail['numero_lote'],
                        'fecha_fabricacion' => $manufacturingDate,
                        'fecha_vencimiento' => $detail['fecha_vencimiento'],
                        'fecha_recepcion' => $data['fecha_recepcion'],
                        'estado' => 'ACTIVO',
                    ]);
                }
                $data['detalles'][$index]['fecha_fabricacion'] = $manufacturingDate;

                DB::table('remesa_detalle')->insert([
                    'remesa_id' => $shipmentId,
                    'producto_id' => $detail['producto_id'],
                    'numero_lote' => $detail['numero_lote'],
                    'fecha_fabricacion' => $manufacturingDate,
                    'fecha_vencimiento' => $detail['fecha_vencimiento'],
                    'cantidad' => $quantitySnapshot['base_quantity'],
                    'cantidad_aceptada' => $acceptedQuantity,
                    'cantidad_rechazada' => $rejectedQuantity,
                    'motivo_rechazo' => $rejectedQuantity > 0 ? trim($detail['motivo_rechazo']) : null,
                    ...$quantityConverter->remesaDetailSnapshot($quantitySnapshot),
                ]);

                if ($acceptedQuantity > 0) {
                    $inventory = DB::table('inventario')
                        ->where('lote_id', $lotId)
                        ->where('ubicacion_id', $locationId)
                        ->lockForUpdate()
                        ->first();
                    if ($inventory && (float) $inventory->cantidad <= 0) {
                        $kardex->archiveEmptyInventory(
                            (int) $inventory->inventario_id,
                            (string) Auth::id(),
                            'REMESA',
                            (int) $shipmentId,
                            $data['codigo_remesa']
                        );
                        $inventory = null;
                    }
                    if ($inventory) {
                        DB::table('inventario')->where('inventario_id', $inventory->inventario_id)->increment('cantidad', $acceptedQuantity);
                    } else {
                        DB::table('inventario')->insert([
                            'lote_id' => $lotId,
                            'ubicacion_id' => $locationId,
                            'cantidad' => $acceptedQuantity,
                        ]);
                    }

                    DB::table('movimientos_inventario')->insert([
                        'lote_id' => $lotId,
                        'ubicacion_id' => $locationId,
                        'tipo_movimiento' => 'RECEPCION',
                        'cantidad' => $acceptedQuantity,
                        ...$quantityConverter->movementSnapshot($quantitySnapshot),
                        'usuario_rut' => Auth::id(),
                        'referencia_tipo' => 'REMESA',
                        'referencia_id' => $shipmentId,
                        'observacion' => 'Recepción '.$data['codigo_remesa'],
                    ]);
                    $kardex->archiveEmptyInventoryRowsForProductAtLocation(
                        (int) $detail['producto_id'],
                        $locationId,
                        (string) Auth::id(),
                        'REMESA',
                        (int) $shipmentId,
                        $data['codigo_remesa'],
                        'RECEPCION_OTRO_LOTE',
                        'Fila de inventario agotada archivada al ingresar otro lote en la misma ubicación.'
                    );
                    $alerts->check($lotId, $locationId);
                }
            }

            if ($reorderRequest) {
                DB::table('solicitudes_reposicion')->where('solicitud_id', $data['solicitud_id'])->update(['estado' => 'RECIBIDA']);
            }

            $audit->record('RECEPCION_REMESA', 'remesas', $shipmentId, 'EXITOSO', [
                'codigo_remesa' => $data['codigo_remesa'],
                'lineas' => count($data['detalles']),
                'cantidad_aceptada' => collect($data['detalles'])->sum(
                    fn ($line) => $line['quantity_snapshot']['base_quantity'] - (float) ($line['cantidad_rechazada'] ?? 0)
                ),
                'cantidad_rechazada' => collect($data['detalles'])->sum(
                    fn ($line) => (float) ($line['cantidad_rechazada'] ?? 0)
                ),
            ]);

            return $shipmentId;
        });

        if ($request->expectsJson()) {
            return response()->json(['message' => 'Remesa registrada correctamente.', 'remesa_id' => $shipmentId], 201);
        }

        return redirect()->route('supplies.index')->with('status', 'Remesa registrada correctamente.');
    }

    private function authorizeSupplyOperation(AuditLogger $audit, string $entity): void
    {
        $user = Auth::user();
        if (! $user instanceof Usuario || ! $user->hasAnyRole(self::SUPPLY_OPERATORS)) {
            $audit->record('ACCESO_DENEGADO', $entity, null, 'RECHAZADO');
            abort(403);
        }
    }
}
