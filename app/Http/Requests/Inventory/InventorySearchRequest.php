<?php

namespace App\Http\Requests\Inventory;

use Illuminate\Foundation\Http\FormRequest;

class InventorySearchRequest extends FormRequest
{
    // Permite consultar inventario a los usuarios autenticados de la ruta.
    public function authorize(): bool
    {
        return true;
    }

    // Valida filtros de búsqueda y paginación del inventario.
    public function rules(): array
    {
        return [
            'buscar' => ['nullable', 'string', 'max:100'],
            'buscar_global' => ['nullable', 'string', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
