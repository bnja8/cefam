<?php

namespace App\Actions\Dispensations;

use Illuminate\Database\Query\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DispensationProductSearchAction
{
    public function searchProducts(Request $request): JsonResponse
    {
        $data = $request->validate([
            'q' => ['required', 'string', 'max:100'],
        ]);
        $term = trim($data['q']);
        if (mb_strlen($term) < 2) {
            return response()->json([]);
        }

        $products = $this->dispensationProductsQuery()
            ->where('productos.nombre', 'like', '%'.$term.'%')
            ->orderBy('productos.nombre')
            ->limit(20)
            ->get()
            ->map(fn ($product) => [
                'producto_id' => (int) $product->producto_id,
                'nombre' => $product->nombre,
                'es_controlado' => (bool) $product->es_controlado,
                'stock_disponible' => (float) $product->stock_disponible,
                'unidad_base_codigo' => $product->unidad_base_codigo,
            ])
            ->values();

        return response()->json($products);
    }

    private function dispensationProductsQuery(): Builder
    {
        $availableStock = DB::table('inventario')
            ->join('lotes', 'lotes.lote_id', '=', 'inventario.lote_id')
            ->join('ubicaciones', 'ubicaciones.ubicacion_id', '=', 'inventario.ubicacion_id')
            ->where('lotes.estado', 'ACTIVO')
            ->where('ubicaciones.tipo', 'VITRINA')
            ->where('inventario.cantidad', '>', 0)
            ->whereDate('lotes.fecha_vencimiento', '>=', now()->toDateString())
            ->select('lotes.producto_id')
            ->selectRaw('SUM(inventario.cantidad) as stock_disponible')
            ->groupBy('lotes.producto_id');

        return DB::table('productos')
            ->leftJoinSub($availableStock, 'stock_vitrina', fn ($join) => $join
                ->on('stock_vitrina.producto_id', '=', 'productos.producto_id'))
            ->leftJoin('unidades_medida as base_units', 'base_units.unidad_medida_id', '=', 'productos.unidad_base_inventario_id')
            ->where('productos.activo', true)
            ->select([
                'productos.producto_id',
                'productos.nombre',
                'productos.es_controlado',
                'base_units.codigo as unidad_base_codigo',
            ])
            ->selectRaw('COALESCE(stock_vitrina.stock_disponible, 0) as stock_disponible');
    }
}
