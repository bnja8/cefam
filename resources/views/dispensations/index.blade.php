@extends('layouts.app')
@section('content')
    <x-page-header :title="$screen === 'create' ? 'Dispensaciones' : 'Entregas recientes'" description="Registro y consulta de dispensaciones con verificación de la receta y selección FEFO">
        <x-slot:actions>@if ($screen === 'create')<a class="btn btn-outline" href="{{ route('dispensations.deliveries') }}">Consultar entregas</a><span class="badge badge-info"><i class="fa-solid fa-shield-halved"></i> Operación auditada</span>@else<a class="btn btn-primary" href="{{ route('dispensations.index') }}">Registrar dispensación</a>@endif</x-slot:actions>
    </x-page-header>

    @include('dispensations.partials.create')

    @include('dispensations.partials.deliveries')

@endsection
