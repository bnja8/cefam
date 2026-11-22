@extends('layouts.app')
@section('content')
    <x-page-header title="Ficha de paciente" description="Datos personales, recetas y dispensaciones asociadas al paciente.">
        <x-slot:actions>
            <a class="btn btn-outline" href="{{ route('patients.index') }}">Volver a pacientes</a>
            @if (auth()->user()->hasAnyRole(['Administrador']))
                <a class="btn btn-primary" href="{{ route('patients.edit', $patient->rut) }}">Editar paciente</a>
            @endif
        </x-slot:actions>
    </x-page-header>

    <section class="card">
        <div class="card-header">
            <h2 class="card-title"><i class="fa-solid fa-folder-open text-primary" aria-hidden="true"></i> {{ $patient->nombre_completo }}</h2>
            <span class="badge {{ $patient->activo ? 'badge-success' : 'badge-danger' }}">{{ $patient->activo ? 'Paciente activo' : 'Paciente inactivo' }}</span>
        </div>
        <div class="card-body">
            <div class="person-card-meta-row"><i class="fa-regular fa-id-card" aria-hidden="true"></i><span>RUT <strong>{{ $patient->rut }}</strong></span></div>
            <div class="person-card-meta-row"><i class="fa-solid fa-cake-candles" aria-hidden="true"></i><span>Fecha de nacimiento <strong>{{ $patient->fecha_nacimiento ? \Illuminate\Support\Carbon::parse($patient->fecha_nacimiento)->format('d/m/Y') : '—' }}</strong></span></div>
            <div class="person-card-meta-row"><i class="fa-solid fa-phone" aria-hidden="true"></i><span>Teléfono <strong>{{ $patient->telefono ?: '—' }}</strong></span></div>
        </div>
    </section>

    <section class="card" id="ficha-recetas">
        <div class="card-header">
            <h2 class="card-title"><i class="fa-solid fa-file-prescription text-primary" aria-hidden="true"></i> Recetas registradas</h2>
            <span class="muted">{{ number_format($prescriptions->count(), 0, ',', '.') }} registros históricos</span>
        </div>
        <div class="table-responsive">
            <table class="data-table">
                <thead><tr><th>Receta</th><th>Profesional</th><th>Fechas</th><th>Medicamentos</th><th>Estado</th><th>Ficha</th><th>Acciones</th></tr></thead>
                <tbody>
                    @forelse ($prescriptions as $prescription)
                        <tr>
                            <td><strong>{{ $prescription->numero_receta }}</strong><br><small>{{ $prescription->tipo_receta }}</small></td>
                            <td>{{ $prescription->profesional }}</td>
                            <td>Emitida {{ \Illuminate\Support\Carbon::parse($prescription->fecha_emision)->format('d/m/Y') }}<br><small>Vence {{ \Illuminate\Support\Carbon::parse($prescription->fecha_vencimiento)->format('d/m/Y') }}</small></td>
                            <td>
                                @forelse ($prescription->detalles as $detail)
                                    <div>{{ $detail->producto }} · {{ \App\Support\Formatting\NumberFormat::format($detail->cantidad_indicada) }}</div>
                                @empty
                                    —
                                @endforelse
                            </td>
                            <td><span class="badge {{ $prescription->estado === 'VIGENTE' ? 'badge-success' : ($prescription->estado === 'UTILIZADA' ? 'badge-info' : 'badge-warning') }}">{{ $prescription->estado }}</span></td>
                            <td><span class="badge {{ $prescription->estado_ficha === 'ARCHIVADA' ? 'badge-warning' : 'badge-info' }}">{{ $prescription->estado_ficha }}</span></td>
                            <td><button class="btn btn-outline btn-small" type="button" data-print-prescription-url="{{ route('recipes.print', $prescription->receta_id) }}">Ver receta</button></td>
                        </tr>
                    @empty
                        <tr><td colspan="7">No hay recetas registradas para este paciente.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <section class="card" id="ficha-dispensaciones">
        <div class="card-header">
            <h2 class="card-title"><i class="fa-solid fa-prescription-bottle-medical text-primary" aria-hidden="true"></i> Dispensaciones registradas</h2>
            <span class="muted">{{ number_format($deliveries->count(), 0, ',', '.') }} registros históricos</span>
        </div>
        <div class="table-responsive">
            <table class="data-table">
                <thead><tr><th>Fecha</th><th>Medicamentos entregados</th><th>Retira</th><th>Responsable</th><th>Estado</th><th>Ficha</th><th>Acciones</th></tr></thead>
                <tbody>
                    @forelse ($deliveries as $delivery)
                        <tr>
                            <td>{{ \Illuminate\Support\Carbon::parse($delivery->fecha_hora)->format('d/m/Y H:i') }}<br><small>{{ $delivery->numero_orden ?: 'Sin orden' }}</small></td>
                            <td>
                                @forelse ($delivery->detalles as $detail)
                                    <div>{{ $detail->producto }} · {{ \App\Support\Formatting\NumberFormat::format($detail->cantidad) }}</div>
                                @empty
                                    —
                                @endforelse
                            </td>
                            <td>{{ $delivery->persona_autorizada ?: 'Paciente titular' }}</td>
                            <td>{{ $delivery->responsable }}</td>
                            <td><span class="badge {{ $delivery->estado === 'CONFIRMADA' ? 'badge-success' : 'badge-warning' }}">{{ $delivery->estado }}</span></td>
                            <td><span class="badge {{ $delivery->estado_ficha === 'ARCHIVADA' ? 'badge-warning' : 'badge-info' }}">{{ $delivery->estado_ficha }}</span></td>
                            <td>
                                @if ($delivery->orden_entrega_id)
                                    <button class="btn btn-outline btn-small" type="button" data-print-order-url="{{ route('delivery-orders.receipt', $delivery->orden_entrega_id) }}">Ver comprobante</button>
                                @else
                                    —
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7">No hay dispensaciones registradas para este paciente.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
    <p class="muted" data-print-status aria-live="polite" hidden></p>
@endsection
