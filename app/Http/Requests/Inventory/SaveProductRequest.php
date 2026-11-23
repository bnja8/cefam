<?php

namespace App\Http\Requests\Inventory;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SaveProductRequest extends InventoryRequest
{
    private const PHARMACEUTICAL_FORM_UNITS = [
        'comprimido' => ['comprimido'],
        'cápsula' => ['cápsula'],
        'solución oral' => ['mL', 'L'],
        'suspensión oral' => ['mL', 'L'],
        'polvo' => ['sobre'],
        'polvo para suspensión' => ['mL', 'L', 'sobre'],
        'crema' => ['g'],
        'pomada' => ['g'],
        'gel' => ['g'],
        'jarabe' => ['mL', 'L'],
        'inyectable' => ['mL'],
        'gotas' => ['mL'],
        'inhalador' => ['dosis'],
        'supositorio' => ['unidad'],
        'parche transdérmico' => ['unidad'],
    ];

    protected const AUTHORIZED_PERMISSION = 'inventory.products.manage';

    protected const AUDIT_ENTITY = 'productos';

    // Completa valores derivados que el formulario permite omitir.
    protected function prepareForValidation(): void
    {
        if ($this->boolean('presentaciones_comerciales_present')) {
            $this->merge([
                'presentaciones_comerciales' => $this->input('presentaciones_comerciales', []),
            ]);
        }

        if (! $this->filled('tipo_producto') && $this->has('es_medicamento')) {
            $this->merge([
                'tipo_producto' => $this->boolean('es_medicamento') ? 'MEDICAMENTO' : 'INSUMO_CLINICO',
            ]);
        }

        if ($this->filled('unidad_base_inventario_id')) {
            return;
        }

        if ($this->input('tipo_producto') === 'MEDICAMENTO') {
            $unitId = $this->input('contenido_envase_unidad_id');
        } elseif ($productId = $this->route('productId')) {
            $unitId = DB::table('productos')->where('producto_id', $productId)->value('unidad_base_inventario_id');
        } else {
            $unitId = DB::table('unidades_medida')->where('codigo', 'unidad')->value('unidad_medida_id');
        }

        if ($unitId) {
            $this->merge(['unidad_base_inventario_id' => $unitId]);
        }
    }

    // Define las reglas para crear o actualizar un producto.
    public function rules(): array
    {
        $productId = $this->route('productId');
        $productCodeRule = Rule::unique('productos', 'numero_serie');
        $barcodeRule = Rule::unique('productos_codigo_barra', 'codigo_barra');

        if ($productId) {
            $productCodeRule->ignore($productId, 'producto_id');
            $barcodeValue = $this->input('codigo_barra');
            $barcodeId = is_string($barcodeValue) && $barcodeValue !== ''
                ? DB::table('productos_codigo_barra')
                    ->where('producto_id', $productId)
                    ->where('codigo_barra', $barcodeValue)
                    ->value('codigo_barra_id')
                : null;
            $barcodeId ??= DB::table('productos_codigo_barra')
                ->where('producto_id', $productId)
                ->where('activo', true)
                ->value('codigo_barra_id');
            if ($barcodeId) {
                $barcodeRule->ignore($barcodeId, 'codigo_barra_id');
            }
        }

        return [
            'codigo_producto' => ['required', 'string', 'max:50', $productCodeRule],
            'nombre' => ['required', 'string', 'max:150'],
            'presentaciones_comerciales_present' => ['sometimes', 'boolean'],
            'presentaciones_comerciales' => ['sometimes', 'array', 'max:20'],
            'presentaciones_comerciales.*' => ['required', 'string', 'max:150', 'distinct:ignore_case'],
            'categoria_id' => ['required', 'integer', 'exists:categorias_producto,categoria_id'],
            'descripcion' => ['nullable', 'string'],
            'codigo_barra' => ['nullable', 'string', 'max:100', $barcodeRule],
            'tipo_producto' => ['required', Rule::in(['MEDICAMENTO', 'INSUMO_CLINICO', 'DISPOSITIVO_MEDICO'])],
            'unidad_base_inventario_id' => [
                'nullable',
                'integer',
                Rule::exists('unidades_medida', 'unidad_medida_id')->where('activo', true),
            ],
            'forma_farmaceutica_id' => [
                'exclude_unless:tipo_producto,MEDICAMENTO',
                'required',
                'integer',
                Rule::exists('formas_farmaceuticas', 'forma_farmaceutica_id')->where('activo', true),
            ],
            'via_administracion' => [
                'exclude_unless:tipo_producto,MEDICAMENTO',
                'required',
                'string',
                Rule::in(['ORAL', 'TOPICA', 'INTRAVENOSA', 'INTRAMUSCULAR', 'SUBCUTANEA', 'INHALATORIA', 'OFTALMICA', 'OTICA', 'RECTAL', 'VAGINAL', 'NASAL', 'TRANSDERMICA', 'BUCAL', 'SUBLINGUAL']),
            ],
            'principios_activos' => ['exclude_unless:tipo_producto,MEDICAMENTO', 'required', 'array', 'min:1'],
            'principios_activos.*.nombre' => ['required', 'string', 'max:150', 'distinct:ignore_case'],
            'principios_activos.*.concentracion_cantidad' => ['required', 'numeric', 'gt:0', 'decimal:0,4'],
            'principios_activos.*.concentracion_unidad_id' => [
                'required',
                'integer',
                Rule::exists('unidades_medida', 'unidad_medida_id')->whereIn('tipo', ['MASA', 'PORCENTAJE']),
            ],
            'principios_activos.*.concentracion_base_cantidad' => ['nullable', 'numeric', 'gt:0', 'decimal:0,4'],
            'principios_activos.*.concentracion_base_unidad_id' => [
                'nullable',
                'integer',
                Rule::exists('unidades_medida', 'unidad_medida_id')->whereIn('tipo', ['MASA', 'VOLUMEN', 'PRESENTACION']),
            ],
            'contenido_envase_cantidad' => ['exclude_unless:tipo_producto,MEDICAMENTO', 'required', 'numeric', 'gt:0', 'decimal:0,3'],
            'contenido_envase_unidad_id' => [
                'exclude_unless:tipo_producto,MEDICAMENTO',
                'required',
                'integer',
                Rule::exists('unidades_medida', 'unidad_medida_id')->whereIn('tipo', ['MASA', 'VOLUMEN', 'PRESENTACION']),
            ],
            'tipo_presentacion' => [
                'exclude_unless:tipo_producto,MEDICAMENTO',
                'required',
                'string',
                Rule::in(['CAJA', 'FRASCO', 'TUBO', 'BLISTER', 'SOBRE', 'UNIDAD', 'OTRO']),
            ],
            'presentaciones' => ['sometimes', 'array', 'max:20'],
            'presentaciones.*.nombre' => ['required', 'string', 'max:100', 'distinct:ignore_case'],
            'presentaciones.*.factor_conversion' => ['required', 'numeric', 'gt:0', 'decimal:0,4'],
            'bioequivalencia_estado' => [
                'exclude_unless:tipo_producto,MEDICAMENTO',
                'required',
                Rule::in(['SI', 'NO', 'NO_APLICA', 'PENDIENTE']),
            ],
            'es_controlado' => ['nullable', 'boolean'],
            'tipo_control_legal' => [
                'exclude_unless:es_controlado,1',
                'required',
                Rule::in(['ESTUPEFACIENTE', 'PSICOTROPICO', 'OTRO']),
            ],
            'registro_sanitario_isp' => ['exclude_unless:tipo_producto,MEDICAMENTO', 'nullable', 'string', 'max:80'],
            'estado_registro_sanitario' => [
                'exclude_unless:tipo_producto,MEDICAMENTO',
                'required',
                Rule::in(['VIGENTE', 'SUSPENDIDO', 'VENCIDO', 'CANCELADO', 'PENDIENTE', 'NO_APLICA']),
            ],
            'activo' => ['sometimes', 'boolean'],
        ];
    }

