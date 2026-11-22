<?php

namespace App\Actions\Carts;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CartProductSearchAction
{
    public function searchUnidosisProducts(Request $request): JsonResponse
    {
        $data = $request->validate([
            'q' => ['required', 'string', 'min:2', 'max:100'],
        ]);
        $term = trim($data['q']);
        $products = DB::table('productos')
            ->join('lotes', 'lotes.producto_id', '=', 'productos.producto_id')
            ->join('inventario', 'inventario.lote_id', '=', 'lotes.lote_id')
            ->join('ubicaciones', 'ubicaciones.ubicacion_id', '=', 'inventario.ubicacion_id')
            ->join('carros', 'carros.ubicacion_id', '=', 'ubicaciones.ubicacion_id')
            ->where('productos.activo', true)
            ->where('productos.es_controlado', false)
            ->where('carros.tipo_carro', 'UNIDOSIS')
            ->where('lotes.estado', 'ACTIVO')
            ->where('inventario.cantidad', '>', 0)
            ->whereDate('lotes.fecha_vencimiento', '>=', now()->toDateString())
            ->where('productos.nombre', 'like', '%'.$term.'%')
            ->select('productos.producto_id', 'productos.nombre')
            ->distinct()
            ->orderBy('productos.nombre')
            ->limit(20)
            ->get();

        return response()->json($products->map(static fn ($product) => [
            'producto_id' => (int) $product->producto_id,
            'nombre' => $product->nombre,
            'es_controlado' => false,
        ]));
    }
}
