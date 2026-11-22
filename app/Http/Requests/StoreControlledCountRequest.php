<?php


namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreControlledCountRequest extends FormRequest
{
    // Comprueba si el usuario puede realizar esta acción.
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'producto_id' => ['required', 'integer', 'exists:productos,producto_id'],
            'lote_id' => ['required', 'integer', 'exists:lotes,lote_id'],
            'ubicacion_id' => ['required', 'integer', 'exists:ubicaciones,ubicacion_id'],
            'cantidad_contada' => ['required', 'numeric', 'decimal:0,2', 'gte:0'],
            'observacion' => ['required', 'string', 'max:1000'],
            'password' => ['required', 'string'],
        ];
    }
}
