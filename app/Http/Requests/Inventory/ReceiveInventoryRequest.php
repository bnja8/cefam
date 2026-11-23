<?php

namespace App\Http\Requests\Inventory;

use App\Rules\FourDigitDate;
use Illuminate\Validation\Validator;

class ReceiveInventoryRequest extends InventoryRequest
{
    protected const AUTHORIZED_PERMISSION = 'supplies.receive';

    protected const AUDIT_ENTITY = 'inventario';

    // Define los datos permitidos para recibir existencias.
    public function rules(): array
    {
        return [
            'producto_id' => ['required', 'integer', 'exists:productos,producto_id,activo,1'],
            'ubicacion_id' => ['required', 'integer', 'exists:ubicaciones,ubicacion_id'],
            'numero_lote' => ['required', 'string', 'max:100'],
            'fecha_fabricacion' => ['nullable', 'bail', new FourDigitDate(), 'before_or_equal:today', 'before:fecha_vencimiento'],
            'fecha_vencimiento' => ['required', 'bail', new FourDigitDate(), 'after_or_equal:today'],
            'fecha_recepcion' => ['nullable', 'bail', new FourDigitDate(), 'before_or_equal:now'],
            'cantidad' => ['nullable', 'numeric', 'decimal:0,2', 'gt:0', 'required_without_all:cantidad_envases,unidades_sueltas'],
            'presentacion_id' => ['nullable', 'integer', 'exists:presentaciones_producto,presentacion_id'],
            'cantidad_envases' => ['nullable', 'integer', 'gte:0'],
            'unidades_por_envase' => ['nullable', 'numeric', 'decimal:0,4', 'gt:0'],
            'unidades_sueltas' => ['nullable', 'numeric', 'decimal:0,2', 'gte:0'],
            'cantidad_rechazada' => ['nullable', 'numeric', 'decimal:0,2', 'gte:0'],
            'motivo_rechazo' => ['nullable', 'string', 'max:500'],
            'origen' => ['required', 'string', 'max:150'],
            'documento_recepcion' => ['required', 'string', 'max:100'],
        ];
    }

    // Exige un motivo cuando se rechaza una parte de la recepción.
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                if ((float) $this->input('cantidad_rechazada', 0) > 0
                    && trim((string) $this->input('motivo_rechazo', '')) === '') {
                    $validator->errors()->add(
                        'motivo_rechazo',
                        'Indica el motivo de rechazo para dejar trazabilidad del control de calidad.'
                    );
                }
            },
        ];
    }
}
