@extends('layouts.app')
@section('content')
    <x-page-header :title="$screen === 'create' ? 'Registrar persona autorizada' : 'Personas autorizadas'" description="Personas habilitadas para retirar medicamentos por un paciente">
        <x-slot:actions>
            @if ($screen === 'list' && $canRegister)
                <a class="btn btn-primary" href="{{ route('authorized-people.create') }}"><i class="fa-solid fa-user-plus"></i> Registrar persona autorizada</a>
            @endif
        </x-slot:actions>
    </x-page-header>

    @if ($screen === 'list')
    <section class="card" id="personas-autorizadas">
        <div class="card-header"><h2 class="card-title"><i class="fa-solid fa-users-gear text-primary"></i> Consultar personas autorizadas</h2></span></div>
        <div class="card-body">
            <form action="{{ route('authorized-people.index') }}" method="get" class="form-grid">
                <div class="form-group"><label for="buscar-autorizacion">Buscar autorización</label><input class="form-control" id="buscar-autorizacion" name="buscar" value="{{ $filters['buscar'] ?? '' }}" placeholder="Nombre, RUT, vínculo o paciente"></div>
                <x-patient-search id="paciente-autorizacion" name="paciente_rut" label="Paciente" :selected-patient="$selectedPatientFilter" placeholder="Todos los pacientes" help-text="Deja vacío para incluir todos los pacientes." />
                <div class="form-group"><label for="estado-autorizacion">Estado</label><select class="form-control" id="estado-autorizacion" name="estado"><option value="" @selected(($filters['estado'] ?? null) === null)>Todos</option><option value="activo" @selected(($filters['estado'] ?? null) === 'activo')>Activa</option><option value="inactivo" @selected(($filters['estado'] ?? null) === 'inactivo')>Revocada</option></select></div>
                <div class="form-actions"><button class="btn btn-primary" type="submit"><i class="fa-solid fa-magnifying-glass"></i> Buscar</button><a class="btn btn-outline" href="{{ route('authorized-people.index') }}">Limpiar</a></div>
            </form>
        </div>
        <div class="person-card-grid searchable-cards">
            @forelse ($authorizedPeople as $authorizedPerson)
                <x-person-card
                    :name="$authorizedPerson->nombre_completo"
                    :subtitle="'Vínculo: '.$authorizedPerson->vinculo"
                    :status="$authorizedPerson->activo ? 'Activo' : 'Inactivo'"
                    :active="$authorizedPerson->activo"
                >
                    <x-slot:details>
                        <div class="person-card-meta-row">
                            <i class="fa-regular fa-id-card" aria-hidden="true"></i>
                            <span>RUT <strong>{{ $authorizedPerson->rut }}</strong></span>
                        </div>
                        <div class="person-card-meta-row">
                            <i class="fa-solid fa-user" aria-hidden="true"></i>
                            <span>Paciente representado <strong>{{ $authorizedPerson->paciente }}</strong> <span class="muted">({{ $authorizedPerson->paciente_rut }})</span></span>
                        </div>
                    </x-slot:details>
                    <x-slot:actions>
                        @if (auth()->user()->hasAnyRole(['Administrador']))
                            <a class="btn btn-outline btn-small" href="{{ route('authorized-people.edit', $authorizedPerson->persona_autorizada_id) }}">Editar</a>
                            <form action="{{ route('authorized-people.toggle', $authorizedPerson->persona_autorizada_id) }}" method="post">
                                @csrf @method('patch')
                                <button class="btn btn-outline btn-small" type="submit">{{ $authorizedPerson->activo ? 'Desactivar' : 'Activar' }}</button>
                            </form>
                        @else
                            <span class="person-card-unavailable">{{ $authorizedPerson->activo ? 'Vigente' : 'Revocada' }}</span>
                        @endif
                    </x-slot:actions>
                </x-person-card>
            @empty
                <div class="empty-panel" role="status">
                    <span class="empty-icon"><i class="fa-regular fa-folder-open"></i></span>
                    <h2>No se encontraron autorizaciones</h2>
                    <p>Prueba con otros términos de búsqueda o filtros.</p>
                </div>
            @endforelse
        </div>
        <x-record-pagination :paginator="$authorizedPeople" />
    </section>
    @endif

    @if ($screen === 'create' && $canRegister)
    <section class="card" id="nueva-persona-autorizada">
        <div class="card-header"><h2 class="card-title"><i class="fa-solid fa-user-plus text-primary"></i> Registrar persona autorizada</h2></div>
        <div class="card-body">
            @if ($activePatientCount === 0)
                <div class="notice notice-warning"><i class="fa-solid fa-circle-exclamation"></i><span>Debes registrar un paciente antes de asociar una persona autorizada.</span></div>
            @else
                <form action="{{ route('authorized-people.store') }}" method="post" class="form-grid" data-person-lookup data-person-lookup-url="{{ route('people.lookup') }}" data-person-type="personas_autorizadas">
                    @csrf
                    <x-patient-search id="persona_autorizada-paciente" :selected-patient="$selectedPatient" required class="form-span" />
                    <div class="form-group"><label for="persona-autorizada-rut">RUT de la persona autorizada *</label><input class="form-control" id="persona-autorizada-rut" name="rut" value="{{ old('rut') }}" required maxlength="12" placeholder="12.345.678-9" data-rut-input data-person-rut aria-describedby="persona-autorizada-rut-error"><small id="persona-autorizada-rut-error" class="field-error" data-rut-error aria-live="polite"></small><small data-person-lookup-status aria-live="polite"></small></div>
                    <div class="form-group"><label for="persona-autorizada-nombre">Nombre *</label><input class="form-control" id="persona-autorizada-nombre" name="nombre" value="{{ old('nombre') }}" required maxlength="50"></div>
                    <div class="form-group"><label for="persona-autorizada-apellido-paterno">Apellido paterno *</label><input class="form-control" id="persona-autorizada-apellido-paterno" name="apellidoPaterno" value="{{ old('apellidoPaterno') }}" required maxlength="50"></div>
                    <div class="form-group"><label for="persona-autorizada-apellido-materno">Apellido materno *</label><input class="form-control" id="persona-autorizada-apellido-materno" name="apellidoMaterno" value="{{ old('apellidoMaterno') }}" required maxlength="50"></div>
                    <div class="form-group"><label for="persona-autorizada-vinculo">Vínculo con el paciente *</label><input class="form-control" id="persona-autorizada-vinculo" name="vinculo" value="{{ old('vinculo') }}" required maxlength="100" placeholder="Hija, cuidador/a, apoderado"></div>
                    <div class="form-actions"><button class="btn btn-primary" type="submit"><i class="fa-solid fa-floppy-disk"></i> Guardar autorización</button></div>
                </form>
            @endif
        </div>
    </section>
    @endif
@endsection
