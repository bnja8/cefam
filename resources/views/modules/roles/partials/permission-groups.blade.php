@php($moduleIcons = [
    'dashboard' => 'fa-chart-pie',
    'patients' => 'fa-user-group',
    'personal' => 'fa-user-doctor',
    'recipes' => 'fa-file-prescription',
    'programs' => 'fa-heart-pulse',
    'authorized_people' => 'fa-user-check',
    'supplies' => 'fa-truck-ramp-box',
    'inventory' => 'fa-boxes-stacked',
    'carts' => 'fa-kit-medical',
    'dispensations' => 'fa-prescription-bottle-medical',
    'alerts' => 'fa-triangle-exclamation',
    'reports' => 'fa-chart-column',
    'controlled_ledger' => 'fa-book-medical',
    'users' => 'fa-users-gear',
    'roles' => 'fa-user-shield',
    'permissions' => 'fa-key',
])
<div class="permission-module-grid">
    @foreach ($groups as $groupKey => $group)
        @php($modulePermissions = array_merge([$group['view']], $group['actions']))
        <fieldset class="permission-module-card" data-permission-group>
            <legend class="sr-only">{{ $group['title'] }}</legend>
            <header class="permission-module-header">
                <span class="permission-module-icon" aria-hidden="true">
                    <i class="fa-solid {{ $moduleIcons[$groupKey] ?? 'fa-shield-halved' }}"></i>
                </span>
                <span class="permission-module-heading">
                    <strong>{{ $group['title'] }}</strong>
                    <small>Permiso de consulta y acciones del módulo</small>
                </span>
            </header>
            <div class="permission-option-list">
                @foreach ($modulePermissions as $definition)
                    @php($permission = $permissions->get($definition['slug']))
                    @php($isSelected = in_array($definition['slug'], $selectedPermissions, true))
                    @php($isInitial = in_array($definition['slug'], $initialPermissions, true))
                    <label class="permission-option {{ $loop->first ? 'permission-option-view' : 'permission-option-action' }} {{ $permission['locked'] ? 'permission-option-locked' : '' }}">
                        <input
                            type="checkbox"
                            name="permissions[]"
                            value="{{ $definition['slug'] }}"
                            data-permission-toggle
                            data-permission-name="{{ $definition['name'] }}"
                            data-initially-checked="{{ $isInitial ? 'true' : 'false' }}"
                            @checked($isSelected)
                            @disabled($readOnly || $permission['locked'])>
                        <span class="permission-option-copy">
                            <strong>{{ $definition['name'] }}</strong>
                            <small>{{ $loop->first ? 'Permite consultar este módulo.' : 'Concede esta acción operativa.' }}</small>
                        </span>
                        @if ($permission['locked'])
                            <span class="permission-status permission-status-locked"><i class="fa-solid fa-lock" aria-hidden="true"></i> Protegido</span>
                        @else
                            <span class="permission-status">{{ $loop->first ? 'Consulta' : 'Acción' }}</span>
                        @endif
                    </label>
                @endforeach
            </div>
        </fieldset>
    @endforeach
</div>
