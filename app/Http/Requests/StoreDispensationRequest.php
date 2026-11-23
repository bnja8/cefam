<?php


namespace App\Http\Requests;

use App\Support\People\Rut;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreDispensationRequest extends FormRequest
{
    // Comprueba si el usuario puede realizar esta acción.
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $isSos = $this->boolean('tratamiento_sos');
        $rules = [
            'paciente_rut' => ['required', 'string', 'max:12', 'exists:pacientes,rut'],
            'tratamiento_sos' => ['sometimes', 'boolean'],
            'receta_id' => [$isSos ? 'prohibited' : 'required', 'nullable', 'integer', 'exists:recetas,receta_id'],
            'persona_autorizada_id' => [$isSos ? 'prohibited' : 'nullable', 'integer', 'exists:personas_autorizadas,persona_autorizada_id'],
            'nueva_persona_autorizada' => [$isSos ? 'prohibited' : 'nullable', 'array:rut,nombre,apellidoPaterno,apellidoMaterno,vinculo'],
            'nueva_persona_autorizada.rut' => [
                'required_with:nueva_persona_autorizada',
                'string',
                'max:9',
                'regex:/^\d{7,8}[0-9K]$/',
                fn ($attribute, $value, $fail) => Rut::validarRut((string) $value) ?: $fail('El RUT ingresado no es válido.'),
                Rule::unique('personas_autorizadas', 'rut')->where('paciente_rut', $this->input('paciente_rut')),
            ],
            'nueva_persona_autorizada.nombre' => ['required_with:nueva_persona_autorizada', 'string', 'max:50'],
            'nueva_persona_autorizada.apellidoPaterno' => ['required_with:nueva_persona_autorizada', 'string', 'max:50'],
            'nueva_persona_autorizada.apellidoMaterno' => ['required_with:nueva_persona_autorizada', 'string', 'max:50'],
            'nueva_persona_autorizada.vinculo' => ['required_with:nueva_persona_autorizada', 'string', 'max:100'],
            'observacion' => ['nullable', 'string', 'max:1000'],
            'autorizador_rut' => ['nullable', 'string', 'max:12'],
            'autorizador_password' => ['nullable', 'string', 'max:256'],
            'transcripcion_manual' => ['sometimes', 'accepted'],
            'detalles' => ['required', 'array', 'min:1', 'max:30'],
            'detalles.*.producto_id' => ['required', 'integer', 'distinct', 'exists:productos,producto_id'],
            'detalles.*.cantidad' => ['required', 'numeric', 'decimal:0,2', 'gt:0'],
            'detalles.*.dosis' => ['nullable', 'string', 'max:100'],
            'detalles.*.frecuencia' => ['nullable', 'string', 'max:100'],
            'detalles.*.indicaciones' => ['nullable', 'string', 'max:1000'],
        ];

        if ($this->boolean('transcripcion_manual') && ! $isSos) {
            $rules += [
                'detalles.*.unidades_por_toma' => ['required', 'numeric', 'decimal:0,2', 'gt:0'],
                'detalles.*.duracion_dias' => ['nullable', 'integer', 'min:1', 'max:365'],
                'detalles.*.cantidad_indicada' => ['required', 'numeric', 'decimal:0,2', 'gt:0'],
            ];
        }

        if (! $this->expectsJson() && $this->boolean('transcripcion_manual') && ! $this->boolean('tratamiento_sos')) {
            $rules['confirmacion_receta'] = ['required', 'accepted'];
        }

        return $rules;
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($this->filled('persona_autorizada_id') && is_array($this->input('nueva_persona_autorizada'))) {
                    $validator->errors()->add('persona_autorizada_id', 'Selecciona una persona autorizada existente o registra una nueva, no ambas.');
                }
            },
        ];
    }

    protected function prepareForValidation(): void
    {
        $newAuthorizedPerson = $this->input('nueva_persona_autorizada');
        if (is_array($newAuthorizedPerson) && isset($newAuthorizedPerson['rut'])) {
            $newAuthorizedPerson['rut'] = Rut::limpiarRut((string) $newAuthorizedPerson['rut']);
            $this->merge(['nueva_persona_autorizada' => $newAuthorizedPerson]);
        }

        if ($this->has('producto_id') && $this->has('cantidad') && ! $this->has('detalles')) {
            $this->merge([
                'detalles' => [[
                    'producto_id' => $this->input('producto_id'),
                    'cantidad' => $this->input('cantidad'),
                    'dosis' => $this->input('dosis'),
                    'frecuencia' => $this->input('frecuencia'),
                    'indicaciones' => $this->input('indicaciones'),
                ]],
            ]);
        }
    }

    public function attributes(): array
    {
        return [
            'paciente_rut' => 'paciente',
            'receta_id' => 'receta vigente',
            'persona_autorizada_id' => 'persona autorizada',
            'confirmacion_receta' => 'confirmación de cotejo de la receta',
            'nueva_persona_autorizada.rut' => 'RUT de la persona autorizada',
            'nueva_persona_autorizada.nombre' => 'nombre de la persona autorizada',
            'nueva_persona_autorizada.apellidoPaterno' => 'apellido paterno de la persona autorizada',
            'nueva_persona_autorizada.apellidoMaterno' => 'apellido materno de la persona autorizada',
            'nueva_persona_autorizada.vinculo' => 'vínculo con el paciente',
            'detalles' => 'medicamentos',
            'detalles.*.producto_id' => 'producto',
            'detalles.*.cantidad' => 'cantidad',
            'detalles.*.unidades_por_toma' => 'dosis por toma',
            'detalles.*.duracion_dias' => 'duración',
            'detalles.*.frecuencia' => 'frecuencia',
            'detalles.*.cantidad_indicada' => 'cantidad prescrita',
        ];
    }
}
