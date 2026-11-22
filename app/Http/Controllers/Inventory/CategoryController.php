<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\StoreCategoryRequest;
use App\Http\Requests\Inventory\UpdateCategoryRequest;
use App\Models\Usuario;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class CategoryController extends Controller
{
    public function index(Request $request, AuditLogger $audit): View|JsonResponse
    {
        $this->authorizeCategoryManager($audit);
        $categories = DB::table('categorias_producto')->orderBy('nombre')
            ->paginate(25, ['categoria_id', 'nombre', 'descripcion'], 'categories_page')
            ->withQueryString();

        if ($request->expectsJson()) {
            return response()->json(['data' => $categories]);
        }

        return view('inventory.index', [
            'screen' => 'categories',
            'categories' => $categories,
            'canManageCatalog' => Auth::user()?->hasAnyRole(['Administrador']) ?? false,
            'canManageCategories' => true,
        ]);
    }

    public function create(Request $request, AuditLogger $audit): View|JsonResponse
    {
        $this->authorizeCategoryManager($audit);
        if ($request->expectsJson()) {
            return response()->json(['data' => ['campos' => ['nombre_categoria', 'descripcion_categoria']]]);
        }

        return view('inventory.index', [
            'screen' => 'category-create',
            'canManageCatalog' => Auth::user()?->hasAnyRole(['Administrador']) ?? false,
            'canManageCategories' => true,
        ]);
    }

    // Registra una categoría y su evento de auditoría.
    public function store(StoreCategoryRequest $request, AuditLogger $audit): RedirectResponse|JsonResponse
    {
        $data = $request->validated();
        $categoryId = DB::transaction(function () use ($data, $audit): int {
            $categoryId = DB::table('categorias_producto')->insertGetId([
                'nombre' => $data['nombre_categoria'],
                'descripcion' => $data['descripcion_categoria'] ?? null,
            ]);
            $audit->record('CREACION', 'categorias_producto', $categoryId, 'EXITOSO', [
                'nombre' => $data['nombre_categoria'],
            ]);

            return (int) $categoryId;
        });

        if ($request->expectsJson()) {
            return response()->json(['message' => 'Categoría agregada al catálogo.', 'categoria_id' => $categoryId], 201);
        }

        return redirect()->route('categories.index')->with('status', 'Categoría agregada al catálogo.');
    }

    public function edit(Request $request, int $categoryId, AuditLogger $audit): View|JsonResponse
    {
        $this->authorizeCategoryManager($audit, $categoryId);
        $category = DB::table('categorias_producto')->where('categoria_id', $categoryId)->first();
        abort_unless($category, 404);

        if ($request->expectsJson()) {
            return response()->json(['data' => $category]);
        }

        return view('inventory.category-edit', compact('category'));
    }

    // Actualiza una categoría y registra el cambio.
    public function update(
        UpdateCategoryRequest $request,
        int $categoryId,
        AuditLogger $audit
    ): RedirectResponse|JsonResponse {
        $data = $request->validated();

        DB::transaction(function () use ($data, $categoryId, $audit): void {
            DB::table('categorias_producto')->where('categoria_id', $categoryId)->update([
                'nombre' => $data['nombre_categoria'],
                'descripcion' => $data['descripcion_categoria'] ?? null,
            ]);
            $audit->record('ACTUALIZACION', 'categorias_producto', $categoryId, 'EXITOSO', [
                'nombre' => $data['nombre_categoria'],
            ]);
        });

        if ($request->expectsJson()) {
            return response()->json(['message' => 'Categoría actualizada correctamente.', 'categoria_id' => $categoryId]);
        }

        return redirect()->route('categories.index')->with('status', 'Categoría actualizada correctamente.');
    }

    // Autoriza la consulta y creación de categorías a los roles gestores.
    private function authorizeCategoryManager(AuditLogger $audit, int|string|null $categoryId = null): void
    {
        $user = Auth::user();
        if (! $user instanceof Usuario || ! $user->hasAnyRole(['Administrador', 'Químico Farmacéutico'])) {
            $audit->record(
                'ACCESO_DENEGADO',
                'categorias_producto',
                is_numeric($categoryId) ? (int) $categoryId : null,
                'RECHAZADO'
            );
            abort(403);
        }
    }
}
