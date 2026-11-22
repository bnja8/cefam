<?php


namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class LookupBarcodeRequest extends FormRequest
{
    // Comprueba si el usuario puede realizar esta acción.
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'codigo_barra' => ['required', 'string', 'max:100'],
        ];
    }

    public function attributes(): array
    {
        return ['codigo_barra' => 'código de barras'];
    }
}
