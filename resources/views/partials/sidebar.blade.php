<aside class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <a class="sidebar-brand-link" href="{{ route('dashboard') }}">
            <span class="sidebar-logo-icon"><img src="{{ asset('logo.jpg') }}" alt=""></span>
            <span class="sidebar-brand">
                <strong>Farmacia Asistencial</strong>
                <small>Atención Primaria de Salud · APS</small>
            </span>
        </a>
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
        <div class="nav-group {{ request()->routeIs('dashboard') ? 'nav-group-active' : '' }}" role="group" aria-labelledby="nav-group-main-title">
            <span class="nav-section-title" id="nav-group-main-title"><span>Principal</span></span>
            <a class="nav-item {{ request()->routeIs('dashboard') ? 'active' : '' }}" href="{{ route('dashboard') }}" aria-current="{{ request()->routeIs('dashboard') ? 'page' : 'false' }}" data-search-text="inicio panel de control"><i class="fa-solid fa-chart-pie" aria-hidden="true"></i><span>Inicio / Panel de Control</span></a>
        </div>
        @foreach ($moduleNavigation as $module)
            @php($moduleIsActive = request()->routeIs(...$module['active_routes']))
            <div class="nav-group {{ $moduleIsActive ? 'nav-group-active' : '' }}" role="group" aria-labelledby="nav-group-{{ $loop->index }}-title">
                <span class="nav-section-title" id="nav-group-{{ $loop->index }}-title"><span>{{ $module['title'] }}</span></span>
                @foreach ($module['items'] as $item)
                    @php($itemIsActive = request()->routeIs($item['route']))
                    <a class="nav-item {{ $itemIsActive ? 'active' : '' }}" href="{{ $item['url'] }}" aria-current="{{ $itemIsActive ? 'page' : 'false' }}" data-search-text="{{ $module['title'] }} {{ $item['title'] }} {{ $item['description'] }}">
                        <i class="fa-solid {{ $item['icon'] }}" aria-hidden="true"></i><span>{{ $item['title'] }}</span>
                    </a>
                @endforeach
            </div>
        @endforeach
    </nav>
    <div class="sidebar-footer">
        <div class="sidebar-footer-meta">
            <strong>CESFAM Central</strong>
            <small>Gestión Farmacéutica Prototipo</small>
        </div>
    </div>
</aside>
