<?php

namespace App\Services\Inventory;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class InventoryStockDetailService
{
    public function __construct() {}

    public function productStock(int $productId): ?array
    {
        $product = DB::table('productos')
            ->join('categorias_producto', 'categorias_producto.categoria_id', '=', 'productos.categoria_id')
            ->leftJoin('unidades_medida as base_units', 'base_units.unidad_medida_id', '=', 'productos.unidad_base_inventario_id')
            ->where('productos.producto_id', $productId)
            ->first([
                'productos.producto_id',
                'productos.numero_serie as codigo_producto',
                'productos.nombre',
                'productos.activo',
                'categorias_producto.nombre as categoria',
                'base_units.codigo as unidad_base_codigo',
            ]);
        if (! $product) {
            return null;
        }

        $stockRows = DB::table('lotes')
            ->leftJoin('inventario', 'inventario.lote_id', '=', 'lotes.lote_id')
            ->leftJoin('ubicaciones', 'ubicaciones.ubicacion_id', '=', 'inventario.ubicacion_id')
            ->leftJoin('vw_stock_producto_ubicacion as stock_view', function ($join) {
                $join->on('stock_view.producto_id', '=', 'lotes.producto_id')
                    ->on('stock_view.ubicacion_id', '=', 'inventario.ubicacion_id');
            })
            ->where('lotes.producto_id', $productId)
            ->orderByRaw('CASE WHEN lotes.fecha_vencimiento < ? THEN 1 ELSE 0 END', [today()->toDateString()])
            ->orderBy('lotes.fecha_vencimiento')
            ->orderBy('lotes.numero_lote')
            ->orderBy('ubicaciones.nombre')
            ->orderBy('lotes.lote_id')
            ->orderBy('inventario.ubicacion_id')
            ->paginate(25, [
                'lotes.lote_id',
                'lotes.numero_lote',
                'lotes.fecha_fabricacion',
                'lotes.fecha_vencimiento',
                'lotes.fecha_recepcion',
                'lotes.estado as estado_lote',
                'inventario.ubicacion_id',
                'inventario.cantidad',
                'stock_view.stock_minimo',
                'stock_view.stock_disponible',
                'stock_view.es_stock_critico',
                'ubicaciones.nombre as ubicacion',
            ])
            ->through(fn ($lot) => $this->serializeLotStock($lot, (bool) $product->activo));

        $stockTotals = DB::table('lotes')
            ->leftJoin('inventario', 'inventario.lote_id', '=', 'lotes.lote_id')
            ->where('lotes.producto_id', $productId)
            ->selectRaw('COALESCE(SUM(inventario.cantidad), 0) as total_quantity')
            ->selectRaw('COUNT(DISTINCT lotes.lote_id) as lot_count')
            ->selectRaw(
                "COALESCE(SUM(CASE WHEN ? = 1 AND lotes.estado = 'ACTIVO' AND lotes.fecha_vencimiento >= ? THEN inventario.cantidad ELSE 0 END), 0) as available_quantity",
                [(bool) $product->activo, today()->toDateString()]
            )
            ->first();

        return [
            'product' => $product,
            'presentations' => DB::table('presentaciones_producto')
                ->where('producto_id', $productId)
                ->where('activo', true)
                ->where('nombre', '<>', 'Unidad base')
                ->orderBy('nombre')
                ->get(['nombre', 'factor_conversion'])
                ->map(fn ($presentation) => (array) $presentation)
                ->all(),
            'lots' => $stockRows,
            'stockByLocation' => $this->stockByLocation($productId, (bool) $product->activo),
            'totalQuantity' => (float) $stockTotals->total_quantity,
            'availableQuantity' => (float) $stockTotals->available_quantity,
            'lotCount' => (int) $stockTotals->lot_count,
        ];
    }

    // Obtiene el detalle de lotes y el resumen para la API de inventario.

