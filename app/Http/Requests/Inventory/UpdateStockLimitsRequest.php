<?php

namespace App\Http\Requests\Inventory;

use Illuminate\Validation\Validator;

class UpdateStockLimitsRequest extends InventoryRequest
{
    protected const AUTHORIZED_PERMISSION = 'inventory.limits.manage';

    protected const AUDIT_ENTITY = 'stock_minimos_ubicacion';

    protected function getRedirectUrl(): string
    {
        $url = route('products.stock', $this->route('productId'));
        $locationId = filter_var($this->input('ubicacion_id'), FILTER_VALIDATE_INT);

        return $locationId === false
            ? $url
            : $url.'#stock-ubicacion-'.$locationId;
    }

    // Define los datos permitidos para los límites por ubicación.
    public function rules(): array
    {
        return [
            'ubicacion_id' => ['required', 'integer', 'exists:ubicaciones,ubicacion_id'],
            'stock_minimo' => ['required', 'numeric', 'decimal:0,2', 'gte:0'],
            'stock_maximo' => ['nullable', 'numeric', 'decimal:0,2', 'gt:0'],
        ];
    }

    // Comprueba que el máximo no sea inferior al mínimo.
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $minimum = $this->input('stock_minimo');
                $maximum = $this->input('stock_maximo');

                if ($maximum !== null && $minimum !== null && (float) $maximum < (float) $minimum) {
                    $validator->errors()->add(
                        'stock_maximo',
                        'El stock máximo debe ser mayor o igual al stock mínimo.'
                    );
                }
            },
        ];
    }
}
