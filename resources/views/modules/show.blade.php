@extends('layouts.app')
@section('content')
    <x-page-header :title="$screen === 'create-user' ? 'Crear cuenta de usuario' : $title" :description="$description">
        <x-slot:actions>@if ($module === 'administracion' && $screen === 'list' && auth()->user()->hasAnyRole(['Administrador']))<a class="btn btn-primary" href="{{ route('users.create') }}">Crear cuenta</a>@endif</x-slot:actions>
    </x-page-header>
    @if ($module === 'administracion' && $screen === 'create-user')
        <section class="card" id="crear-usuario">
            <div class="card-header"><h2 class="card-title"><i class="fa-solid fa-user-plus text-primary"></i> Crear cuenta de usuario</h2></div>
            @if (auth()->user()->hasAnyRole(['Administrador']))
                <div class="card-body">
                    <form action="{{ route('users.store') }}" method="post" class="form-grid" data-person-lookup data-person-lookup-url="{{ route('people.lookup') }}" data-person-type="usuarios">
                        @csrf
                        <div class="form-group"><label for="user-rut">RUT *</label><input class="form-control" id="user-rut" name="rut" value="{{ old('rut') }}" required maxlength="12" placeholder="12.345.678-9" data-rut-input data-person-rut aria-describedby="user-rut-error"><small id="user-rut-error" class="field-error" data-rut-error aria-live="polite"></small><small data-person-lookup-status aria-live="polite"></small></div>
                        <div class="form-group"><label for="user-name">Nombre *</label><input class="form-control" id="user-name" name="nombre" value="{{ old('nombre') }}" required maxlength="50"></div>
                        <div class="form-group"><label for="user-paternal-surname">Apellido paterno *</label><input class="form-control" id="user-paternal-surname" name="apellidoPaterno" value="{{ old('apellidoPaterno') }}" required maxlength="50"></div>
                        <div class="form-group"><label for="user-maternal-surname">Apellido materno *</label><input class="form-control" id="user-maternal-surname" name="apellidoMaterno" value="{{ old('apellidoMaterno') }}" required maxlength="50"></div>
                        <x-phone-input id="user-phone" />
                        <div class="form-group"><label for="user-role">Rol *</label><select class="form-control" id="user-role" name="rol_id" required>@foreach ($roles as $role)<option value="{{ $role->rol_id }}" @selected(old('rol_id') == $role->rol_id)>{{ $role->nombre }}</option>@endforeach</select></div>
                        <div class="form-group"><label for="user-personal">Personal de salud vinculado (opcional)</label><select class="form-control" id="user-personal" name="personal_rut"><option value="">Sin vínculo</option>@foreach ($personal as $member)<option value="{{ $member->rut }}" @selected(old('personal_rut') === $member->rut)>{{ $member->nombre_completo }} - {{ $member->rut }}</option>@endforeach</select></div>
                        <div class="form-group form-span"><label for="user-password">Contraseña temporal * (mínimo 12 caracteres)</label><input class="form-control" id="user-password" type="password" name="password" minlength="12" required autocomplete="new-password"></div>
                        <div class="form-actions"><button class="btn btn-primary" type="submit"><i class="fa-solid fa-user-plus"></i> Crear cuenta</button></div>
                    </form>
                </div>
            @else
                <div class="card-body notice notice-info"><i class="fa-solid fa-lock"></i><span>La creación y activación de cuentas está reservada al perfil Administrador.</span></div>
            @endif
        </section>
    @endif
    @if ($screen === 'list')
    <section class="card" id="usuarios-registrados">
        @if ($module === 'administracion')
            <div class="card-header"><h2 class="card-title"><i class="fa-solid fa-table-list text-primary"></i> Consultar usuarios</h2><span class="muted">{{ number_format($rows->total(), 0, ',', '.') }} resultados</span></div>
            <div class="card-body">
                <form action="{{ route('users.index') }}" method="get" class="form-grid">
                    <div class="form-group form-span"><label for="buscar-usuario">Buscar usuario</label><input class="form-control" id="buscar-usuario" name="buscar" value="{{ $filters['buscar'] ?? '' }}" placeholder="Nombre, RUT, teléfono o rol"></div>
                    <div class="form-group"><label for="estado-usuario">Estado de la cuenta</label><select class="form-control" id="estado-usuario" name="estado"><option value="" @selected($filters['estado'] === null)>Todos</option><option value="activo" @selected($filters['estado'] === 'activo')>Activo</option><option value="inactivo" @selected($filters['estado'] === 'inactivo')>Desactivado</option></select></div>
                    <div class="form-group"><label for="rol-usuario">Rol</label><select class="form-control" id="rol-usuario" name="rol_id"><option value="">Todos los roles</option>@foreach ($roles as $role)<option value="{{ $role->rol_id }}" @selected((string) ($filters['rol_id'] ?? '') === (string) $role->rol_id)>{{ $role->nombre }}</option>@endforeach</select></div>
                    <div class="form-actions"><button class="btn btn-primary" type="submit"><i class="fa-solid fa-magnifying-glass"></i> Buscar</button><a class="btn btn-outline" href="{{ route('users.index') }}">Limpiar</a></div>
                </form>
            </div>
        @else
            <div class="card-header"><h2 class="card-title"><i class="fa-solid fa-table-list text-primary"></i> Registros</h2><span class="muted">{{ number_format($rows->total(), 0, ',', '.') }} resultados</span></div>
        @endif
        @if ($rows->isEmpty())
            <div class="empty-panel"><span class="empty-icon"><i class="fa-regular fa-folder-open"></i></span><h2>{{ $module === 'administracion' ? 'No se encontraron usuarios' : 'No hay registros disponibles' }}</h2><p>{{ $module === 'administracion' ? 'Prueba con otros términos de búsqueda o filtros.' : 'Los datos aparecerán aquí cuando se registren operaciones en el sistema.' }}</p></div>
        @else
            @if ($module === 'administracion')
                <div class="person-card-grid searchable-cards">
                    @foreach ($rows as $row)
                        <x-person-card
                            :name="$row->nombre_completo"
                            :subtitle="$row->rol ? $row->rol.(! $row->rol_activo ? ' (rol desactivado)' : '') : 'Sin rol asignado'"
                            :status="$row->activo ? 'Activo' : 'Desactivado'"
                            :active="$row->activo"
                        >
                            <x-slot:details>
                                <div class="person-card-meta-row">
                                    <i class="fa-regular fa-id-card" aria-hidden="true"></i>
                                    <span>RUT <strong>{{ $row->rut }}</strong></span>
                                </div>
                                <div class="person-card-meta-row">
                                    <i class="fa-solid fa-phone" aria-hidden="true"></i>
                                    <span>Teléfono <strong>{{ $row->telefono ?: '—' }}</strong></span>
                                </div>
                                <div class="person-card-meta-row">
                                    <i class="fa-solid fa-user-doctor" aria-hidden="true"></i>
                                    <span>Personal de salud <strong>{{ $row->personal_nombre ?: 'No vinculado' }}</strong></span>
                                </div>
                            </x-slot:details>
                            @if (auth()->user()->hasAnyRole(['Administrador']))
                                <x-slot:actions>
                                    <a class="btn btn-outline btn-small" href="{{ route('users.edit', $row->rut) }}">Editar</a>
                                    @if ($row->rut !== auth()->id())
                                        <form action="{{ route('users.toggle', $row->rut) }}" method="post">
                                            @csrf @method('patch')
                                            <button class="btn btn-small {{ $row->activo ? 'btn-outline' : 'btn-primary' }}" type="submit">{{ $row->activo ? 'Desactivar' : 'Activar' }}</button>
                                        </form>
                                    @endif
                                </x-slot:actions>
                            @endif
                        </x-person-card>
                    @endforeach
                </div>
            @else
                <div class="table-responsive">
                    <table class="data-table searchable-table">
                        <thead><tr>@foreach (array_keys((array) $rows->first()) as $column)<th>{{ str_replace('_', ' ', $column) }}</th>@endforeach</tr></thead>
                        <tbody>
                        @foreach ($rows as $row)
                            <tr>
                                @foreach ((array) $row as $key => $value)<td>@if (is_bool($value)){{ $value ? 'Sí' : 'No' }}@else{{ in_array($key, ['cantidad', 'stock_minimo', 'unidades'], true) ? \App\Support\Formatting\NumberFormat::format($value) : ($value ?? '—') }}@endif</td>@endforeach
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
            <x-record-pagination :paginator="$rows" />
        @endif
    </section>
    @endif
@endsection
