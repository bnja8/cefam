<?php

namespace App\Http\Requests\Inventory;

use App\Models\Usuario;
use App\Services\Audit\AuditLogger;
use Illuminate\Foundation\Http\FormRequest;

abstract class LocationRequest extends FormRequest
{
    // Autoriza la gestión de ubicaciones y audita denegaciones.
    public function authorize(AuditLogger $audit): bool
    {
        $user = $this->user();
        if ($user instanceof Usuario && $user->hasAnyRole(['Administrador', 'Químico Farmacéutico'])) {
            return true;
        }

        $locationId = $this->route('locationId');
        $audit->record(
            'ACCESO_DENEGADO',
            'ubicaciones',
            is_numeric($locationId) ? (int) $locationId : null,
            'RECHAZADO'
        );

        return false;
    }
}
