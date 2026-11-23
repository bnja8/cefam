<?php

namespace App\Http\Requests\Inventory;

use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;

class UpdateCategoryRequest extends InventoryRequest
{
    protected const AUTHORIZED_PERMISSION = 'inventory.catalog.manage';

    protected const AUDIT_ENTITY = 'categorias_producto';

    // Define los datos permitidos para actualizar una categoría.
    public function rules(): array
    {
        return [
            'nombre_categoria' => [
                'required',
                'string',
                'max:100',
                Rule::unique('categorias_producto', 'nombre')->ignore($this->route('categoryId'), 'categoria_id'),
            ],
            'descripcion_categoria' => ['nullable', 'string', 'max:255'],
        ];
    }

    // Confirma que la categoría exista después de autorizar al usuario.
    public function authorize(\App\Services\Audit\AuditLogger $audit): bool
    {
        if (! parent::authorize($audit)) {
            return false;
        }

        abort_unless(
            DB::table('categorias_producto')->where('categoria_id', $this->route('categoryId'))->exists(),
            404
        );

        return true;
    }

    // Obtiene el identificador de categoría para la auditoría de acceso.
    protected function auditRecordId(): int|string|null
    {
        return $this->route('categoryId');
    }
}
