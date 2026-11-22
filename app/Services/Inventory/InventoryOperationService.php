<?php

namespace App\Services\Inventory;

class InventoryOperationService
{
    public function __construct(
        private readonly InventoryReceiptService $receipts,
        private readonly InventoryAdjustmentService $adjustments,
        private readonly InventoryTransferService $transfers
    ) {}

    // Registra una recepción y actualiza el stock en una transacción.
    public function receive(array $data, string $userRut): void
    {
        $this->receipts->receive($data, $userRut);
    }

    // Crea una solicitud; el stock cambia únicamente al autorizarla.
    public function requestAdjustment(array $data, string $requesterRut): int
    {
        return $this->adjustments->requestAdjustment($data, $requesterRut);
    }

    public function resolveAdjustmentRequest(int $requestId, string $approverRut, string $decision, ?string $resolutionReason = null): void
    {
        $this->adjustments->resolveAdjustmentRequest($requestId, $approverRut, $decision, $resolutionReason);
    }

    public function adjust(array $data, string $userRut, ?int $adjustmentRequestId = null, ?string $requesterRut = null): int
    {
        return $this->adjustments->adjust($data, $userRut, $adjustmentRequestId, $requesterRut);
    }

    // Registra el traslado del mismo lote entre dos ubicaciones.
    public function transfer(array $data, string $userRut): void
    {
        $this->transfers->transfer($data, $userRut);
    }

    // Guarda los límites de stock configurados para un producto y ubicación.
    public function updateStockLimits(int $productId, array $data): void
    {
        $this->transfers->updateStockLimits($productId, $data);
    }
}
