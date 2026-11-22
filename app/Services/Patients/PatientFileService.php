<?php

namespace App\Services\Patients;

use Illuminate\Support\Facades\DB;
use RuntimeException;

class PatientFileService
{
    public function ensureForPatient(string $patientRut): int
    {
        $existingId = DB::table('fichas_pacientes')
            ->where('paciente_rut', $patientRut)
            ->value('ficha_id');
        if ($existingId !== null) {
            return (int) $existingId;
        }

        if (! DB::table('pacientes')->where('rut', $patientRut)->exists()) {
            throw new RuntimeException('No se puede crear una ficha para un paciente inexistente.');
        }

        $now = now();
        DB::table('fichas_pacientes')->insertOrIgnore([
            'paciente_rut' => $patientRut,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $fichaId = DB::table('fichas_pacientes')
            ->where('paciente_rut', $patientRut)
            ->value('ficha_id');
        if ($fichaId === null) {
            throw new RuntimeException('No se pudo crear la ficha del paciente.');
        }

        return (int) $fichaId;
    }
}
