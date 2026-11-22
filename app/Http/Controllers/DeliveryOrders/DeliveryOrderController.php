<?php


namespace App\Http\Controllers\DeliveryOrders;

use App\Http\Controllers\Controller;
use App\Services\Audit\AuditLogger;
use App\Services\DeliveryOrders\DeliveryOrderData;
use App\Services\DeliveryOrders\DeliveryOrderLabelService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DeliveryOrderController extends Controller
{
    public function show(Request $request, int $orderId, DeliveryOrderData $orderData): View|JsonResponse
    {
        ['order' => $order, 'details' => $details] = $orderData->forOrder($orderId);

        return ($request->routeIs('api.v1.*') || $request->expectsJson())
            ? response()->json(['data' => compact('order', 'details')])
            : view('delivery-orders.show', compact('order', 'details'));
    }

    public function receipt(int $orderId, DeliveryOrderData $orderData): View
    {
        return view('delivery-orders.receipt', $orderData->forOrder($orderId));
    }

    public function labels(Request $request, int $orderId, DeliveryOrderLabelService $labelService, AuditLogger $audit): View|JsonResponse
    {
        $data = $labelService->forOrder($orderId);
        $this->recordLabelRequest($orderId, $data['details']->count(), $data['printSettings']['configured'], $audit);

        return ($request->routeIs('api.v1.*') || $request->expectsJson())
            ? response()->json(['data' => $data])
            : view('delivery-orders.label', $data);
    }

    public function label(
        Request $request,
        int $orderId,
        int $detailId,
        DeliveryOrderLabelService $labelService,
        AuditLogger $audit
    ): View|JsonResponse {
        $data = $labelService->forOrder($orderId, $detailId);
        $this->recordLabelRequest($orderId, $data['details']->count(), $data['printSettings']['configured'], $audit);

        if ($request->routeIs('api.v1.*') || $request->expectsJson()) {
            return response()->json(['data' => ['detail' => $data['details']->first()]]);
        }

        return view('delivery-orders.label', $data);
    }

    private function recordLabelRequest(int $orderId, int $labelCount, bool $printReady, AuditLogger $audit): void
    {
        if (! $printReady) {
            return;
        }

        $isReprint = DB::table('auditoria')
            ->where('entidad', 'ordenes_entrega')
            ->where('registro_id', $orderId)
            ->whereIn('accion', ['SOLICITUD_ETIQUETAS', 'REIMPRESION_ETIQUETAS'])
            ->exists();

        $audit->record(
            $isReprint ? 'REIMPRESION_ETIQUETAS' : 'SOLICITUD_ETIQUETAS',
            'ordenes_entrega',
            $orderId,
            'EXITOSO',
            ['cantidad_etiquetas' => $labelCount]
        );
    }
}
