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
                        <span>La decisión se aplicará a todas las líneas de esta solicitud. Revisa los medicamentos y cantidades antes de confirmar.</span>
                    </div>
                    <div class="adjustment-resolution-actions">
                        <button class="btn btn-primary" type="button" data-open-adjustment-confirmation="APROBAR">
                            <i class="fa-solid fa-check"></i> Aprobar solicitud
                        </button>
                        <button class="btn btn-outline" type="button" data-open-adjustment-confirmation="RECHAZAR">
                            <i class="fa-solid fa-xmark"></i> Rechazar solicitud
                        </button>
                    </div>

                    <dialog
                        class="delivery-order-dialog inventory-adjustment-confirmation"
                        data-adjustment-confirmation
                        data-open-on-load="{{ $errors->any() ? 'true' : 'false' }}"
                        data-initial-decision="{{ old('decision', 'APROBAR') }}"
                        aria-labelledby="adjustment-confirmation-title"
                    >
                        <header class="delivery-order-dialog-header">
                            <span class="delivery-order-dialog-icon" aria-hidden="true"><img src="{{ asset('logo.jpg') }}" alt=""></span>
                            <div>
                                <h2 id="adjustment-confirmation-title" data-adjustment-confirmation-title>Confirmar solicitud</h2>
                                <p>Revisión de medicamentos y cantidades antes de resolver</p>
                            </div>
                            <button class="delivery-order-dialog-close" type="button" data-close-adjustment-confirmation aria-label="Cerrar ventana">
                                <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                            </button>
                        </header>
                        <form
                            action="{{ route('inventory.adjustments.resolve', $adjustmentDetails->first()->solicitud_ajuste_id) }}"
                            method="post"
                            class="inventory-adjustment-confirmation-form"
                            data-adjustment-resolution-form
                        >
                            @csrf
                            @method('PATCH')
                            <input type="hidden" name="decision" value="{{ old('decision', 'APROBAR') }}" data-adjustment-decision>
                            <div class="delivery-order-dialog-body">
                                <div class="notice notice-info">
                                    <i class="fa-solid fa-circle-info"></i>
                                    <span data-adjustment-confirmation-message>Al confirmar, se aprobarán todas las líneas de esta solicitud y se actualizarán las existencias.</span>
                                </div>
                                <div class="table-responsive">
                                    <table class="delivery-order-items">
                                        <thead>
                                            <tr>
                                                <th>MEDICAMENTO</th>
                                                <th>LOTE</th>
                                                <th>CANTIDAD</th>
                                                <th>UBICACIÓN</th>
                                                <th>TIPO</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach ($adjustmentDetails as $detail)
                                                <tr>
                                                    <td><strong>{{ $detail->producto }}</strong></td>
                                                    <td>{{ $detail->numero_lote }}<small>Vence {{ \Illuminate\Support\Carbon::parse($detail->fecha_vencimiento)->format('d/m/Y') }}</small></td>
                                                    <td><strong>{{ \App\Support\Formatting\NumberFormat::format($detail->cantidad) }}</strong></td>
                                                    <td>{{ $detail->ubicacion }}</td>
                                                    <td>{{ $detail->tipo_ajuste === 'ENTRADA' ? 'Entrada' : 'Salida' }}</td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                                <div class="form-group adjustment-rejection-reason" data-adjustment-rejection-reason hidden>
                                    <label for="motivo_resolucion">Motivo del rechazo <span class="required-mark">*</span></label>
                                    <textarea
                                        id="motivo_resolucion"
                                        class="form-control"
                                        name="motivo_resolucion"
                                        rows="3"
                                        maxlength="1000"
                                        data-adjustment-rejection-input
                                        disabled
                                    >{{ old('motivo_resolucion') }}</textarea>
                                </div>
                                @if ($errors->any())
                                    <div class="notice notice-warning" role="alert">
                                        <i class="fa-solid fa-triangle-exclamation"></i>
                                        <span>@foreach ($errors->all() as $error){{ $error }}@if (!$loop->last) <br>@endif @endforeach</span>
                                    </div>
                                @endif
                            </div>
                            <footer class="delivery-order-dialog-footer inventory-adjustment-confirmation-footer">
                                <button class="btn btn-outline" type="button" data-close-adjustment-confirmation>Cerrar ventana</button>
                                <button class="btn btn-primary" type="submit" data-adjustment-confirm-button>Confirmar solicitud</button>
                            </footer>
                        </form>
                    </dialog>
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
