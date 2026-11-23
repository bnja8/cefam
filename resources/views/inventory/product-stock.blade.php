@extends('layouts.app')
@section('content')
    @php
        $expiredQuantity = $stockByLocation->sum('stock_vencido');
        $blockedQuantity = $stockByLocation->sum('stock_bloqueado');
        $criticalLocationCount = $stockByLocation->where('es_stock_critico', true)->count();
    @endphp

    <div class="product-stock-page">
        <x-page-header :title="$product->nombre" description="Existencias por lote y ubicación, ordenadas según prioridad FEFO.">
            <x-slot:actions>
                @if ($canManageStockLimits)
                    <a class="btn btn-outline" href="#stock-por-ubicacion"><i class="fa-solid fa-sliders" aria-hidden="true"></i> Configurar mínimos y máximos</a>
                @endif
                @if ($canManageCatalog)
                    <a class="btn btn-outline" href="{{ route('products.edit', $product->producto_id) }}"><i class="fa-solid fa-pen" aria-hidden="true"></i> Editar producto</a>
                @endif
            </x-page-header>

            <div class="product-stock-navigation">
                <a class="btn btn-outline" href="{{ route('inventory.index') }}"><i class="fa-solid fa-arrow-left" aria-hidden="true"></i> Volver al inventario</a>
            </div>

            <div class="product-stock-context">
                <span class="product-stock-code"><i class="fa-solid fa-barcode" aria-hidden="true"></i>{{ $product->codigo_producto }}</span>
                <span>{{ $product->categoria }}</span>
                <span>Unidad base: <strong>{{ $product->unidad_base_codigo }}</strong></span>
                @if ($criticalLocationCount > 0)
                    <span class="badge badge-warning"><i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i> {{ $criticalLocationCount }} {{ $criticalLocationCount === 1 ? 'ubicación bajo mínimo' : 'ubicaciones bajo mínimo' }}</span>
                @else
                    <span class="badge badge-success"><i class="fa-solid fa-circle-check" aria-hidden="true"></i> Ubicaciones dentro de rango</span>
                @endif
            </div>

            <section class="product-stock-summary" aria-label="Resumen de existencias del producto">
                <article class="product-stock-summary-card">
                    <div class="product-stock-summary-label">
                        <span>Stock disponible</span>
                        <i class="fa-solid fa-boxes-stacked product-stock-summary-icon is-primary" aria-hidden="true"></i>
                    </div>
                    <div class="product-stock-summary-value">
                        <strong>{{ \App\Support\Formatting\NumberFormat::format($availableQuantity) }}</strong>
                        <span>{{ $product->unidad_base_codigo }}</span>
                    </div>
                    <p>Stock registrado: {{ \App\Support\Formatting\NumberFormat::format($totalQuantity) }} unidades físicas</p>
                </article>
                <article class="product-stock-summary-card">
                    <div class="product-stock-summary-label">
                        <span>Lotes registrados</span>
                        <i class="fa-solid fa-layer-group product-stock-summary-icon is-primary" aria-hidden="true"></i>
                    </div>
                    <div class="product-stock-summary-value">
                        <strong>{{ \App\Support\Formatting\NumberFormat::format($lotCount) }}</strong>
                        <span>{{ $lotCount === 1 ? 'lote' : 'lotes' }}</span>
                    </div>
                    <p>Identificados y ordenados por vencimiento</p>
                </article>
                <article class="product-stock-summary-card">
                    <div class="product-stock-summary-label">
                        <span>Stock vencido</span>
                        <i class="fa-solid fa-calendar-xmark product-stock-summary-icon is-danger" aria-hidden="true"></i>
                    </div>
                    <div class="product-stock-summary-value">
                        <strong>{{ \App\Support\Formatting\NumberFormat::format($expiredQuantity) }}</strong>
                        <span>{{ $product->unidad_base_codigo }}</span>
                    </div>
                    <p>Existencias no disponibles para dispensar</p>
                </article>
                <article class="product-stock-summary-card">
                    <div class="product-stock-summary-label">
                        <span>Stock bloqueado</span>
                        <i class="fa-solid fa-lock product-stock-summary-icon is-neutral" aria-hidden="true"></i>
                    </div>
                    <div class="product-stock-summary-value">
                        <strong>{{ \App\Support\Formatting\NumberFormat::format($blockedQuantity) }}</strong>
                        <span>{{ $product->unidad_base_codigo }}</span>
                    </div>
                    <p>Lotes inactivos o sin disponibilidad</p>
                </article>
            </section>

            <section class="card product-stock-section" id="lotes-producto" aria-labelledby="product-lots-title">
                <div class="card-header product-stock-section-header">
                    <div class="product-stock-section-heading">
                        <i class="fa-solid fa-layer-group" aria-hidden="true"></i>
                        <div>
                            <h2 class="card-title" id="product-lots-title">Lotes y existencias por ubicación</h2>
                            <p>Los lotes vigentes aparecen primero, ordenados por vencimiento. Los vencidos se muestran al final y no deben dispensarse.</p>
                        </div>
                        <span class="badge badge-info">FEFO: vence primero, sale primero</span>
                    </div>
                    <span class="product-stock-record-count">{{ number_format($lots->total(), 0, ',', '.') }} {{ $lots->total() === 1 ? 'registro' : 'registros' }}</span>
                </div>
                <div class="table-responsive">
                    <table class="data-table product-stock-lot-table" aria-label="Lotes y existencias por ubicación">
                        <thead>
                            <tr>
                                <th>LOTE</th>
                                <th>FABRICACIÓN</th>
                                <th>VENCIMIENTO</th>
                                <th>CONDICIÓN</th>
                                <th>DISPONIBILIDAD</th>
                                <th>RECEPCIÓN</th>
                                <th>UBICACIÓN</th>
                                <th>CANTIDAD</th>
                                <th>ACCIÓN</th>
                            </tr>
                        </thead>
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
                                    <td data-label="Lote"><strong>{{ $lot['numero_lote'] }}</strong></td>
                                    <td data-label="Fabricación">{{ $lot['fecha_fabricacion'] ? \Illuminate\Support\Carbon::parse($lot['fecha_fabricacion'])->format('d/m/Y') : '—' }}</td>
                                    <td data-label="Vencimiento">{{ \Illuminate\Support\Carbon::parse($lot['fecha_vencimiento'])->format('d/m/Y') }}</td>
                                    <td data-label="Condición"><span class="badge {{ $expiryClass }}">{{ $expiryLabel }}</span></td>
                                    <td data-label="Disponibilidad"><span class="badge {{ $availabilityClass }}">{{ $availabilityLabel }}</span></td>
                                    <td data-label="Recepción">{{ $lot['fecha_recepcion'] ? \Illuminate\Support\Carbon::parse($lot['fecha_recepcion'])->format('d/m/Y H:i') : '—' }}</td>
                                    <td data-label="Ubicación">{{ $lot['ubicacion'] ?? ($lot['cantidad'] > 0 ? 'Sin ubicación registrada' : 'Lote histórico sin existencias') }}</td>
                                    <td data-label="Cantidad"><strong>{{ \App\Support\Formatting\NumberFormat::format($lot['cantidad']) }}</strong></td>
                                    <td data-label="Acción">
                                        @if ($product->activo && $lot['ubicacion_id'] && $lot['cantidad'] > 0 && ! in_array($lot['estado_disponibilidad'], ['PRODUCTO_INACTIVO', 'BLOQUEADO'], true) && $lot['condicion_vencimiento'] !== 'VENCIDO')
                                            <a class="btn btn-outline btn-small" href="{{ route('inventory.transfer.form', ['lote_id' => $lot['lote_id'], 'ubicacion_id' => $lot['ubicacion_id']]) }}">Traspasar</a>
                                        @else
                                            <span aria-label="Sin acciones disponibles">—</span>
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

            <section class="card product-stock-section" id="stock-por-ubicacion" aria-labelledby="product-locations-title">
                <div class="card-header product-stock-section-header">
                    <div class="product-stock-section-heading">
                        <i class="fa-solid fa-warehouse" aria-hidden="true"></i>
                        <div>
                            <h2 class="card-title" id="product-locations-title">Stock agrupado por ubicación</h2>
                            <p>Control de existencias y umbrales operativos por punto de almacenamiento.</p>
                        </div>
                    </div>
                    <a class="btn btn-outline btn-small" href="{{ route('inventory.index') }}"><i class="fa-solid fa-arrow-left" aria-hidden="true"></i> Volver al inventario</a>
                </div>
                <div class="product-stock-section-intro">
                    Cantidades expresadas en <strong>{{ $product->unidad_base_codigo }}</strong>. El disponible excluye lotes vencidos o inactivos; el faltante indica cuánto se necesita para alcanzar el mínimo configurado. Se incluyen ubicaciones sin existencias.
                </div>
                <div class="table-responsive">
                    <table class="data-table product-stock-location-table {{ $canManageStockLimits ? 'has-actions' : '' }}" aria-label="Stock agrupado por ubicación">
                        <thead>
                            <tr>
                                <th>UBICACIÓN</th>
                                <th>REGISTRADO</th>
                                <th>DISPONIBLE</th>
                                <th>VENCIDO</th>
                                <th>BLOQUEADO</th>
                                <th>MÍNIMO</th>
                                <th>MÁXIMO</th>
                                <th>FALTANTE AL MÍNIMO</th>
                                <th>ESTADO</th>
                                @if ($canManageStockLimits)
                                    <th>CONFIGURAR</th>
                                @endif
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($stockByLocation as $stock)
                                <tr id="stock-ubicacion-{{ $stock->ubicacion_id }}">
                                    <td data-label="Ubicación"><strong>{{ $stock->ubicacion }}</strong></td>
                                    <td data-label="Registrado">{{ \App\Support\Formatting\NumberFormat::format($stock->stock_fisico) }}</td>
                                    <td data-label="Disponible"><strong>{{ \App\Support\Formatting\NumberFormat::format($stock->stock_disponible) }}</strong></td>
                                    <td data-label="Vencido">{{ \App\Support\Formatting\NumberFormat::format($stock->stock_vencido) }}</td>
                                    <td data-label="Bloqueado">{{ \App\Support\Formatting\NumberFormat::format($stock->stock_bloqueado) }}</td>
                                    <td data-label="Mínimo">{{ $stock->stock_minimo === null ? 'Sin configurar' : \App\Support\Formatting\NumberFormat::format($stock->stock_minimo) }}</td>
                                    <td data-label="Máximo">{{ $stock->stock_maximo === null ? 'Sin límite' : \App\Support\Formatting\NumberFormat::format($stock->stock_maximo) }}</td>
                                    <td data-label="Faltante al mínimo">{{ $stock->faltante_minimo === null ? '—' : \App\Support\Formatting\NumberFormat::format($stock->faltante_minimo) }}</td>
                                    <td data-label="Estado">
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
                                        <td data-label="Configurar">
                                            <form action="{{ route('products.stock-limits.update', $product->producto_id) }}" method="post" class="product-stock-limit-form">
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
        </div>
    @endsection