    public function apiProductLots(int $productId): ?array
    {
        $product = DB::table('productos')
            ->where('producto_id', $productId)
            ->first(['producto_id', 'numero_serie', 'nombre', 'activo']);
        if (! $product) {
            return null;
        }

        $lots = DB::table('lotes')
            ->leftJoin('inventario', 'inventario.lote_id', '=', 'lotes.lote_id')
            ->leftJoin('ubicaciones', 'ubicaciones.ubicacion_id', '=', 'inventario.ubicacion_id')
            ->leftJoin('vw_stock_producto_ubicacion as stock_view', function ($join) {
                $join->on('stock_view.producto_id', '=', 'lotes.producto_id')
                    ->on('stock_view.ubicacion_id', '=', 'inventario.ubicacion_id');
            })
            ->where('lotes.producto_id', $productId)
            ->orderByRaw('CASE WHEN lotes.fecha_vencimiento < ? THEN 1 ELSE 0 END', [today()->toDateString()])
            ->orderBy('lotes.fecha_vencimiento')
            ->orderBy('lotes.numero_lote')
            ->orderBy('ubicaciones.nombre')
            ->get([
                'lotes.lote_id',
                'lotes.numero_lote',
                'lotes.fecha_fabricacion',
                'lotes.fecha_vencimiento',
                'lotes.fecha_recepcion',
                'lotes.estado as estado_lote',
                'inventario.ubicacion_id',
                'stock_view.stock_minimo',
                'stock_view.stock_disponible',
                'stock_view.es_stock_critico',
                'inventario.cantidad',
                'ubicaciones.nombre as ubicacion',
            ])
            ->map(fn ($lot) => $this->serializeLotStock($lot, (bool) $product->activo))
            ->values();

        return [
            'medicamento' => [
                'medicamento_id' => (int) $product->producto_id,
                'codigo' => $product->numero_serie,
                'nombre' => $product->nombre,
            ],
            'stock_total' => $product->activo
                ? (float) $lots->where('estado_disponibilidad', 'DISPONIBLE')->sum('cantidad')
                : 0.0,
            'stock_vencido' => (float) $lots->where('condicion_vencimiento', 'VENCIDO')->sum('cantidad'),
            'stock_bloqueado' => (float) $lots
                ->whereIn('estado_disponibilidad', ['BLOQUEADO', 'PRODUCTO_INACTIVO'])
                ->sum('cantidad'),
            'stock_fisico' => (float) $lots->sum('cantidad'),
            'estado_producto' => $product->activo ? 'ACTIVO' : 'INACTIVO',
            'lotes' => $lots,
        ];
    }

    // Obtiene lotes FEFO disponibles en ubicaciones tipo vitrina.

    public function fefoLots(int $productId): ?array
    {
        $product = DB::table('productos')->where('producto_id', $productId)
            ->where('activo', true)->first(['producto_id', 'nombre']);
        if (! $product) {
            return null;
        }

        $lots = DB::table('inventario')
            ->join('lotes', 'lotes.lote_id', '=', 'inventario.lote_id')
            ->join('ubicaciones', 'ubicaciones.ubicacion_id', '=', 'inventario.ubicacion_id')
            ->where('lotes.producto_id', $productId)
            ->where('lotes.estado', 'ACTIVO')
            ->where('ubicaciones.tipo', 'VITRINA')
            ->where('inventario.cantidad', '>', 0)
            ->whereDate('lotes.fecha_vencimiento', '>=', now()->toDateString())
            ->orderBy('lotes.fecha_vencimiento')
            ->orderBy('lotes.lote_id')
            ->orderBy('inventario.ubicacion_id')
            ->get([
                'lotes.lote_id',
                'lotes.numero_lote',
                'lotes.fecha_vencimiento',
                'ubicaciones.nombre as ubicacion',
                'inventario.cantidad',
            ]);

        return [
            'producto' => $product->nombre,
            'stock_vigente_vitrina' => (float) $lots->sum('cantidad'),
            'lotes' => $lots->map(fn ($lot) => [
                'lote_id' => (int) $lot->lote_id,
                'numero_lote' => $lot->numero_lote,
                'fecha_vencimiento' => $lot->fecha_vencimiento,
                'ubicacion' => $lot->ubicacion,
                'cantidad_disponible' => (float) $lot->cantidad,
            ])->values(),
        ];
    }

    // Construye la consulta con los filtros de catálogo e inventario.

