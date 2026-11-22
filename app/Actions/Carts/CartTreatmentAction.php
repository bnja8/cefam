<?php

namespace App\Actions\Carts;

use App\Rules\FourDigitDate;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CartTreatmentAction
{
    public function storeTreatment(Request $request, AuditLogger $audit): RedirectResponse|JsonResponse
    {
        $data = $request->validate([
            'paciente_rut' => ['required', 'string', 'max:12', 'exists:pacientes,rut'],
            'descripcion' => ['required', 'string', 'max:2000'],
            'fecha_inicio' => ['required', 'bail', new FourDigitDate()],
            'fecha_termino' => ['nullable', 'bail', new FourDigitDate(), 'after_or_equal:fecha_inicio'],
        ]);

        $treatmentId = DB::transaction(function () use ($data, $audit) {
            $patientIsActive = DB::table('pacientes')->where('rut', $data['paciente_rut'])
                ->where('activo', true)->lockForUpdate()->first(['rut']);
            if (! $patientIsActive) {
                throw ValidationException::withMessages(['paciente_rut' => 'No se puede crear un tratamiento para un paciente inactivo.']);
            }
            $treatmentId = DB::table('tratamientos')->insertGetId($data + ['activo' => true]);
            $audit->record('CREACION', 'tratamientos', $treatmentId, 'EXITOSO', ['paciente_rut' => $data['paciente_rut']]);

            return $treatmentId;
        });

        return ($request->routeIs('api.v1.*') || $request->expectsJson())
            ? response()->json(['data' => ['tratamiento_id' => $treatmentId, 'message' => 'Tratamiento registrado para preparación de unidosis.']], 201)
            : redirect()->route('carts.unidosis.create')->with('status', 'Tratamiento registrado para preparación de unidosis.');
    }
}
