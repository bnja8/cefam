@extends('layouts.app')
@section('content')
    <x-page-header :title="$screen === 'create' ? 'Registrar paciente' : 'Gestión de Pacientes'" description="Registro de beneficiarios CESFAM y programas inscritos">
        <x-slot:actions>
            @if ($screen === 'list' && auth()->user()->hasAnyRole(['Administrador']))
                <a class="btn btn-primary" href="{{ route('patients.create') }}"><i class="fa-solid fa-user-plus"></i> Registrar paciente</a>
            @endif
        </x-slot:actions>
    </x-page-header>

    @if ($screen === 'list')
    <section class="card" id="pacientes-registrados">
        <div class="card-header">
            <h2 class="card-title"><i class="fa-solid fa-hospital-user text-primary"></i> Pacientes registrados</h2>
        </div>
        <form action="{{ route('patients.index') }}" method="get" class="patient-filter-grid">
            <div class="form-group">
                <label for="buscar-paciente">Paciente</label>
                <input class="form-control" id="buscar-paciente" name="buscar" value="{{ request('buscar') }}" placeholder="Buscar por RUT o nombre">
            </div>
            <div class="form-group">
                <label for="programa-paciente">Programa activo</label>
                <select class="form-control" id="programa-paciente" name="programa_id">
                    <option value="">Todos los programas activos</option>
                    @foreach ($activePrograms as $program)
                        <option value="{{ $program->programa_id }}" @selected((string) request('programa_id') === (string) $program->programa_id)>{{ $program->nombre }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-group">
                <label for="estado-paciente">Estado del paciente</label>
                <select class="form-control" id="estado-paciente" name="estado">
                    <option value="todos" @selected(request('estado', 'todos') === 'todos')>Todos</option>
                    <option value="activo" @selected(request('estado') === 'activo')>Activo</option>
                    <option value="inactivo" @selected(request('estado') === 'inactivo')>Inactivo</option>
                </select>
            </div>
            <div class="form-group">
                <label for="programa-estado-paciente">Afiliación vigente</label>
                <select class="form-control" id="programa-estado-paciente" name="programa_estado">
                    <option value="todos" @selected(request('programa_estado', 'todos') === 'todos')>Todos</option>
                    <option value="con_programa" @selected(request('programa_estado') === 'con_programa')>Con programa activo</option>
                    <option value="sin_programa" @selected(request('programa_estado') === 'sin_programa')>Sin programa activo</option>
                </select>
            </div>
            <div class="patient-filter-actions">
                <button class="btn btn-primary" type="submit"><i class="fa-solid fa-filter"></i> Filtrar</button>
                <a class="btn btn-outline" href="{{ route('patients.index') }}">Limpiar</a>
            </div>
        </form>
        <div class="person-card-grid searchable-cards">
            @forelse ($patients as $patient)
                <x-person-card
                    :name="$patient->nombre_completo"
                    :subtitle="'RUT '.$patient->rut"
                    :status="$patient->activo ? 'Activo' : 'Inactivo'"
                    :active="$patient->activo"
                >
                    <x-slot:details>
                        <div class="person-card-meta-row">
                            <i class="fa-solid fa-cake-candles" aria-hidden="true"></i>
                            <span>Edad <strong>{{ $patient->fecha_nacimiento ? \Illuminate\Support\Carbon::parse($patient->fecha_nacimiento)->age.' años' : '—' }}</strong></span>
                        </div>
                        <div class="person-card-programs">
                            @forelse ($patient->programas as $program)
                                <span class="badge badge-info">{{ $program->nombre }}</span>
                            @empty
                                <span class="muted">Sin programa activo</span>
                            @endforelse
                        </div>
                        <div class="person-card-authorized-people">
                            <strong class="person-card-authorized-heading">Personas autorizadas</strong>
                            @forelse ($patient->personas_autorizadas as $authorizedPerson)
                                <div class="person-card-authorized-person">
                                    <span class="person-card-authorized-person-copy">
                                        <strong>{{ $authorizedPerson->nombre_completo }}</strong>
                                        <small>RUT {{ $authorizedPerson->rut }} · {{ $authorizedPerson->vinculo }}</small>
                                    </span>
                                    <span class="badge {{ $authorizedPerson->activo ? 'badge-success' : 'badge-danger' }}">{{ $authorizedPerson->activo ? 'Activo' : 'Inactivo' }}</span>
                                </div>
                            @empty
                                <span class="muted">Sin personas autorizadas</span>
                            @endforelse
                        </div>
                    </x-slot:details>
                    <x-slot:actions>
                        <a class="btn btn-outline btn-small" href="{{ route('patients.file', $patient->rut) }}">Ficha de Paciente</a>
                        @if ($patient->activo)
                            <a class="btn btn-primary btn-small" href="{{ route('dispensations.index', ['paciente_rut' => $patient->rut]) }}"><i class="fa-solid fa-pills" aria-hidden="true"></i> Dispensar</a>
                        @else
                            <span class="person-card-unavailable">No se puede dispensar</span>
                        @endif
                    </x-slot:actions>
                </x-person-card>
            @empty
                <div class="empty-panel" role="status">
                    <span class="empty-icon"><i class="fa-regular fa-folder-open"></i></span>
                    <h2>No se encontraron pacientes</h2>
                    <p>Prueba con otros términos de búsqueda o filtros.</p>
                </div>
            @endforelse
        </div>
        <x-record-pagination :paginator="$patients" />
    </section>
    @endif

    @if ($screen === 'create' && auth()->user()->hasAnyRole(['Administrador']))
    <section class="card" id="nuevo-paciente">
        <div class="card-header"><h2 class="card-title"><i class="fa-solid fa-user-plus text-primary"></i> Registrar paciente</h2></div>
        <div class="card-body">
            <form action="{{ route('patients.store') }}" method="post" class="form-grid" data-person-lookup data-person-lookup-url="{{ route('people.lookup') }}" data-person-type="pacientes">
                @csrf
                <div class="form-group"><label for="rut">RUT *</label><input class="form-control" id="rut" name="rut" value="{{ old('rut') }}" required maxlength="12" placeholder="12.345.678-9" data-rut-input data-person-rut aria-describedby="rut-error"><small id="rut-error" class="field-error" data-rut-error aria-live="polite"></small><small data-person-lookup-status aria-live="polite"></small></div>
                <div class="form-group"><label for="nombre">Nombre *</label><input class="form-control" id="nombre" name="nombre" value="{{ old('nombre') }}" required maxlength="50"></div>
                <div class="form-group"><label for="apellidoPaterno">Apellido paterno *</label><input class="form-control" id="apellidoPaterno" name="apellidoPaterno" value="{{ old('apellidoPaterno') }}" required maxlength="50"></div>
                <div class="form-group"><label for="apellidoMaterno">Apellido materno</label><input class="form-control" id="apellidoMaterno" name="apellidoMaterno" value="{{ old('apellidoMaterno') }}" maxlength="50"></div>
                <div class="form-group"><label for="fecha_nacimiento">Fecha de nacimiento *</label><input class="form-control" id="fecha_nacimiento" name="fecha_nacimiento" type="date" value="{{ old('fecha_nacimiento') }}" required></div>
                <x-phone-input id="telefono" />
                <div class="form-actions"><button class="btn btn-primary" type="submit"><i class="fa-solid fa-floppy-disk"></i> Guardar paciente</button></div>
            </form>
        </div>
    </section>
    @endif
@endsection
