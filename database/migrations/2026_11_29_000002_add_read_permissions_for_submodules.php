<?php

use App\Support\Authorization\PermissionCatalog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const PERMISSION_GRANTS = [
        'programs.affiliations.view' => 'programs.enroll',
        'supplies.requests.view' => 'supplies.view',
        'inventory.catalog.view' => 'inventory.catalog.manage',
        'inventory.products.view' => 'inventory.products.manage',
    ];

    public function up(): void
    {
        foreach (PermissionCatalog::definitions()->whereIn('slug', array_keys(self::PERMISSION_GRANTS)) as $permission) {
            DB::table('permissions')->insertOrIgnore(collect($permission)->only([
                'slug', 'name', 'module', 'module_name', 'action', 'locked',
            ])->all());
        }

        foreach (self::PERMISSION_GRANTS as $newSlug => $existingSlug) {
            $newPermissionId = DB::table('permissions')->where('slug', $newSlug)->value('permission_id');
            $existingPermissionId = DB::table('permissions')->where('slug', $existingSlug)->value('permission_id');
            $roleIds = DB::table('permission_role')
                ->where('permission_id', $existingPermissionId)
                ->pluck('rol_id');

            foreach ($roleIds as $roleId) {
                DB::table('permission_role')->insertOrIgnore([
                    'rol_id' => $roleId,
                    'permission_id' => $newPermissionId,
                ]);
            }
        }
    }

    public function down(): void
    {
        $permissionIds = DB::table('permissions')
            ->whereIn('slug', array_keys(self::PERMISSION_GRANTS))
            ->pluck('permission_id');

        DB::table('permission_role')->whereIn('permission_id', $permissionIds)->delete();
        DB::table('permissions')->whereIn('slug', array_keys(self::PERMISSION_GRANTS))->delete();
    }
};
