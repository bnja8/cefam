<?php
// Convierte las cantidades ingresadas a las unidades base del inventario.


namespace App\Services\Inventory;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use LogicException;

class InventoryQuantityConverter
{
    // Conserva los datos de cantidad asociados a un movimiento.
    public function movementSnapshot(array $quantity): array
    {
        return [
            'cantidad_original' => $quantity['cantidad_original'],
            'unidad_ingresada' => $quantity['unidad_ingresada'],
            'factor_conversion' => $quantity['factor_conversion'],
            'cantidad_envases' => $quantity['cantidad_envases'],
            'unidades_sueltas' => $quantity['unidades_sueltas'],
            'presentacion_nombre' => $quantity['presentacion_nombre'],
            'unidad_base_codigo' => $quantity['unidad_base_codigo'],
        ];
    }

    // Conserva los datos de presentación y conversión de una remesa.
    public function remesaDetailSnapshot(array $quantity): array
    {
        return [
            'cantidad_envases' => $quantity['cantidad_envases'],
            'unidades_sueltas' => $quantity['unidades_sueltas'],
            'presentacion_nombre' => $quantity['presentacion_nombre'],
            'factor_conversion' => $quantity['factor_conversion'],
            'unidad_base_codigo' => $quantity['unidad_base_codigo'],
        ];
    }

    // Valida la entrada y calcula su equivalente en unidades base.
    public function resolve(array $input, string $fieldPrefix = ''): array
    {
        if (DB::transactionLevel() === 0) {
            throw new LogicException('Quantity conversion must run inside a database transaction.');
        }

        $product = DB::table('productos')
            ->join('unidades_medida', 'unidades_medida.unidad_medida_id', '=', 'productos.unidad_base_inventario_id')
            ->where('productos.producto_id', $input['producto_id'])
            ->lockForUpdate()
            ->first([
                'productos.producto_id',
                'unidades_medida.codigo as unidad_base_codigo',
            ]);
        if (! $product) {
            throw ValidationException::withMessages([
                $this->field($fieldPrefix, 'producto_id') => 'El producto no tiene una unidad base de inventario configurada.',
            ]);
        }

        $hasPackageInput = array_key_exists('cantidad_envases', $input)
            || array_key_exists('unidades_sueltas', $input)
            || array_key_exists('unidades_por_envase', $input);
        if (! $hasPackageInput) {
            $rawQuantity = $input['cantidad'] ?? null;
            if (! is_numeric($rawQuantity)
                || ! is_finite((float) $rawQuantity)
                || (float) $rawQuantity <= 0
            ) {
                throw ValidationException::withMessages([
                    $this->field($fieldPrefix, 'cantidad') => 'Ingresa una cantidad mayor que cero.',
                ]);
            }
            $quantity = (float) $rawQuantity;

            return [
                'base_quantity' => $quantity,
                'cantidad_original' => $quantity,
                'cantidad_envases' => 0,
                'unidades_sueltas' => $quantity,
                'factor_conversion' => 1,
                'presentacion_nombre' => 'Unidad base',
                'unidad_ingresada' => $product->unidad_base_codigo,
                'unidad_base_codigo' => $product->unidad_base_codigo,
            ];
        }

        $rawPackageCount = $input['cantidad_envases'] ?? 0;
        $rawLooseCount = $input['unidades_sueltas'] ?? 0;
        if (! is_numeric($rawPackageCount)
            || ! is_numeric($rawLooseCount)
            || ! is_finite((float) $rawPackageCount)
            || ! is_finite((float) $rawLooseCount)
            || (float) $rawPackageCount < 0
            || (float) $rawLooseCount < 0
        ) {
            throw ValidationException::withMessages([
                $this->field($fieldPrefix, 'cantidad_envases') => 'Las cantidades de envases y unidades sueltas deben ser números iguales o mayores que cero.',
            ]);
        }
        $packageCount = (float) $rawPackageCount;
        $looseCount = (float) $rawLooseCount;
        $presentation = null;
        $factor = 1.0;
        $presentationName = 'Unidad base';
        if (! empty($input['presentacion_id'])) {
            $presentation = DB::table('presentaciones_producto')
                ->where('presentacion_id', $input['presentacion_id'])
                ->where('producto_id', $product->producto_id)
                ->where('activo', true)
                ->lockForUpdate()
                ->first(['nombre', 'factor_conversion']);
            if (! $presentation) {
                throw ValidationException::withMessages([
                    $this->field($fieldPrefix, 'presentacion_id') => 'La presentación seleccionada no está disponible para este producto.',
                ]);
            }
            $factor = (float) $presentation->factor_conversion;
            $presentationName = $presentation->nombre;
        }

        if ($packageCount > 0 && ! $presentation) {
            $factorInput = $input['unidades_por_envase'] ?? null;
            if (! is_numeric($factorInput)
                || ! is_finite((float) $factorInput)
                || (float) $factorInput <= 0
            ) {
                throw ValidationException::withMessages([
                    $this->field($fieldPrefix, 'unidades_por_envase') => 'Indica cuántas unidades base contiene cada envase.',
                ]);
            }

            $factor = (float) $factorInput;
            $presentationName = DB::table('presentaciones_producto')
                ->where('producto_id', $product->producto_id)
                ->where('activo', true)
                ->where('factor_conversion', $factor)
                ->orderBy('presentacion_id')
                ->value('nombre');
            if ($presentationName === null) {
                $formattedFactor = rtrim(rtrim(number_format($factor, 4, '.', ''), '0'), '.');
                $presentationName = 'Envase de '.$formattedFactor.' '.$product->unidad_base_codigo;
            }
        }
        if ($packageCount <= 0 && $looseCount <= 0) {
            throw ValidationException::withMessages([
                $this->field($fieldPrefix, 'cantidad_envases') => 'Ingresa al menos un envase o una unidad suelta.',
            ]);
        }
        if ($packageCount <= 0) {
            $factor = 1.0;
            $presentationName = 'Unidad base';
        }

        $baseQuantity = $packageCount * $factor + $looseCount;
        if (! is_finite($baseQuantity) || abs(round($baseQuantity, 2) - $baseQuantity) > 0.000001) {
            throw ValidationException::withMessages([
                $this->field($fieldPrefix, 'cantidad_envases') => 'La conversión supera los dos decimales admitidos para el inventario.',
            ]);
        }

        return [
            'base_quantity' => round($baseQuantity, 2),
            'cantidad_original' => $packageCount > 0 ? $packageCount : $looseCount,
            'cantidad_envases' => $packageCount,
            'unidades_sueltas' => $looseCount,
            'factor_conversion' => $factor,
            'presentacion_nombre' => $presentationName,
            'unidad_ingresada' => $presentationName,
            'unidad_base_codigo' => $product->unidad_base_codigo,
        ];
    }

    private function field(string $prefix, string $field): string
    {
        return $prefix === '' ? $field : $prefix.'.'.$field;
    }
}
