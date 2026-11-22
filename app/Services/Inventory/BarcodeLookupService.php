<?php


namespace App\Services\Inventory;

use App\Models\Producto;
use Illuminate\Support\Facades\DB;

class BarcodeLookupService
{
    public function findProduct(string $barcode): ?array
    {
        $product = Producto::query()
            ->active()
            ->whereHas('codigosBarra',  fn ($query) => $query
                ->where('codigo_barra', $barcode)
                ->where('activo', true))
            ->first(['producto_id', 'numero_serie', 'nombre', 'es_controlado']);

        if (! $product) {
            return null;
        }

        $fefoLot = DB::table('inventario')
            ->join('lotes', 'lotes.lote_id', '=', 'inventario.lote_id')
            ->join('ubicaciones', 'ubicaciones.ubicacion_id', '=', 'inventario.ubicacion_id')
            ->where('lotes.producto_id', $product->producto_id)
            ->where('lotes.estado', 'ACTIVO')
            ->where('ubicaciones.tipo', 'VITRINA')
            ->where('inventario.cantidad', '>', 0)
            ->whereDate('lotes.fecha_vencimiento', '>=', now()->toDateString())
            ->orderBy('lotes.fecha_vencimiento')
            ->orderBy('lotes.lote_id')
            ->orderBy('inventario.ubicacion_id')
            ->first([
                'lotes.numero_lote',
                'lotes.fecha_vencimiento',
                'inventario.cantidad',
                'ubicaciones.nombre as ubicacion',
            ]);

        return [
            'producto_id' => $product->producto_id,
            'codigo_producto' => $product->numero_serie,
            'nombre' => $product->nombre,
            'es_controlado' => $product->es_controlado,
            'lote_fefo' => $fefoLot ? [
                'numero_lote' => $fefoLot->numero_lote,
                'fecha_vencimiento' => $fefoLot->fecha_vencimiento,
                'cantidad_disponible' => (float) $fefoLot->cantidad,
                'ubicacion' => $fefoLot->ubicacion,
            ] : null,
        ];
    }
}
