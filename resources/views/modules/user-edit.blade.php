@extends('layouts.app')
@section('content')
    <x-page-header title="Editar usuario" description="Modifica los datos de acceso y el rol asignado al usuario." />
    <section class="card">
        <div class="card-body">
            <form action="{{ route('users.update', $user->rut) }}" method="post" class="form-grid">
                @csrf
                @method('put')
                <div class="form-group"><label for="rut">RUT (no modificable) *</label><input class="form-control" id="rut" name="rut" value="{{ old('rut', $user->rut) }}" required maxlength="12" readonly></div>
                <div class="form-group"><label for="nombre">Nombre *</label><input class="form-control" id="nombre" name="nombre" value="{{ old('nombre', $user->nombre) }}" required maxlength="50"></div>
                <div class="form-group"><label for="apellidoPaterno">Apellido paterno *</label><input class="form-control" id="apellidoPaterno" name="apellidoPaterno" value="{{ old('apellidoPaterno', $user->apellidoPaterno) }}" required maxlength="50"></div>
                <div class="form-group"><label for="apellidoMaterno">Apellido materno *</label><input class="form-control" id="apellidoMaterno" name="apellidoMaterno" value="{{ old('apellidoMaterno', $user->apellidoMaterno) }}" required maxlength="50"></div>
                <x-phone-input id="telefono" :value="$user->telefono" />
                @if ($user->rut !== auth()->id())
                    <div class="form-group"><label for="rol_id">Rol *</label><select class="form-control" id="rol_id" name="rol_id" required>@foreach ($roles as $role)<option value="{{ $role->rol_id }}" @selected(old('rol_id', $user->rol_id) == $role->rol_id)>{{ $role->nombre }}{{ ! $role->activo ? ' (desactivado)' : '' }}</option>@endforeach</select></div>
                @else
                    <div class="form-group"><label>Rol</label><p class="form-control">{{ $roles->firstWhere('rol_id', $user->rol_id)?->nombre ?? 'Administrador' }} (no modificable en la propia cuenta)</p></div>
                @endif
                <div class="form-group"><label for="personal_rut">Personal de salud vinculado (opcional)</label><select class="form-control" id="personal_rut" name="personal_rut"><option value="">Sin vínculo</option>@foreach ($personal as $member)<option value="{{ $member->rut }}" @selected(old('personal_rut', $user->personal_rut) === $member->rut)>{{ $member->nombre_completo }} - {{ $member->rut }}</option>@endforeach</select></div>
                <div class="form-group form-span"><label for="password">Nueva contraseña (opcional, mínimo 12 caracteres)</label><input class="form-control" id="password" type="password" name="password" minlength="12" autocomplete="new-password"></div>
                <div class="form-actions"><button class="btn btn-primary" type="submit">Guardar cambios</button><a class="btn btn-outline" href="{{ route('users.index') }}">Cancelar</a></div>
            </form>
        </div>
    </section>
@endsection
