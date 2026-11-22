<?php

namespace App\Services\Programs;

use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class HealthProgramEnrollmentService
{
    public function enroll(array $data, AuditLogger $audit): int
    {
        return DB::transaction(function () use ($data, $audit) {
            $patientIsActive = DB::table('pacientes')->where('rut', $data['paciente_rut'])
                ->where('activo', true)->lockForUpdate()->first(['rut']);
            $programIsActive = DB::table('programas_salud')->where('programa_id', $data['programa_id'])
                ->where('activo', true)->lockForUpdate()->first(['programa_id']);
            if (! $patientIsActive || ! $programIsActive) {
                throw ValidationException::withMessages([
                    'paciente_rut' => 'El paciente o programa ya no está activo.',
                ]);
            }
            $overlappingEnrollment = DB::table('paciente_programa')
                ->where('paciente_rut', $data['paciente_rut'])
                ->where('programa_id', $data['programa_id'])
                ->where('activo', true)
                ->where(function ($query) use ($data) {
                    $query->whereNull('fecha_termino')
                        ->orWhereDate('fecha_termino', '>=', $data['fecha_inicio']);
                })
                ->when(! empty($data['fecha_termino']), fn ($query) => $query
                    ->whereDate('fecha_inicio', '<=', $data['fecha_termino']))
                ->lockForUpdate()
                ->first(['paciente_programa_id']);
            if ($overlappingEnrollment) {
                throw ValidationException::withMessages([
                    'programa_id' => 'El paciente ya tiene una afiliación vigente o programada para ese período.',
                ]);
            }

            $quotas = $data['detalles'] ?? [];
            if ($quotas !== []) {
                $activeProductIds = DB::table('productos')
                    ->whereIn('producto_id', collect($quotas)->pluck('producto_id'))
                    ->where('activo', true)
                    ->lockForUpdate()
                    ->pluck('producto_id')
                    ->map(fn ($productId) => (string) $productId)
                    ->all();
                foreach ($quotas as $index => $quota) {
                    if (! in_array((string) $quota['producto_id'], $activeProductIds, true)) {
                        throw ValidationException::withMessages([
                            "detalles.$index.producto_id" => 'No se puede asignar una cuota para un producto inactivo.',
                        ]);
                    }
                }
            }

            $enrollmentId = DB::table('paciente_programa')->insertGetId([
                'paciente_rut' => $data['paciente_rut'],
                'programa_id' => $data['programa_id'],
                'fecha_inicio' => $data['fecha_inicio'],
                'fecha_termino' => $data['fecha_termino'] ?? null,
                'cobertura' => $data['cobertura'] ?? null,
                'activo' => true,
            ]);

            foreach ($quotas as $quota) {
                DB::table('cuotas_paciente')->insert([
                    'paciente_programa_id' => $enrollmentId,
                    'producto_id' => $quota['producto_id'],
                    'periodo_inicio' => $quota['periodo_inicio'],
                    'periodo_termino' => $quota['periodo_termino'],
                    'cantidad_autorizada' => $quota['cantidad_autorizada'],
                    'cantidad_utilizada' => 0,
                ]);
            }

            $audit->record('AFILIACION', 'paciente_programa', $enrollmentId, 'EXITOSO', [
                'paciente_rut' => $data['paciente_rut'],
                'programa_id' => $data['programa_id'],
                'cuotas_producto_ids' => collect($quotas)->pluck('producto_id')->all(),
            ]);

            return $enrollmentId;
        });
    }
}
