<?php


namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\InventorySearchRequest;
use App\Models\Usuario;
use App\Services\Inventory\InventoryStockService;
use App\Services\Alerts\StockAlertService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class InventoryController extends Controller
{
    public function index(
        InventorySearchRequest $request,
        InventoryStockService $inventory,
        StockAlertService $alerts
    ): View
    {
        $user = Auth::user();
        abort_unless($user instanceof Usuario, 401);

        $filters = $request->validated();
        $canManageCatalog = $user->hasPermission('inventory.products.manage');
        $canViewProductCatalog = $user->hasPermission('inventory.products.view');
        $canManageCategories = $user->hasPermission('inventory.catalog.manage');
        $canViewCategories = $user->hasPermission('inventory.catalog.view');
        $productStock = $inventory->products($filters['buscar'] ?? null, $filters['buscar_global'] ?? null)
            ->withQueryString();
        $stockSummary = $inventory->productSummary();
        $lowStockCount = $alerts->refreshConfiguredStockAlerts()->count();
        $locations = $canViewCategories ? $inventory->locations() : collect();

        return view('inventory.index', [
            'screen' => 'stock',
            'productStock' => $productStock,
            'stockSummary' => $stockSummary,
            'filters' => $filters,
            'canViewProductCatalog' => $canViewProductCatalog,
            'canManageCatalog' => $canManageCatalog,
            'canViewCategories' => $canViewCategories,
            'canManageCategories' => $canManageCategories,
            'canRequestAdjustments' => $user->hasPermission('inventory.adjust.request'),
            'canTransferStock' => $user->hasPermission('inventory.transfer'),
            'locations' => $locations,
            'lowStockCount' => $lowStockCount,
        ]);
    }

    public function apiIndex(InventorySearchRequest $request, InventoryStockService $inventory): JsonResponse
    {
        $filters = $request->validated();
        $products = $inventory->products($filters['buscar'] ?? null, $filters['buscar_global'] ?? null)
            ->withQueryString()
            ->through(fn ($product) => [
                'medicamento_id' => (int) $product->producto_id,
                'codigo' => $product->codigo_producto,
                'nombre' => $product->producto,
                'stock_total' => (float) $product->cantidad_disponible,
                'stock_fisico' => (float) $product->cantidad_total,
                'stock_vencido' => (float) $product->cantidad_vencida,
                'stock_bloqueado' => (float) $product->cantidad_bloqueada,
                'stock_bodega' => (float) $product->stock_bodega,
                'stock_vitrina' => (float) $product->stock_vitrina,
                'unidad_base' => $product->unidad_base_codigo,
                'cantidad_lotes' => (int) $product->cantidad_lotes,
                'proximo_lote' => $product->proximo_lote,
                'proximo_vencimiento' => $product->proximo_vencimiento,
                'estado' => ! $product->activo
                    ? 'PRODUCTO_INACTIVO'
                    : ((float) $product->cantidad_disponible > 0 ? 'DISPONIBLE' : 'SIN_STOCK'),
                'url' => \Illuminate\Support\Facades\Route::has('products.stock')
                    ? route('products.stock', $product->producto_id)
                    : url('/api/v1/inventory/'.$product->producto_id),
            ]);

        return response()->json($products);
    }

    public function apiProductLots(int $productId, InventoryStockService $inventory): JsonResponse
    {
        $data = $inventory->apiProductLots($productId);
        abort_unless($data, 404);

        return response()->json($data);
    }

    public function apiFefoLots(int $productId, InventoryStockService $inventory): JsonResponse
    {
        $data = $inventory->fefoLots($productId);
        abort_unless($data, 404);

        return response()->json($data);
    }

    public function apiProductDetail(int $productId, InventoryStockService $inventory): JsonResponse
    {
        $data = $inventory->productStock($productId);
        abort_unless($data, 404);

        return response()->json([
            'data' => [
                'producto' => $data['product'],
                'presentaciones' => $data['presentations'],
                'lotes' => $data['lots'],
                'stock_por_ubicacion' => $data['stockByLocation'],
                'stock_total' => $data['totalQuantity'],
                'stock_disponible' => $data['availableQuantity'],
                'cantidad_lotes' => $data['lotCount'],
            ],
        ]);
    }

    public function productStock(int $productId, InventoryStockService $inventory): View
    {
        $user = Auth::user();
        abort_unless($user instanceof Usuario, 401);
        $data = $inventory->productStock($productId);
        abort_unless($data, 404);

        return view('inventory.product-stock', [
            ...$data,
            'lots' => $data['lots']->withQueryString(),
            'canManageCatalog' => $user->hasPermission('inventory.products.manage'),
            'canManageStockLimits' => $user->hasPermission('inventory.limits.manage'),
        ]);
    }

}
