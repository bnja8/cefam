@extends('layouts.app')
@section('content')
    <x-page-header title="Panel de Control" description="Resumen operativo de la farmacia asistencial CESFAM">
    </x-page-header>

    <div class="dashboard-analytics-grid">
        <section class="card chart-panel">
            <div class="card-header chart-card-header">
                <div>
                    <h2 class="card-title">Actividad de dispensación</h2>
                    <p class="chart-subtitle">Atenciones confirmadas durante {{ \Illuminate\Support\Str::lower($activityPeriodLabel) }}</p>
                </div>
                <form class="chart-period-form" action="{{ route('dashboard') }}" method="get">
                    <label for="activity-period">Período</label>
                    <div class="chart-period-actions">
                        <select class="form-control" id="activity-period" name="periodo_actividad">
                            @foreach (['7d' => 'Últimos 7 días', '30d' => 'Últimos 30 días', '1m' => 'Mes actual', '3m' => 'Últimos 3 meses', '6m' => 'Últimos 6 meses', '12m' => 'Últimos 12 meses'] as $period => $label)
                                <option value="{{ $period }}" @selected($selectedActivityPeriod === $period)>{{ $label }}</option>
                            @endforeach
                        </select>
                        <button class="btn btn-outline btn-small" type="submit">Ver</button>
                    </div>
                </form>
            </div>
            <div class="card-body chart-card-body">
                <div class="chart-summary-row">
                    <div><span class="chart-big-number">{{ number_format($activityPeriodTotal, 0, ',', '.') }}</span><span class="chart-unit">atenciones en el período</span></div>
                    <span class="trend-pill trend-neutral">Actualizado <span>{{ now()->format('H:i') }}</span></span>
                </div>
                <svg class="dashboard-chart line-chart" viewBox="0 0 640 225" role="img" aria-label="Atenciones confirmadas durante {{ \Illuminate\Support\Str::lower($activityPeriodLabel) }}">
                    <defs>
                        <linearGradient id="activityArea" x1="0" x2="0" y1="0" y2="1">
                            <stop offset="0%" stop-color="#0b5578" stop-opacity=".18" />
                            <stop offset="100%" stop-color="#0b5578" stop-opacity=".015" />
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

        <section class="card chart-panel stock-chart-panel">
            <div class="card-header chart-card-header">
                <div>
                    <h2 class="card-title">Existencias por categoría</h2>
                    <p class="chart-subtitle">Distribución del stock vigente por categoría</p>
                </div>
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
                <a class="chart-text-link" href="{{ route('inventory.index') }}">Revisar inventario <i class="fa-solid fa-arrow-right" aria-hidden="true"></i></a>
            </div>
        </section>
    </div>

    <div class="dashboard-grid">
        <section class="card">
            <div class="card-header"><h2 class="card-title"><i class="fa-solid fa-clock-rotate-left text-primary"></i> Últimas entregas de hoy</h2><a class="text-link" href="{{ route('dispensations.index') }}">Ver dispensaciones <i class="fa-solid fa-arrow-right"></i></a></div>
            <div class="table-responsive">
                <table class="data-table">
                    <thead><tr><th>FECHA</th><th>PACIENTE</th><th>MEDICAMENTO</th><th>CANT.</th><th>RESPONSABLE</th></tr></thead>
                    <tbody>
                    @forelse ($deliveries as $delivery)
                        <tr><td>{{ \Illuminate\Support\Carbon::parse($delivery->fecha_hora)->format('H:i') }}</td><td><strong>{{ $delivery->paciente }}</strong></td><td>{{ $delivery->producto ?? '—' }}</td><td>{{ \App\Support\Formatting\NumberFormat::format($delivery->cantidad) }}</td><td>{{ $delivery->responsable }}</td></tr>
                    @empty
                        <tr><td colspan="5" class="empty-state">Aún no hay entregas confirmadas hoy.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </section>
        <section class="card">
            <div class="card-header"><h2 class="card-title"><i class="fa-solid fa-triangle-exclamation text-danger"></i> Alertas operativas</h2><a class="text-link" href="{{ route('alerts.map') }}">Ver alertas</a></div>
            <div class="card-body">
                @forelse ($lowStock as $item)
                    <div class="alert-item danger"><i class="fa-solid fa-box-open alert-icon"></i><div class="alert-content"><h3>Stock crítico · {{ $item->producto }}</h3><p>{{ $item->ubicacion }} · Disponible {{ \App\Support\Formatting\NumberFormat::format($item->stock_disponible) }} / mínimo {{ \App\Support\Formatting\NumberFormat::format($item->stock_minimo) }} · Diferencia {{ \App\Support\Formatting\NumberFormat::format(max(0, (float) $item->stock_minimo - (float) $item->stock_disponible)) }} unidades · <a class="text-link" href="{{ route('products.stock', $item->producto_id) }}">Ver lotes y vencimientos</a></p></div></div>
                @empty
                    <div class="alert-item success"><i class="fa-solid fa-circle-check alert-icon"></i><div class="alert-content"><h3>Sin alertas de stock crítico</h3><p>Los niveles de inventario están sobre su mínimo.</p></div></div>
                @endforelse
                @foreach ($expiring as $item)
                    <div class="alert-item critical"><i class="fa-solid fa-calendar-xmark alert-icon"></i><div class="alert-content"><h3>Lote próximo a vencer</h3><p>{{ $item->producto }} · {{ $item->numero_lote }} · {{ \Illuminate\Support\Carbon::parse($item->fecha_vencimiento)->format('d/m/Y') }}</p></div></div>
                @endforeach
            </div>
        </section>
    </div>

    <div class="dashboard-secondary-charts">
        <section class="card chart-panel">
            <div class="card-header chart-card-header">
                <div>
                    <h2 class="card-title">Unidades disponibles por categoría</h2>
                    <p class="chart-subtitle">Existencias vigentes agrupadas por categoría</p>
                </div>
            </div>
            <div class="card-body chart-card-body chart-bars">
                @forelse ($stockCategories as $category)
                    <div class="chart-bar-row">
                        <span class="chart-bar-label" title="{{ $category->nombre }}">{{ $category->nombre }}</span>
                        <span class="chart-bar-track"><span class="chart-bar-fill" @style(['width' => min(100, ((float) $category->total / $maxCategoryStock) * 100).'%'])></span></span>
                        <strong class="chart-bar-value">{{ \App\Support\Formatting\NumberFormat::format($category->total) }}</strong>
                    </div>
                @empty
                    <p class="chart-empty">No hay existencias vigentes.</p>
                @endforelse
            </div>
        </section>

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
    </div>
@endsection
