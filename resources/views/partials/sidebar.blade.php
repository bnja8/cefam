<aside class="sidebar" id="sidebar">
    @php($canViewDashboard = auth()->user()?->hasPermission('dashboard.view'))
    <div class="sidebar-header">
        @if ($canViewDashboard)
        <a class="sidebar-brand-link" href="{{ route('dashboard') }}">
        @else
        <div class="sidebar-brand-link">
        @endif
            <span class="sidebar-logo-icon"><img src="{{ asset('logo.jpg') }}" alt=""></span>
            <span class="sidebar-brand">
                <strong>Farmacia Asistencial</strong>
                <small>CESFAM · Tu salud nos importa</small>
            </span>
        @if ($canViewDashboard)
        </a>
        @else
        </div>
        @endif
    </div>
    <div class="sidebar-module-search" role="search">
        <label class="sr-only" for="sidebar-module-search">Filtrar módulos del menú</label>
        <div class="sidebar-module-search-control">
            <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
            <input id="sidebar-module-search" type="search" placeholder="Filtrar módulo clínico..." autocomplete="off" aria-controls="sidebar-nav" data-sidebar-module-search>
        </div>
        <p class="sidebar-module-search-empty" role="status" data-sidebar-search-empty hidden>No se encontraron módulos para esta búsqueda.</p>
    </div>
    <nav class="sidebar-nav-container" id="sidebar-nav" aria-label="Navegación principal">
        @if ($canViewDashboard || !empty($moduleNavigation['principal_items']))
            <div class="nav-group {{ request()->routeIs('dashboard', 'alerts.*') ? 'nav-group-active' : '' }}" role="group" aria-labelledby="nav-group-main-title">
                <span class="nav-section-title" id="nav-group-main-title"><span>Principal</span></span>
                @if ($canViewDashboard)
                    <a class="nav-item {{ request()->routeIs('dashboard') ? 'active' : '' }}" href="{{ route('dashboard') }}" aria-current="{{ request()->routeIs('dashboard') ? 'page' : 'false' }}" data-search-text="inicio panel de control"><i class="fa-solid fa-chart-pie" aria-hidden="true"></i><span>Inicio / Panel de Control</span></a>
                @endif
                @foreach ($moduleNavigation['principal_items'] ?? [] as $item)
                    @php($itemIsActive = request()->routeIs($item['route']))
                    <a class="nav-item {{ $itemIsActive ? 'active' : '' }}" href="{{ $item['url'] }}" aria-current="{{ $itemIsActive ? 'page' : 'false' }}" data-search-text="Principal Alertas operativas {{ $item['title'] }} {{ $item['description'] }}">
                        <i class="fa-solid {{ $item['icon'] }}" aria-hidden="true"></i><span>{{ $item['title'] }}</span>
                    </a>
                @endforeach
            </div>
        @endif
        @foreach ($moduleNavigation['groups'] ?? [] as $module)
            @php($moduleIsActive = request()->routeIs(...$module['active_routes']))
            <div class="nav-group {{ $moduleIsActive ? 'nav-group-active' : '' }}" role="group" aria-labelledby="nav-group-{{ $module['key'] }}-title">
                <span class="nav-section-title" id="nav-group-{{ $module['key'] }}-title"><span>{{ $module['title'] }}</span></span>
                @foreach ($module['items'] as $item)
                    @php($itemIsActive = request()->routeIs($item['route']))
                    <a class="nav-item {{ $itemIsActive ? 'active' : '' }}" href="{{ $item['url'] }}" aria-current="{{ $itemIsActive ? 'page' : 'false' }}" data-search-text="{{ $module['title'] }} {{ $item['title'] }} {{ $item['description'] }}">
                        <i class="fa-solid {{ $item['icon'] }}" aria-hidden="true"></i><span>{{ $item['title'] }}</span>
                    </a>
                @endforeach
                @foreach ($module['subsections'] as $subsection)
                    <span class="nav-section-title" id="nav-subgroup-{{ $module['key'] }}-{{ $subsection['key'] }}-title"><span>{{ $subsection['title'] }}</span></span>
                    @foreach ($subsection['items'] as $item)
                        @php($itemIsActive = request()->routeIs($item['route']))
                        <a class="nav-item {{ $itemIsActive ? 'active' : '' }}" href="{{ $item['url'] }}" aria-current="{{ $itemIsActive ? 'page' : 'false' }}" data-search-text="{{ $module['title'] }} {{ $subsection['title'] }} {{ $item['title'] }} {{ $item['description'] }}">
                            <i class="fa-solid {{ $item['icon'] }}" aria-hidden="true"></i><span>{{ $item['title'] }}</span>
                        </a>
                    @endforeach
                @endforeach
            </div>
        @endforeach
    </nav>
</aside>
