<?php

namespace App\Http\Controllers\Supplies;

use App\Http\Controllers\Controller;
use App\Actions\Supplies\SupplyIndexAction;
use App\Actions\Supplies\SupplyReceiptAction;
use App\Actions\Supplies\SupplyRequestAction;
use App\Actions\Supplies\SupplySearchAndDetailAction;
use App\Services\Audit\AuditLogger;
use App\Services\Inventory\InventoryKardexService;
use App\Services\Inventory\InventoryQuantityConverter;
use App\Services\Alerts\StockAlertService;
use App\Services\Notifications\UserNotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SupplyController extends Controller
{
    public function searchRequestProducts(
        Request $request,
        AuditLogger $audit,
        SupplySearchAndDetailAction $query
    ): JsonResponse
    {
        return $query->searchRequestProducts($request, $audit);
    }

    public function shipmentDetail(int $shipmentId, SupplySearchAndDetailAction $query): JsonResponse
    {
        return $query->shipmentDetail($shipmentId);
    }

    public function requestDetail(int $requestId, SupplySearchAndDetailAction $query): JsonResponse
    {
        return $query->requestDetail($requestId);
    }

    public function index(Request $request, AuditLogger $audit, SupplyIndexAction $query): View|JsonResponse
    {
        return $query->index($request, $audit);
    }

    public function storeRequest(
        Request $request,
        AuditLogger $audit,
        UserNotificationService $notifications,
        SupplyRequestAction $action
    ): RedirectResponse|JsonResponse
    {
        return $action->storeRequest($request, $audit, $notifications);
    }

    public function receive(
        Request $request,
        AuditLogger $audit,
        StockAlertService $alerts,
        InventoryQuantityConverter $quantityConverter,
        InventoryKardexService $kardex,
        SupplyReceiptAction $action
    ): RedirectResponse|JsonResponse {
        return $action->receive($request, $audit, $alerts, $quantityConverter, $kardex);
    }
}
