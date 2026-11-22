<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\SaveProductRequest;
use App\Models\Usuario;
use App\Services\Audit\AuditLogger;
use App\Services\Inventory\Catalog\ProductCatalogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ProductController extends Controller
{
    public function index(Request $request, AuditLogger $audit, ProductCatalogService $catalog): View|JsonResponse
    {
        $this->authorizeAdministrator($audit, 'productos');
        $user = Auth::user();
        $products = $catalog->catalogPage()->withQueryString();

        if ($request->expectsJson()) {
            return response()->json(['data' => $products]);
        }

        return view('inventory.index', [
            'screen' => 'catalog',
            'catalogProducts' => $products,
            'canManageCatalog' => true,
            'canManageCategories' => $user instanceof Usuario
                && $user->hasAnyRole(['Administrador', 'Químico Farmacéutico']),
        ]);
    }

    public function create(Request $request, AuditLogger $audit, ProductCatalogService $catalog): View|JsonResponse
    {
        $this->authorizeAdministrator($audit, 'productos');
        $user = Auth::user();
        $options = [
            'categorias' => DB::table('categorias_producto')->orderBy('nombre')->get(['categoria_id', 'nombre']),
            'presentaciones' => [],
            'commercialPresentations' => [],
            ...$catalog->formOptions(),
        ];

        if ($request->expectsJson()) {
            return response()->json(['data' => $options]);
        }

        return view('inventory.index', [
            'screen' => 'product-create',
            'categories' => $options['categorias'],
            'presentations' => $options['presentaciones'],
            'canManageCatalog' => true,
            'canManageCategories' => $user instanceof Usuario
                && $user->hasAnyRole(['Administrador', 'Químico Farmacéutico']),
            ...[
                'dosageForms' => $options['dosageForms'],
                'measurementUnits' => $options['measurementUnits'],
            ],
        ]);
    }

    // Registra un producto con sus relaciones de catálogo.
    public function store(SaveProductRequest $request, ProductCatalogService $catalog): RedirectResponse|JsonResponse
    {
        $productId = $catalog->create($request->validated(), $request->boolean('es_controlado'));

        if ($request->expectsJson()) {
            return response()->json(['message' => 'Producto agregado al catálogo.', 'producto_id' => $productId], 201);
        }

        return redirect()->route('inventory.catalog')->with('status', 'Producto agregado al catálogo.');
    }

    public function edit(Request $request, int $productId, AuditLogger $audit, ProductCatalogService $catalog): View|JsonResponse
    {
        $this->authorizeAdministrator($audit, 'productos', $productId);
        $data = $catalog->editData($productId);

        if ($request->expectsJson()) {
            return response()->json(['data' => $data]);
        }

        return view('inventory.product-edit', $data);
    }

    // Actualiza el producto y mantiene sus relaciones consistentes.
    public function update(
        SaveProductRequest $request,
        int $productId,
        ProductCatalogService $catalog
    ): RedirectResponse|JsonResponse {
        $catalog->update(
            $productId,
            $request->validated(),
            $request->boolean('es_controlado'),
            $request->boolean('activo')
        );

        if ($request->expectsJson()) {
            return response()->json(['message' => 'Datos del producto actualizados.', 'producto_id' => $productId]);
        }

        return redirect()->route('inventory.catalog')->with('status', 'Datos del producto actualizados.');
    }
}
