@extends('layouts.app')
@section('content')
    <x-page-header :title="$screen === 'create' ? 'Registrar personal de salud' : 'Gestión de Personal de Salud'" description="Consulta y registra profesionales de salud del CESFAM">
        <x-slot:actions>
            @if ($screen === 'list' && $canRegister)
                <a class="btn btn-primary" href="{{ route('professionals.create') }}"><i class="fa-solid fa-user-plus"></i> Registrar personal</a>
            @endif
        </x-slot:actions>
    </x-page-header>

    @if ($screen === 'list')
    <section class="card" id="personal-registrado">
        <div class="card-header">
            <h2 class="card-title"><i class="fa-solid fa-user-doctor text-primary"></i> Personal registrado</h2>
        </div>
        <form action="{{ route('professionals.index') }}" method="get" class="patient-filter-grid">
            <div class="form-group">
                <label for="buscar-personal">Personal</label>
                <input class="form-control" id="buscar-personal" name="buscar" value="{{ request('buscar') }}" placeholder="Buscar por RUT o nombre">
            </div>
            <div class="form-group">
                <label for="tipo-personal">Tipo de profesional</label>
                <select class="form-control" id="tipo-personal" name="tipo_profesional">
                    <option value="">Todos los tipos</option>
                    @foreach ($types as $type)
                        <option value="{{ $type }}" @selected(request('tipo_profesional') === $type)>{{ $type }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-group">
                <label for="estado-personal">Estado</label>
                <select class="form-control" id="estado-personal" name="estado">
                    <option value="">Todos</option>
                    <option value="activo" @selected(request('estado') === 'activo')>Activo</option>
                    <option value="inactivo" @selected(request('estado') === 'inactivo')>Inactivo</option>
                </select>
            </div>
            <div class="patient-filter-actions">
                <button class="btn btn-primary" type="submit"><i class="fa-solid fa-filter"></i> Filtrar</button>
                <a class="btn btn-outline" href="{{ route('professionals.index') }}">Limpiar</a>
            </div>
        </form>
        <div class="person-card-grid searchable-cards">
            @forelse ($professionals as $professional)
                <x-person-card
                    :name="$professional->nombre_completo"
                    :subtitle="$professional->tipo_profesional"
                    :status="$professional->activo ? 'Activo' : 'Inactivo'"
                    :active="$professional->activo"
                >
                    <x-slot:details>
                        <div class="person-card-meta-row">
                            <i class="fa-regular fa-id-card" aria-hidden="true"></i>
                            <span>RUT <strong>{{ $professional->rut }}</strong></span>
                        </div>
                    </x-slot:details>
                    @if ($canManage)
                        <x-slot:actions>
                            <a class="btn btn-outline btn-small" href="{{ route('professionals.edit', $professional->rut) }}">Editar</a>
                            <form action="{{ route('professionals.toggle', $professional->rut) }}" method="post">
                                @csrf @method('patch')
                                <button class="btn btn-small {{ $professional->activo ? 'btn-outline' : 'btn-primary' }}" type="submit">{{ $professional->activo ? 'Desactivar' : 'Activar' }}</button>
                            </form>
                        </x-slot:actions>
                    @endif
                </x-person-card>
            @empty
                <div class="empty-panel" role="status">
                    <span class="empty-icon"><i class="fa-regular fa-folder-open"></i></span>
                    <h2>No se encontró personal</h2>
                    <p>Prueba con otros términos de búsqueda o filtros.</p>
                </div>
            @endforelse
        </div>
        <x-record-pagination :paginator="$professionals" />
    </section>
    @endif

    @if ($screen === 'create' && $canRegister)
    <section class="card" id="nuevo-personal">
        <div class="card-header"><h2 class="card-title"><i class="fa-solid fa-user-plus text-primary"></i> Registrar personal de salud</h2></div>
        <div class="card-body">
            <form action="{{ route('professionals.store') }}" method="post" class="form-grid" data-person-lookup data-person-lookup-url="{{ route('people.lookup') }}" data-person-type="personal_salud">
                @csrf
                <div class="form-group"><label for="rut-personal">RUT *</label><input class="form-control" id="rut-personal" name="rut" value="{{ old('rut') }}" required maxlength="12" placeholder="12.345.678-9" data-rut-input data-person-rut aria-describedby="rut-personal-error"><small id="rut-personal-error" class="field-error" data-rut-error aria-live="polite"></small><small data-person-lookup-status aria-live="polite"></small></div>
                <div class="form-group"><label for="nombre-personal">Nombre *</label><input class="form-control" id="nombre-personal" name="nombre" value="{{ old('nombre') }}" required maxlength="50"></div>
                <div class="form-group"><label for="apellido-paterno-personal">Apellido paterno *</label><input class="form-control" id="apellido-paterno-personal" name="apellidoPaterno" value="{{ old('apellidoPaterno') }}" required maxlength="50"></div>
                <div class="form-group"><label for="apellido-materno-personal">Apellido materno *</label><input class="form-control" id="apellido-materno-personal" name="apellidoMaterno" value="{{ old('apellidoMaterno') }}" required maxlength="50"></div>
                <div class="form-group">
                    <label for="tipo-profesional">Tipo de profesional *</label>
                    <select class="form-control" id="tipo-profesional" name="tipo_profesional" required>
                        <option value="">Selecciona un tipo de profesional</option>
                        @foreach ($professionalTypes as $type)
                            <option value="{{ $type }}" @selected(old('tipo_profesional') === $type)>{{ $type }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group"><label for="especialidad-personal">Especialidad</label><input class="form-control" id="especialidad-personal" name="especialidad" value="{{ old('especialidad') }}" maxlength="100"></div>
                <div class="form-actions"><button class="btn btn-primary" type="submit"><i class="fa-solid fa-floppy-disk"></i> Guardar personal</button></div>
            </form>
        </div>
    </section>
    @endif
@endsection
