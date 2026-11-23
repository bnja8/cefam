<?php

namespace App\Http\Requests\Inventory;

use Illuminate\Validation\Rule;

class StoreCategoryRequest extends InventoryRequest
{
    protected const AUTHORIZED_PERMISSION = 'inventory.catalog.manage';

    protected const AUDIT_ENTITY = 'categorias_producto';

    // Define los datos permitidos para registrar una categoría.
    public function rules(): array
    {
        return [
            'nombre_categoria' => ['required', 'string', 'max:100', Rule::unique('categorias_producto', 'nombre')],
            'descripcion_categoria' => ['nullable', 'string', 'max:255'],
        ];
    }
}
