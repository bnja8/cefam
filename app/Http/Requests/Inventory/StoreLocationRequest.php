<?php

namespace App\Http\Requests\Inventory;

use App\Support\Inventory\InventoryLocationTypes;
use Illuminate\Validation\Rule;

class StoreLocationRequest extends LocationRequest
{
    // Define los campos permitidos para registrar una ubicación.
    public function rules(): array
    {
        return [
            'nombre' => ['required', 'string', 'max:100', Rule::unique('ubicaciones', 'nombre')],
            'tipo' => ['required', Rule::in(array_keys(InventoryLocationTypes::OPTIONS))],
            'descripcion' => ['nullable', 'string', 'max:255'],
        ];
    }
}
