<?php

namespace App\Http\Requests\Inventory;

use App\Models\Usuario;
use App\Services\Audit\AuditLogger;
use Illuminate\Foundation\Http\FormRequest;

abstract class InventoryRequest extends FormRequest
{
    protected const AUTHORIZED_ROLES = [];

    protected const AUDIT_ENTITY = 'inventario';

    // Autoriza la operación y registra los intentos denegados.
    public function authorize(AuditLogger $audit): bool
    {
        $user = $this->user();
        if ($user instanceof Usuario && $user->hasAnyRole(static::AUTHORIZED_ROLES)) {
            return true;
        }

        $recordId = $this->auditRecordId();
        $audit->record(
            'ACCESO_DENEGADO',
            static::AUDIT_ENTITY,
            is_numeric($recordId) ? (int) $recordId : null,
            'RECHAZADO'
        );

        return false;
    }

    // Identifica el registro asociado al intento rechazado.
    protected function auditRecordId(): int|string|null
    {
        return $this->route('productId');
    }
}
