<?php

namespace App\Http\Requests\Inventory;

use Illuminate\Validation\Rule;

class AdjustInventoryRequest extends InventoryRequest
{
    protected const AUTHORIZED_ROLES = ['Administrador', 'Químico Farmacéutico', 'Técnico Farmacéutico'];

    protected const AUDIT_ENTITY = 'solicitudes_ajuste_inventario';

    // Define los datos permitidos para ajustar existencias.
    public function rules(): array
    {
        return [
            'lote_id' => ['required', 'integer', 'exists:lotes,lote_id'],
            'ubicacion_id' => ['required', 'integer', 'exists:ubicaciones,ubicacion_id'],
            'tipo_ajuste' => ['required', Rule::in(['ENTRADA', 'SALIDA'])],
            'cantidad' => ['required', 'numeric', 'decimal:0,2', 'gt:0'],
            'motivo' => ['required', 'string', 'max:1000'],
        ];
    }
}
