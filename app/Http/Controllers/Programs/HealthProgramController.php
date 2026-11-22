<?php

namespace App\Http\Controllers\Programs;

use App\Http\Controllers\Controller;
use App\Models\Usuario;
use App\Rules\FourDigitDate;
use App\Services\Audit\AuditLogger;
use App\Services\Programs\HealthProgramEnrollmentService;
use App\Services\Programs\HealthProgramQueryService;
use App\Support\People\PersonName;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class HealthProgramController extends Controller
{
    public function index(Request $request, HealthProgramQueryService $queries): View|JsonResponse
    {
        $user = Auth::user();
        abort_unless($user instanceof Usuario, 401);
        /** @var Usuario $user */
        $screen = $request->route('screen', 'programs');
        if (in_array($screen, ['create', 'edit'], true)) {
            abort_unless($user->hasAnyRole(['Administrador', 'Químico Farmacéutico']), 403);

            $program = $screen === 'edit'
                ? DB::table('programas_salud')->where('programa_id', $request->route('programId'))->first() ?? abort(404)
                : null;
            if ($request->expectsJson()) {
                return response()->json(['data' => $program]);
            }

            return view('programs.index', [
                'screen' => $screen,
                'canCreateProgram' => true,
                'program' => $program,
            ]);
        }

        if ($screen === 'enroll') {
            $programs = DB::table('programas_salud')->where('activo', true)->orderBy('nombre')->get(['programa_id', 'nombre', 'activo']);
            $products = DB::table('productos')->where('activo', true)->orderBy('nombre')->get(['producto_id', 'nombre']);
            if ($request->expectsJson()) {
                return response()->json([
                    'data' => [],
                    'options' => [
                        'programs' => $programs,
                        'products' => $products,
                        'patients' => DB::table('pacientes')->where('activo', true)
                            ->orderBy('apellidoPaterno')->orderBy('apellidoMaterno')->orderBy('nombre')
                            ->get(['rut', PersonName::expression('pacientes', 'nombre_completo')]),
                    ],
                ]);
            }

            $quotaRows = old('detalles', [[]]);
            if (! is_array($quotaRows)) {
                $quotaRows = [[]];
            }
            $quotaRows = array_map(
                // Transforma cada elemento de la colección.
                fn ($quota) => is_array($quota) ? $quota : [],
                array_slice(array_values($quotaRows), 0, 30)
            );

            return view('programs.index', [
                'screen' => 'enroll',
                'programs' => $programs,
                'products' => $products,
                'quotaRows' => $quotaRows,
                'activePatientCount' => DB::table('pacientes')->where('activo', true)->count(),
                'selectedPatient' => DB::table('pacientes')
                    ->where('rut', old('paciente_rut'))
                    ->where('activo', true)
                    ->first(['rut', PersonName::expression('pacientes', 'nombre_completo')]),
            ]);
        }

        $programFilters = $screen === 'programs' ? $request->validate([
            'buscar_programa' => ['nullable', 'string', 'max:100'],
            'estado_programa' => ['nullable', Rule::in(['activo', 'inactivo'])],
        ]) : [];
        $enrollmentFilters = $screen === 'affiliations' ? $request->validate([
            'buscar_afiliacion' => ['nullable', 'string', 'max:100'],
            'programa_id_afiliacion' => ['nullable', 'integer', 'exists:programas_salud,programa_id'],
            'estado_afiliacion' => ['nullable', Rule::in(['VIGENTE', 'PROGRAMADA', 'FINALIZADA', 'INACTIVA'])],
            'desde_afiliacion' => ['nullable', 'bail', new FourDigitDate()],
            'hasta_afiliacion' => ['nullable', 'bail', new FourDigitDate(), 'after_or_equal:desde_afiliacion'],
        ]) : [];
        $allPrograms = $screen === 'affiliations'
            ? DB::table('programas_salud')->orderBy('nombre')->get(['programa_id', 'nombre'])
            : collect();
        $today = now()->toDateString();
        $programs = collect();
        if ($screen === 'programs') {
            $programs = $queries->programs($programFilters);
        }

        $enrollments = collect();
        if ($screen === 'affiliations') {
            $enrollments = $queries->enrollments($enrollmentFilters, $today);
        }

        if ($request->expectsJson()) {
            $rows = $screen === 'programs' ? $programs : $enrollments;

            return response()->json([
                'data' => $rows->items(),
                'meta' => [
                    'current_page' => $rows->currentPage(),
                    'last_page' => $rows->lastPage(),
                    'per_page' => $rows->perPage(),
                    'total' => $rows->total(),
                ],
                'options' => ['allPrograms' => $screen === 'affiliations' ? $allPrograms : []],
            ]);
        }

        return view('programs.index', [
            'screen' => $screen,
            'programs' => $programs,
            'enrollments' => $enrollments,
            'programFilters' => $programFilters,
            'enrollmentFilters' => $enrollmentFilters,
            'canCreateProgram' => $user->hasAnyRole(['Administrador', 'Químico Farmacéutico']),
            'activeProgramCount' => $screen === 'programs' ? DB::table('programas_salud')->where('activo', true)->count() : 0,
            'activeEnrollmentCount' => $screen === 'programs' ? $queries->activeEnrollmentCount($today) : 0,
            'allPrograms' => $allPrograms,
            'activePatientCount' => 0,
            'selectedPatient' => null,
            'products' => $screen === 'programs' ? DB::table('productos')->where('activo', true)->orderBy('nombre')->get(['producto_id', 'nombre']) : collect(),
        ]);
    }

    // Valida y guarda un nuevo registro.
    public function store(Request $request, AuditLogger $audit): RedirectResponse|JsonResponse
    {
        $user = Auth::user();
        if (! $user instanceof Usuario || ! $user->hasAnyRole(['Administrador', 'Químico Farmacéutico'])) {
            $audit->record('ACCESO_DENEGADO', 'programas_salud', null, 'RECHAZADO');
            abort(403);
        }

        $data = $request->validate([
            'nombre' => ['required', 'string', 'max:100', Rule::unique('programas_salud', 'nombre')],
            'descripcion' => ['nullable', 'string', 'max:255'],
        ]);

        $programId = DB::table('programas_salud')->insertGetId($data + ['activo' => true]);
        $audit->record('CREACION', 'programas_salud', $programId, 'EXITOSO', ['nombre' => $data['nombre']]);

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Programa de salud creado.',
                'data' => ['programa_id' => $programId] + $data + ['activo' => true, 'pacientes_activos' => 0],
            ], 201);
        }

        return redirect()->route('programs.index')->with('status', 'Programa de salud creado.');
    }

    public function show(int $programId, Request $request, AuditLogger $audit): JsonResponse
    {
        $user = Auth::user();
        if (! $user instanceof Usuario || ! $user->hasAnyRole(['Administrador', 'Químico Farmacéutico'])) {
            $audit->record('ACCESO_DENEGADO', 'programas_salud', $programId, 'RECHAZADO');
            abort(403);
        }

        $program = DB::table('programas_salud')
            ->where('programa_id', $programId)
            ->first();
        abort_unless($program, 404);

        return response()->json(['data' => $program]);
    }

    public function update(Request $request, int $programId, AuditLogger $audit): RedirectResponse|JsonResponse
    {
        $user = Auth::user();
        if (! $user instanceof Usuario || ! $user->hasAnyRole(['Administrador', 'Químico Farmacéutico'])) {
            $audit->record('ACCESO_DENEGADO', 'programas_salud', $programId, 'RECHAZADO');
            abort(403);
        }

        $program = DB::table('programas_salud')->where('programa_id', $programId)->first();
        abort_unless($program, 404);
        $data = $request->validate([
            'nombre' => ['required', 'string', 'max:100', Rule::unique('programas_salud', 'nombre')->ignore($programId, 'programa_id')],
            'descripcion' => ['nullable', 'string', 'max:255'],
        ]);
        DB::table('programas_salud')->where('programa_id', $programId)->update($data);
        $audit->record('ACTUALIZACION', 'programas_salud', $programId, 'EXITOSO', ['nombre' => $data['nombre']]);

        if ($request->expectsJson()) {
            return response()->json(['message' => 'Programa de salud actualizado.', 'data' => $data + ['programa_id' => $programId]]);
        }

        return redirect()->route('programs.index')->with('status', 'Programa de salud actualizado.');
    }

    public function toggle(int $programId, AuditLogger $audit, Request $request): RedirectResponse|JsonResponse
    {
        $user = Auth::user();
        if (! $user instanceof Usuario || ! $user->hasAnyRole(['Administrador', 'Químico Farmacéutico'])) {
            $audit->record('ACCESO_DENEGADO', 'programas_salud', $programId, 'RECHAZADO');
            abort(403);
        }

        $program = DB::table('programas_salud')->where('programa_id', $programId)->first();
        abort_unless($program, 404);
        $active = ! $program->activo;
        DB::table('programas_salud')->where('programa_id', $programId)->update(['activo' => $active]);
        $audit->record('CAMBIO_ESTADO', 'programas_salud', $programId, 'EXITOSO', ['activo' => $active]);

        if ($request->expectsJson()) {
            return response()->json(['message' => 'Estado del programa actualizado.', 'data' => ['activo' => $active]]);
        }

        return redirect()->route('programs.index')->with('status', 'Estado del programa actualizado.');
    }

    public function enroll(
        Request $request,
        AuditLogger $audit,
        HealthProgramEnrollmentService $enrollment
    ): RedirectResponse|JsonResponse
    {
        $quotaRows = $request->input('detalles');
        if (is_array($quotaRows)) {
            $quotaRows = array_values(array_filter($quotaRows, fn ($quota) => ! is_array($quota)
                || collect($quota)->contains(fn ($value) => filled($value))));
            $request->merge(['detalles' => $quotaRows]);
        }

        $data = $request->validate([
            'paciente_rut' => ['required', 'string', 'max:12', 'exists:pacientes,rut,activo,1'],
            'programa_id' => ['required', 'integer', 'exists:programas_salud,programa_id,activo,1'],
            'fecha_inicio' => [
                'required',
                'bail',
                new FourDigitDate(),
                Rule::unique('paciente_programa', 'fecha_inicio')
                    ->where('paciente_rut', $request->input('paciente_rut'))
                    ->where('programa_id', $request->input('programa_id')),
            ],
            'fecha_termino' => ['nullable', 'bail', new FourDigitDate(), 'after_or_equal:fecha_inicio'],
            'cobertura' => ['nullable', 'string', 'max:255'],
            'detalles' => ['nullable', 'array', 'max:30'],
            'detalles.*' => ['array'],
            'detalles.*.producto_id' => ['required', 'integer', 'distinct', 'exists:productos,producto_id,activo,1'],
            'detalles.*.periodo_inicio' => ['required', 'bail', new FourDigitDate()],
            'detalles.*.periodo_termino' => ['required', 'bail', new FourDigitDate()],
            'detalles.*.cantidad_autorizada' => ['required', 'numeric', 'decimal:0,2', 'gte:0'],
        ]);

        foreach ($data['detalles'] ?? [] as $index => $quota) {
            if ($quota['periodo_termino'] < $quota['periodo_inicio']) {
                throw ValidationException::withMessages([
                    "detalles.$index.periodo_termino" => 'El término de la cuota debe ser igual o posterior a su inicio.',
                ]);
            }

            if ($quota['periodo_termino'] < $data['fecha_inicio'] ||
                (! empty($data['fecha_termino']) && $quota['periodo_inicio'] > $data['fecha_termino'])) {
                $field = $quota['periodo_termino'] < $data['fecha_inicio']
                    ? "detalles.$index.periodo_termino"
                    : "detalles.$index.periodo_inicio";
                throw ValidationException::withMessages([
                    $field => 'El período de la cuota debe coincidir con la vigencia de la afiliación.',
                ]);
            }
        }

        $enrollmentId = $enrollment->enroll($data, $audit);

        $quotaCount = count($data['detalles'] ?? []);
        $message = 'Paciente afiliado al programa.';
        if ($quotaCount > 0) {
            $message .= ' Cuotas de productos registradas: '.$quotaCount.'.';
        }

        if ($request->expectsJson()) {
            return response()->json([
                'message' => $message,
                'data' => ['paciente_programa_id' => $enrollmentId],
            ], 201);
        }

        return redirect()->route('programs.affiliations.index')->with('status', $message);
    }
}
