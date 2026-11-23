@extends('layouts.app')
@section('content')
    <x-page-header title="Roles y permisos" description="Consulta los roles, revisa sus permisos y administra su estado.">
        <x-slot:actions>
            <a class="btn btn-outline" href="{{ route('users.index') }}">Volver a Administración</a>
            <a class="btn btn-primary" href="{{ route('roles.create') }}"><i class="fa-solid fa-user-shield" aria-hidden="true"></i> Registrar rol</a>
        </x-slot:actions>
    </x-page-header>

    <section class="card" id="roles-registrados">
        <div class="card-header">
            <h2 class="card-title"><i class="fa-solid fa-shield-halved text-primary" aria-hidden="true"></i> Roles registrados</h2>
        </div>
        <div class="person-card-grid">
            @forelse ($roles as $role)
                <x-person-card
                    :name="$role->nombre"
                    :subtitle="$role->descripcion ?: 'Sin descripción'"
                    :status="$role->activo ? 'Activo' : 'Desactivado'"
                    :active="$role->activo"
                >
                    <x-slot:details>
                        <div class="person-card-meta-row">
                            <i class="fa-solid fa-users" aria-hidden="true"></i>
                            <span>Cuentas asignadas <strong>{{ number_format($role->assigned_users, 0, ',', '.') }}</strong></span>
                        </div>
                        @if ($role->nombre === 'Administrador')
                            <span class="badge badge-success">Rol protegido</span>
                        @endif
                    </x-slot:details>
                    <x-slot:actions>
                        <a class="btn btn-outline btn-small" href="{{ route('roles.permissions.show', $role->rol_id) }}">
                            <i class="fa-solid fa-key" aria-hidden="true"></i> Ver permisos
                        </a>
                        @if ($role->nombre !== 'Administrador')
                            <form action="{{ route('roles.toggle', $role->rol_id) }}" method="post">
                                @csrf
                                @method('patch')
                                <button class="btn btn-small {{ $role->activo ? 'btn-outline' : 'btn-primary' }}" type="submit">
                                    {{ $role->activo ? 'Desactivar' : 'Activar' }}
                                </button>
                            </form>
                        @endif
                    </x-slot:actions>
                </x-person-card>
            @empty
                <div class="empty-panel" role="status">
                    <span class="empty-icon"><i class="fa-regular fa-folder-open" aria-hidden="true"></i></span>
                    <h2>No hay roles registrados</h2>
                    <p>Registra un rol para configurar sus permisos.</p>
                </div>
            @endforelse
        </div>
    </section>
@endsection
