<?php


namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class GlobalSearchRequest extends FormRequest
{
    // Comprueba si el usuario puede realizar esta acción.
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['q' => ['required', 'string', 'min:2', 'max:100']];
    }
}
