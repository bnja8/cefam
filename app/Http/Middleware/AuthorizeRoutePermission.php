<?php

namespace App\Http\Middleware;

use App\Models\Usuario;
use App\Services\Audit\AuditLogger;
use App\Support\Authorization\PermissionCatalog;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthorizeRoutePermission
{
    public function __construct(private readonly AuditLogger $audit)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if ($user === null) {
            return $next($request);
        }
        $routeName = $request->route()?->getName();

        foreach (PermissionCatalog::permissionsForRoute($routeName) as $permission) {
            if ($user instanceof Usuario && $user->hasPermission($permission)) {
                continue;
            }

            $entityRoute = str_starts_with((string) $routeName, 'api.v1.')
                ? substr((string) $routeName, 7)
                : $routeName;
            $entities = config('permissions.route_entities', []);
            $this->audit->record('ACCESO_DENEGADO', $entities[$entityRoute] ?? 'permisos', null, 'RECHAZADO', [
                'ruta' => $routeName,
                'permiso' => $permission,
            ]);
            abort(403);
        }

        return $next($request);
    }
}
