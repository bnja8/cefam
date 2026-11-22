<header class="topbar">
    <div class="topbar-left">
        <button class="sidebar-toggle-btn" id="sidebar-toggle" type="button" aria-label="Mostrar navegación" aria-controls="sidebar" aria-expanded="false">
            <i class="fa-solid fa-bars" aria-hidden="true"></i>
        </button>
    </div>
    <div class="topbar-right">
        <div class="notification-menu" data-notification-menu>
            <button
                class="icon-btn notification-toggle"
                type="button"
                aria-label="Abrir alertas y notificaciones"
                aria-controls="notification-panel"
                aria-expanded="false"
                title="Notificaciones"
                data-notification-toggle
            >
                <i class="fa-regular fa-bell" aria-hidden="true"></i>
                @if ($unreadNotificationCount > 0)
                    <span class="notification-badge" aria-hidden="true">{{ $unreadNotificationCount > 9 ? '9+' : $unreadNotificationCount }}</span>
                @endif
            </button>
            <section class="notification-panel" id="notification-panel" aria-label="Notificaciones" hidden data-notification-panel>
                <header class="notification-panel-header">
                    <div>
                        <h2>Notificaciones</h2>
                        <p>Actualizaciones de alertas y reposiciones</p>
                    </div>
                    @if ($unreadNotificationCount > 0)
                        <form action="{{ route('notifications.read-all') }}" method="post">
                            @csrf
                            <button class="notification-read-all" type="submit">Marcar todas como leídas</button>
                        </form>
                    @endif
                </header>
                <nav class="notification-tabs" aria-label="Filtrar notificaciones">
                    <button class="notification-tab is-active" type="button" aria-pressed="true" data-notification-tab="all">
                        Todas <span>{{ $notificationCount }}</span>
                    </button>
                    <button class="notification-tab" type="button" aria-pressed="false" data-notification-tab="unread">
                        No leídas <span>{{ $unreadNotificationCount }}</span>
                    </button>
                </nav>
                @if ($userNotifications->isNotEmpty())
                    <ul class="notification-list" data-notification-list>
                        @foreach ($userNotifications as $notification)
                            <li
                                class="notification-item {{ $notification->leida_at === null ? 'is-unread' : '' }}"
                                data-notification-item
                                data-notification-unread="{{ $notification->leida_at === null ? 'true' : 'false' }}"
                            >
                                <a class="notification-item-link" href="{{ route('notifications.open', $notification->notificacion_id) }}">
                                    <span class="notification-item-icon" aria-hidden="true"><i class="fa-solid {{ $notification->icon }}"></i></span>
                                    <span class="notification-item-copy">
                                        <span class="notification-item-heading">
                                            <strong>{{ $notification->titulo }}</strong>
                                            <time datetime="{{ $notification->created_at_iso }}" title="{{ $notification->created_at_title }}">
                                                {{ $notification->created_at_relative }}
                                            </time>
                                        </span>
                                        <span class="notification-item-message">{{ $notification->mensaje }}</span>
                                        @if ($notification->accion_texto)
                                            <span class="notification-item-action">{{ $notification->accion_texto }} <i class="fa-solid fa-chevron-right" aria-hidden="true"></i></span>
                                        @endif
                                    </span>
                                    @if ($notification->leida_at === null)
                                        <span class="notification-unread-dot" aria-label="No leída"></span>
                                    @endif
                                </a>
                            </li>
                        @endforeach
                    </ul>
                @endif
                <p class="notification-empty" data-notification-empty @if ($userNotifications->isNotEmpty()) hidden @endif>No tienes notificaciones por ahora.</p>
                <p class="notification-empty" data-notification-filter-empty hidden>No tienes notificaciones sin leer.</p>
            </section>
        </div>
        <div class="user-profile">
            <div class="user-info">
                <span class="user-name">{{ $userName }}</span>
                <span class="user-role">{{ $userRoles->first() ?? 'Personal CESFAM' }}</span>
            </div>
            <span class="user-avatar" aria-hidden="true">{{ $userAvatar }}</span>
        </div>
        <form action="{{ route('logout') }}" method="post">
            @csrf
            <button class="icon-btn" type="submit" aria-label="Cerrar sesión" title="Cerrar sesión">
                <i class="fa-solid fa-arrow-right-from-bracket" aria-hidden="true"></i>
            </button>
        </form>
    </div>
</header>
