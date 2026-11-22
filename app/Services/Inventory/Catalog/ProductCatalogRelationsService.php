<?php

namespace App\Services\Inventory\Catalog;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProductCatalogRelationsService
{
    public function __construct() {}

    public function syncProductIngredients(int $productId, array $ingredients): void
    {
        if ($ingredients === []) {
            return;
        }

        $rows = [];
        foreach ($ingredients as $ingredient) {
            $name = trim(preg_replace('/\s+/u', ' ', $ingredient['nombre']) ?? $ingredient['nombre']);
            $normalizedName = mb_strtolower($name, 'UTF-8');
            DB::table('principios_activos')->insertOrIgnore([
                'nombre' => $name,
                'nombre_normalizado' => $normalizedName,
            ]);
            $ingredientId = DB::table('principios_activos')
                ->where('nombre_normalizado', $normalizedName)
                ->value('principio_activo_id');
            if (! $ingredientId) {
                throw new \RuntimeException('No se pudo guardar el principio activo del producto.');
            }

            $rows[] = [
                'producto_id' => $productId,
                'principio_activo_id' => $ingredientId,
                'concentracion_cantidad' => $ingredient['concentracion_cantidad'],
                'concentracion_unidad_id' => $ingredient['concentracion_unidad_id'],
                'concentracion_base_cantidad' => $ingredient['concentracion_base_cantidad'] ?? null,
                'concentracion_base_unidad_id' => $ingredient['concentracion_base_unidad_id'] ?? null,
            ];
        }

        DB::table('productos_principios_activos')->insert($rows);
    }

    // Actualiza presentaciones sin perder el historial de las inactivadas.

    public function syncProductPresentations(int $productId, array $presentations): void
    {
        DB::table('presentaciones_producto')->updateOrInsert(
            ['producto_id' => $productId, 'nombre' => 'Unidad base'],
            ['factor_conversion' => 1, 'activo' => true]
        );

        foreach ($presentations as $presentation) {
            if (mb_strtolower(trim($presentation['nombre'])) === 'unidad base') {
                throw ValidationException::withMessages([
                    'presentaciones' => '“Unidad base” está reservada y se administra automáticamente.',
                ]);
            }
        }

        $existing = DB::table('presentaciones_producto')
            ->where('producto_id', $productId)
            ->where('nombre', '<>', 'Unidad base')
            ->get()
            ->keyBy(fn ($presentation) => mb_strtolower($presentation->nombre));
        $activeNames = [];

        foreach ($presentations as $presentation) {
            $name = trim($presentation['nombre']);
            $key = mb_strtolower($name);
            $activeNames[] = $key;
            if ($existing->has($key)) {
                DB::table('presentaciones_producto')
                    ->where('presentacion_id', $existing->get($key)->presentacion_id)
                    ->update([
                        'nombre' => $name,
                        'factor_conversion' => $presentation['factor_conversion'],
                        'activo' => true,
                    ]);
            } else {
                DB::table('presentaciones_producto')->insert([
                    'producto_id' => $productId,
                    'nombre' => $name,
                    'factor_conversion' => $presentation['factor_conversion'],
                    'activo' => true,
                ]);
            }
        }

        DB::table('presentaciones_producto')
            ->where('producto_id', $productId)
            ->where('nombre', '<>', 'Unidad base')
            ->whereNotIn(DB::raw('LOWER(nombre)'), $activeNames)
            ->update(['activo' => false]);
    }

    // Conserva presentaciones existentes o genera la presentación inicial.
}
