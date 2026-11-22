@extends('layouts.app')
@section('content')
    <x-page-header :title="$location ? 'Editar ubicación' : 'Agregar ubicación'" description="Define el nombre, tipo y descripción de la ubicación">
        <x-slot:actions>
            <a class="btn btn-outline" href="{{ route('locations.index') }}">Volver a ubicaciones</a>
        </x-slot:actions>
    </x-page-header>

    <section class="card">
        <div class="card-header">
            <h2 class="card-title"><i class="fa-solid fa-location-dot text-primary"></i> {{ $location ? 'Datos de la ubicación' : 'Nueva ubicación' }}</h2>
        </div>
        <div class="card-body">
            <form action="{{ $location ? route('locations.update', $location->ubicacion_id) : route('locations.store') }}" method="post" class="form-grid">
                @csrf
                @if ($location)
                    @method('put')
                @endif
                <div class="form-group">
                    <label for="nombre">Nombre *</label>
                    <input class="form-control" id="nombre" name="nombre" value="{{ old('nombre', $location->nombre ?? '') }}" maxlength="100" required>
                    @error('nombre')<small class="field-error">{{ $message }}</small>@enderror
                </div>
                <div class="form-group">
                    <label for="tipo">Tipo de ubicación *</label>
                    <select class="form-control" id="tipo" name="tipo" @disabled($typeLocked) required>
                        <option value="">Selecciona un tipo</option>
                        @foreach ($types as $value => $label)
                            <option value="{{ $value }}" @selected(($typeLocked ? $location->tipo : old('tipo', $location->tipo ?? '')) === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    @if ($typeLocked)
                        <input type="hidden" name="tipo" value="{{ $location->tipo }}">
                        <small>El tipo no se puede cambiar porque esta ubicación ya tiene existencias, movimientos o un carro asociado.</small>
                    @endif
                    @error('tipo')<small class="field-error">{{ $message }}</small>@enderror
                </div>
                <div class="form-group form-span">
                    <label for="descripcion">Descripción</label>
                    <textarea class="form-control" id="descripcion" name="descripcion" rows="3" maxlength="255">{{ old('descripcion', $location->descripcion ?? '') }}</textarea>
                    @error('descripcion')<small class="field-error">{{ $message }}</small>@enderror
                </div>
                <div class="form-actions">
                    <button class="btn btn-primary" type="submit"><i class="fa-solid fa-floppy-disk"></i> {{ $location ? 'Guardar cambios' : 'Agregar ubicación' }}</button>
                    <a class="btn btn-outline" href="{{ route('locations.index') }}">Cancelar</a>
                </div>
            </form>
        </div>
    </section>
@endsection
