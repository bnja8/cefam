{{-- Muestra el detalle y las acciones de una alerta operativa. --}}
@extends('layouts.app')
@section('content')
    @php
        $isExpiry = in_array($alert->tipo_alerta, ['VENCIMIENTO', 'VENCIDO'], true);
        $isCritical = in_array($alert->tipo_alerta, ['SIN_EXISTENCIAS', 'STOCK_CRITICO', 'STOCK_BAJO'], true);
        $detectedAt = $alert->detectada_en ?? $alert->fecha_generacion;
        $alertState = $alert->estado ?? ($alert->resuelta_at ? 'RESUELTA' : 'ACTIVA');
    @endphp

    <x-page-header :title="$alert->producto ?? 'Detalle de alerta'" description="Consulta el estado y gestiona las acciones de esta alerta">
        <x-slot:actions>
            <a class="btn btn-outline" href="{{ route('alerts.map') }}"><i class="fa-solid fa-arrow-left"></i> Volver al mapa operativo</a>
        </x-slot:actions>
    </x-page-header>

    <section class="card alert-detail-card">
        <div class="card-header alert-section-header">
            <div>
                <h2 class="card-title"><i class="fa-solid fa-triangle-exclamation"></i> {{ str_replace('_', ' ', $alert->tipo_alerta) }}</h2>
                <p>{{ $alert->mensaje }}</p>
            </div>
            <span class="badge {{ $alert->resuelta_at ? 'badge-success' : ($alertState === 'EN_SEGUIMIENTO' ? 'badge-info' : 'badge-warning') }}">
                {{ $alert->resuelta_at ? 'Resuelta' : str_replace('_', ' ', $alertState) }}
            </span>
        </div>
        <div class="card-body">
            <div class="alert-detail-grid">
                <div><small>Producto</small><strong>{{ $alert->producto ?? 'Producto sin identificación' }}</strong></div>
                <div><small>Ubicación</small><strong>{{ $alert->ubicacion ?? 'Sin ubicación' }}</strong></div>
                <div><small>Responsable</small><strong>{{ $alert->responsable ?? 'Sin asignar' }}</strong></div>
                <div><small>Reconocimiento</small><strong>{{ $alert->reconocida_en ? 'Reconocida' : 'Sin reconocer' }}</strong></div>
                <div><small>Detectada</small><strong>{{ \Illuminate\Support\Carbon::parse($detectedAt)->format('d/m/Y H:i') }}</strong></div>
                @if ($alert->numero_lote)
                    <div><small>Lote</small><strong>{{ $alert->numero_lote }}</strong></div>
                @endif
                @if ($alert->fecha_vencimiento)
                    <div><small>Vencimiento</small><strong>{{ \Illuminate\Support\Carbon::parse($alert->fecha_vencimiento)->format('d/m/Y') }}</strong></div>
                @endif
                @if ($isCritical)
                    <div><small>Disponible</small><strong>{{ \App\Support\Formatting\NumberFormat::format($alert->nivel_actual) }}</strong></div>
                    @if ($alert->stock_minimo !== null)
                        <div><small>Stock mínimo</small><strong>{{ \App\Support\Formatting\NumberFormat::format($alert->stock_minimo) }}</strong></div>
                    @endif
                @endif
                @if ($alert->resuelta_at)
                    <div><small>Resuelta</small><strong>{{ \Illuminate\Support\Carbon::parse($alert->resuelta_at)->format('d/m/Y H:i') }}</strong></div>
                @endif
            </div>
        </div>
    </section>

    @if (! $alert->resuelta_at)
        <section class="card alert-actions-card">
            <div class="card-header alert-section-header">
                <div>
                    <h2 class="card-title"><i class="fa-solid fa-list-check"></i> Acciones</h2>
                    <p>{{ $isTechnician ? 'Consulta el inventario o inicia la solicitud correspondiente a esta alerta.' : 'Asigna, reconoce o registra el seguimiento de esta alerta.' }}</p>
                </div>
            </div>
            <div class="card-body">
                @if ($alert->tipo_alerta === 'VENCIDO')
                    <div class="notice notice-info">
                        <i class="fa-solid fa-circle-info" aria-hidden="true"></i>
                        <span>El lote vencido permanece en las existencias físicas y no se puede dispensar. El retiro se registra mediante una solicitud de salida y solo descuenta stock cuando un QF la autoriza.</span>
                    </div>
                @endif
                @if ($canManageAlertActions || ($alert->producto_id && $canManageReplenishment && ! $isExpiry))
                    <div class="alert-detail-actions">
                        @if ($canManageAlertActions)
                            <form action="{{ route('alerts.assign', $alert->alerta_id) }}" method="post" class="alert-action-form">
                                @csrf @method('patch')
                                <label for="responsable-alerta">Asignar responsable</label>
                                <select class="form-control" id="responsable-alerta" name="responsable_rut" required>
                                    <option value="">Seleccionar responsable</option>
                                    @foreach ($responsibles as $responsible)
                                        @php $responsibleName = trim(($responsible->nombre ?? '').' '.($responsible->apellidoPaterno ?? '').' '.($responsible->apellidoMaterno ?? '')); @endphp
                                        <option value="{{ $responsible->rut }}" @selected($alert->responsable_rut === $responsible->rut)>{{ $responsibleName ?: $responsible->rut }}</option>
                                    @endforeach
                                </select>
                                <button class="btn btn-outline btn-small" type="submit"><i class="fa-solid fa-user-check"></i> Asignar / reasignar</button>
                            </form>

                            @if (! $alert->reconocida_en)
                                <form action="{{ route('alerts.acknowledge', $alert->alerta_id) }}" method="post">
                                    @csrf @method('patch')
                                    <button class="btn btn-outline btn-small" type="submit"><i class="fa-solid fa-eye"></i> Reconocer</button>
                                </form>
                            @endif

                            <form action="{{ route('alerts.follow', $alert->alerta_id) }}" method="post" class="alert-action-form">
                                @csrf
                                <label for="observacion-alerta">Registrar seguimiento</label>
                                <input class="form-control" id="observacion-alerta" name="observacion" maxlength="1000" placeholder="Acción u observación" required>
                                <button class="btn btn-outline btn-small" type="submit"><i class="fa-solid fa-comments"></i> Registrar seguimiento</button>
                            </form>
                        @endif

                        @if ($alert->producto_id && $canManageReplenishment && ! $isExpiry)
                            <form action="{{ route('alerts.replenish', $alert->alerta_id) }}" method="post">
                                @csrf
                                <button class="btn btn-primary btn-small" type="submit"><i class="fa-solid fa-truck-ramp-box"></i> Gestión de reposición</button>
                            </form>
                        @endif
                    </div>
                @endif

                <div class="alert-detail-links">
                    @if ($isTechnician)
                        <a class="btn btn-primary btn-small" href="{{ route('inventory.adjust.form') }}"><i class="fa-solid fa-file-circle-plus"></i> Realizar solicitud</a>
                    @endif
                    @if ($isExpiry)
                        @if ($alert->tipo_alerta === 'VENCIDO' && (string) $alert->responsable_rut === (string) Auth::id())
                            <a class="btn btn-primary btn-small" href="{{ route('alerts.expiry-withdrawal', $alert->alerta_id) }}"><i class="fa-solid fa-scale-balanced"></i> Solicitar retiro por vencimiento</a>
                        @endif
                    @endif
                    @if ($alert->producto_id)
                        <a class="btn btn-outline btn-small" href="{{ route('products.stock', $alert->producto_id) }}"><i class="fa-solid fa-boxes-stacked"></i> Consultar lote en Inventario</a>
                    @endif
                </div>
            </div>
        </section>
    @endif
@endsection
