@extends('layouts.app')
@section('content')
    <x-page-header title="Editar persona autorizada" description="Actualiza la persona, el paciente representado y el estado de la autorización." />
    <section class="card">
        <div class="card-body">
            <form action="{{ route('authorized-people.update', $authorizedPerson->persona_autorizada_id) }}" method="post" class="form-grid" data-person-lookup data-person-lookup-url="{{ route('people.lookup') }}" data-person-type="personas_autorizadas">
                @csrf
                @method('put')
                <x-patient-search id="paciente-rut" :selected-patient="$selectedPatient" required class="form-span" />
                <div class="form-group"><label for="rut">RUT *</label><input class="form-control" id="rut" name="rut" value="{{ old('rut', $authorizedPerson->rut) }}" required maxlength="12" data-rut-input data-person-rut aria-describedby="rut-error"><small id="rut-error" class="field-error" data-rut-error aria-live="polite"></small><small data-person-lookup-status aria-live="polite"></small></div>
                <div class="form-group"><label for="nombre">Nombre *</label><input class="form-control" id="nombre" name="nombre" value="{{ old('nombre', $authorizedPerson->nombre) }}" required maxlength="50"></div>
                <div class="form-group"><label for="apellidoPaterno">Apellido paterno *</label><input class="form-control" id="apellidoPaterno" name="apellidoPaterno" value="{{ old('apellidoPaterno', $authorizedPerson->apellidoPaterno) }}" required maxlength="50"></div>
                <div class="form-group"><label for="apellidoMaterno">Apellido materno *</label><input class="form-control" id="apellidoMaterno" name="apellidoMaterno" value="{{ old('apellidoMaterno', $authorizedPerson->apellidoMaterno) }}" required maxlength="50"></div>
                <div class="form-group"><label for="vinculo">Vínculo con el paciente *</label><input class="form-control" id="vinculo" name="vinculo" value="{{ old('vinculo', $authorizedPerson->vinculo) }}" required maxlength="100"></div>
                <label class="check-label"><input type="checkbox" name="activo" value="1" @checked(old('activo', $authorizedPerson->activo))> Autorización activa</label>
                <div class="form-actions"><button class="btn btn-primary" type="submit">Guardar cambios</button><a class="btn btn-outline" href="{{ route('authorized-people.index') }}">Cancelar</a></div>
            </form>
        </div>
    </section>
@endsection
