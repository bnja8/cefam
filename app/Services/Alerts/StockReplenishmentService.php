<?php

namespace App\Services\Alerts;

use App\Services\Audit\AuditLogger;
use App\Services\Notifications\UserNotificationService;
use Illuminate\Support\Facades\DB;
use LogicException;

class StockReplenishmentService
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly UserNotificationService $notifications
    ) {}

    public function checkForReplenishment(int $productId, string $userRut, ?int $locationId = null): void
    {
        if ($userRut === '') {
            throw new LogicException('La reposición automática requiere el RUT de un usuario.');
        }

        DB::transaction(function () use ($productId, $userRut, $locationId) {
            $product = DB::table('productos')
                ->where('producto_id', $productId)
                ->where('activo', true)
                ->lockForUpdate()
                ->first(['producto_id', 'nombre']);

            if (! $product) {
                return;
            }

            $hasOpenRequest = DB::table('solicitud_reposicion_detalle')
                ->join('solicitudes_reposicion', 'solicitudes_reposicion.solicitud_id', '=', 'solicitud_reposicion_detalle.solicitud_id')
                ->where('solicitud_reposicion_detalle.producto_id', $productId)
                ->whereIn('solicitudes_reposicion.estado', ['PENDIENTE', 'SOLICITADA'])
                ->exists();

            if ($hasOpenRequest) {
                return;
            }

            $minimums = DB::table('stock_minimos_ubicacion')->where('producto_id', $productId);
            if ($locationId !== null) {
                $minimums->where('ubicacion_id', $locationId);
            }
            $targetStock = (float) $minimums->sum('stock_minimo');
            if ($targetStock <= 0) {
                return;
            }

            $availableStockQuery = DB::table('inventario')
                ->join('lotes', 'lotes.lote_id', '=', 'inventario.lote_id')
                ->where('lotes.producto_id', $productId)
                ->where('lotes.estado', 'ACTIVO')
                ->whereDate('lotes.fecha_vencimiento', '>=', now()->toDateString());
            if ($locationId !== null) {
                $availableStockQuery->where('inventario.ubicacion_id', $locationId);
            }
            $availableStock = (float) $availableStockQuery->sum('inventario.cantidad');
            $quantityToOrder = round($targetStock - $availableStock, 2);

            if ($quantityToOrder <= 0) {
                return;
            }

            $requestId = DB::table('solicitudes_reposicion')->insertGetId([
                'estado' => 'PENDIENTE',
                'usuario_rut' => $userRut,
            ]);
            DB::table('solicitud_reposicion_detalle')->insert([
                'solicitud_id' => $requestId,
                'producto_id' => $productId,
                'cantidad_solicitada' => $quantityToOrder,
            ]);

            $this->audit->record('REPOSICION_AUTOMATICA', 'solicitudes_reposicion', $requestId, 'EXITOSO', [
                'producto_id' => $productId,
                'producto' => $product->nombre,
                'stock_minimo_objetivo' => $targetStock,
                'stock_disponible' => $availableStock,
                'cantidad_solicitada' => $quantityToOrder,
            ]);
            $this->notifications->replenishmentRequested($requestId, [$productId]);
        });
    }
}
