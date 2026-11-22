<?php

namespace App\Services\Programs;

use App\Support\People\PersonName;
use App\Support\People\Rut;
use Illuminate\Support\Facades\DB;

class HealthProgramQueryService
{
    public function programs(array $filters): mixed
    {
        return DB::table('programas_salud')
            ->leftJoin('paciente_programa', function ($join) {
                $join->on('paciente_programa.programa_id', '=', 'programas_salud.programa_id')
                    ->where('paciente_programa.activo', true)
                    ->whereDate('paciente_programa.fecha_inicio', '<=', now()->toDateString())
                    ->where(function ($query) {
                        $query->whereNull('paciente_programa.fecha_termino')
                            ->orWhereDate('paciente_programa.fecha_termino', '>=', now()->toDateString());
                    });
            })
            ->leftJoin('pacientes', function ($join) {
                $join->on('pacientes.rut', '=', 'paciente_programa.paciente_rut')
                    ->where('pacientes.activo', true)
                    ->where('programas_salud.activo', true);
            })
            ->groupBy('programas_salud.programa_id', 'programas_salud.nombre', 'programas_salud.descripcion', 'programas_salud.activo')
            ->when(($filters['estado_programa'] ?? null) === 'activo', fn ($query) => $query->where('programas_salud.activo', true))
            ->when(($filters['estado_programa'] ?? null) === 'inactivo', fn ($query) => $query->where('programas_salud.activo', false))
            ->when($filters['buscar_programa'] ?? null, function ($query, string $search) {
                $term = '%'.$search.'%';
                $query->where(fn ($query) => $query->where('programas_salud.nombre', 'like', $term)
                    ->orWhere('programas_salud.descripcion', 'like', $term));
            })
            ->orderBy('programas_salud.nombre')
            ->paginate(15, [
                'programas_salud.programa_id',
                'programas_salud.nombre',
                'programas_salud.descripcion',
                'programas_salud.activo',
                DB::raw('COUNT(pacientes.rut) as pacientes_activos'),
            ], 'programas_page')
            ->withQueryString();
    }

    public function enrollments(array $filters, string $today): mixed
    {
        return DB::table('paciente_programa')
            ->join('pacientes', 'pacientes.rut', '=', 'paciente_programa.paciente_rut')
            ->join('programas_salud', 'programas_salud.programa_id', '=', 'paciente_programa.programa_id')
            ->when($filters['programa_id_afiliacion'] ?? null, fn ($query, $programId) => $query->where('programas_salud.programa_id', $programId))
            ->when($filters['desde_afiliacion'] ?? null, fn ($query, $date) => $query->whereDate('paciente_programa.fecha_inicio', '>=', $date))
            ->when($filters['hasta_afiliacion'] ?? null, fn ($query, $date) => $query->whereDate('paciente_programa.fecha_inicio', '<=', $date))
            ->when($filters['buscar_afiliacion'] ?? null, function ($query, string $search) {
                $term = '%'.$search.'%';
                $rutTerm = '%'.Rut::limpiarRut($search).'%';
                $query->where(function ($query) use ($search, $term, $rutTerm) {
                    $query->where('pacientes.rut', 'like', $rutTerm)
                        ->orWhere('programas_salud.nombre', 'like', $term)
                        ->orWhere('paciente_programa.cobertura', 'like', $term)
                        ->orWhere(function ($nameQuery) use ($search) {
                            PersonName::whereMatches($nameQuery, 'pacientes', $search);
                        });
                });
            })
            ->when(($filters['estado_afiliacion'] ?? null) === 'VIGENTE', function ($query) use ($today) {
                $query->where('paciente_programa.activo', true)
                    ->where('pacientes.activo', true)
                    ->where('programas_salud.activo', true)
                    ->whereDate('paciente_programa.fecha_inicio', '<=', $today)
                    ->where(fn ($query) => $query->whereNull('paciente_programa.fecha_termino')
                        ->orWhereDate('paciente_programa.fecha_termino', '>=', $today));
            })
            ->when(($filters['estado_afiliacion'] ?? null) === 'PROGRAMADA', function ($query) use ($today) {
                $query->where('paciente_programa.activo', true)
                    ->where('pacientes.activo', true)
                    ->where('programas_salud.activo', true)
                    ->whereDate('paciente_programa.fecha_inicio', '>', $today);
            })
            ->when(($filters['estado_afiliacion'] ?? null) === 'FINALIZADA', function ($query) use ($today) {
                $query->where('paciente_programa.activo', true)
                    ->where('pacientes.activo', true)
                    ->where('programas_salud.activo', true)
                    ->whereNotNull('paciente_programa.fecha_termino')
                    ->whereDate('paciente_programa.fecha_termino', '<', $today);
            })
            ->when(($filters['estado_afiliacion'] ?? null) === 'INACTIVA', fn ($query) => $query
                ->where(fn ($query) => $query->where('paciente_programa.activo', false)
                    ->orWhere('pacientes.activo', false)
                    ->orWhere('programas_salud.activo', false)))
            ->orderByDesc('paciente_programa.fecha_inicio')
            ->paginate(15, [
                'paciente_programa.paciente_programa_id',
                'pacientes.rut',
                'pacientes.activo as paciente_activo',
                'programas_salud.activo as programa_activo',
                'paciente_programa.activo as afiliacion_activa',
                PersonName::expression('pacientes', 'paciente'),
                'programas_salud.nombre as programa',
                'paciente_programa.fecha_inicio',
                'paciente_programa.fecha_termino',
                'paciente_programa.cobertura',
            ], 'afiliaciones_page')
            ->withQueryString()
            ->through(function ($enrollment) use ($today) {
                $enrollment->estado = ! $enrollment->afiliacion_activa || ! $enrollment->paciente_activo || ! $enrollment->programa_activo
                    ? 'INACTIVA'
                    : ($enrollment->fecha_inicio > $today
                        ? 'PROGRAMADA'
                        : ($enrollment->fecha_termino && $enrollment->fecha_termino < $today ? 'FINALIZADA' : 'VIGENTE'));

                return $enrollment;
            });
    }

    public function activeEnrollmentCount(string $today): int
    {
        return DB::table('paciente_programa')
            ->join('pacientes', 'pacientes.rut', '=', 'paciente_programa.paciente_rut')
            ->join('programas_salud', 'programas_salud.programa_id', '=', 'paciente_programa.programa_id')
            ->where('paciente_programa.activo', true)
            ->where('pacientes.activo', true)
            ->where('programas_salud.activo', true)
            ->whereDate('paciente_programa.fecha_inicio', '<=', $today)
            ->where(fn ($query) => $query->whereNull('paciente_programa.fecha_termino')
                ->orWhereDate('paciente_programa.fecha_termino', '>=', $today))
            ->count();
    }
}
