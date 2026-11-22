@extends('layouts.app')
@section('content')
    <x-page-header title="Editar personal de salud" description="Actualiza los datos y el estado del profesional." />
    <section class="card">
        <div class="card-body">
            <form action="{{ route('professionals.update', $professional->rut) }}" method="post" class="form-grid">
                @csrf
                @method('put')
                <div class="form-group"><label for="rut-personal">RUT</label><input class="form-control" id="rut-personal" value="{{ $professional->rut }}" readonly></div>
                <div class="form-group"><label for="nombre">Nombre *</label><input class="form-control" id="nombre" name="nombre" value="{{ old('nombre', $professional->nombre) }}" required maxlength="50"></div>
                <div class="form-group"><label for="apellidoPaterno">Apellido paterno *</label><input class="form-control" id="apellidoPaterno" name="apellidoPaterno" value="{{ old('apellidoPaterno', $professional->apellidoPaterno) }}" required maxlength="50"></div>
                <div class="form-group"><label for="apellidoMaterno">Apellido materno *</label><input class="form-control" id="apellidoMaterno" name="apellidoMaterno" value="{{ old('apellidoMaterno', $professional->apellidoMaterno) }}" required maxlength="50"></div>
                <div class="form-group"><label for="tipo_profesional">Tipo de profesional *</label><input class="form-control" id="tipo_profesional" name="tipo_profesional" value="{{ old('tipo_profesional', $professional->tipo_profesional) }}" required maxlength="50"></div>
                <label class="check-label"><input type="checkbox" name="activo" value="1" @checked(old('activo', $professional->activo))> Personal activo</label>
                <div class="form-actions"><button class="btn btn-primary" type="submit">Guardar cambios</button><a class="btn btn-outline" href="{{ route('professionals.index') }}">Cancelar</a></div>
            </form>
        </div>
    </section>
@endsection