    private function stockByLocation(int $productId, bool $productActive): Collection
    {
        $today = today()->toDateString();
        $stockByLocation = DB::table('inventario')
            ->join('lotes', 'lotes.lote_id', '=', 'inventario.lote_id')
            ->where('lotes.producto_id', $productId)
            ->groupBy('inventario.ubicacion_id')
            ->select('inventario.ubicacion_id')
            ->selectRaw('COALESCE(SUM(inventario.cantidad), 0) as stock_fisico')
            ->selectRaw(
                "COALESCE(SUM(CASE WHEN ? = 1 AND lotes.estado = 'ACTIVO' AND lotes.fecha_vencimiento >= ? THEN inventario.cantidad ELSE 0 END), 0) as stock_disponible",
                [$productActive, $today]
            )
            ->selectRaw(
                'COALESCE(SUM(CASE WHEN lotes.fecha_vencimiento < ? THEN inventario.cantidad ELSE 0 END), 0) as stock_vencido',
                [$today]
            )
            ->selectRaw(
                "COALESCE(SUM(CASE WHEN lotes.fecha_vencimiento >= ? AND (? = 0 OR lotes.estado <> 'ACTIVO') THEN inventario.cantidad ELSE 0 END), 0) as stock_bloqueado",
                [$today, $productActive]
            );

        return DB::table('ubicaciones')
            ->leftJoinSub($stockByLocation, 'stock', 'stock.ubicacion_id', '=', 'ubicaciones.ubicacion_id')
            ->leftJoin('stock_minimos_ubicacion', function ($join) use ($productId) {
                $join->on('stock_minimos_ubicacion.ubicacion_id', '=', 'ubicaciones.ubicacion_id')
                    ->where('stock_minimos_ubicacion.producto_id', '=', $productId);
            })
            ->orderBy('ubicaciones.nombre')
            ->get([
                'ubicaciones.ubicacion_id',
                'ubicaciones.nombre as ubicacion',
                'stock_minimos_ubicacion.stock_minimo',
                'stock_minimos_ubicacion.stock_maximo',
                DB::raw('COALESCE(stock.stock_fisico, 0) as stock_fisico'),
                DB::raw('COALESCE(stock.stock_disponible, 0) as stock_disponible'),
                DB::raw('COALESCE(stock.stock_vencido, 0) as stock_vencido'),
                DB::raw('COALESCE(stock.stock_bloqueado, 0) as stock_bloqueado'),
            ])
            ->map(function ($stock) {
                $stock->faltante_minimo = $stock->stock_minimo === null
                    ? null
                    : max(0, (float) $stock->stock_minimo - (float) $stock->stock_disponible);
                $stock->sobre_maximo = $stock->stock_maximo !== null
                    && (float) $stock->stock_fisico > (float) $stock->stock_maximo;
                $stock->es_stock_critico = $stock->stock_minimo !== null
                    && (float) $stock->stock_disponible <= (float) $stock->stock_minimo;

                return $stock;
            });
    }

    // Calcula el estado de disponibilidad y vencimiento de un lote.

    private function serializeLotStock(object $lot, bool $productActive = true): array
    {
        $quantity = (float) ($lot->cantidad ?? 0);
        $minimum = (float) ($lot->stock_minimo ?? 0);
        $availableAtLocation = (float) ($lot->stock_disponible ?? 0);
        $expiry = Carbon::parse($lot->fecha_vencimiento);
        $daysToExpiry = today()->diffInDays($expiry, false);
        $state = match (true) {
            ! $productActive => 'PRODUCTO_INACTIVO',
            $lot->estado_lote !== 'ACTIVO' => 'INACTIVO',
            $expiry->lt(today()) => 'VENCIDO',
            $quantity <= 0 => 'SIN_STOCK',
            $daysToExpiry <= 30 => 'PROXIMO_A_VENCER',
            default => 'DISPONIBLE',
        };
        $availability = match (true) {
            $expiry->lt(today()) => 'NO_DISPONIBLE',
            ! $productActive => 'PRODUCTO_INACTIVO',
            $lot->estado_lote !== 'ACTIVO' => 'BLOQUEADO',
            $quantity <= 0 => 'SIN_STOCK',
            default => 'DISPONIBLE',
        };
        $expiryCondition = match (true) {
            $expiry->lt(today()) => 'VENCIDO',
            $daysToExpiry <= 30 => 'PROXIMO_A_VENCER',
            default => 'VIGENTE',
        };

        return [
            'lote_id' => (int) $lot->lote_id,
            'numero_lote' => $lot->numero_lote,
            'fecha_fabricacion' => $lot->fecha_fabricacion,
            'fecha_vencimiento' => $lot->fecha_vencimiento,
            'fecha_recepcion' => $lot->fecha_recepcion,
            'ubicacion_id' => $lot->ubicacion_id === null ? null : (int) $lot->ubicacion_id,
            'ubicacion' => $lot->ubicacion,
            'cantidad' => $quantity,
            'stock_minimo' => $minimum,
            'stock_disponible_ubicacion' => $availableAtLocation,
            'estado_lote' => $lot->estado_lote,
            'estado' => $state,
            'condicion_vencimiento' => $expiryCondition,
            'estado_disponibilidad' => $availability,
            'stock_critico' => (bool) ($lot->es_stock_critico ?? false),
        ];
    }
}
