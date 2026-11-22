<?php


namespace App\Http\Controllers\AuthorizedPeople;

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

class AuthorizedPersonController extends Controller
{
    public function index(Request $request): View|JsonResponse
    {
        $user = Auth::user();
        abort_unless($user instanceof Usuario, 401);
        /** @var Usuario $user */

        $screen = $request->route('screen', 'list');
        if ($screen === 'create') {
            abort_unless($user->hasAnyRole(['Administrador', 'Técnico Farmacéutico']), 403);

            $patients = DB::table('pacientes')->where('activo', true)
                ->orderBy('apellidoPaterno')->orderBy('apellidoMaterno')->orderBy('nombre')
                ->get(['rut', PersonName::expression('pacientes', 'nombre_completo')]);
            if ($request->expectsJson()) {
                return response()->json([
                    'data' => [],
                    'options' => [
                        'patients' => $patients,
                        'activePatientCount' => $patients->count(),
                    ],
                ]);
            }

            return view('authorized-people.index', [
                'screen' => 'create',
                'canRegister' => true,
                'activePatientCount' => $patients->count(),
                'selectedPatient' => DB::table('pacientes')
                    ->where('rut', old('paciente_rut', $request->query('paciente_rut')))
                    ->where('activo', true)
                    ->first(['rut', PersonName::expression('pacientes', 'nombre_completo')]),
            ]);
        }

        $filters = $request->validate([
            'buscar' => ['nullable', 'string', 'max:100'],
            'paciente_rut' => ['nullable', 'string', 'max:12', 'exists:pacientes,rut'],
            'estado' => ['nullable', Rule::in(['activo', 'inactivo'])],
        ]);

        $authorizedPeople = DB::table('personas_autorizadas')
            ->join('pacientes', 'pacientes.rut', '=', 'personas_autorizadas.paciente_rut')
            ->when($filters['paciente_rut'] ?? null,  fn ($query, $patientRut) => $query->where('pacientes.rut', $patientRut))
            ->when(($filters['estado'] ?? null) === 'activo',  fn ($query) => $query->where('personas_autorizadas.activo', true))
            ->when(($filters['estado'] ?? null) === 'inactivo',  fn ($query) => $query->where('personas_autorizadas.activo', false))
            ->when($filters['buscar'] ?? null,  function ($query, string $search) {
                $term = '%'.$search.'%';
                $rutTerm = '%'.Rut::limpiarRut($search).'%';
                $query->where( function ($query) use ($search, $term, $rutTerm) {
                    $query->where('personas_autorizadas.rut', 'like', $rutTerm)
                        ->orWhere('pacientes.rut', 'like', $rutTerm)
                        ->orWhere('personas_autorizadas.vinculo', 'like', $term)
                        ->orWhere( function ($nameQuery) use ($search) {
                            PersonName::whereMatches($nameQuery, 'personas_autorizadas', $search);
                        })
                        ->orWhere( function ($nameQuery) use ($search) {
                            PersonName::whereMatches($nameQuery, 'pacientes', $search);
                        });
                });
            })
            ->orderBy('personas_autorizadas.apellidoPaterno')
            ->orderBy('personas_autorizadas.apellidoMaterno')
            ->orderBy('personas_autorizadas.nombre')
            ->paginate(15, [
                'personas_autorizadas.persona_autorizada_id',
                'personas_autorizadas.rut',
                'personas_autorizadas.vinculo',
                'personas_autorizadas.activo',
                'pacientes.rut as paciente_rut',
                PersonName::expression('personas_autorizadas', 'nombre_completo'),
                PersonName::expression('pacientes', 'paciente'),
            ])
            ->withQueryString();

        $selectedPatientFilter = isset($filters['paciente_rut'])
            ? DB::table('pacientes')->where('rut', $filters['paciente_rut'])
                ->first(['rut', PersonName::expression('pacientes', 'nombre_completo')])
            : null;

        if ($request->expectsJson()) {
            return response()->json([
                'data' => $authorizedPeople->items(),
                'meta' => [
                    'current_page' => $authorizedPeople->currentPage(),
                    'last_page' => $authorizedPeople->lastPage(),
                    'per_page' => $authorizedPeople->perPage(),
                    'total' => $authorizedPeople->total(),
                ],
                'options' => [
                    'patients' => DB::table('pacientes')->where('activo', true)->orderBy('apellidoPaterno')->orderBy('apellidoMaterno')
                        ->get(['rut', PersonName::expression('pacientes', 'nombre_completo')]),
                    'selectedPatient' => $selectedPatientFilter,
                ],
            ]);
        }

        return view('authorized-people.index', [
            'screen' => 'list',
            'authorizedPeople' => $authorizedPeople,
            'filters' => $filters,
            'canRegister' => $user->hasAnyRole(['Administrador', 'Técnico Farmacéutico']),
            'activePatientCount' => DB::table('pacientes')->where('activo', true)->count(),
            'selectedPatient' => DB::table('pacientes')
                ->where('rut', old('paciente_rut'))
                ->where('activo', true)
                ->first(['rut', PersonName::expression('pacientes', 'nombre_completo')]),
            'selectedPatientFilter' => $selectedPatientFilter,
        ]);
    }

