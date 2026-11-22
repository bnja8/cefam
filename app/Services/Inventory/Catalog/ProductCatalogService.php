<?php

namespace App\Services\Inventory\Catalog;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ProductCatalogService
{
    public function __construct(
        private readonly ProductCatalogQueryService $queries,
        private readonly ProductCatalogWriterService $writer
    ) {}

    // Obtiene el catálogo paginado con los resúmenes de productos.
    public function catalogPage(): LengthAwarePaginator
    {
        return $this->queries->catalogPage();
    }

    // Obtiene las opciones para los formularios de productos.
    public function formOptions(): array
    {
        return $this->queries->formOptions();
    }

    // Obtiene un producto y sus relaciones para editarlo.
    public function editData(int $productId): array
    {
        return $this->queries->editData($productId);
    }

    // Crea un producto y sus datos relacionados en una sola transacción.
    public function create(array $data, bool $isControlled): int
    {
        return $this->writer->create($data, $isControlled);
    }

    // Actualiza el producto y mantiene consistentes sus datos relacionados.
    public function update(int $productId, array $data, bool $isControlled, bool $isActive): void
    {
        $this->writer->update($productId, $data, $isControlled, $isActive);
    }
}
