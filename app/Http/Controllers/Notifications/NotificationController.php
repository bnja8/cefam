<?php

namespace App\Http\Controllers\Notifications;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

class NotificationController extends Controller
{
    public function index(): JsonResponse
    {
        $userNotifications = DB::table('notificaciones')
            ->where('usuario_rut', Auth::id());

        return response()->json(['data' => [
            'notifications' => (clone $userNotifications)
                ->orderByDesc('created_at')
                ->orderByDesc('notificacion_id')
                ->limit(10)
                ->get([
                    'notificacion_id',
                    'tipo',
                    'titulo',
                    'mensaje',
                    'accion_texto',
                    'accion_url',
                    'leida_at',
                    'created_at',
                ]),
            'total' => (clone $userNotifications)->count(),
            'unread' => (clone $userNotifications)->whereNull('leida_at')->count(),
        ]]);
    }

    public function openJson(int $notificationId): JsonResponse
    {
        $notification = DB::table('notificaciones')
            ->where('notificacion_id', $notificationId)
            ->where('usuario_rut', Auth::id())
            ->first(['notificacion_id', 'leida_at', 'accion_url']);

        abort_unless($notification, 404);

        if ($notification->leida_at === null) {
            DB::table('notificaciones')
                ->where('notificacion_id', $notificationId)
                ->where('usuario_rut', Auth::id())
                ->whereNull('leida_at')
                ->update(['leida_at' => now()]);
        }

        $actionUrl = $notification->accion_url;
        abort_unless(
            is_string($actionUrl)
                && str_starts_with($actionUrl, '/')
                && ! str_starts_with($actionUrl, '//'),
            404
        );

        return response()->json(['data' => ['action_url' => $actionUrl]]);
    }

    public function open(int $notificationId): RedirectResponse
    {
        $notification = DB::table('notificaciones')
            ->where('notificacion_id', $notificationId)
            ->where('usuario_rut', Auth::id())
            ->first(['notificacion_id', 'leida_at', 'accion_url']);

        abort_unless($notification, 404);

        if ($notification->leida_at === null) {
            DB::table('notificaciones')
                ->where('notificacion_id', $notificationId)
                ->where('usuario_rut', Auth::id())
                ->whereNull('leida_at')
                ->update(['leida_at' => now()]);
        }

        $actionUrl = $notification->accion_url;
        abort_unless(
            is_string($actionUrl)
                && str_starts_with($actionUrl, '/')
                && ! str_starts_with($actionUrl, '//'),
            404
        );

        return redirect($actionUrl);
    }

    public function markAllRead(): RedirectResponse|JsonResponse
    {
        DB::table('notificaciones')
            ->where('usuario_rut', Auth::id())
            ->whereNull('leida_at')
            ->update(['leida_at' => now()]);

        return request()->expectsJson()
            ? response()->json(['data' => ['message' => 'Notificaciones marcadas como leídas.']])
            : back()->with('status', 'Notificaciones marcadas como leídas.');
    }
}
