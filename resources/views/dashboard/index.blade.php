@extends('layouts.app')
@section('content')
    <x-page-header title="Panel de Control" description="Resumen operativo de la farmacia asistencial CESFAM">
        <x-slot:actions>
            <span class="dashboard-date-badge"><i class="fa-regular fa-calendar" aria-hidden="true"></i> {{ now()->format('d/m/Y') }}</span>
        </x-slot:actions>
    </x-page-header>

    @if ($canViewRecipes || ($canViewDispensations && $canCreateDispensations) || $canViewPatients || $canViewInventory)
    <section class="dashboard-quick-actions" aria-labelledby="quick-actions-title">
        <div class="quick-actions-heading">
            <h2 id="quick-actions-title">Accesos rápidos</h2>
            <p>Ir directamente a los módulos principales</p>
        </div>
        <nav class="quick-actions-grid" aria-label="Módulos principales">
            @if ($canViewRecipes)
            <a class="quick-action-link" href="{{ route('recipes.index') }}">
                <span class="quick-action-icon quick-action-icon-recipes"><i class="fa-solid fa-file-prescription" aria-hidden="true"></i></span>
                <span class="quick-action-copy"><strong>Recetas</strong><small>Consultar recetas</small></span>
                <i class="fa-solid fa-arrow-right quick-action-arrow" aria-hidden="true"></i>
            </a>
            @endif
            @if ($canViewDispensations && $canCreateDispensations)
            <a class="quick-action-link" href="{{ route('dispensations.index') }}">
                <span class="quick-action-icon quick-action-icon-dispensations"><i class="fa-solid fa-prescription-bottle-medical" aria-hidden="true"></i></span>
                <span class="quick-action-copy"><strong>Dispensación</strong><small>Registrar una entrega</small></span>
                <i class="fa-solid fa-arrow-right quick-action-arrow" aria-hidden="true"></i>
            </a>
            @endif
            @if ($canViewPatients)
            <a class="quick-action-link" href="{{ route('patients.index') }}">
                <span class="quick-action-icon quick-action-icon-patients"><i class="fa-solid fa-user-group" aria-hidden="true"></i></span>
                <span class="quick-action-copy"><strong>Pacientes</strong><small>Buscar y gestionar pacientes</small></span>
                <i class="fa-solid fa-arrow-right quick-action-arrow" aria-hidden="true"></i>
            </a>
            @endif
            @if ($canViewInventory)
            <a class="quick-action-link" href="{{ route('inventory.index') }}">
                <span class="quick-action-icon quick-action-icon-inventory"><i class="fa-solid fa-boxes-stacked" aria-hidden="true"></i></span>
                <span class="quick-action-copy"><strong>Inventario</strong><small>Revisar existencias</small></span>
                <i class="fa-solid fa-arrow-right quick-action-arrow" aria-hidden="true"></i>
            </a>
            @endif
        </nav>
    </section>
    @endif

    <div class="dashboard-flow">
    @if ($canViewDispensations || $canViewRecipes || $canViewAlerts || $canViewInventory)
    <section class="dashboard-metrics-grid" aria-label="Indicadores operativos">
        @if ($canViewDispensations)
        <article class="dashboard-metric-card dashboard-metric-blue">
            <div>
                <span class="dashboard-metric-label">Atenciones hoy</span>
                <strong class="dashboard-metric-value">{{ number_format($todayDeliveryCount, 0, ',', '.') }}</strong>
                @if ($todayDeliveryCount >= $yesterdayDeliveryCount)
                    <span class="dashboard-metric-change"><i class="fa-solid fa-arrow-up" aria-hidden="true"></i> {{ number_format($todayDeliveryCount - $yesterdayDeliveryCount, 0, ',', '.') }} vs ayer</span>
                @else
                    <span class="dashboard-metric-change dashboard-metric-change-down"><i class="fa-solid fa-arrow-down" aria-hidden="true"></i> {{ number_format($yesterdayDeliveryCount - $todayDeliveryCount, 0, ',', '.') }} vs ayer</span>
                @endif
            </div>
            <span class="dashboard-metric-icon"><i class="fa-solid fa-circle-check" aria-hidden="true"></i></span>
        </article>
        @endif
        @if ($canViewRecipes)
        <article class="dashboard-metric-card dashboard-metric-violet">
            <div>
                <span class="dashboard-metric-label">Recetas pendientes</span>
                <strong class="dashboard-metric-value">{{ number_format($pendingPrescriptions, 0, ',', '.') }}</strong>
                <a class="dashboard-metric-link" href="{{ route('recipes.index') }}">En espera en ventanilla</a>
            </div>
            <span class="dashboard-metric-icon"><i class="fa-solid fa-clock" aria-hidden="true"></i></span>
        </article>
        @endif
        @if ($canViewAlerts)
        <article class="dashboard-metric-card dashboard-metric-red">
            <div>
                <span class="dashboard-metric-label">Stock crítico</span>
                <strong class="dashboard-metric-value">{{ number_format($lowStockCount, 0, ',', '.') }}</strong>
                <a class="dashboard-metric-link" href="{{ route('alerts.map') }}">Revisar alerta</a>
            </div>
            <span class="dashboard-metric-icon"><i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i></span>
        </article>
        @endif
        @if ($canViewInventory)
        <article class="dashboard-metric-card dashboard-metric-amber">
            <div>
                <span class="dashboard-metric-label">Lotes por vencer</span>
                <strong class="dashboard-metric-value">{{ number_format($expiringLotCount, 0, ',', '.') }} <small>lotes</small></strong>
                <span class="dashboard-metric-footnote">≤ 30 días · FEFO</span>
            </div>
            <span class="dashboard-metric-icon"><i class="fa-solid fa-calendar-days" aria-hidden="true"></i></span>
        </article>
        @endif
    </section>
    @endif

    @if ($canViewDispensations || $canViewAlerts || $canViewInventory)
    <section class="dashboard-operations-grid" aria-label="Operación diaria">
        @if ($canViewDispensations)
        <section class="card dashboard-deliveries-card" aria-labelledby="latest-deliveries-title">
            <header class="card-header dashboard-section-header">
                <div class="dashboard-section-title">
                    <span class="dashboard-section-icon dashboard-section-icon-blue"><i class="fa-solid fa-clock-rotate-left" aria-hidden="true"></i></span>
                    <div>
                        <h2 class="card-title" id="latest-deliveries-title">Últimas entregas de hoy</h2>
                        <p class="chart-subtitle">Dispensaciones confirmadas en ventanilla</p>
                    </div>
                </div>
                <a class="text-link" href="{{ route('dispensations.deliveries') }}">Ver dispensaciones <i class="fa-solid fa-arrow-right" aria-hidden="true"></i></a>
            </header>
            <div class="table-responsive">
                <table class="data-table dashboard-delivery-table">
                    <thead>
                        <tr><th>HORA</th><th>PACIENTE</th><th>RETIRO</th><th>MEDICAMENTO</th><th>CANT.</th><th class="dashboard-delivery-responsible">RESPONSABLE</th>@if ($canPrintDispensations)<th>COMPROBANTE</th>@endif</tr>
                    </thead>
                    <tbody>
                    @forelse ($deliveries as $delivery)
                        <tr>
                            <td class="dashboard-delivery-time">{{ \Illuminate\Support\Carbon::parse($delivery->fecha_hora)->format('H:i') }}</td>
                            <td>
                                <strong>{{ $delivery->paciente }}</strong>
                                <small class="dashboard-patient-rut">{{ $delivery->paciente_rut }}</small>
                            </td>
                            <td>
                                <span @class(['dashboard-recipient-badge', 'dashboard-recipient-authorized' => $delivery->persona_autorizada_id])>
                                    {{ $delivery->persona_autorizada_id ? 'Tercero autorizado' : 'Titular' }}
                                </span>
                            </td>
                            <td>{{ $delivery->producto ?? '—' }}</td>
                            <td class="dashboard-delivery-quantity">{{ \App\Support\Formatting\NumberFormat::format($delivery->cantidad) }}</td>
                            <td class="dashboard-delivery-responsible">{{ $delivery->responsable }}</td>
                            @if ($canPrintDispensations)
                            <td>
                                @if ($delivery->orden_entrega_id)
                                    <a class="btn btn-outline btn-small dashboard-receipt-link" href="{{ route('dashboard', ['comprobante' => $delivery->orden_entrega_id]) }}" aria-label="Abrir comprobante de la dispensación">
                                        <i class="fa-solid fa-receipt" aria-hidden="true"></i> Comprobante
                                    </a>
                                @else
                                    <span class="muted">No disponible</span>
                                @endif
                            </td>
                            @endif
                        </tr>
                    @empty
                        <tr><td colspan="{{ $canPrintDispensations ? 7 : 6 }}" class="empty-state">Aún no hay entregas confirmadas hoy.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            <footer class="dashboard-card-footer">
                <span>{{ number_format($todayDeliveryCount, 0, ',', '.') }} atenciones confirmadas hoy</span>
                <span><i class="fa-solid fa-shield-halved" aria-hidden="true"></i> Trazabilidad FEFO</span>
            </footer>
        </section>
        @endif

        @if ($canViewAlerts || $canViewInventory)
        <div class="dashboard-side-stack">
            @if ($canViewAlerts)
            <section class="card dashboard-side-card" id="seccion-alertas" aria-labelledby="operational-alerts-title">
                <header class="card-header dashboard-section-header">
                    <div class="dashboard-section-title">
                        <span class="dashboard-section-icon dashboard-section-icon-red"><i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i></span>
                        <h2 class="card-title" id="operational-alerts-title">Alertas operativas</h2>
                    </div>
                    <a class="text-link" href="{{ route('alerts.map') }}">Ver alertas</a>
                </header>
                <div class="card-body dashboard-alert-list">
                    @forelse ($lowStock as $item)
                        <article class="dashboard-alert-item">
                            <span class="dashboard-alert-item-icon"><i class="fa-solid fa-box-open" aria-hidden="true"></i></span>
                            <div>
                                <h3>Stock crítico · {{ $item->producto }}</h3>
                                <p>{{ $item->ubicacion }} · Disponible {{ \App\Support\Formatting\NumberFormat::format($item->stock_disponible) }} / mínimo {{ \App\Support\Formatting\NumberFormat::format($item->stock_minimo) }}</p>
                                @if ($canViewInventory)
                                <a href="{{ route('products.stock', $item->producto_id) }}">Ver lotes y vencimientos <i class="fa-solid fa-arrow-right" aria-hidden="true"></i></a>
                                @endif
                            </div>
                        </article>
                    @empty
                        <div class="dashboard-alert-empty"><i class="fa-solid fa-circle-check" aria-hidden="true"></i><span>Sin alertas de stock crítico</span></div>
                    @endforelse
                </div>
            </section>
            @endif

            @if ($canViewInventory)
            <section class="card dashboard-side-card" id="seccion-fefo" aria-labelledby="expiring-stock-title">
                <header class="card-header dashboard-section-header">
                    <div>
                        <h2 class="card-title" id="expiring-stock-title">Próximos vencimientos</h2>
                        <p class="chart-subtitle">Control FEFO · próximos 30 días</p>
                    </div>
                    <span class="dashboard-count-badge dashboard-count-badge-amber">{{ number_format($expiringLotCount, 0, ',', '.') }} lotes</span>
                </header>
                <div class="card-body dashboard-expiring-list">
                    @forelse ($expiring as $item)
                        <a class="dashboard-expiring-item" href="{{ route('products.stock', $item->producto_id) }}">
                            <span class="dashboard-expiring-icon"><i class="fa-solid fa-prescription-bottle-medical" aria-hidden="true"></i></span>
                            <span class="dashboard-expiring-copy">
                                <strong>{{ $item->producto }}</strong>
                                <small>Lote {{ $item->numero_lote }}</small>
                            </span>
                            <time datetime="{{ $item->fecha_vencimiento }}">{{ \Illuminate\Support\Carbon::parse($item->fecha_vencimiento)->format('d/m/Y') }}</time>
                        </a>
                    @empty
                        <p class="dashboard-expiring-empty">No hay lotes próximos a vencer.</p>
                    @endforelse
                </div>
            </section>
            @endif
        </div>
        @endif
    </section>
    @endif

    @if ($canViewDispensations || $canViewInventory)
    <section class="dashboard-insights-grid" aria-label="Analítica e inventario">
        @if ($canViewDispensations)
        <section class="card chart-panel dashboard-activity-card">
            <div class="card-header chart-card-header">
                <div>
                    <h2 class="card-title">Actividad de dispensación</h2>
                    <p class="chart-subtitle">Atenciones confirmadas durante {{ \Illuminate\Support\Str::lower($activityPeriodLabel) }}</p>
                </div>
                <form class="chart-period-form" action="{{ route('dashboard') }}" method="get">
                    <label for="activity-period">Período</label>
                    <div class="chart-period-actions">
                        <select class="form-control" id="activity-period" name="periodo_actividad" onchange="this.form.submit()">
                            @foreach (['hoy' => 'Hoy (por hora)', '7d' => 'Últimos 7 días', '30d' => 'Últimos 30 días', '1m' => 'Mes actual', '3m' => 'Últimos 3 meses', '6m' => 'Últimos 6 meses', '12m' => 'Últimos 12 meses'] as $period => $label)
                                <option value="{{ $period }}" @selected($selectedActivityPeriod === $period)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                </form>
            </div>
            <div class="card-body chart-card-body">
                <div class="chart-summary-row">
                    <div><span class="chart-big-number">{{ number_format($activityPeriodTotal, 0, ',', '.') }}</span><span class="chart-unit">atenciones en el período</span></div>
                    <span class="trend-pill trend-neutral">Actualización cada <span>10 min</span></span>
                </div>
                <svg class="dashboard-chart line-chart" viewBox="0 0 640 225" role="img" aria-label="Atenciones confirmadas durante {{ \Illuminate\Support\Str::lower($activityPeriodLabel) }}">
                    <defs>
                        <linearGradient id="activityArea" x1="0" x2="0" y1="0" y2="1">
                            <stop offset="0%" stop-color="#0284c7" stop-opacity=".18" />
                            <stop offset="100%" stop-color="#0284c7" stop-opacity=".015" />
                        </linearGradient>
                    </defs>
                    <g class="chart-grid-lines">
                        <line x1="45" y1="52" x2="597" y2="52" />
                        <line x1="45" y1="94" x2="597" y2="94" />
                        <line x1="45" y1="136" x2="597" y2="136" />
                        <line x1="45" y1="178" x2="597" y2="178" />
                    </g>
                    <path class="chart-area-fill" d="{{ $activityArea }}" />
                    <polyline class="chart-line-path" points="{{ $activityLine }}" />
                    <g class="chart-points">
                        @foreach ($activityPoints as $point)
                            <circle @class(['chart-point-current' => $loop->last]) cx="{{ $point['x'] }}" cy="{{ $point['y'] }}" r="{{ $loop->last ? 5 : 4 }}">
                                <title>{{ $point['date'] }}: {{ $point['total'] }} atenciones</title>
                            </circle>
                        @endforeach
                    </g>
                    <g class="chart-axis-labels chart-x-labels">
                        @foreach ($activityPoints as $point)
                            @if ($point['showLabel'])
                                <text x="{{ $point['x'] }}" y="210" text-anchor="middle">{{ $point['label'] }}</text>
                            @endif
                        @endforeach
                    </g>
                </svg>
                <div class="chart-legend"><span><i class="legend-dot legend-blue"></i>Atenciones confirmadas</span><span class="chart-note">Fuente: registros de dispensación</span></div>
            </div>
        </section>
        @endif

        @if ($canViewInventory || $canViewDispensations)
        <div class="dashboard-insights-side">
            @if ($canViewInventory)
            <section class="card chart-panel stock-chart-panel">
                <div class="card-header chart-card-header">
                    <div>
                        <h2 class="card-title">Salud del inventario</h2>
                        <p class="chart-subtitle">Existencias por categoría · {{ \App\Support\Formatting\NumberFormat::format($stockCount) }} unidades vigentes</p>
                    </div>
                    <a class="text-link" href="{{ route('inventory.index') }}">Inventario <i class="fa-solid fa-arrow-right" aria-hidden="true"></i></a>
                </div>
                <div class="card-body chart-card-body stock-chart-body">
                    <div class="donut-wrap">
                        <svg class="donut-chart" viewBox="0 0 180 180" role="img" aria-label="Distribución de {{ number_format($stockCount, 0, ',', '.') }} unidades disponibles por categoría">
                            <circle class="donut-track" cx="90" cy="90" r="54" />
                            @foreach ($donutSlices as $slice)
                                <circle class="donut-segment" cx="90" cy="90" r="54" @style([
                                    'stroke' => $slice['color'],
                                    'stroke-dasharray' => $slice['length'].' '.$circleCircumference,
                                    'stroke-dashoffset' => -$slice['offset'],
                                ])>
                                    <title>{{ $slice['name'] }}: {{ \App\Support\Formatting\NumberFormat::format($slice['total']) }} unidades ({{ $slice['percentage'] }}%)</title>
                                </circle>
                            @endforeach
                            <text class="donut-total" x="90" y="87" text-anchor="middle">{{ \App\Support\Formatting\NumberFormat::format($stockCount) }}</text>
                            <text class="donut-label" x="90" y="106" text-anchor="middle">unidades</text>
                        </svg>
                    </div>
                    <div class="stock-legend">
                        @forelse ($donutSlices as $slice)
                            <div class="stock-legend-row">
                                <span class="stock-legend-name"><i class="legend-dot" @style(['background' => $slice['color']])></i>{{ $slice['name'] }}</span>
                                <strong>{{ \App\Support\Formatting\NumberFormat::format($slice['total']) }}</strong>
                                <small>{{ $slice['percentage'] }}%</small>
                            </div>
                        @empty
                            <p class="chart-empty">No hay existencias vigentes para graficar.</p>
                        @endforelse
                    </div>
                </div>
            </section>
            @endif

            @if ($canViewDispensations)
            <section class="card chart-panel">
                <div class="card-header chart-card-header">
                    <div>
                        <h2 class="card-title">Productos más dispensados</h2>
                        <p class="chart-subtitle">Unidades entregadas en los últimos 30 días</p>
                    </div>
                </div>
                <div class="card-body chart-card-body chart-bars">
                    @forelse ($mostDispensed as $product)
                        <div class="chart-bar-row">
                            <span class="chart-bar-label" title="{{ $product->nombre }}">{{ $product->nombre }}</span>
                            <span class="chart-bar-track"><span class="chart-bar-fill chart-bar-fill-alt" @style(['width' => min(100, ((float) $product->cantidad / $maxDispensed) * 100).'%'])></span></span>
                            <strong class="chart-bar-value">{{ \App\Support\Formatting\NumberFormat::format($product->cantidad) }}</strong>
                        </div>
                    @empty
                        <p class="chart-empty">No hay dispensaciones en el período.</p>
                    @endforelse
                </div>
            </section>
            @endif
        </div>
        @endif
    </section>
    @endif
    </div>

    @if ($canPrintDispensations && $deliveryOrder)
        @php($order = $deliveryOrder['order'])
        <dialog class="delivery-order-dialog" data-dispensation-result-dialog data-open-on-load="true" aria-labelledby="dashboard-result-title">
            <header class="delivery-order-dialog-header">
                <span class="delivery-order-dialog-icon" aria-hidden="true"><img src="{{ asset('logo.jpg') }}" alt=""></span>
                <div>
                    <h2 id="dashboard-result-title">{{ $order->es_sos ? 'Tratamiento SOS' : 'Receta Médica' }}</h2>
                    <p>CESFAM Central · Atención Primaria de Salud · Operación auditada</p>
                </div>
                <form method="dialog">
                    <button class="delivery-order-dialog-close" type="submit" aria-label="Cerrar comprobante"><i class="fa-solid fa-xmark"></i></button>
                </form>
            </header>
            <div class="delivery-order-dialog-body">
                <div class="delivery-order-summary">
                    <div><span>N° ORDEN ENTREGA</span><strong>{{ $order->numero_orden }}</strong></div>
                    <div><span>{{ $order->es_sos ? 'TIPO DE DISPENSACIÓN' : 'FOLIO RECETA COTEJADA' }}</span><strong>{{ $order->es_sos ? 'TRATAMIENTO SOS' : ($order->numero_receta ?: 'Sin folio') }}</strong></div>
                    <div><span>FECHA Y HORA DESPACHO</span><strong>{{ \Illuminate\Support\Carbon::parse($order->fecha_hora)->format('d/m/Y H:i') }}</strong></div>
                </div>
                <div class="delivery-order-parties">
                    <article>
                        <span>PACIENTE TITULAR</span>
                        <strong>{{ $order->paciente }} ({{ $order->rut }})</strong>
                    </article>
                    <article>
                        <span>PERSONA QUE RETIRA</span>
                        <strong>{{ $order->es_sos ? 'SOS' : ($order->persona_autorizada ? $order->persona_autorizada.($order->persona_autorizada_rut ? ' ('.$order->persona_autorizada_rut.')' : '') : 'Paciente') }}</strong>
                    </article>
                </div>
                <div class="delivery-order-items-wrap">
                    <table class="delivery-order-items">
                        <thead><tr><th>Medicamento / Insumo</th><th>Lote FEFO</th><th>Vencimiento</th><th>Entregado</th><th>Etiqueta</th></tr></thead>
                        <tbody>
                            @foreach ($deliveryOrder['details'] as $detail)
                                <tr>
                                    <td><strong>{{ $detail->producto }}</strong><small>{{ $detail->dosis ?: 'Según indicación profesional' }} · {{ \App\Support\Prescriptions\MedicationFrequency::label($detail->horario) }}</small></td>
                                    <td>{{ $detail->numero_lote }}</td>
                                    <td>{{ \Illuminate\Support\Carbon::parse($detail->fecha_vencimiento)->format('d/m/Y') }}</td>
                                    <td><strong>{{ \App\Support\Formatting\NumberFormat::format($detail->cantidad) }} {{ $detail->unidad_base_codigo }}</strong></td>
                                    <td><button class="btn btn-outline btn-small" type="button" data-print-label-url="{{ route('delivery-orders.label', [$order->orden_entrega_id, $detail->entrega_detalle_id]) }}"><i class="fa-solid fa-print" aria-hidden="true"></i> Imprimir etiqueta</button></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <p class="delivery-order-print-status" data-print-status aria-live="polite">Se genera una etiqueta por medicamento y lote; cada solicitud de impresión queda auditada.</p>
                <footer class="delivery-order-dialog-footer">
                    <span>Dispensación confirmada por <strong>{{ $order->responsable }}</strong></span>
                    <div class="delivery-order-dialog-actions">
                        <button class="btn btn-outline" type="button" data-print-label-url="{{ route('delivery-orders.labels', $order->orden_entrega_id) }}"><i class="fa-solid fa-print" aria-hidden="true"></i> Imprimir etiquetas</button>
                        <button class="btn btn-outline" type="button" data-print-order-url="{{ route('delivery-orders.receipt', $order->orden_entrega_id) }}"><i class="fa-solid fa-print" aria-hidden="true"></i> Imprimir Orden</button>
                        <form method="dialog"><button class="btn btn-primary" type="submit">Cerrar</button></form>
                    </div>
                </footer>
            </div>
        </dialog>
    @endif
@endsection
