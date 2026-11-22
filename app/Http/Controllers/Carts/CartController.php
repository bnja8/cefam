<?php

namespace App\Http\Controllers\Carts;

use App\Http\Controllers\Controller;
use App\Actions\Carts\CartIndexAction;
use App\Actions\Carts\CartParoAction;
use App\Actions\Carts\CartPreparationAction;
use App\Actions\Carts\CartProductSearchAction;
use App\Actions\Carts\CartTreatmentAction;
use App\Services\Audit\AuditLogger;
use App\Services\Inventory\FefoInventoryAllocator;
use App\Services\Alerts\StockAlertService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CartController extends Controller
{
    public function searchUnidosisProducts(Request $request, CartProductSearchAction $query): JsonResponse
    {
        return $query->searchUnidosisProducts($request);
    }

    public function index(Request $request, CartIndexAction $query): View|JsonResponse
    {
        return $query->index($request);
    }

    public function storeTreatment(Request $request, AuditLogger $audit, CartTreatmentAction $action): RedirectResponse|JsonResponse
    {
        return $action->storeTreatment($request, $audit);
    }

    public function prepare(
        Request $request,
        AuditLogger $audit,
        FefoInventoryAllocator $allocator,
        StockAlertService $alerts,
        CartPreparationAction $action
    ): RedirectResponse|JsonResponse {
        return $action->prepare($request, $audit, $allocator, $alerts);
    }

    public function confirm(
        Request $request,
        int $preparationId,
        AuditLogger $audit,
        CartParoAction $action
    ): RedirectResponse|JsonResponse
    {
        return $action->confirm($request, $preparationId, $audit);
    }

    public function dispenseFromParo(
        Request $request,
        AuditLogger $audit,
        StockAlertService $alerts,
        CartParoAction $action
    ): RedirectResponse|JsonResponse
    {
        return $action->dispenseFromParo($request, $audit, $alerts);
    }
}
