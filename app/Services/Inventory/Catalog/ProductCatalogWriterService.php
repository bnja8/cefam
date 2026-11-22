<?php

namespace App\Services\Inventory\Catalog;

use App\Services\Audit\AuditLogger;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProductCatalogWriterService
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly ProductCatalogDataService $data,
        private readonly ProductCatalogRelationsService $relations
    ) {}

    public function create(array $data, bool $isControlled): int
    {
        return DB::transaction(function () use ($data, $isControlled): int {
            $data = $this->data->normalizeProductData($data);
            $productId = DB::table('productos')->insertGetId([
                'numero_serie' => $data['codigo_producto'],
                'nombre' => $data['nombre'],
                'descripcion' => $data['descripcion'] ?? null,
                'categoria_id' => $data['categoria_id'],
                ...$this->data->productAttributes($data, $isControlled),
                'activo' => true,
            ]);

            $this->relations->syncProductIngredients($productId, $data['principios_activos'] ?? []);
            $this->relations->syncProductPresentations($productId, $data['presentaciones']);

            if (! empty($data['codigo_barra'])) {
                DB::table('productos_codigo_barra')->insert([
                    'producto_id' => $productId,
                    'codigo_barra' => $data['codigo_barra'],
                    'activo' => true,
                ]);
            }

            $this->audit->record('CREACION', 'productos', $productId, 'EXITOSO', [
                'codigo_producto' => $data['codigo_producto'],
                'es_medicamento' => $data['es_medicamento'],
                'tipo_producto' => $data['tipo_producto'],
                'es_controlado' => $isControlled,
            ]);

            return (int) $productId;
        });
    }

    // Actualiza el producto y mantiene consistentes sus datos relacionados.

    public function update(int $productId, array $data, bool $isControlled, bool $isActive): void
    {
        DB::transaction(function () use ($data, $productId, $isControlled, $isActive): void {
            $product = DB::table('productos')->where('producto_id', $productId)->lockForUpdate()->first();
            if (! $product) {
                throw new ModelNotFoundException('No se encontró el producto solicitado.');
            }

            $data = $this->data->normalizeProductData($data, $productId);
            $this->data->validateBaseUnitIsUnchanged($product, (int) $data['unidad_base_inventario_id']);

            DB::table('productos')->where('producto_id', $productId)->update([
                'numero_serie' => $data['codigo_producto'],
                'nombre' => $data['nombre'],
                'descripcion' => $data['descripcion'] ?? null,
                'categoria_id' => $data['categoria_id'],
                ...$this->data->productAttributes($data, $isControlled),
                'activo' => $isActive,
            ]);

            DB::table('productos_principios_activos')->where('producto_id', $productId)->delete();
            $this->relations->syncProductIngredients($productId, $data['principios_activos'] ?? []);
            $this->relations->syncProductPresentations($productId, $data['presentaciones']);

            $barcode = DB::table('productos_codigo_barra')
                ->where('producto_id', $productId)
                ->where('activo', true)
                ->lockForUpdate()
                ->first();
            $newBarcode = $data['codigo_barra'] ?? null;
            if (! $barcode || $barcode->codigo_barra !== $newBarcode) {
                if ($barcode) {
                    DB::table('productos_codigo_barra')->where('codigo_barra_id', $barcode->codigo_barra_id)
                        ->update(['activo' => false]);
                }

                if ($newBarcode !== null && $newBarcode !== '') {
                    $existingBarcode = DB::table('productos_codigo_barra')
                        ->where('codigo_barra', $newBarcode)
                        ->lockForUpdate()
                        ->first();

                    if ($existingBarcode && (int) $existingBarcode->producto_id !== $productId) {
                        throw ValidationException::withMessages([
                            'codigo_barra' => 'Este código de barras ya está asociado a otro producto.',
                        ]);
                    }

                    if ($existingBarcode) {
                        DB::table('productos_codigo_barra')
                            ->where('codigo_barra_id', $existingBarcode->codigo_barra_id)
                            ->update(['activo' => true]);
                    } else {
                        DB::table('productos_codigo_barra')->insert([
                            'producto_id' => $productId,
                            'codigo_barra' => $newBarcode,
                            'activo' => true,
                        ]);
                    }
                }
            }

            $this->audit->record('ACTUALIZACION', 'productos', $productId, 'EXITOSO', [
                'codigo_producto' => $data['codigo_producto'],
                'activo' => $isActive,
                'es_medicamento' => $data['es_medicamento'],
                'tipo_producto' => $data['tipo_producto'],
                'es_controlado' => $isControlled,
            ]);
        });
    }
}
