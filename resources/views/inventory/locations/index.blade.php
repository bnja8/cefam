@extends('layouts.app')
@section('content')
    <x-page-header title="Ubicaciones de inventario" description="Consulta y administra las bodegas, vitrinas y carros del sistema">
        <x-slot:actions>
            <a class="btn btn-outline" href="{{ route('inventory.index') }}">Volver a inventario</a>
            @if ($canManageLocations)<a class="btn btn-primary" href="{{ route('locations.create') }}"><i class="fa-solid fa-plus"></i> Agregar ubicación</a>@endif
        </x-slot:actions>
    </x-page-header>

    @if (session('status'))
        <div class="notice notice-success" role="status">{{ session('status') }}</div>
    @endif

    <section class="card">
        <div class="card-header">
            <h2 class="card-title"><i class="fa-solid fa-location-dot text-primary"></i> Ubicaciones registradas</h2>
            <span class="muted">{{ number_format($locations->total(), 0, ',', '.') }} {{ $locations->total() === 1 ? 'ubicación' : 'ubicaciones' }}</span>
        </div>
        <div class="table-responsive">
            <table class="data-table">
                <thead><tr><th>UBICACIÓN</th><th>TIPO</th><th>DESCRIPCIÓN</th>@if ($canManageLocations)<th>ACCIÓN</th>@endif</tr></thead>
                <tbody>
                    @forelse ($locations as $location)
                        <tr>
                            <td><strong>{{ $location->nombre }}</strong></td>
                            <td>{{ $types[$location->tipo] ?? $location->tipo }}</td>
                            <td>{{ $location->descripcion ?: '—' }}</td>
                            @if ($canManageLocations)<td><a class="btn btn-outline btn-small" href="{{ route('locations.edit', $location->ubicacion_id) }}">Editar</a></td>@endif
                        </tr>
                    @empty
                        <tr><td colspan="{{ $canManageLocations ? 4 : 3 }}" class="empty-state">No hay ubicaciones registradas.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <x-record-pagination :paginator="$locations" />
    </section>
@endsection
