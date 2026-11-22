<?php

namespace App\Services\Inventory;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class InventoryStockQueryService
{
    public function products(?string $term = null, ?string $globalTerm = null): LengthAwarePaginator
    {
        return $this->productStockQuery($term, $globalTerm)->paginate(25);
    }

    // Resume cuántos productos tienen existencias disponibles.

    public function productSummary(): object
    {
        return DB::table('productos')
            ->leftJoin('lotes', 'lotes.producto_id', '=', 'productos.producto_id')
            ->leftJoin('inventario', 'inventario.lote_id', '=', 'lotes.lote_id')
            ->selectRaw(
                "COUNT(DISTINCT CASE WHEN productos.activo = 1 AND lotes.estado = 'ACTIVO' AND lotes.fecha_vencimiento >= ? AND inventario.cantidad > 0 THEN productos.producto_id END) as productos_con_existencia",
                [today()->toDateString()]
            )
            ->first();
    }

    // Obtiene las ubicaciones disponibles para las métricas de inventario.

    public function locations(): Collection
    {
        return DB::table('ubicaciones')->orderBy('nombre')->get(['ubicacion_id', 'nombre']);
    }

    // Obtiene detalle de un producto con existencias y sus lotes paginados.

    private function productStockQuery(?string $term, ?string $globalTerm = null): Builder
    {
        $applySearchTerm = function (Builder $query, string $term): Builder {
            $searchTerm = '%'.trim($term).'%';

            return $query->where(function ($search) use ($searchTerm) {
                $search->where('productos.numero_serie', 'like', $searchTerm)
                    ->orWhere('productos.nombre', 'like', $searchTerm)
                    ->orWhereRaw('CAST(productos.presentaciones_comerciales AS CHAR) LIKE ?', [$searchTerm])
                    ->orWhere('categorias_producto.nombre', 'like', $searchTerm)
                    ->orWhereExists(function ($matchingStock) use ($searchTerm) {
                        $matchingStock->selectRaw('1')
                            ->from('lotes as search_lots')
                            ->leftJoin('inventario as search_inventory', 'search_inventory.lote_id', '=', 'search_lots.lote_id')
                            ->leftJoin('ubicaciones as search_locations', 'search_locations.ubicacion_id', '=', 'search_inventory.ubicacion_id')
                            ->whereColumn('search_lots.producto_id', 'productos.producto_id')
                            ->where(function ($lotSearch) use ($searchTerm) {
                                $lotSearch->where('search_lots.numero_lote', 'like', $searchTerm)
                                    ->orWhere('search_locations.nombre', 'like', $searchTerm);
                            });
                    })
                    ->orWhereExists(function ($barcode) use ($searchTerm) {
                        $barcode->selectRaw('1')
                            ->from('productos_codigo_barra')
                            ->whereColumn('productos_codigo_barra.producto_id', 'productos.producto_id')
                            ->where('productos_codigo_barra.activo', true)
                            ->where('productos_codigo_barra.codigo_barra', 'like', $searchTerm);
                    });
            });
        };

        return DB::table('productos')
            ->join('categorias_producto', 'categorias_producto.categoria_id', '=', 'productos.categoria_id')
            ->leftJoin('unidades_medida as base_units', 'base_units.unidad_medida_id', '=', 'productos.unidad_base_inventario_id')
            ->leftJoin('lotes', 'lotes.producto_id', '=', 'productos.producto_id')
            ->leftJoin('inventario', 'inventario.lote_id', '=', 'lotes.lote_id')
            ->leftJoin('ubicaciones', 'ubicaciones.ubicacion_id', '=', 'inventario.ubicacion_id')
            ->when($term !== null && trim($term) !== '', fn ($query) => $applySearchTerm($query, $term))
            ->when($globalTerm !== null && trim($globalTerm) !== '', fn ($query) => $applySearchTerm($query, $globalTerm))
            ->select([
                'productos.producto_id',
                'productos.numero_serie as codigo_producto',
                'productos.nombre as producto',
                'productos.activo',
                'categorias_producto.nombre as categoria',
                'base_units.codigo as unidad_base_codigo',
            ])
            ->selectSub($this->upcomingLotSubquery('numero_lote'), 'proximo_lote')
            ->selectSub($this->upcomingLotSubquery('fecha_vencimiento'), 'proximo_vencimiento')
            ->selectRaw('COALESCE(SUM(inventario.cantidad), 0) as cantidad_total')
            ->selectRaw(
                "COALESCE(SUM(CASE WHEN productos.activo = 1 AND lotes.estado = 'ACTIVO' AND lotes.fecha_vencimiento >= ? THEN inventario.cantidad ELSE 0 END), 0) as cantidad_disponible",
                [today()->toDateString()]
            )
            ->selectRaw(
                'COALESCE(SUM(CASE WHEN lotes.fecha_vencimiento < ? THEN inventario.cantidad ELSE 0 END), 0) as cantidad_vencida',
                [today()->toDateString()]
            )
            ->selectRaw(
                "COALESCE(SUM(CASE WHEN lotes.fecha_vencimiento >= ? AND (productos.activo = 0 OR lotes.estado <> 'ACTIVO') THEN inventario.cantidad ELSE 0 END), 0) as cantidad_bloqueada",
                [today()->toDateString()]
            )
            ->selectRaw(
                "COALESCE(SUM(CASE WHEN productos.activo = 1 AND ubicaciones.tipo = 'BODEGA' AND lotes.estado = 'ACTIVO' AND lotes.fecha_vencimiento >= ? THEN inventario.cantidad ELSE 0 END), 0) as stock_bodega",
                [today()->toDateString()]
            )
            ->selectRaw(
                "COALESCE(SUM(CASE WHEN productos.activo = 1 AND ubicaciones.tipo = 'VITRINA' AND lotes.estado = 'ACTIVO' AND lotes.fecha_vencimiento >= ? THEN inventario.cantidad ELSE 0 END), 0) as stock_vitrina",
                [today()->toDateString()]
            )
            ->selectRaw('COUNT(DISTINCT lotes.lote_id) as cantidad_lotes')
            ->groupBy('productos.producto_id', 'productos.numero_serie', 'productos.nombre', 'productos.activo', 'categorias_producto.nombre', 'base_units.codigo')
            ->orderBy('productos.nombre');
    }

    // Encuentra el lote activo con existencias que vence primero en los próximos 30 días.

    private function upcomingLotSubquery(string $column): Builder
    {
        $today = today()->toDateString();

        return DB::table('lotes as upcoming_lots')
            ->whereColumn('upcoming_lots.producto_id', 'productos.producto_id')
            ->where('upcoming_lots.estado', 'ACTIVO')
            ->whereBetween('upcoming_lots.fecha_vencimiento', [$today, today()->addDays(30)->toDateString()])
            ->whereExists(function (Builder $query) {
                $query->selectRaw('1')
                    ->from('inventario as upcoming_inventory')
                    ->whereColumn('upcoming_inventory.lote_id', 'upcoming_lots.lote_id')
                    ->where('upcoming_inventory.cantidad', '>', 0);
            })
            ->orderBy('upcoming_lots.fecha_vencimiento')
            ->orderBy('upcoming_lots.lote_id')
            ->limit(1)
            ->select("upcoming_lots.{$column}");
    }

    // Calcula existencias por ubicación y alertas de stock.
}