    // Valida y guarda un nuevo registro.
    public function store(Request $request, AuditLogger $audit): RedirectResponse|JsonResponse
    {
        $user = Auth::user();
        if (! $user instanceof Usuario || ! $user->hasAnyRole(['Administrador', 'Técnico Farmacéutico'])) {
            $audit->record('ACCESO_DENEGADO', 'personas_autorizadas', null, 'RECHAZADO');
            abort(403);
        }

        $request->merge(['rut' => Rut::limpiarRut((string) $request->input('rut', ''))]);
        $data = $request->validate([
            'paciente_rut' => ['required', 'string', 'max:12', 'exists:pacientes,rut,activo,1'],
            'rut' => [
                'required',
                'string',
                'max:9',
                'regex:/^\d{7,8}[0-9K]$/',
                fn ($attribute, $value, $fail) => Rut::validarRut($value) ?: $fail('El RUT ingresado no es válido.'),
                Rule::unique('personas_autorizadas', 'rut')->where('paciente_rut', $request->input('paciente_rut')),
            ],
            'nombre' => ['required', 'string', 'max:50'],
            'apellidoPaterno' => ['required', 'string', 'max:50'],
            'apellidoMaterno' => ['required', 'string', 'max:50'],
            'vinculo' => ['required', 'string', 'max:100'],
        ]);

        $authorizedPersonId = DB::table('personas_autorizadas')->insertGetId($data + ['activo' => true]);
        $audit->record('CREACION', 'personas_autorizadas', $authorizedPersonId, 'EXITOSO', [
            'rut' => $data['rut'],
            'paciente_rut' => $data['paciente_rut'],
        ]);

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Persona autorizada registrada.',
                'data' => ['persona_autorizada_id' => $authorizedPersonId] + $data,
            ], 201);
        }

        return redirect()->route('authorized-people.index')->with('status', 'Persona autorizada registrada.');
    }

    public function edit(int $authorizedPersonId, AuditLogger $audit, Request $request): View|JsonResponse
    {
        $this->authorizeAdministrator($audit, 'personas_autorizadas', $authorizedPersonId);
        $authorizedPerson = DB::table('personas_autorizadas')->where('persona_autorizada_id', $authorizedPersonId)->first();
        abort_unless($authorizedPerson, 404);

        $selectedPatient = DB::table('pacientes')
            ->where('rut', old('paciente_rut', $authorizedPerson->paciente_rut))
            ->first(['rut', PersonName::expression('pacientes', 'nombre_completo')]);
        if ($request->expectsJson()) {
            return response()->json([
                'data' => $authorizedPerson,
                'options' => [
                    'patients' => DB::table('pacientes')->where('activo', true)->orderBy('apellidoPaterno')->orderBy('apellidoMaterno')
                        ->get(['rut', PersonName::expression('pacientes', 'nombre_completo')]),
                    'selectedPatient' => $selectedPatient,
                ],
            ]);
        }

        return view('authorized-people.edit', [
            'authorizedPerson' => $authorizedPerson,
            'selectedPatient' => $selectedPatient,
        ]);
    }

    // Valida y actualiza el registro existente.
    public function update(Request $request, int $authorizedPersonId, AuditLogger $audit): RedirectResponse|JsonResponse
    {
        $this->authorizeAdministrator($audit, 'personas_autorizadas', $authorizedPersonId);
        $authorizedPerson = DB::table('personas_autorizadas')->where('persona_autorizada_id', $authorizedPersonId)->first();
        abort_unless($authorizedPerson, 404);
        $request->merge(['rut' => Rut::limpiarRut((string) $request->input('rut', ''))]);
        $data = $request->validate([
            'paciente_rut' => ['required', 'string', 'max:12', 'exists:pacientes,rut,activo,1'],
            'rut' => [
                'required', 'string', 'max:9', 'regex:/^\d{7,8}[0-9K]$/',
                // Selecciona el primer elemento disponible.
                fn ($attribute, $value, $fail) => Rut::validarRut($value) ?: $fail('El RUT ingresado no es válido.'),
                Rule::unique('personas_autorizadas', 'rut')
                    ->where('paciente_rut', $request->input('paciente_rut'))
                    ->ignore($authorizedPersonId, 'persona_autorizada_id'),
            ],
            'nombre' => ['required', 'string', 'max:50'],
            'apellidoPaterno' => ['required', 'string', 'max:50'],
            'apellidoMaterno' => ['required', 'string', 'max:50'],
            'vinculo' => ['required', 'string', 'max:100'],
        ]);
        $data['activo'] = $request->boolean('activo');
        DB::table('personas_autorizadas')->where('persona_autorizada_id', $authorizedPersonId)->update($data);
        $audit->record('ACTUALIZACION', 'personas_autorizadas', $authorizedPersonId, 'EXITOSO', [
            'rut' => $data['rut'],
            'paciente_rut' => $data['paciente_rut'],
            'activo' => $data['activo'],
        ]);

        if ($request->expectsJson()) {
            return response()->json(['message' => 'Datos de la persona autorizada actualizados.', 'data' => $data]);
        }

        return redirect()->route('authorized-people.index')->with('status', 'Datos de la persona autorizada actualizados.');
    }

    public function toggle(int $authorizedPersonId, AuditLogger $audit, Request $request): RedirectResponse|JsonResponse
    {
        $this->authorizeAdministrator($audit, 'personas_autorizadas', $authorizedPersonId);
        $authorizedPerson = DB::table('personas_autorizadas')->where('persona_autorizada_id', $authorizedPersonId)->first();
        abort_unless($authorizedPerson, 404);

        $active = ! $authorizedPerson->activo;
        DB::table('personas_autorizadas')->where('persona_autorizada_id', $authorizedPersonId)->update(['activo' => $active]);
        $audit->record('CAMBIO_ESTADO', 'personas_autorizadas', $authorizedPersonId, 'EXITOSO', ['activo' => $active]);

        if ($request->expectsJson()) {
            return response()->json(['message' => 'Estado de la persona autorizada actualizado.', 'data' => ['activo' => $active]]);
        }

        return redirect()->route('authorized-people.index')->with('status', 'Estado de la persona autorizada actualizado.');
    }
}
