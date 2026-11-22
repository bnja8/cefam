<?php

namespace App\Services\Inventory\Catalog;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProductCatalogDataService
{
    public function __construct() {}

    public function normalizeProductData(array $data, ?int $productId = null): array
    {
        $data['es_medicamento'] = $data['tipo_producto'] === 'MEDICAMENTO';
        $data['unidad_base_inventario_id'] = $data['unidad_base_inventario_id']
            ?? ($data['es_medicamento']
                ? ($data['contenido_envase_unidad_id'] ?? null)
                : ($productId
                    ? DB::table('productos')->where('producto_id', $productId)->value('unidad_base_inventario_id')
                    : DB::table('unidades_medida')->where('codigo', 'unidad')->value('unidad_medida_id')));

        if (! $data['unidad_base_inventario_id']) {
            throw ValidationException::withMessages([
                'unidad_base_inventario_id' => 'No existe una unidad de medida predeterminada para este producto.',
            ]);
        }

        $data['presentaciones'] = $this->defaultProductPresentations($data, $productId);
        $commercialPresentations = $data['presentaciones_comerciales']
            ?? ($productId === null
                ? []
                : $this->decodeCommercialPresentations(
                    DB::table('productos')->where('producto_id', $productId)->value('presentaciones_comerciales')
                ));
        $data['presentaciones_comerciales'] = array_values(array_map(
            fn (string $name) => trim(preg_replace('/\s+/u', ' ', $name) ?? $name),
            array_filter(
                $commercialPresentations,
                fn ($name) => trim((string) $name) !== ''
            )
        ));

        return $data;
    }

    /**
     * @return array<int, string>
     */
    public function decodeCommercialPresentations(?string $presentations): array
    {
        if ($presentations === null || $presentations === '') {
            return [];
        }

        $decoded = json_decode($presentations, true, 512, JSON_THROW_ON_ERROR);
        if (! is_array($decoded) || ! array_is_list($decoded)) {
            throw new \UnexpectedValueException('Las presentaciones comerciales del producto deben ser una lista JSON.');
        }

        return array_values(array_filter($decoded, 'is_string'));
    }

    // Prepara los atributos compatibles con el tipo de producto.

    public function productAttributes(array $data, bool $isControlled): array
    {
        $isMedicine = $data['es_medicamento'];

        return [
            'tipo_producto' => $data['tipo_producto'],
            'es_medicamento' => $isMedicine,
            'unidad_base_inventario_id' => $data['unidad_base_inventario_id'],
            'forma_farmaceutica_id' => $isMedicine ? $data['forma_farmaceutica_id'] : null,
            'via_administracion' => $isMedicine ? $data['via_administracion'] : null,
            'contenido_envase_cantidad' => $isMedicine ? $data['contenido_envase_cantidad'] : null,
            'contenido_envase_unidad_id' => $isMedicine ? $data['contenido_envase_unidad_id'] : null,
            'tipo_presentacion' => $isMedicine ? $data['tipo_presentacion'] : null,
            'bioequivalencia_estado' => $isMedicine ? $data['bioequivalencia_estado'] : 'NO_APLICA',
            'es_controlado' => $isMedicine && $isControlled,
            'tipo_control_legal' => $isMedicine && $isControlled
                ? $data['tipo_control_legal']
                : null,
            'registro_sanitario_isp' => $isMedicine ? ($data['registro_sanitario_isp'] ?? null) : null,
            'estado_registro_sanitario' => $isMedicine ? $data['estado_registro_sanitario'] : 'NO_APLICA',
            'presentaciones_comerciales' => json_encode(
                $data['presentaciones_comerciales'],
                JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
            ),
        ];
    }

    // Sincroniza principios activos normalizados con sus concentraciones.

    public function defaultProductPresentations(array $data, ?int $productId): array
    {
        $presentations = array_values(array_filter(
            $data['presentaciones'] ?? [],
            fn ($presentation) => trim((string) ($presentation['nombre'] ?? '')) !== ''
                || trim((string) ($presentation['factor_conversion'] ?? '')) !== ''
        ));
        if ($presentations !== []) {
            return $presentations;
        }

        if (! $data['es_medicamento']) {
            if ($productId === null) {
                return [];
            }

            return DB::table('presentaciones_producto')
                ->where('producto_id', $productId)
                ->where('nombre', '<>', 'Unidad base')
                ->where('activo', true)
                ->get(['nombre', 'factor_conversion'])
                ->map(fn ($presentation) => [
                    'nombre' => $presentation->nombre,
                    'factor_conversion' => $presentation->factor_conversion,
                ])->all();
        }

        $presentationNames = [
            'CAJA' => 'Caja',
            'FRASCO' => 'Frasco',
            'TUBO' => 'Tubo',
            'BLISTER' => 'Blíster',
            'SOBRE' => 'Sobre',
            'UNIDAD' => 'Unidad',
            'OTRO' => 'Otro envase',
        ];

        return [[
            'nombre' => $presentationNames[$data['tipo_presentacion']],
            'factor_conversion' => $data['contenido_envase_cantidad'],
        ]];
    }

    // Impide cambiar la unidad base después de registrar movimientos o stock.

    public function validateBaseUnitIsUnchanged(object $product, int $unitId): void
    {
        if ((int) $product->unidad_base_inventario_id === $unitId) {
            return;
        }

        $hasInventoryHistory = DB::table('lotes')
            ->where('producto_id', $product->producto_id)
            ->where(function ($query) {
                $query->whereExists(function ($inventory) {
                    $inventory->selectRaw('1')
                        ->from('inventario')
                        ->whereColumn('inventario.lote_id', 'lotes.lote_id');
                })->orWhereExists(function ($movements) {
                    $movements->selectRaw('1')
                        ->from('movimientos_inventario')
                        ->whereColumn('movimientos_inventario.lote_id', 'lotes.lote_id');
                });
            })
            ->exists();
        if ($hasInventoryHistory) {
            throw ValidationException::withMessages([
                'es_medicamento' => 'No se puede cambiar la unidad de control de un producto con existencias o movimientos. Conserva el tipo y la unidad de contenido actuales.',
            ]);
        }
    }
}
