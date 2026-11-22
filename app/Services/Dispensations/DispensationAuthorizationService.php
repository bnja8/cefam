<?php

namespace App\Services\Dispensations;

use App\Models\Usuario;
use App\Services\Audit\AuditLogger;
use App\Support\People\Rut;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class DispensationAuthorizationService
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function authorizeControlledDispensation(array $data): string
    {
        $rut = Rut::limpiarRut((string) ($data['autorizador_rut'] ?? ''));
        $password = $data['autorizador_password'] ?? null;
        $pharmacist = Rut::validarRut($rut)
            ? Usuario::query()->where('rut', $rut)->where('activo', true)->first()
            : null;

        if (
            ! $pharmacist
            || ! $pharmacist->hasAnyRole(['Químico Farmacéutico'])
            || ! is_string($password)
            || ! Hash::check($password, $pharmacist->getAuthPassword())
        ) {
            $this->audit->record('FIRMA_CONTROLADO_RECHAZADA', 'entregas', null, 'RECHAZADO');
            throw ValidationException::withMessages([
                'autorizacion_qf' => 'No se pudo validar la autorización. Verifica el RUT y la contraseña de un Químico Farmacéutico activo.',
            ]);
        }

        return $pharmacist->rut;
    }
}
