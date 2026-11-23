<?php

namespace App\Support\Authorization;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class PermissionCatalog
{
    public static function groups(): array
    {
        return config('permissions.modules', []);
    }

    public static function definitions(): Collection
    {
        return collect(self::groups())->flatMap(function (array $module, string $key): array {
            $moduleSlug = Str::beforeLast($module['view']['slug'], '.');
            $definitions = [
                [
                    'slug' => $module['view']['slug'],
                    'name' => $module['view']['name'],
                    'module' => $key,
                    'module_name' => $module['title'],
                    'action' => 'view',
                    'locked' => (bool) ($module['view']['locked'] ?? false),
                ],
            ];

            foreach ($module['actions'] as $action) {
                $definitions[] = [
                    'slug' => $action['slug'],
                    'name' => $action['name'],
                    'module' => $key,
                    'module_name' => $module['title'],
                    'action' => Str::after($action['slug'], $moduleSlug.'.'),
                    'locked' => (bool) ($action['locked'] ?? false),
                ];
            }

            return $definitions;
        })->values();
    }

    public static function defaultsForRole(string $roleName): array
    {
        $defaults = config('permissions.role_defaults.'.$roleName, []);

        if (! in_array('*', $defaults, true)) {
            return $defaults;
        }

        return self::definitions()
            ->reject(static fn (array $permission): bool => $permission['module'] === 'controlled_ledger')
            ->pluck('slug')
            ->all();
    }

    public static function permissionsForRoute(?string $routeName): array
    {
        if ($routeName === null) {
            return [];
        }

        if (str_starts_with($routeName, 'api.v1.')) {
            $routeName = substr($routeName, 7);
        }

        $routes = config('permissions.route_permissions', []);
        if (array_key_exists($routeName, $routes)) {
            return $routes[$routeName];
        }

        foreach ($routes as $pattern => $permissions) {
            if (str_contains($pattern, '*') && Str::is($pattern, $routeName)) {
                return $permissions;
            }
        }

        return [];
    }
}
