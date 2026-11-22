<?php


namespace App\Http\Controllers;

use App\Models\Usuario;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\Auth;

abstract class Controller
{
    protected function authorizeAdministrator(AuditLogger $audit, string $entity, int|string|null $recordId = null): void
    {
        $user = Auth::user();
        if (! $user instanceof Usuario || ! $user->hasAnyRole(['Administrador'])) {
            $audit->record(
                'ACCESO_DENEGADO',
                $entity,
                is_int($recordId) ? $recordId : null,
                'RECHAZADO',
                is_string($recordId) ? ['rut' => $recordId] : []
            );
            abort(403);
        }
    }
}
