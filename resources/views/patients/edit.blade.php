@extends('layouts.app')
@section('content')
    <x-page-header title="Editar paciente" description="Actualiza los datos y el estado de la ficha del paciente." />
    <section class="card">
        <div class="card-body">
            <form action="{{ route('patients.update', $patient->rut) }}" method="post" class="form-grid">
                @csrf
                @method('put')
                <div class="form-group"><label for="rut">RUT *</label><input class="form-control" id="rut" name="rut" value="{{ old('rut', $patient->rut) }}" required maxlength="12" readonly data-rut-input aria-describedby="rut-error"><small id="rut-error" class="field-error" data-rut-error aria-live="polite"></small></div>
                <div class="form-group"><label for="nombre">Nombre *</label><input class="form-control" id="nombre" name="nombre" value="{{ old('nombre', $patient->nombre) }}" required maxlength="50"></div>
                <div class="form-group"><label for="apellidoPaterno">Apellido paterno *</label><input class="form-control" id="apellidoPaterno" name="apellidoPaterno" value="{{ old('apellidoPaterno', $patient->apellidoPaterno) }}" required maxlength="50"></div>
                <div class="form-group"><label for="apellidoMaterno">Apellido materno *</label><input class="form-control" id="apellidoMaterno" name="apellidoMaterno" value="{{ old('apellidoMaterno', $patient->apellidoMaterno) }}" required maxlength="50"></div>
                <div class="form-group"><label for="fecha_nacimiento">Fecha de nacimiento</label><input class="form-control" id="fecha_nacimiento" name="fecha_nacimiento" type="date" value="{{ old('fecha_nacimiento', $patient->fecha_nacimiento) }}"></div>
                <x-phone-input id="telefono" :value="$patient->telefono" />
                <label class="check-label"><input type="checkbox" name="activo" value="1" @checked(old('activo', $patient->activo))> Paciente activo</label>
                <div class="form-actions"><button class="btn btn-primary" type="submit">Guardar cambios</button><a class="btn btn-outline" href="{{ route('patients.file', $patient->rut) }}">Cancelar</a></div>
            </form>
        </div>
    </section>
@endsection
