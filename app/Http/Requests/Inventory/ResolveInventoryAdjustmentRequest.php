<?php

namespace App\Http\Requests\Inventory;

use Illuminate\Validation\Rule;

class ResolveInventoryAdjustmentRequest extends InventoryRequest
{
    protected const AUTHORIZED_PERMISSION = 'inventory.adjust.resolve';

    protected const AUDIT_ENTITY = 'solicitudes_ajuste_inventario';

    public function rules(): array
    {
        return [
            'decision' => ['required', Rule::in(['APROBAR', 'RECHAZAR'])],
            'motivo_resolucion' => ['required_if:decision,RECHAZAR', 'nullable', 'string', 'max:1000'],
        ];
    }

    protected function auditRecordId(): int|string|null
    {
        return $this->route('adjustmentId');
    }
}
