@extends('layouts.app')
@section('content')
    <x-page-header :title="'Solicitud de ajuste #'.$adjustmentRequest->grupo_ajuste_id" description="Detalle de los medicamentos, cantidades y resolución de la solicitud">
        <x-slot:actions>
            <a class="btn btn-outline" href="{{ route('inventory.adjustments.index') }}#solicitudes-ajuste">
                <i class="fa-solid fa-arrow-left"></i> Volver a solicitudes
            </a>
        </x-slot:actions>
    </x-page-header>

    <section class="card">
        <div class="card-header">
            <h2 class="card-title"><i class="fa-solid fa-circle-info text-primary"></i> Datos de la solicitud</h2>
            <span class="badge {{ $adjustmentRequest->estado === 'APROBADA' ? 'badge-success' : ($adjustmentRequest->estado === 'RECHAZADA' ? 'badge-danger' : 'badge-warning') }}">
                {{ ucfirst(strtolower($adjustmentRequest->estado)) }}
            </span>
        </div>
        <div class="card-body">
            <p><strong>Solicitante:</strong> {{ $adjustmentRequest->solicitante }}</p>
            <p><strong>Fecha de solicitud:</strong> {{ \Illuminate\Support\Carbon::parse($adjustmentRequest->fecha_solicitud)->format('d/m/Y H:i') }}</p>
            @if ($adjustmentRequest->estado !== 'PENDIENTE')
                <p><strong>Resuelta por:</strong> {{ $adjustmentRequest->autorizador ?: 'No registrado' }}</p>
                @if ($adjustmentRequest->fecha_resolucion)
                    <p><strong>Fecha de resolución:</strong> {{ \Illuminate\Support\Carbon::parse($adjustmentRequest->fecha_resolucion)->format('d/m/Y H:i') }}</p>
                @endif
                @if ($adjustmentRequest->motivo_resolucion)
                    <p><strong>Motivo de resolución:</strong> {{ $adjustmentRequest->motivo_resolucion }}</p>
                @endif
            @endif
        </div>
    </section>

    <section class="card">
        <div class="card-header">
            <h2 class="card-title"><i class="fa-solid fa-capsules text-primary"></i> Medicamentos solicitados</h2>
            <span class="badge badge-info">{{ $adjustmentDetails->count() }} líneas</span>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>MEDICAMENTO</th>
                            <th>LOTE / VENCIMIENTO</th>
                            <th>CANTIDAD</th>
                            <th>UBICACIÓN</th>
                            <th>TIPO DE AJUSTE</th>
                            <th>MOTIVO</th>
                            @if ($adjustmentRequest->estado === 'APROBADA')<th>MOVIMIENTO</th>@endif
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($adjustmentDetails as $detail)
                            <tr>
                                <td><strong>{{ $detail->producto }}</strong></td>
                                <td>
                                    {{ $detail->numero_lote }}
                                    <small class="table-subtitle">Vence {{ \Illuminate\Support\Carbon::parse($detail->fecha_vencimiento)->format('d/m/Y') }}</small>
                                </td>
                                <td>{{ \App\Support\Formatting\NumberFormat::format($detail->cantidad) }}</td>
                                <td>{{ $detail->ubicacion }}</td>
                                <td>{{ $detail->tipo_ajuste === 'ENTRADA' ? 'Entrada' : 'Salida' }}</td>
                                <td>{{ $detail->motivo }}</td>
                                @if ($adjustmentRequest->estado === 'APROBADA')
                                    <td>{{ $detail->movimiento_id ? '#'.$detail->movimiento_id : '—' }}</td>
                                @endif
                            </tr>
                        @empty
                            <tr><td colspan="{{ $adjustmentRequest->estado === 'APROBADA' ? 7 : 6 }}" class="empty-state">La solicitud no tiene líneas de medicamento.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </section>

    @if ($adjustmentRequest->estado === 'PENDIENTE')
        <section class="card">
            <div class="card-header">
                <h2 class="card-title"><i class="fa-solid fa-user-check text-primary"></i> Resolver solicitud</h2>
            </div>
            <div class="card-body">
                @if ($canResolveAdjustment && $adjustmentDetails->isNotEmpty())
                    <div class="notice notice-info">
                        <i class="fa-solid fa-circle-info"></i>
                        <span>La decisión se aplicará a todas las líneas de esta solicitud. Aprobar actualizará las existencias; rechazar requiere indicar el motivo.</span>
                    </div>
                    <form action="{{ route('inventory.adjustments.resolve', $adjustmentDetails->first()->solicitud_ajuste_id) }}" method="post" class="inline-form">
                        @csrf @method('PATCH')
                        <input type="hidden" name="decision" value="APROBAR">
                        <button class="btn btn-primary" type="submit"><i class="fa-solid fa-check"></i> Aprobar solicitud</button>
                    </form>
                    <form action="{{ route('inventory.adjustments.resolve', $adjustmentDetails->first()->solicitud_ajuste_id) }}" method="post" class="stack-form">
                        @csrf @method('PATCH')
                        <input type="hidden" name="decision" value="RECHAZAR">
                        <label for="motivo_resolucion">Motivo del rechazo <span class="required-mark">*</span></label>
                        <textarea id="motivo_resolucion" class="form-control" name="motivo_resolucion" rows="3" maxlength="1000" required>{{ old('motivo_resolucion') }}</textarea>
                        @error('motivo_resolucion')<span class="field-error">{{ $message }}</span>@enderror
                        <div class="form-actions">
                            <button class="btn btn-outline" type="submit"><i class="fa-solid fa-xmark"></i> Rechazar solicitud</button>
                        </div>
                    </form>
                @elseif ($selfAuthorizationBlocked)
                    <div class="notice notice-warning">
                        <i class="fa-solid fa-user-shield"></i>
                        <span>Una solicitud realizada por un QF debe ser resuelta por otro QF.</span>
                    </div>
                @else
                    <p class="muted">No tienes permiso para resolver esta solicitud.</p>
                @endif
            </div>
        </section>
    @endif
@endsection
