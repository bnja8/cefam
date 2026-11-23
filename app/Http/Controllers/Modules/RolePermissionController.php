<?php

namespace App\Http\Controllers\Modules;

use App\Http\Controllers\Controller;
use App\Models\Rol;
use App\Services\Audit\AuditLogger;
use App\Support\Authorization\PermissionCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class RolePermissionController extends Controller
{
    public function index(): View
    {
        $this->ensureAdministrator();

        $roles = DB::table('roles')->orderBy('nombre')->get();
        $assignedUsers = DB::table('usuario_rol')
            ->selectRaw('rol_id, COUNT(*) as total')
            ->groupBy('rol_id')
            ->pluck('total', 'rol_id');
        foreach ($roles as $role) {
            $role->assigned_users = (int) $assignedUsers->get($role->rol_id, 0);
        }

        return view('modules.permissions', [
            'roles' => $roles,
        ]);
    }

    public function create(): View
    {
        $this->ensureAdministrator();

        return view('modules.roles.create', [
            'groups' => PermissionCatalog::groups(),
            'permissions' => PermissionCatalog::definitions()->keyBy('slug'),
        ]);
    }

    public function showPermissions(int $roleId): View
    {
        $this->ensureAdministrator();

        $role = Rol::query()->findOrFail($roleId);
        $grants = $role->permissions()->pluck('slug')->all();
        if (! $role->permissions_initialized) {
            $grants = array_values(array_unique(array_merge(
                $grants,
                PermissionCatalog::defaultsForRole($role->nombre)
            )));
        }

        return view('modules.roles.permissions', [
            'role' => $role,
            'groups' => PermissionCatalog::groups(),
            'permissions' => PermissionCatalog::definitions()->keyBy('slug'),
            'roleGrants' => $grants,
        ]);
    }

    public function store(Request $request, AuditLogger $audit): RedirectResponse
    {
        $this->ensureAdministrator();
        $data = $request->validate([
            'nombre' => ['required', 'string', 'max:50', Rule::unique('roles', 'nombre')],
            'descripcion' => ['nullable', 'string', 'max:255'],
            'permissions' => ['sometimes', 'array'],
            'permissions.*' => ['string', 'distinct', Rule::exists('permissions', 'slug')],
        ]);
        $requestedPermissions = collect($data['permissions'] ?? []);
        $grantedPermissions = DB::table('permissions')
            ->whereIn('slug', $requestedPermissions)
            ->where('locked', false)
            ->get(['permission_id', 'slug']);
        $permissionIds = $grantedPermissions->pluck('permission_id');

        $baseSlug = Str::slug($data['nombre']);
        if ($baseSlug === '') {
            $baseSlug = 'role';
        }
        $slug = $baseSlug;
        $suffix = 2;
        while (DB::table('roles')->where('slug', $slug)->exists()) {
            $slug = $baseSlug.'-'.$suffix++;
        }

        $roleId = DB::transaction(function () use ($data, $slug, $audit, $permissionIds, $grantedPermissions): int {
            $roleId = DB::table('roles')->insertGetId([
                'nombre' => $data['nombre'],
                'descripcion' => $data['descripcion'] ?? null,
                'slug' => $slug,
                'permissions_initialized' => true,
            ]);
            if ($permissionIds->isNotEmpty()) {
                DB::table('permission_role')->insert(
                    $permissionIds->map(static fn (int $permissionId): array => [
                        'rol_id' => $roleId,
                        'permission_id' => $permissionId,
                    ])->all()
                );
            }
            $audit->record('CREACION', 'roles', $roleId, 'EXITOSO', [
                'nombre' => $data['nombre'],
                'permisos' => $grantedPermissions->pluck('slug')->values()->all(),
            ]);

            return $roleId;
        });

        return redirect()->route('roles.permissions.show', $roleId)->with('status', 'Rol creado correctamente.');
    }

    public function updatePermissions(Request $request, int $roleId, AuditLogger $audit): RedirectResponse
    {
        $this->ensureAdministrator();

        $role = Rol::query()->findOrFail($roleId);
        abort_if($role->nombre === 'Administrador', 403);

        $data = $request->validate([
            'permissions' => ['sometimes', 'array'],
            'permissions.*' => ['string', 'distinct', Rule::exists('permissions', 'slug')],
        ]);

        $requested = collect($data['permissions'] ?? []);
        $unlockedRequested = DB::table('permissions')
            ->whereIn('slug', $requested)
            ->where('locked', false)
            ->pluck('slug');
        $currentLocked = $role->permissions()->where('locked', true)->pluck('slug');
        $permissionIds = DB::table('permissions')
            ->whereIn('slug', $unlockedRequested->merge($currentLocked)->unique())
            ->pluck('permission_id');

        DB::transaction(function () use ($role, $permissionIds, $audit, $unlockedRequested, $currentLocked): void {
            $role->permissions()->sync($permissionIds);
            $role->forceFill(['permissions_initialized' => true])->save();
            $audit->record('ACTUALIZACION_PERMISOS', 'roles', $role->rol_id, 'EXITOSO', [
                'permisos' => $unlockedRequested->merge($currentLocked)->unique()->values()->all(),
            ]);
        });

        return redirect()->route('roles.permissions.show', $role->rol_id)->with('status', 'Permisos actualizados correctamente.');
    }

    public function toggleStatus(int $roleId, AuditLogger $audit): RedirectResponse
    {
        $this->ensureAdministrator();

        $role = Rol::query()->findOrFail($roleId);
        abort_if($role->nombre === 'Administrador', 403);

        $active = ! $role->activo;
        $role->forceFill(['activo' => $active])->save();
        $audit->record('CAMBIO_ESTADO', 'roles', $role->rol_id, 'EXITOSO', [
            'nombre' => $role->nombre,
            'activo' => $active,
        ]);

        return redirect()->route('permissions.index')->with(
            'status',
            $active ? 'Rol activado correctamente.' : 'Rol desactivado; sus permisos dejaron de aplicarse a las cuentas asignadas.'
        );
    }

    private function ensureAdministrator(): void
    {
        abort_unless(Auth::user()?->hasAnyRole(['Administrador']), 403);
    }
}
