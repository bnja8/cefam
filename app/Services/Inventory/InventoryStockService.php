<?php

namespace App\Services\Inventory;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class InventoryStockService
{
    public function __construct(
        private readonly InventoryStockQueryService $queries,
        private readonly InventoryStockDetailService $details
    ) {}

    // Pagina productos junto con existencias por lote y ubicación.
    public function products(?string $term = null, ?string $globalTerm = null): LengthAwarePaginator
    {
        return $this->queries->products($term, $globalTerm);
    }

    // Resume cuántos productos tienen existencias disponibles.
    public function productSummary(): object
    {
        return $this->queries->productSummary();
    }

    // Obtiene las ubicaciones disponibles para las métricas de inventario.
    public function locations(): Collection
    {
        return $this->queries->locations();
    }

    // Obtiene detalle de un producto con existencias y sus lotes paginados.
    public function productStock(int $productId): ?array
    {
        return $this->details->productStock($productId);
    }

    // Obtiene el detalle de lotes y el resumen para la API de inventario.
    public function apiProductLots(int $productId): ?array
    {
        return $this->details->apiProductLots($productId);
    }

    // Obtiene lotes FEFO disponibles en ubicaciones tipo vitrina.
    public function fefoLots(int $productId): ?array
    {
        return $this->details->fefoLots($productId);
    }
}
