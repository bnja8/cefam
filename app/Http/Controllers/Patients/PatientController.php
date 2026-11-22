<?php


namespace App\Http\Controllers\Patients;

use App\Http\Controllers\Controller;
use App\Rules\FourDigitDate;
use App\Services\Audit\AuditLogger;
use App\Services\Patients\PatientFileService;
use App\Support\People\PersonName;
use App\Support\People\ChileanMobilePhone;
use App\Support\People\Rut;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PatientController extends Controller
{
    public function search(Request $request): JsonResponse
    {
        $request->merge(['q' => trim((string) $request->query('q', ''))]);
        $filters = $request->validate([
            'q' => ['required', 'string', 'min:2', 'max:100'],
        ]);
        $term = $filters['q'];
        $cleanRut = Rut::limpiarRut($term);
        $rutTerm = $cleanRut === '' ? null : '%'.$cleanRut.'%';

        $patients = DB::table('pacientes')
            ->where('activo', true)
            ->where( function ($query) use ($rutTerm, $term) {
                if ($rutTerm !== null) {
                    $query->whereRaw(
                        "REPLACE(REPLACE(REPLACE(UPPER(pacientes.rut), '.', ''), '-', ''), ' ', '') LIKE ?",
                        [$rutTerm]
                    )
                        ->orWhere( fn ($nameQuery) => PersonName::whereMatches($nameQuery, 'pacientes', $term));
                } else {
                    PersonName::whereMatches($query, 'pacientes', $term);
                }
            })
            ->orderBy('apellidoPaterno')
            ->orderBy('apellidoMaterno')
            ->orderBy('nombre')
            ->limit(10)
            ->get(['rut', PersonName::expression('pacientes', 'nombre_completo')]);

        return response()->json($patients);
    }

    public function index(Request $request): View|JsonResponse
    {
        $screen = $request->boolean('mostrar_formulario') ? 'create' : $request->route('screen', 'list');
        if ($screen === 'create') {
            abort_unless(auth()->user()?->hasAnyRole(['Administrador']), 403);

            if ($request->expectsJson()) {
                return response()->json(['data' => ['activePrograms' => []]]);
            }

            return view('patients.index', [
                'screen' => 'create',
                'patients' => null,
                'activePrograms' => collect(),
            ]);
        }

        $filters = $request->validate([
            'buscar' => ['nullable', 'string', 'max:100'],
            'programa_id' => ['nullable', 'integer', 'exists:programas_salud,programa_id'],
            'estado' => ['nullable', Rule::in(['todos', 'activo', 'inactivo'])],
            'programa_estado' => ['nullable', Rule::in(['todos', 'con_programa', 'sin_programa'])],
        ]);
        $today = now()->toDateString();
        $activeProgramEnrollment =  fn ($query) => $query
            ->selectRaw('1')
            ->from('paciente_programa')
            ->join('programas_salud', 'programas_salud.programa_id', '=', 'paciente_programa.programa_id')
            ->whereColumn('paciente_programa.paciente_rut', 'pacientes.rut')
            ->where('paciente_programa.activo', true)
            ->where('programas_salud.activo', true)
            ->whereDate('paciente_programa.fecha_inicio', '<=', $today)
            ->where( function ($query) use ($today) {
                $query->whereNull('paciente_programa.fecha_termino')
                    ->orWhereDate('paciente_programa.fecha_termino', '>=', $today);
            });
        $activePrograms = DB::table('programas_salud')
            ->where('activo', true)
            ->orderBy('nombre')
            ->get(['programa_id', 'nombre']);

        $patients = DB::table('pacientes')
            ->select('pacientes.*', PersonName::expression('pacientes', 'nombre_completo'))
            ->when(($filters['estado'] ?? 'todos') === 'activo',  fn ($query) => $query->where('pacientes.activo', true))
            ->when(($filters['estado'] ?? 'todos') === 'inactivo',  fn ($query) => $query->where('pacientes.activo', false))
            ->when(($filters['programa_estado'] ?? 'todos') === 'con_programa' || ($filters['programa_id'] ?? null),  function ($query) use ($activeProgramEnrollment, $filters) {
                $query->whereExists( fn ($activeEnrollment) => $activeProgramEnrollment($activeEnrollment)
                    ->when($filters['programa_id'] ?? null,  fn ($programQuery, $programId) => $programQuery->where('programas_salud.programa_id', $programId)));
            })
            ->when(($filters['programa_estado'] ?? 'todos') === 'sin_programa',  fn ($query) => $query->whereNotExists($activeProgramEnrollment))
            ->when($filters['buscar'] ?? null,  function ($query, string $search) {
                $term = '%'.$search.'%';
                $rutTerm = '%'.Rut::limpiarRut($search).'%';
                $query->where( function ($query) use ($search, $term, $rutTerm) {
                    $query->where('pacientes.rut', 'like', $rutTerm)
                        ->orWhere( fn ($nameQuery) => PersonName::whereMatches($nameQuery, 'pacientes', $search));
                });
            })
            ->orderBy('apellidoPaterno')
            ->orderBy('apellidoMaterno')
            ->orderBy('nombre')
            ->paginate(12)
            ->withQueryString();

        $patientRuts = $patients->getCollection()->pluck('rut')->filter()->values()->all();
        $activeEnrollments = DB::table('paciente_programa')
            ->join('programas_salud', 'programas_salud.programa_id', '=', 'paciente_programa.programa_id')
            ->when($patientRuts !== [],  fn ($query) => $query->whereIn('paciente_programa.paciente_rut', $patientRuts))
            ->where('paciente_programa.activo', true)
            ->where('programas_salud.activo', true)
            ->whereDate('paciente_programa.fecha_inicio', '<=', now()->toDateString())
            ->where( function ($query) {
                $query->whereNull('paciente_programa.fecha_termino')
                    ->orWhereDate('paciente_programa.fecha_termino', '>=', now()->toDateString());
            })
            ->get(['paciente_programa.paciente_rut', 'programas_salud.nombre']);
        $programsByPatient = $activeEnrollments->groupBy('paciente_rut');
        $authorizedPeople = $patientRuts === [] ? collect() : DB::table('personas_autorizadas')
            ->whereIn('paciente_rut', $patientRuts)
            ->orderBy('apellidoPaterno')
            ->orderBy('apellidoMaterno')
            ->orderBy('nombre')
            ->get([
                'paciente_rut',
                'rut',
                PersonName::expression('personas_autorizadas', 'nombre_completo'),
                'vinculo',
                'activo',
            ]);
        $authorizedPeopleByPatient = $authorizedPeople->groupBy('paciente_rut');
        $patients->getCollection()->transform( function ($patient) use ($programsByPatient, $authorizedPeopleByPatient) {
            $patient->programas = $programsByPatient->get($patient->rut, collect());
            $patient->personas_autorizadas = $authorizedPeopleByPatient->get($patient->rut, collect());

            return $patient;
        });

        if ($request->expectsJson()) {
            return response()->json([
                'data' => $patients->items(),
                'meta' => [
                    'current_page' => $patients->currentPage(),
                    'last_page' => $patients->lastPage(),
                    'per_page' => $patients->perPage(),
                    'total' => $patients->total(),
                ],
                'options' => ['activePrograms' => $activePrograms],
            ]);
        }

        return view('patients.index', compact('patients', 'activePrograms', 'screen'));
    }

    // Valida y guarda un nuevo registro.
    public function store(Request $request, AuditLogger $audit, PatientFileService $patientFiles): RedirectResponse|JsonResponse
    {
        $this->authorizeAdministrator($audit, 'pacientes');
        $request->merge([
            'rut' => Rut::limpiarRut((string) $request->input('rut', '')),
            'telefono' => ChileanMobilePhone::normalize($request->input('telefono')),
        ]);
        $data = $request->validate([
            'rut' => [
                'required',
                'string',
                'max:9',
                'regex:/^\d{7,8}[0-9K]$/',
                fn ($attribute, $value, $fail) => Rut::validarRut($value) ?: $fail('El RUT ingresado no es válido.'),
                Rule::unique('pacientes', 'rut'),
            ],
            'nombre' => ['required', 'string', 'max:50'],
            'apellidoPaterno' => ['required', 'string', 'max:50'],
            'apellidoMaterno' => ['required', 'string', 'max:50'],
            'fecha_nacimiento' => ['required', 'bail', new FourDigitDate(), 'before_or_equal:today'],
            'telefono' => ['nullable', 'string', 'regex:/^\+56 9 \d{8}$/'],
        ]);

        DB::transaction(function () use ($data, $audit, $patientFiles) {
            DB::table('pacientes')->insert($data + ['activo' => true]);
            $patientFiles->ensureForPatient($data['rut']);
            $audit->record('CREACION', 'pacientes', $data['rut'], 'EXITOSO', ['rut' => $data['rut']]);
        });

        if ($request->expectsJson()) {
            return response()->json(['message' => 'Paciente registrado correctamente.', 'data' => $data], 201);
        }

        return redirect()->route('patients.index')->with('status', 'Paciente registrado correctamente.');
    }

    public function edit(string $patientRut, AuditLogger $audit, Request $request): View|JsonResponse
    {
        $this->authorizeAdministrator($audit, 'pacientes', $patientRut);
        $patient = DB::table('pacientes')->where('rut', $patientRut)->first();
        abort_unless($patient, 404);

        if ($request->expectsJson()) {
            return response()->json(['data' => $patient]);
        }

        return view('patients.edit', compact('patient'));
    }

    // Valida y actualiza el registro existente.
    public function update(Request $request, string $patientRut, AuditLogger $audit): RedirectResponse|JsonResponse
    {
        $this->authorizeAdministrator($audit, 'pacientes', $patientRut);
        abort_unless(DB::table('pacientes')->where('rut', $patientRut)->exists(), 404);
        $request->merge([
            'rut' => Rut::limpiarRut((string) $request->input('rut', '')),
            'telefono' => ChileanMobilePhone::normalize($request->input('telefono')),
        ]);
        $data = $request->validate([
            'rut' => [
                'required', 'string', 'max:9', 'regex:/^\d{7,8}[0-9K]$/',
                fn ($attribute, $value, $fail) => Rut::validarRut($value) ?: $fail('El RUT ingresado no es válido.'),
                Rule::unique('pacientes', 'rut')->ignore($patientRut, 'rut'),
                fn ($attribute, $value, $fail) => $value === $patientRut ?: $fail('El RUT no se puede modificar porque identifica al paciente.'),
            ],
            'nombre' => ['required', 'string', 'max:50'],
            'apellidoPaterno' => ['required', 'string', 'max:50'],
            'apellidoMaterno' => ['required', 'string', 'max:50'],
            'fecha_nacimiento' => ['nullable', 'bail', new FourDigitDate(), 'before_or_equal:today'],
            'telefono' => ['nullable', 'string', 'regex:/^\+56 9 \d{8}$/'],
        ]);
        $data['activo'] = $request->boolean('activo');
        DB::table('pacientes')->where('rut', $patientRut)->update($data);
        $audit->record('ACTUALIZACION', 'pacientes', $patientRut, 'EXITOSO', ['rut' => $data['rut'], 'activo' => $data['activo']]);

        if ($request->expectsJson()) {
            return response()->json(['message' => 'Datos del paciente actualizados.', 'data' => $data]);
        }

        return redirect()->route('patients.file', $patientRut)->with('status', 'Datos del paciente actualizados.');
    }
}
