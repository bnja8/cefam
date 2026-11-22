<?php

namespace App\Http\Controllers\Dispensations;

use App\Http\Controllers\Controller;
use App\Actions\Dispensations\DispensationIndexAction;
use App\Actions\Dispensations\DispensationPatientOptionsAction;
use App\Actions\Dispensations\DispensationProductSearchAction;
use App\Http\Requests\StoreDispensationRequest;
use App\Services\Audit\AuditLogger;
use App\Services\DeliveryOrders\DeliveryOrderData;
use App\Services\Dispensations\DispensationStoreAction;
use App\Services\Patients\PatientRecordArchiver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DispensationController extends Controller
{
    public function index(
        Request $request,
        DeliveryOrderData $deliveryOrderData,
        PatientRecordArchiver $recordArchiver,
        DispensationIndexAction $query
    ): View|JsonResponse
    {
        return $query->index($request, $deliveryOrderData, $recordArchiver);
    }

    public function patientOptions(string $patientRut, DispensationPatientOptionsAction $query): JsonResponse
    {
        return $query->patientOptions($patientRut);
    }

    public function searchProducts(Request $request, DispensationProductSearchAction $query): JsonResponse
    {
        return $query->searchProducts($request);
    }

    public function store(
        StoreDispensationRequest $request,
        DispensationStoreAction $storeAction
    ): RedirectResponse|JsonResponse {
        $delivery = $storeAction->store($request->validated());

        return ($request->routeIs('api.v1.*') || $request->expectsJson())
            ? response()->json(['data' => [
                'entrega_id' => $delivery['delivery_id'],
                'orden_entrega_id' => $delivery['order_id'],
                'message' => 'Entrega confirmada; el stock y la trazabilidad fueron actualizados.',
            ]], 201)
            : redirect()->route('dispensations.index', ['comprobante' => $delivery['order_id']])
                ->with('delivery_order_id', $delivery['order_id'])
                ->with('status', 'Entrega confirmada; puedes imprimir las etiquetas desde el comprobante.');
    }
}
