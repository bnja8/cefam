<?php

namespace App\Services\Inventory\Catalog;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;

class ProductCatalogQueryService
{
    public function __construct(private readonly ProductCatalogDataService $data) {}

    public function catalogPage(): LengthAwarePaginator
    {
        $products = DB::table('productos')
            ->join('categorias_producto', 'categorias_producto.categoria_id', '=', 'productos.categoria_id')
            ->leftJoin('productos_codigo_barra', function ($join) {
                $join->on('productos_codigo_barra.producto_id', '=', 'productos.producto_id')
                    ->where('productos_codigo_barra.activo', true);
            })
            ->leftJoin('formas_farmaceuticas', 'formas_farmaceuticas.forma_farmaceutica_id', '=', 'productos.forma_farmaceutica_id')
            ->leftJoin('unidades_medida as content_units', 'content_units.unidad_medida_id', '=', 'productos.contenido_envase_unidad_id')
            ->leftJoin('unidades_medida as base_units', 'base_units.unidad_medida_id', '=', 'productos.unidad_base_inventario_id')
            ->orderBy('productos.nombre')
            ->paginate(25, [
                'productos.producto_id',
                'productos.numero_serie',
                'productos.nombre',
                'productos.presentaciones_comerciales',
                'categorias_producto.nombre as categoria',
                'productos_codigo_barra.codigo_barra',
                'productos.tipo_producto',
                'productos.es_medicamento',
                'productos.es_controlado',
                'productos.activo',
                'formas_farmaceuticas.nombre as forma_farmaceutica',
                'productos.contenido_envase_cantidad',
                'content_units.codigo as unidad_contenido',
                'productos.tipo_presentacion',
                'base_units.codigo as unidad_base_inventario',
            ], 'catalog_page');

        $productIds = $products->getCollection()->pluck('producto_id');
        $ingredientSummaries = DB::table('productos_principios_activos')
            ->join('principios_activos', 'principios_activos.principio_activo_id', '=', 'productos_principios_activos.principio_activo_id')
            ->join('unidades_medida as concentration_units', 'concentration_units.unidad_medida_id', '=', 'productos_principios_activos.concentracion_unidad_id')
            ->leftJoin('unidades_medida as base_units', 'base_units.unidad_medida_id', '=', 'productos_principios_activos.concentracion_base_unidad_id')
            ->whereIn('productos_principios_activos.producto_id', $productIds)
            ->orderBy('principios_activos.nombre')
            ->get([
                'productos_principios_activos.producto_id',
                'principios_activos.nombre',
                'productos_principios_activos.concentracion_cantidad',
                'concentration_units.codigo as concentracion_unidad',
                'productos_principios_activos.concentracion_base_cantidad',
                'base_units.codigo as unidad_base',
            ])
            ->groupBy('producto_id')
            ->map(fn ($ingredients) => $ingredients->map(function ($ingredient) {
                $amount = rtrim(rtrim(number_format((float) $ingredient->concentracion_cantidad, 4, '.', ''), '0'), '.');
                $base = $ingredient->concentracion_base_cantidad === null
                    ? ''
                    : ' / '.rtrim(rtrim(number_format((float) $ingredient->concentracion_base_cantidad, 4, '.', ''), '0'), '.').' '.$ingredient->unidad_base;

                return $ingredient->nombre.' '.$amount.' '.$ingredient->concentracion_unidad.$base;
            })->implode('; '));
        $presentationSummaries = DB::table('presentaciones_producto')
            ->whereIn('producto_id', $productIds)
            ->where('activo', true)
            ->orderBy('nombre')
            ->get(['producto_id', 'nombre', 'factor_conversion'])
            ->groupBy('producto_id')
            ->map(fn ($presentations) => $presentations
                ->reject(fn ($presentation) => $presentation->nombre === 'Unidad base')
                ->map(fn ($presentation) => $presentation->nombre.' · '.
                    rtrim(rtrim(number_format((float) $presentation->factor_conversion, 4, '.', ''), '0'), '.'))
                ->implode('; '));

        $products->getCollection()->transform(function ($product) use ($ingredientSummaries, $presentationSummaries) {
            $product->principios_activos = $ingredientSummaries->get($product->producto_id, '');
            $product->presentaciones_resumen = $presentationSummaries->get($product->producto_id, '');
            $product->presentaciones_comerciales = $this->data->decodeCommercialPresentations($product->presentaciones_comerciales);

            return $product;
        });

        return $products;
    }

    // Obtiene las opciones para los formularios de productos.

    public function formOptions(): array
    {
        return [
            'dosageForms' => DB::table('formas_farmaceuticas')
                ->where('activo', true)->orderBy('nombre')->get(['forma_farmaceutica_id', 'nombre']),
            'measurementUnits' => DB::table('unidades_medida')
                ->where('activo', true)->orderBy('nombre')
                ->get(['unidad_medida_id', 'codigo', 'nombre', 'tipo']),
        ];
    }

    // Obtiene un producto y sus relaciones para editarlo.

    public function editData(int $productId): array
    {
        $product = DB::table('productos')->where('producto_id', $productId)->first();
        if (! $product) {
            throw new ModelNotFoundException('No se encontró el producto solicitado.');
        }

        return [
            'product' => $product,
            'commercialPresentations' => $this->data->decodeCommercialPresentations($product->presentaciones_comerciales),
            'barcode' => DB::table('productos_codigo_barra')->where('producto_id', $productId)
                ->where('activo', true)->first(),
            'categories' => DB::table('categorias_producto')->orderBy('nombre')->get(['categoria_id', 'nombre']),
            'ingredients' => DB::table('productos_principios_activos')
                ->join('principios_activos', 'principios_activos.principio_activo_id', '=', 'productos_principios_activos.principio_activo_id')
                ->where('productos_principios_activos.producto_id', $productId)
                ->orderBy('principios_activos.nombre')
                ->get([
                    'principios_activos.nombre',
                    'productos_principios_activos.concentracion_cantidad',
                    'productos_principios_activos.concentracion_unidad_id',
                    'productos_principios_activos.concentracion_base_cantidad',
                    'productos_principios_activos.concentracion_base_unidad_id',
                ])->map(fn ($ingredient) => (array) $ingredient)->all(),
            'presentations' => DB::table('presentaciones_producto')
                ->where('producto_id', $productId)
                ->where('activo', true)
                ->where('nombre', '<>', 'Unidad base')
                ->orderBy('nombre')
                ->get(['nombre', 'factor_conversion'])
                ->map(fn ($presentation) => (array) $presentation)
                ->all(),
            ...$this->formOptions(),
        ];
    }
}
