<?php

namespace Database\Seeders;

use App\Support\Authorization\PermissionCatalog;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        foreach (PermissionCatalog::definitions() as $permission) {
            DB::table('permissions')->updateOrInsert(
                ['slug' => $permission['slug']],
                collect($permission)->only(['name', 'module', 'module_name', 'action', 'locked'])->all()
            );
        }

        foreach (DB::table('roles')->get(['rol_id', 'nombre', 'permissions_initialized']) as $role) {
            if ($role->permissions_initialized) {
                continue;
            }

            foreach (PermissionCatalog::defaultsForRole($role->nombre) as $slug) {
                $permissionId = DB::table('permissions')->where('slug', $slug)->value('permission_id');
                if ($permissionId !== null) {
                    DB::table('permission_role')->insertOrIgnore([
                        'rol_id' => $role->rol_id,
                        'permission_id' => $permissionId,
                    ]);
                }
            }

            DB::table('roles')->where('rol_id', $role->rol_id)->update(['permissions_initialized' => true]);
        }
    }
}
