@extends('layouts.app')
@section('content')
    <x-page-header :title="$product->nombre" description="Existencias por lote y ubicación, ordenadas según prioridad FEFO.">
        <x-slot:actions>
            <a class="btn btn-outline" href="{{ route('inventory.index') }}"><i class="fa-solid fa-arrow-left"></i> Volver al inventario</a>
            @if ($canManageStockLimits)
                <a class="btn btn-outline" href="#stock-por-ubicacion"><i class="fa-solid fa-sliders"></i> Configurar mínimos y máximos</a>
            @endif
            @if ($canManageCatalog)
                <a class="btn btn-outline" href="{{ route('products.edit', $product->producto_id) }}"><i class="fa-solid fa-pen"></i> Editar producto</a>
            @endif
        </x-slot:actions>
    </x-page-header>

    <div class="metrics-grid metrics-grid-compact">
        <x-metric-card :label="'Stock disponible ('.$product->unidad_base_codigo.')'" :value="\App\Support\Formatting\NumberFormat::format($availableQuantity)" icon="fa-boxes-stacked" tone="blue" />
        <x-metric-card :label="'Stock registrado ('.$product->unidad_base_codigo.')'" :value="\App\Support\Formatting\NumberFormat::format($totalQuantity)" icon="fa-boxes-stacked" tone="teal" />
        <x-metric-card label="Stock vencido" :value="\App\Support\Formatting\NumberFormat::format($stockByLocation->sum('stock_vencido'))" icon="fa-calendar-xmark" tone="red" />
        <x-metric-card label="Stock bloqueado" :value="\App\Support\Formatting\NumberFormat::format($stockByLocation->sum('stock_bloqueado'))" icon="fa-lock" tone="red" />
        <x-metric-card label="Lotes registrados" :value="$lotCount" icon="fa-layer-group" tone="teal" />
        <x-metric-card label="Código de producto" :value="$product->codigo_producto" icon="fa-barcode" tone="green" />
    </div>

    <section class="card" id="stock-por-ubicacion">
        <div class="card-header"><h2 class="card-title"><i class="fa-solid fa-warehouse text-primary"></i> Stock agrupado por ubicación</h2></div>
        <div class="card-body">
            <div class="notice notice-info"><i class="fa-solid fa-circle-info"></i><span>Todas las cantidades y límites se expresan en <strong>{{ $product->unidad_base_codigo }}</strong>, la unidad en la que se dispensa este producto. El stock registrado es físico; disponible excluye lotes vencidos o inactivos. El faltante al mínimo muestra cuánto falta para alcanzar el umbral configurado. Se listan todas las ubicaciones, aunque no tengan existencias.</span></div>
        </div>
        <div class="table-responsive">
            <table class="data-table">
                <thead><tr><th>UBICACIÓN</th><th>REGISTRADO</th><th>DISPONIBLE</th><th>VENCIDO</th><th>BLOQUEADO</th><th>MÍNIMO</th><th>MÁXIMO</th><th>FALTANTE AL MÍNIMO</th><th>ESTADO</th>@if ($canManageStockLimits)<th>CONFIGURAR</th>@endif</tr></thead>
                <tbody>
                @forelse ($stockByLocation as $stock)
                    <tr>
                        <td>{{ $stock->ubicacion }}</td>
                        <td>{{ \App\Support\Formatting\NumberFormat::format($stock->stock_fisico) }}</td>
                        <td><strong>{{ \App\Support\Formatting\NumberFormat::format($stock->stock_disponible) }}</strong></td>
                        <td>{{ \App\Support\Formatting\NumberFormat::format($stock->stock_vencido) }}</td>
                        <td>{{ \App\Support\Formatting\NumberFormat::format($stock->stock_bloqueado) }}</td>
                        <td>{{ $stock->stock_minimo === null ? 'Sin configurar' : \App\Support\Formatting\NumberFormat::format($stock->stock_minimo) }}</td>
                        <td>{{ $stock->stock_maximo === null ? 'Sin límite' : \App\Support\Formatting\NumberFormat::format($stock->stock_maximo) }}</td>
                        <td>{{ $stock->faltante_minimo === null ? '—' : \App\Support\Formatting\NumberFormat::format($stock->faltante_minimo) }}</td>
                        <td>
                            @if ($stock->sobre_maximo)
                                <span class="badge badge-warning">Sobre máximo</span>
                            @elseif ($stock->stock_minimo === null)
                                <span class="badge badge-info">Sin configurar</span>
                            @elseif ($stock->es_stock_critico)
                                <span class="badge badge-danger">Stock crítico</span>
                            @else
                                <span class="badge badge-success">Dentro de rango</span>
                            @endif
                        </td>
                        @if ($canManageStockLimits)
                            <td>
                                <form action="{{ route('products.stock-limits.update', $product->producto_id) }}" method="post" class="stack-form">
                                    @csrf
                                    @method('PUT')
                                    <input type="hidden" name="ubicacion_id" value="{{ $stock->ubicacion_id }}">
                                    <div class="form-group">
                                        <label for="stock-minimo-{{ $stock->ubicacion_id }}">Mínimo</label>
                                        <input class="form-control" id="stock-minimo-{{ $stock->ubicacion_id }}" name="stock_minimo" type="number" min="0" step="0.01" value="{{ $stock->stock_minimo ?? 0 }}" required>
                                    </div>
                                    <div class="form-group">
                                        <label for="stock-maximo-{{ $stock->ubicacion_id }}">Máximo</label>
                                        <input class="form-control" id="stock-maximo-{{ $stock->ubicacion_id }}" name="stock_maximo" type="number" min="0.01" step="0.01" value="{{ $stock->stock_maximo }}">
                                    </div>
                                    <button class="btn btn-outline btn-small" type="submit">Guardar</button>
                                </form>
                            </td>
                        @endif
                    </tr>
                @empty
                    <tr><td colspan="{{ $canManageStockLimits ? 10 : 9 }}" class="empty-state">Este producto no tiene ubicaciones configuradas.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <section class="card" id="lotes-producto">
        <div class="card-header">
            <h2 class="card-title"><i class="fa-solid fa-layer-group text-primary"></i> Lotes y existencias por ubicación</h2>
            <span class="badge badge-info">FEFO: vence primero, sale primero</span>
            <span class="muted">{{ number_format($lots->total(), 0, ',', '.') }} registros</span>
        </div>
        <div class="card-body">
            <div class="notice notice-info"><i class="fa-solid fa-circle-info"></i><span>Los lotes vigentes aparecen primero, ordenados por fecha de vencimiento. Los lotes vencidos se muestran al final y no deben dispensarse.</span></div>
        </div>
        <div class="table-responsive">
            <table class="data-table">
                <thead><tr><th>LOTE</th><th>FABRICACIÓN</th><th>VENCIMIENTO</th><th>CONDICIÓN</th><th>DISPONIBILIDAD</th><th>RECEPCIÓN</th><th>UBICACIÓN</th><th>CANTIDAD</th><th>ACCIÓN</th></tr></thead>
                <tbody>
                    @forelse ($lots as $lot)
                        @php
                            $expiryClass = match ($lot['condicion_vencimiento']) {
                                'VENCIDO' => 'badge-danger',
                                'PROXIMO_A_VENCER' => 'badge-warning',
                                default => 'badge-success',
                            };
                            $availabilityClass = match ($lot['estado_disponibilidad']) {
                                'PRODUCTO_INACTIVO', 'BLOQUEADO' => 'badge-danger',
                                'NO_DISPONIBLE' => 'badge-warning',
                                'SIN_STOCK' => 'badge-info',
                                default => 'badge-success',
                            };
                            $availabilityLabel = match ($lot['estado_disponibilidad']) {
                                'PRODUCTO_INACTIVO' => 'Producto inactivo',
                                'BLOQUEADO' => 'Lote inactivo',
                                'NO_DISPONIBLE' => 'No disponible',
                                'SIN_STOCK' => 'Sin stock',
                                default => 'Disponible',
                            };
                            $expiryLabel = match ($lot['condicion_vencimiento']) {
                                'VENCIDO' => 'Vencido',
                                'PROXIMO_A_VENCER' => 'Próximo a vencer',
                                default => 'Vigente',
                            };
                        @endphp
                        <tr>
                            <td><strong>{{ $lot['numero_lote'] }}</strong></td>
                            <td>{{ $lot['fecha_fabricacion'] ? \Illuminate\Support\Carbon::parse($lot['fecha_fabricacion'])->format('d/m/Y') : '—' }}</td>
                            <td>{{ \Illuminate\Support\Carbon::parse($lot['fecha_vencimiento'])->format('d/m/Y') }}</td>
                            <td><span class="badge {{ $expiryClass }}">{{ $expiryLabel }}</span></td>
                            <td><span class="badge {{ $availabilityClass }}">{{ $availabilityLabel }}</span></td>
                            <td>{{ $lot['fecha_recepcion'] ? \Illuminate\Support\Carbon::parse($lot['fecha_recepcion'])->format('d/m/Y H:i') : '—' }}</td>
                            <td>{{ $lot['ubicacion'] ?? ($lot['cantidad'] > 0 ? 'Sin ubicación registrada' : 'Lote histórico sin existencias') }}</td>
                            <td><strong>{{ \App\Support\Formatting\NumberFormat::format($lot['cantidad']) }}</strong></td>
                            <td>
                                @if ($product->activo && $lot['ubicacion_id'] && $lot['cantidad'] > 0 && ! in_array($lot['estado_disponibilidad'], ['PRODUCTO_INACTIVO', 'BLOQUEADO'], true) && $lot['condicion_vencimiento'] !== 'VENCIDO')
                                    <a class="btn btn-outline btn-small" href="{{ route('inventory.transfer.form', ['lote_id' => $lot['lote_id'], 'ubicacion_id' => $lot['ubicacion_id']]) }}">Traspasar</a>
                                @else
                                    —
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="9" class="empty-state">Este producto todavía no tiene lotes ingresados.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <x-record-pagination :paginator="$lots" />
    </section>
@endsection
