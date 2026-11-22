<?php

namespace App\Http\Requests\Inventory;

use App\Support\Inventory\InventoryLocationTypes;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class UpdateLocationRequest extends LocationRequest
{
    // Define los campos permitidos para actualizar una ubicación.
    public function rules(): array
    {
        return [
            'nombre' => [
                'required',
                'string',
                'max:100',
                Rule::unique('ubicaciones', 'nombre')->ignore($this->route('locationId'), 'ubicacion_id'),
            ],
            'tipo' => ['required', Rule::in(array_keys(InventoryLocationTypes::OPTIONS))],
            'descripcion' => ['nullable', 'string', 'max:255'],
        ];
    }

    // Autoriza primero y mantiene la respuesta 404 para ubicaciones inexistentes.
    public function authorize(\App\Services\Audit\AuditLogger $audit): bool
    {
        if (! parent::authorize($audit)) {
            return false;
        }

        abort_unless(
            DB::table('ubicaciones')->where('ubicacion_id', $this->route('locationId'))->exists(),
            404
        );

        return true;
    }
}
