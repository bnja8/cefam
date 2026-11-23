<?php

namespace App\Http\Controllers\Modules;

use App\Http\Controllers\Controller;
use App\Models\Usuario;
use App\Services\Audit\AuditLogger;
use App\Services\Modules\ModuleQueryService;
use App\Support\People\ChileanMobilePhone;
use App\Support\People\PersonName;
use App\Support\People\Rut;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ModuleController extends Controller
{
    private const MODULES = [
        'recetas' => ['Recetas médicas', 'Consulta de prescripciones y su vigencia.'],
        'programas' => ['Programas de Salud', 'Cobertura y programas activos de la red asistencial.'],
        'cenabast' => ['Remesas / CENABAST', 'Recepciones y remesas de abastecimiento registradas.'],
        'personas-autorizadas' => ['Personas autorizadas', 'Personas autorizadas a retirar medicamentos por pacientes.'],
        'reportes' => ['Reportes', 'Movimientos de inventario para seguimiento y auditoría.'],
        'administracion' => ['Administración', 'Usuarios y roles del sistema.'],
        'libro-digital' => ['Libro digital', 'Movimientos de medicamentos controlados.'],
        'alertas' => ['Alertas', 'Supervisión de stock crítico y situaciones operativas.'],
    ];

    public function show(
        string $module,
        Request $request,
        AuditLogger $audit,
        ModuleQueryService $moduleQuery
    ): View|JsonResponse
    {
        abort_unless(isset(self::MODULES[$module]), 404);

        $user = Auth::user();
        $screen = $request->route('screen', 'list');
        $requiredPermission = match ($module) {
            'administracion' => 'users.view',
            'reportes' => 'reports.view',
            default => null,
        };
        if ($requiredPermission !== null
            && (! $user instanceof Usuario || ! $user->hasPermission($requiredPermission))) {
            $audit->record('ACCESO_DENEGADO', $module, null, 'RECHAZADO');
            abort(403);
        }

        if ($module === 'administracion' && $screen === 'create-user') {
            $this->authorizeAdministrator($audit, 'usuarios');

            if ($request->expectsJson()) {
                return response()->json(['data' => [], 'options' => [
                    'roles' => DB::table('roles')->where('activo', true)->orderBy('nombre')->get(['rol_id', 'nombre']),
                    'personal' => $this->availablePersonal(),
                ]]);
            }

            return view('modules.show', [
                'title' => self::MODULES[$module][0],
                'description' => self::MODULES[$module][1],
                'module' => $module,
                'screen' => $screen,
                'roles' => DB::table('roles')->where('activo', true)->orderBy('nombre')->get(['rol_id', 'nombre']),
                'personal' => $this->availablePersonal(),
            ]);
        }

        $filters = $module === 'administracion'
            ? $request->validate([
                'buscar' => ['nullable', 'string', 'max:100'],
                'estado' => ['nullable', Rule::in(['activo', 'inactivo'])],
                'rol_id' => ['nullable', 'integer', 'exists:roles,rol_id'],
            ])
            : [];
        $filters += ['buscar' => null, 'estado' => null, 'rol_id' => null];

        $rows = $moduleQuery->rows($module, $filters);

        if ($request->expectsJson()) {
            $isPaginated = $rows instanceof LengthAwarePaginator;

            return response()->json([
                'data' => $isPaginated ? $rows->items() : $rows,
                'meta' => $isPaginated ? [
                    'current_page' => $rows->currentPage(),
                    'last_page' => $rows->lastPage(),
                    'per_page' => $rows->perPage(),
                    'total' => $rows->total(),
                ] : null,
                'options' => ['roles' => DB::table('roles')->orderBy('nombre')->get(['rol_id', 'nombre'])],
            ]);
        }

        return view('modules.show', [
            'title' => self::MODULES[$module][0],
            'description' => self::MODULES[$module][1],
            'rows' => $rows,
            'module' => $module,
            'screen' => 'list',
            'filters' => $filters,
            'roles' => $module === 'administracion' ? DB::table('roles')->orderBy('nombre')->get(['rol_id', 'nombre']) : collect(),
        ]);
    }

    public function storeUser(Request $request, AuditLogger $audit): RedirectResponse|JsonResponse
    {
        $this->authorizeAdministrator($audit, 'usuarios');

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
                Rule::unique('usuarios', 'rut'),
            ],
            'nombre' => ['required', 'string', 'max:50'],
            'apellidoPaterno' => ['required', 'string', 'max:50'],
            'apellidoMaterno' => ['required', 'string', 'max:50'],
            'telefono' => ['nullable', 'string', 'regex:/^\+56 9 \d{8}$/'],
            'password' => ['required', 'string', 'min:12'],
            'rol_id' => ['required', 'integer', Rule::exists('roles', 'rol_id')->where(fn ($query) => $query->where('activo', true))],
            'personal_rut' => [
                'nullable',
                'string',
                Rule::exists('personal_salud', 'rut')->where(fn ($query) => $query
                    ->whereNull('usuario_rut')->where('activo', true)),
            ],
        ], [
            'required' => 'El campo :attribute es obligatorio.',
        ], [
            'rut' => 'RUT',
            'nombre' => 'Nombre',
            'apellidoPaterno' => 'Apellido paterno',
            'apellidoMaterno' => 'Apellido materno',
            'password' => 'Contraseña',
            'rol_id' => 'Rol',
        ]);

        $userRut = DB::transaction(function () use ($data, $audit) {
            DB::table('usuarios')->insert([
                'rut' => $data['rut'],
                'nombre' => $data['nombre'],
                'apellidoPaterno' => $data['apellidoPaterno'],
                'apellidoMaterno' => $data['apellidoMaterno'] ?? null,
                'telefono' => $data['telefono'] ?? null,
                'password_hash' => Hash::make($data['password']),
                'activo' => true,
            ]);
            DB::table('usuario_rol')->insert([
                'usuario_rut' => $data['rut'],
                'rol_id' => $data['rol_id'],
            ]);
            if (! empty($data['personal_rut'])) {
                DB::table('personal_salud')
                    ->where('rut', $data['personal_rut'])
                    ->whereNull('usuario_rut')
                    ->update(['usuario_rut' => $data['rut']]);
            }
            $audit->record('CREACION', 'usuarios', null, 'EXITOSO', ['rut' => $data['rut'], 'rol_id' => $data['rol_id']]);

            return $data['rut'];
        });

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Usuario creado correctamente.',
                'data' => [
                    'rut' => $data['rut'],
                    'nombre' => $data['nombre'],
                    'apellidoPaterno' => $data['apellidoPaterno'],
                    'apellidoMaterno' => $data['apellidoMaterno'] ?? null,
                    'telefono' => $data['telefono'] ?? null,
                    'rol_id' => $data['rol_id'],
                    'personal_rut' => $data['personal_rut'] ?? null,
                    'activo' => true,
                ],
            ], 201);
        }

        return redirect()->route('users.index')->with('status', 'Usuario creado correctamente.');
    }

    public function editUser(string $userRut, AuditLogger $audit, Request $request): View|JsonResponse
    {
        $this->authorizeAdministrator($audit, 'usuarios', $userRut);
        $user = DB::table('usuarios')->leftJoin('usuario_rol', 'usuario_rol.usuario_rut', '=', 'usuarios.rut')
            ->leftJoin('personal_salud', 'personal_salud.usuario_rut', '=', 'usuarios.rut')
            ->where('usuarios.rut', $userRut)
            ->first([
                'usuarios.rut',
                'usuarios.nombre',
                'usuarios.apellidoPaterno',
                'usuarios.apellidoMaterno',
                'usuarios.telefono',
                'usuarios.activo',
                'usuario_rol.rol_id',
                'personal_salud.rut as personal_rut',
            ]);
        abort_unless($user, 404);
        $roles = DB::table('roles')
            ->where(function ($query) use ($user) {
                $query->where('activo', true)->orWhere('rol_id', $user->rol_id);
            })
            ->orderBy('nombre')
            ->get(['rol_id', 'nombre', 'activo']);

        if ($request->expectsJson()) {
            return response()->json([
                'data' => $user,
                'options' => [
                    'roles' => $roles,
                    'personal' => $this->availablePersonal($userRut),
                    'canChangeRole' => $userRut !== Auth::id(),
                ],
            ]);
        }

        return view('modules.user-edit', [
            'user' => $user,
            'roles' => $roles,
            'personal' => $this->availablePersonal($userRut),
        ]);
    }

    public function updateUser(Request $request, string $userRut, AuditLogger $audit): RedirectResponse|JsonResponse
    {
        $this->authorizeAdministrator($audit, 'usuarios', $userRut);
        $user = DB::table('usuarios')->where('rut', $userRut)->first();
        abort_unless($user, 404);

        $request->merge([
            'rut' => Rut::limpiarRut((string) $request->input('rut', '')),
            'telefono' => ChileanMobilePhone::normalize($request->input('telefono')),
        ]);
        $rules = [
            'rut' => [
                'required', 'string', 'max:9', 'regex:/^\d{7,8}[0-9K]$/',
                // Selecciona el primer elemento disponible.
                fn ($attribute, $value, $fail) => Rut::validarRut($value) ?: $fail('El RUT ingresado no es válido.'),
                Rule::unique('usuarios', 'rut')->ignore($user->rut, 'rut'),
                // Selecciona el primer elemento disponible.
                fn ($attribute, $value, $fail) => $value === $user->rut ?: $fail('El RUT no se puede modificar porque identifica la cuenta.'),
            ],
            'nombre' => ['required', 'string', 'max:50'],
            'apellidoPaterno' => ['required', 'string', 'max:50'],
            'apellidoMaterno' => ['required', 'string', 'max:50'],
            'telefono' => ['nullable', 'string', 'regex:/^\+56 9 \d{8}$/'],
            'password' => ['nullable', 'string', 'min:12'],
        ];
        if ($userRut !== Auth::id()) {
            $currentRoleId = DB::table('usuario_rol')
                ->where('usuario_rut', $userRut)
                ->value('rol_id');
            $rules['rol_id'] = [
                'required',
                'integer',
                function ($attribute, $value, $fail) use ($currentRoleId): void {
                    $isActiveRole = DB::table('roles')
                        ->where('rol_id', $value)
                        ->where('activo', true)
                        ->exists();
                    if (! $isActiveRole && (int) $value !== (int) $currentRoleId) {
                        $fail('Selecciona un rol activo.');
                    }
                },
            ];
        }
        $rules['personal_rut'] = [
            'nullable',
            'string',
            Rule::exists('personal_salud', 'rut')->where(fn ($query) => $query
                ->where(function ($query) use ($userRut) {
                    $query->where(fn ($query) => $query->whereNull('usuario_rut')->where('activo', true))
                        ->orWhere('usuario_rut', $userRut);
                })),
        ];
        $data = $request->validate($rules);

        DB::transaction(function () use ($data, $userRut) {
            $changes = [
                'nombre' => $data['nombre'],
                'apellidoPaterno' => $data['apellidoPaterno'],
                'apellidoMaterno' => $data['apellidoMaterno'],
                'telefono' => $data['telefono'] ?? null,
            ];
            if (! empty($data['password'])) {
                $changes['password_hash'] = Hash::make($data['password']);
            }
            DB::table('usuarios')->where('rut', $userRut)->update($changes);

            if (array_key_exists('personal_rut', $data)) {
                DB::table('personal_salud')->where('usuario_rut', $userRut)->update(['usuario_rut' => null]);
                if ($data['personal_rut'] !== null) {
                    DB::table('personal_salud')->where('rut', $data['personal_rut'])
                        ->whereNull('usuario_rut')->update(['usuario_rut' => $userRut]);
                }
            }

            if ($userRut !== Auth::id()) {
                DB::table('usuario_rol')->where('usuario_rut', $userRut)->delete();
                DB::table('usuario_rol')->insert(['usuario_rut' => $userRut, 'rol_id' => $data['rol_id']]);
            }
        });
        $audit->record('ACTUALIZACION', 'usuarios', null, 'EXITOSO', [
            'rut' => $userRut,
            'rol_id' => $userRut === Auth::id() ? null : $data['rol_id'],
        ]);

        if ($request->expectsJson()) {
            return response()->json(['message' => 'Datos del usuario actualizados.', 'data' => [
                'rut' => $userRut,
                'nombre' => $data['nombre'],
                'apellidoPaterno' => $data['apellidoPaterno'],
                'apellidoMaterno' => $data['apellidoMaterno'],
                'telefono' => $data['telefono'] ?? null,
                'rol_id' => $userRut === Auth::id() ? null : $data['rol_id'],
            ]]);
        }

        return redirect()->route('users.index')->with('status', 'Datos del usuario actualizados.');
    }

    public function toggleUser(string $userRut, AuditLogger $audit, Request $request): RedirectResponse|JsonResponse
    {
        $this->authorizeAdministrator($audit, 'usuarios', $userRut);
        abort_if($userRut === Auth::id(), 422, 'No puedes desactivar tu propia cuenta.');

        $user = DB::table('usuarios')->where('rut', $userRut)->first();
        abort_unless($user, 404);

        $active = ! $user->activo;
        DB::table('usuarios')->where('rut', $userRut)->update(['activo' => $active]);
        $audit->record('CAMBIO_ESTADO', 'usuarios', null, 'EXITOSO', ['rut' => $userRut, 'activo' => $active]);

        if ($request->expectsJson()) {
            return response()->json(['message' => 'Estado de la cuenta actualizado.', 'data' => ['activo' => $active]]);
        }

        return redirect()->route('users.index')->with('status', 'Estado de la cuenta actualizado.');
    }

    private function availablePersonal(?string $userRut = null): \Illuminate\Support\Collection
    {
        return DB::table('personal_salud')
            ->where(function ($query) use ($userRut) {
                $query->where(function ($query) {
                    $query->whereNull('usuario_rut')->where('activo', true);
                });
                if ($userRut !== null) {
                    $query->orWhere('usuario_rut', $userRut);
                }
            })
            ->orderBy('apellidoPaterno')->orderBy('apellidoMaterno')->orderBy('nombre')
            ->get(['rut', PersonName::expression('personal_salud', 'nombre_completo')]);
    }
}
