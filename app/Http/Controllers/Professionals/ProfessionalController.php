<?php


namespace App\Http\Controllers\Professionals;

use App\Http\Controllers\Controller;
use App\Models\Usuario;
use App\Services\Audit\AuditLogger;
use App\Support\People\PersonName;
use App\Support\People\Rut;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ProfessionalController extends Controller
{
    private const PROFESSIONAL_TYPES = [
        'Médico General',
        'Médico Cirujano',
        'Médico Especialista',
        'Enfermera',
        'Enfermero',
        'Técnico en Enfermería (TENS)',
        'Matrona',
        'Matrón',
        'Kinesiólogo',
        'Kinesióloga',
        'Nutricionista',
        'Psicólogo',
        'Psicóloga',
        'Odontólogo',
        'Odontóloga',
        'Químico Farmacéutico',
        'Tecnólogo Médico',
        'Fonoaudiólogo',
        'Fonoaudióloga',
        'Terapeuta Ocupacional',
        'Trabajador Social',
        'Asistente Social',
        'Técnico Farmacéutico',
    ];

    public function index(Request $request): View|JsonResponse
    {
        $user = Auth::user();
        abort_unless(
            $user instanceof Usuario
                && $user->hasAnyRole(['Administrador', 'Químico Farmacéutico', 'Técnico Farmacéutico']),
            403
        );
        /** @var Usuario $user */

        $screen = $request->boolean('mostrar_formulario') ? 'create' : $request->route('screen', 'list');
        if ($screen === 'create') {
            abort_unless($user->hasAnyRole(['Administrador', 'Químico Farmacéutico']), 403);

            if ($request->expectsJson()) {
                return response()->json(['data' => [], 'options' => ['professionalTypes' => self::PROFESSIONAL_TYPES]]);
            }

            return view('professionals.index', [
                'screen' => 'create',
                'canRegister' => true,
                'canManage' => true,
                'professionals' => null,
                'professionalTypes' => self::PROFESSIONAL_TYPES,
            ]);
        }

        $types = DB::table('profesionales_salud')
            ->select('tipo_profesional')
            ->distinct()
            ->orderBy('tipo_profesional')
            ->pluck('tipo_profesional');
        $filters = $request->validate([
            'buscar' => ['nullable', 'string', 'max:100'],
            'tipo_profesional' => ['nullable', 'string', Rule::in($types->all())],
            'estado' => ['nullable', Rule::in(['activo', 'inactivo'])],
        ]);

        $professionals = DB::table('profesionales_salud')
            ->select('profesionales_salud.*', PersonName::expression('profesionales_salud', 'nombre_completo'))
            ->when(($filters['estado'] ?? null) === 'activo',  fn ($query) => $query->where('profesionales_salud.activo', true))
            ->when(($filters['estado'] ?? null) === 'inactivo',  fn ($query) => $query->where('profesionales_salud.activo', false))
            ->when($filters['tipo_profesional'] ?? null,  fn ($query, string $type) => $query->where('tipo_profesional', $type))
            ->when($filters['buscar'] ?? null,  function ($query, string $search) {
                $term = '%'.$search.'%';
                $rutTerm = '%'.Rut::limpiarRut($search).'%';
                $query->where( function ($query) use ($search, $term, $rutTerm) {
                    $query->where('profesionales_salud.rut', 'like', $rutTerm)
                        ->orWhere('profesionales_salud.tipo_profesional', 'like', $term)
                        ->orWhere( fn ($nameQuery) => PersonName::whereMatches($nameQuery, 'profesionales_salud', $search));
                });
            })
            ->orderBy('apellidoPaterno')
            ->orderBy('apellidoMaterno')
            ->orderBy('nombre')
            ->paginate(12)
            ->withQueryString();

        if ($request->expectsJson()) {
            return response()->json([
                'data' => $professionals->items(),
                'meta' => [
                    'current_page' => $professionals->currentPage(),
                    'last_page' => $professionals->lastPage(),
                    'per_page' => $professionals->perPage(),
                    'total' => $professionals->total(),
                ],
                'options' => ['types' => $types, 'professionalTypes' => self::PROFESSIONAL_TYPES],
            ]);
        }

        return view('professionals.index', [
            'screen' => 'list',
            'professionals' => $professionals,
            'types' => $types,
            'canRegister' => $user->hasAnyRole(['Administrador', 'Químico Farmacéutico']),
            'canManage' => $user->hasAnyRole(['Administrador', 'Químico Farmacéutico']),
        ]);
    }

    // Valida y guarda un nuevo registro.
    public function store(Request $request, AuditLogger $audit): RedirectResponse|JsonResponse
    {
        $user = Auth::user();
        if (! $user instanceof Usuario || ! $user->hasAnyRole(['Administrador', 'Químico Farmacéutico'])) {
            $audit->record('ACCESO_DENEGADO', 'profesionales_salud', null, 'RECHAZADO');
            abort(403);
        }

        $request->merge(['rut' => Rut::limpiarRut((string) $request->input('rut', ''))]);
        $data = $request->validate([
            'rut' => [
                'required',
                'string',
                'max:9',
                'regex:/^\d{7,8}[0-9K]$/',
                fn ($attribute, $value, $fail) => Rut::validarRut($value) ?: $fail('El RUT ingresado no es válido.'),
                Rule::unique('profesionales_salud', 'rut'),
            ],
            'nombre' => ['required', 'string', 'max:50'],
            'apellidoPaterno' => ['required', 'string', 'max:50'],
            'apellidoMaterno' => ['required', 'string', 'max:50'],
            'tipo_profesional' => ['required', 'string', 'max:50'],
        ]);

        DB::table('profesionales_salud')->insert($data);
        $audit->record('CREACION', 'profesionales_salud', null, 'EXITOSO', ['rut' => $data['rut']]);

        if ($request->expectsJson()) {
            return response()->json(['message' => 'Personal de salud registrado correctamente.', 'data' => $data], 201);
        }

        return redirect()->route('professionals.index')->with('status', 'Personal de salud registrado correctamente.');
    }

    public function edit(string $professionalRut, AuditLogger $audit, Request $request): View|JsonResponse
    {
        $this->authorizeManagement($audit, $professionalRut);
        $professional = DB::table('profesionales_salud')->where('rut', $professionalRut)->first();
        abort_unless($professional, 404);

        if ($request->expectsJson()) {
            return response()->json([
                'data' => $professional,
                'options' => ['professionalTypes' => self::PROFESSIONAL_TYPES],
            ]);
        }

        return view('professionals.edit', compact('professional'));
    }

    // Valida y actualiza el registro existente.
    public function update(Request $request, string $professionalRut, AuditLogger $audit): RedirectResponse|JsonResponse
    {
        $this->authorizeManagement($audit, $professionalRut);
        $professional = DB::table('profesionales_salud')->where('rut', $professionalRut)->first();
        abort_unless($professional, 404);

        $data = $request->validate([
            'nombre' => ['required', 'string', 'max:50'],
            'apellidoPaterno' => ['required', 'string', 'max:50'],
            'apellidoMaterno' => ['required', 'string', 'max:50'],
            'tipo_profesional' => ['required', 'string', 'max:50'],
            'activo' => ['nullable', 'boolean'],
        ]);
        $data['activo'] = $request->boolean('activo');
        DB::table('profesionales_salud')->where('rut', $professionalRut)->update($data);
        $audit->record('ACTUALIZACION', 'profesionales_salud', null, 'EXITOSO', [
            'rut' => $professionalRut,
            'activo' => $data['activo'],
        ]);

        if ($request->expectsJson()) {
            return response()->json(['message' => 'Datos del personal actualizados.', 'data' => $data]);
        }

        return redirect()->route('professionals.index')->with('status', 'Datos del personal actualizados.');
    }

    public function toggle(string $professionalRut, AuditLogger $audit, Request $request): RedirectResponse|JsonResponse
    {
        $this->authorizeManagement($audit, $professionalRut);
        $professional = DB::table('profesionales_salud')->where('rut', $professionalRut)->first();
        abort_unless($professional, 404);

        $active = ! $professional->activo;
        DB::table('profesionales_salud')->where('rut', $professionalRut)->update(['activo' => $active]);
        $audit->record('CAMBIO_ESTADO', 'profesionales_salud', null, 'EXITOSO', [
            'rut' => $professionalRut,
            'activo' => $active,
        ]);

        if ($request->expectsJson()) {
            return response()->json(['message' => 'Estado del personal actualizado.', 'data' => ['activo' => $active]]);
        }

        return redirect()->route('professionals.index')->with('status', 'Estado del personal actualizado.');
    }

    private function authorizeManagement(AuditLogger $audit, string $professionalRut): void
    {
        $user = Auth::user();
        if (! $user instanceof Usuario || ! $user->hasAnyRole(['Administrador', 'Químico Farmacéutico'])) {
            $audit->record('ACCESO_DENEGADO', 'profesionales_salud', null, 'RECHAZADO', ['rut' => $professionalRut]);
            abort(403);
        }
    }
}
