<?php


namespace App\Support\Inventory;

use LogicException;
use Illuminate\Support\Facades\DB;

class InventoryQuantitySnapshot
{
    // Construye la vista de cantidades en unidades base.
    public static function forBaseQuantity(int $productId, float $quantity): array
    {
        $unitCode = DB::table('productos')
            ->join('unidades_medida', 'unidades_medida.unidad_medida_id', '=', 'productos.unidad_base_inventario_id')
            ->where('productos.producto_id', $productId)
            ->value('unidades_medida.codigo');
        if (! $unitCode) {
            throw new LogicException("Product {$productId} does not have a base inventory unit.");
        }

        return [
            'cantidad_original' => $quantity,
            'unidad_ingresada' => $unitCode ?: 'Unidad base',
            'factor_conversion' => 1,
            'cantidad_envases' => 0,
            'unidades_sueltas' => $quantity,
            'presentacion_nombre' => 'Unidad base',
            'unidad_base_codigo' => $unitCode,
        ];
    }
}
