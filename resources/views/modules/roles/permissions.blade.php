@extends('layouts.app')
@section('content')
    <x-page-header :title="'Permisos de '.$role->nombre" description="Revisa y modifica las vistas y acciones permitidas para este rol.">
        <x-slot:actions>
            <a class="btn btn-outline" href="{{ route('permissions.index') }}">Volver a Roles y permisos</a>
            <span class="badge {{ $role->activo ? 'badge-success' : 'badge-danger' }}">{{ $role->activo ? 'Activo' : 'Desactivado' }}</span>
        </x-slot:actions>
    </x-page-header>

    @if ($role->nombre === 'Administrador')
        <div class="notice notice-info" role="status">El rol Administrador conserva acceso completo y no se puede modificar ni desactivar.</div>
    @endif

    <section class="card">
        <div class="card-body">
            @php($selectedPermissions = (array) old('permissions', $roleGrants))
            <form action="{{ route('roles.permissions.update', $role->rol_id) }}" method="post"
                data-permission-form data-permission-mode="edit" data-role-name="{{ $role->nombre }}">
                @csrf
                @method('put')
                <section class="permission-guidance" aria-label="Recomendación de seguridad">
                    <span class="permission-guidance-icon"><i class="fa-solid fa-shield-halved" aria-hidden="true"></i></span>
                    <div>
                        <strong>Principio de mínimo privilegio</strong>
                        <p>Otorga solo los accesos que este rol necesita. Los cambios se aplican al guardar y se revisarán antes de confirmar.</p>
                    </div>
                </section>

                <section class="permission-review" data-permission-review aria-live="polite">
                    <div class="permission-pending-changes">
                        <strong data-change-summary>No hay cambios pendientes.</strong>
                        <ul data-change-list hidden></ul>
                    </div>
                </section>

                @include('modules.roles.partials.permission-groups', [
                    'selectedPermissions' => $selectedPermissions,
                    'initialPermissions' => $roleGrants,
                    'readOnly' => $role->nombre === 'Administrador',
                ])
                @error('permissions')<p class="field-error">{{ $message }}</p>@enderror
                @error('permissions.*')<p class="field-error">{{ $message }}</p>@enderror
                @include('modules.roles.partials.permission-confirmation')
                <div class="form-actions">
                    @if ($role->nombre !== 'Administrador')
                        <button class="btn btn-primary permission-submit-button" type="submit"><i class="fa-solid fa-floppy-disk" aria-hidden="true"></i> Guardar permisos</button>
                    @endif
                    <a class="btn btn-outline" href="{{ route('permissions.index') }}">Cancelar</a>
                </div>
            </form>
        </div>
    </section>
@endsection
