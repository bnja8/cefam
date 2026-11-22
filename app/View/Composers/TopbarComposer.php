<?php

namespace App\View\Composers;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class TopbarComposer
{
    public function compose(View $view): void
    {
        $userRut = Auth::id();
        $notifications = collect();
        $notificationCount = 0;
        $unreadNotificationCount = 0;

        if ($userRut !== null) {
            $userNotifications = DB::table('notificaciones')->where('usuario_rut', $userRut);
            $notificationCount = (clone $userNotifications)->count();
            $unreadNotificationCount = (clone $userNotifications)->whereNull('leida_at')->count();
            $notifications = (clone $userNotifications)
                ->orderByDesc('created_at')
                ->orderByDesc('notificacion_id')
                ->limit(10)
                ->get()
                ->map(static function (object $notification): object {
                    $notification->icon = match ($notification->tipo) {
                        'REPOSICION_PENDIENTE' => 'fa-truck-ramp-box',
                        'VENCIMIENTO_ASIGNADO' => 'fa-hourglass-half',
                        'ALERTA_RESUELTA' => 'fa-circle-check',
                        'ALERTA_REASIGNADA' => 'fa-user-check',
                        default => 'fa-triangle-exclamation',
                    };
                    $createdAt = Carbon::parse($notification->created_at)->locale('es');
                    $notification->created_at_iso = $createdAt->toIso8601String();
                    $notification->created_at_title = $createdAt->format('d/m/Y H:i');
                    $notification->created_at_relative = $createdAt->diffForHumans();

                    return $notification;
                });
        }

        $view->with([
            'userNotifications' => $notifications,
            'notificationCount' => $notificationCount,
            'unreadNotificationCount' => $unreadNotificationCount,
        ]);
    }
}
