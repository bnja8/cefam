<?php

namespace App\Http\Requests\Inventory;

class TransferInventoryRequest extends InventoryRequest
{
    protected const AUTHORIZED_PERMISSION = 'inventory.transfer';

    protected const AUDIT_ENTITY = 'traspasos_inventario';

    // Define los datos permitidos para transferir existencias.
    public function rules(): array
    {
        return [
            'lote_id' => ['required', 'integer', 'exists:lotes,lote_id'],
            'origen_id' => ['required', 'integer', 'exists:ubicaciones,ubicacion_id', 'different:destino_id'],
            'destino_id' => ['required', 'integer', 'exists:ubicaciones,ubicacion_id'],
            'cantidad' => ['required', 'numeric', 'decimal:0,2', 'gt:0'],
            'observacion' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
