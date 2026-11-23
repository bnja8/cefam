@extends('layouts.app')
@section('content')
    <x-page-header title="Registrar rol" description="Define el nombre, la descripción y los permisos iniciales del rol.">
        <x-slot:actions><a class="btn btn-outline" href="{{ route('permissions.index') }}">Volver a Roles y permisos</a></x-slot:actions>
    </x-page-header>

    <section class="card">
        <div class="card-body">
            @php($selectedPermissions = (array) old('permissions', []))
            <form action="{{ route('roles.store') }}" method="post" data-permission-form data-permission-mode="create" data-role-name="">
                @csrf
                <div class="form-grid">
                    <div class="form-group">
                        <label for="role-name">Nombre del rol *</label>
                        <input class="form-control" id="role-name" name="nombre" value="{{ old('nombre') }}" maxlength="50" required>
                        @error('nombre')<small class="field-error">{{ $message }}</small>@enderror
                    </div>
                    <div class="form-group">
                        <label for="role-description">Descripción</label>
                        <input class="form-control" id="role-description" name="descripcion" value="{{ old('descripcion') }}" maxlength="255">
                        @error('descripcion')<small class="field-error">{{ $message }}</small>@enderror
                    </div>
                </div>

                <div class="permission-section-heading">
                    <h2>Permisos del rol</h2>
                    <p>Selecciona las vistas y acciones que este rol necesita para cumplir sus funciones.</p>
                </div>

                <section class="permission-guidance" aria-label="Recomendación de seguridad">
                    <span class="permission-guidance-icon"><i class="fa-solid fa-shield-halved" aria-hidden="true"></i></span>
                    <div>
                        <strong>Asigna solo los permisos necesarios</strong>
                        <p>Las casillas de acción permiten operaciones, no solo consultas. Revisa cada módulo antes de registrar el rol.</p>
                    </div>
                </div>

                <section class="permission-review" data-permission-review aria-live="polite">
                    <div class="permission-pending-changes">
                        <strong data-change-summary>No hay cambios pendientes.</strong>
                        <ul data-change-list hidden></ul>
                    </div>
                </section>

                @include('modules.roles.partials.permission-groups', [
                    'selectedPermissions' => $selectedPermissions,
                    'initialPermissions' => [],
                    'readOnly' => false,
                ])
                @error('permissions')<p class="field-error">{{ $message }}</p>@enderror
                @error('permissions.*')<p class="field-error">{{ $message }}</p>@enderror
                @include('modules.roles.partials.permission-confirmation')

                <div class="form-actions">
                    <button class="btn btn-primary permission-submit-button" type="submit"><i class="fa-solid fa-floppy-disk" aria-hidden="true"></i> Registrar rol</button>
                    <a class="btn btn-outline" href="{{ route('permissions.index') }}">Cancelar</a>
                </div>
            </form>
        </div>
    </section>
@endsection