    // Comprueba restricciones que dependen de varios campos del producto.
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $data = $validator->validated();
                if (empty($data['unidad_base_inventario_id'])) {
                    $validator->errors()->add(
                        'unidad_base_inventario_id',
                        'No existe una unidad de medida predeterminada para este producto.'
                    );

                    return;
                }

                foreach ($data['principios_activos'] ?? [] as $index => $ingredient) {
                    $hasBaseQuantity = isset($ingredient['concentracion_base_cantidad']) && $ingredient['concentracion_base_cantidad'] !== '';
                    $hasBaseUnit = isset($ingredient['concentracion_base_unidad_id']) && $ingredient['concentracion_base_unidad_id'] !== '';
                    if ($hasBaseQuantity !== $hasBaseUnit) {
                        $validator->errors()->add(
                            "principios_activos.$index.concentracion_base_cantidad",
                            'Completa tanto la cantidad como la unidad de referencia de la concentración.'
                        );
                    }
                }

                if ($data['tipo_producto'] !== 'MEDICAMENTO' || $validator->errors()->isNotEmpty()) {
                    return;
                }

                $formName = DB::table('formas_farmaceuticas')
                    ->where('forma_farmaceutica_id', $data['forma_farmaceutica_id'])
                    ->value('nombre');
                $unitCodes = DB::table('unidades_medida')->pluck('codigo', 'unidad_medida_id');
                $allowedCodes = self::PHARMACEUTICAL_FORM_UNITS[mb_strtolower($formName)]
                    ?? $unitCodes->values()->all();
                $packageUnit = $unitCodes->get($data['contenido_envase_unidad_id']);

                if (! in_array($packageUnit, $allowedCodes, true)) {
                    $validator->errors()->add(
                        'contenido_envase_unidad_id',
                        'La unidad del contenido debe corresponder a la forma farmacéutica seleccionada.'
                    );
                }

                $percentageUnitId = $unitCodes->search('%');
                foreach ($data['principios_activos'] as $index => $ingredient) {
                    $isPercentage = (string) $ingredient['concentracion_unidad_id'] === (string) $percentageUnitId;
                    $hasBaseQuantity = isset($ingredient['concentracion_base_cantidad']) && $ingredient['concentracion_base_cantidad'] !== '';
                    $hasBaseUnit = isset($ingredient['concentracion_base_unidad_id']) && $ingredient['concentracion_base_unidad_id'] !== '';

                    if (! $isPercentage && (! $hasBaseQuantity || ! $hasBaseUnit)) {
                        $validator->errors()->add(
                            "principios_activos.$index.concentracion_base_cantidad",
                            'Indica la cantidad y unidad de referencia de esta concentración.'
                        );
                    }
                    if ($hasBaseUnit && ! in_array($unitCodes->get($ingredient['concentracion_base_unidad_id']), $allowedCodes, true)) {
                        $validator->errors()->add(
                            "principios_activos.$index.concentracion_base_unidad_id",
                            'La unidad de referencia no corresponde a la forma farmacéutica seleccionada.'
                        );
                    }
                }
            },
        ];
    }
}
