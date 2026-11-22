<?php

namespace App\View\Composers;

use App\Http\Controllers\Modules\ModuleHubController;
use App\Models\Usuario;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AppLayoutComposer
{
    public function __construct(
        private readonly ModuleHubController $moduleHub
    ) {}

    public function compose(View $view): void
    {
        $user = Auth::user();
        $userRoles = $user instanceof Usuario
            ? $user->roles()->orderBy('roles.rol_id')->pluck('nombre')
            : collect();
        $userName = $user instanceof Usuario ? $user->nombre_completo : '';
        $userAvatar = collect(preg_split('/\s+/', trim($userName), -1, PREG_SPLIT_NO_EMPTY))
            ->map(static fn (string $part): string => mb_substr($part, 0, 1))
            ->take(2)
            ->join('');

        $view->with([
            'userRoles' => $userRoles,
            'userName' => $userName,
            'userAvatar' => $userAvatar,
            'moduleNavigation' => $user instanceof Usuario
                ? $this->moduleHub->navigationFor($user)
                : [],
        ]);
    }
}
