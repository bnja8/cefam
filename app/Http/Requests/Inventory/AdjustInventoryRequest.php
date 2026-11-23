<?php

namespace App\Http\Requests\Inventory;

use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;

class AdjustInventoryRequest extends InventoryRequest
{
    protected const AUTHORIZED_PERMISSION = 'inventory.adjust.request';

    protected const AUDIT_ENTITY = 'solicitudes_ajuste_inventario';

    // Define los datos permitidos para ajustar existencias.
    public function rules(): array
    {
        return [
            'detalles' => ['sometimes', 'array', 'min:1'],
            'detalles.*.producto_id' => ['required_with:detalles', 'integer', 'exists:productos,producto_id'],
            'detalles.*.lote_id' => ['required_with:detalles', 'integer', 'exists:lotes,lote_id'],
            'detalles.*.ubicacion_id' => ['required_with:detalles', 'integer', 'exists:ubicaciones,ubicacion_id'],
            'detalles.*.tipo_ajuste' => ['required_with:detalles', Rule::in(['ENTRADA', 'SALIDA'])],
            'detalles.*.cantidad' => ['required_with:detalles', 'numeric', 'decimal:0,2', 'gt:0'],
            'detalles.*.motivo' => ['required_with:detalles', 'string', 'max:1000'],
            'lote_id' => ['required_without:detalles', 'integer', 'exists:lotes,lote_id'],
            'ubicacion_id' => ['required_without:detalles', 'integer', 'exists:ubicaciones,ubicacion_id'],
            'tipo_ajuste' => ['required_without:detalles', Rule::in(['ENTRADA', 'SALIDA'])],
            'cantidad' => ['required_without:detalles', 'numeric', 'decimal:0,2', 'gt:0'],
            'motivo' => ['required_without:detalles', 'string', 'max:1000'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $details = $this->input('detalles', []);
            if (! is_array($details)) {
                return;
            }

            foreach ($details as $index => $detail) {
                if (! is_array($detail)
                    || ! is_numeric($detail['producto_id'] ?? null)
                    || ! is_numeric($detail['lote_id'] ?? null)) {
                    continue;
                }

                $lotMatchesProduct = DB::table('lotes')
                    ->where('lote_id', $detail['lote_id'])
                    ->where('producto_id', $detail['producto_id'])
                    ->exists();
                if (! $lotMatchesProduct) {
                    $validator->errors()->add(
                        "detalles.{$index}.lote_id",
                        'El lote seleccionado no corresponde al medicamento.'
                    );
                }
            }
        });
    }
}
