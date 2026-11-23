@extends('layouts.app')
@section('content')
    <x-page-header :title="$title" :description="$description">
        <x-slot:actions>
            @if ($screen === 'deliveries' && $canExport)
                <a class="btn btn-primary" href="{{ route('reports.export', request()->only(['desde', 'hasta', 'buscar_entrega']) + ['tipo' => 'entregas']) }}"><i class="fa-solid fa-file-csv"></i> Descargar CSV</a>
            @elseif ($screen === 'kardex' && $canExport)
                <a class="btn btn-primary" href="{{ route('reports.export', request()->only(['desde', 'hasta', 'buscar_kardex']) + ['tipo' => 'kardex']) }}"><i class="fa-solid fa-file-csv"></i> Imprimir CSV</a>
            @elseif ($screen !== 'controlled' && $screen !== 'deliveries')
                <button class="btn btn-outline" type="button" data-page-print><i class="fa-solid fa-print"></i> Imprimir / PDF</button>
            @endif
            @if ($screen === 'controlled' && $canExport)
                <a class="btn btn-primary" href="{{ route('reports.export', request()->only(['desde', 'hasta']) + ['tipo' => 'controlados']) }}"><i class="fa-solid fa-file-excel"></i> Exportar a Excel (CSV)</a>
            @endif
            @if ($screen === 'movements' && $canExport)
                <a class="btn btn-primary" href="{{ route('reports.export', request()->only(['desde', 'hasta']) + ['tipo' => 'movimientos']) }}"><i class="fa-solid fa-file-excel"></i> Exportar movimientos (CSV)</a>
            @endif
        </x-slot:actions>
    </x-page-header>

    @if ($screen === 'controlled' || $screen === 'kardex')
    <section class="card report-filters" id="filtro-reportes">
        <div class="card-header"><h2 class="card-title"><i class="fa-solid fa-filter text-primary"></i> Filtrar período</h2></div>
        <div class="card-body">
            <form action="{{ request()->url() }}" method="get" class="form-grid {{ $screen === 'kardex' ? 'report-kardex-filters' : '' }}">
                @if ($screen === 'kardex')
                    <div class="form-group"><label for="buscar-kardex">Buscar Kardex</label><input class="form-control" id="buscar-kardex" name="buscar_kardex" value="{{ $kardexFilters['buscar_kardex'] ?? '' }}" placeholder="Producto, lote, ubicación o documento"></div>
                @endif
                <div class="form-group"><label for="desde">Desde</label><input class="form-control" id="desde" name="desde" type="date" value="{{ $filters['desde'] ?? '' }}"></div>
                <div class="form-group"><label for="hasta">Hasta</label><input class="form-control" id="hasta" name="hasta" type="date" value="{{ $filters['hasta'] ?? '' }}"></div>
                <div class="form-actions"><button class="btn btn-primary" type="submit"><i class="fa-solid fa-magnifying-glass"></i> Aplicar filtro</button></div>
            </form>
        </div>
    </section>
    @endif

    @if ($screen === 'controlled')
    <section class="card report-table" id="movimientos-controlados">
        <div class="card-header"><h2 class="card-title"><i class="fa-solid fa-shield-halved text-primary"></i> Libro de medicamentos controlados</h2><span class="muted">{{ number_format($controlledMovements->total(), 0, ',', '.') }} movimientos · período seleccionado</span></div>
        <div class="table-responsive"><table class="data-table"><thead><tr><th>FECHA / HORA</th><th>PRODUCTO</th><th>LOTE</th><th>MOVIMIENTO</th><th>CANTIDAD / UNIDAD BASE</th><th>RESPONSABLE</th><th>AUTORIZA</th><th>FIRMA</th><th>OBSERVACIÓN</th></tr></thead><tbody>
            @forelse ($controlledMovements as $movement)
                <tr><td>{{ \Illuminate\Support\Carbon::parse($movement->fecha_hora)->format('d/m/Y H:i') }}</td><td>{{ $movement->producto }}</td><td>{{ $movement->numero_lote }}</td><td><span class="badge badge-info">{{ $movement->tipo_movimiento }}</span></td><td>{{ \App\Support\Formatting\NumberFormat::format($movement->cantidad) }} {{ $movement->unidad_base_codigo ?? 'unidad no registrada' }}</td><td>{{ $movement->responsable }}</td><td>{{ $movement->autorizador ?? '—' }}</td><td><span class="badge {{ $movement->firmado ? 'badge-success' : 'badge-warning' }}">{{ $movement->firmado ? 'Firmado' : 'Sin firma' }}</span></td><td>{{ $movement->observacion ?? '—' }}</td></tr>
            @empty<tr><td colspan="9" class="empty-state">No hay movimientos de medicamentos controlados en el período.</td></tr>@endforelse
        </tbody></table></div>
        <x-record-pagination :paginator="$controlledMovements" />
    </section>
    @endif

    @if ($screen === 'kardex')
    <section class="card report-table" id="kardex-inventario">
        <div class="card-header"><h2 class="card-title"><i class="fa-solid fa-box-archive text-primary"></i> Lotes agotados archivados</h2><span class="muted">{{ number_format($kardexEntries->total(), 0, ',', '.') }} registros</span></div>
        <div class="notice notice-info"><i class="fa-solid fa-circle-info"></i><span>Estos registros ya no forman parte del stock. Se archivaron al recepcionar nuevamente el mismo lote en la misma ubicación.</span></div>
        <div class="table-responsive"><table class="data-table">
            <thead><tr><th>FECHA ARCHIVO</th><th>PRODUCTO</th><th>LOTE</th><th>UBICACIÓN</th><th>DOCUMENTO DE RECEPCIÓN</th><th>ESTADO HISTÓRICO</th><th>RESPONSABLE</th></tr></thead>
            <tbody>
            @forelse ($kardexEntries as $entry)
                <tr>
                    <td>{{ \Illuminate\Support\Carbon::parse($entry->fecha_hora)->format('d/m/Y H:i') }}</td>
                    <td>{{ $entry->codigo_producto }} · {{ $entry->producto }}</td>
                    <td>{{ $entry->numero_lote }}</td>
                    <td>{{ $entry->ubicacion }}</td>
                    <td>{{ $entry->documento_recepcion ?? '—' }}@if ($entry->referencia_tipo) <small>{{ $entry->referencia_tipo }}@if ($entry->referencia_id) #{{ $entry->referencia_id }}@endif</small>@endif</td>
                    <td><span class="badge badge-info">{{ str_replace('_', ' ', $entry->motivo) }}</span> · Stock final {{ \App\Support\Formatting\NumberFormat::format($entry->cantidad_final) }}</td>
                    <td>{{ $entry->responsable ?? 'Migración de datos' }}</td>
                </tr>
            @empty
                <tr><td colspan="7" class="empty-state">No hay lotes históricos archivados para los filtros seleccionados.</td></tr>
            @endforelse
            </tbody>
        </table></div>
        <x-record-pagination :paginator="$kardexEntries" />
    </section>
    @endif

    @if ($screen === 'deliveries')
    <section class="card report-table" id="entregas-dispensaciones">
        <div class="card-header">
            <h2 class="card-title"><i class="fa-solid fa-prescription-bottle-medical text-primary"></i> Dispensaciones y entregas</h2>
            <span class="muted">
                {{ number_format($deliveries->total(), 0, ',', '.') }} líneas confirmadas
                @if (($filters['desde'] ?? null) || ($filters['hasta'] ?? null)) en el período seleccionado @endif
                · datos de pacientes
            </span>
        </div>
        <div class="card-body">
            <form action="{{ request()->url() }}" method="get" class="form-grid report-delivery-filters">
                <div class="form-group"><label for="buscar-entrega">Buscar entrega</label><input class="form-control" id="buscar-entrega" name="buscar_entrega" value="{{ $deliveryFilters['buscar_entrega'] ?? '' }}" placeholder="Paciente, folio, producto o lote"></div>
                <div class="form-group"><label for="desde">Desde</label><input class="form-control" id="desde" name="desde" type="date" value="{{ $filters['desde'] ?? '' }}"></div>
                <div class="form-group"><label for="hasta">Hasta</label><input class="form-control" id="hasta" name="hasta" type="date" value="{{ $filters['hasta'] ?? '' }}"></div>
                <div class="form-actions"><button class="btn btn-primary" type="submit"><i class="fa-solid fa-filter"></i> Aplicar filtro</button><a class="btn btn-outline" href="{{ request()->url() }}">Limpiar</a></div>
            </form>
        </div>
        <div class="table-responsive"><table class="data-table"><thead><tr><th>FECHA / HORA</th><th>FOLIO</th><th>PACIENTE</th><th>PRODUCTO</th><th>CÓDIGO</th><th>LOTE</th><th>CANTIDAD</th><th>RESPONSABLE</th></tr></thead><tbody>
            @forelse ($deliveries as $delivery)
                <tr><td>{{ \Illuminate\Support\Carbon::parse($delivery->fecha_hora)->format('d/m/Y H:i') }}</td><td>{{ $delivery->entrega_id }}</td><td>{{ $delivery->paciente }}</td><td>{{ $delivery->producto }}</td><td>{{ $delivery->codigo_producto }}</td><td>{{ $delivery->numero_lote }}</td><td>{{ \App\Support\Formatting\NumberFormat::format($delivery->cantidad) }} {{ $delivery->unidad_base_codigo ?? 'unidad no registrada' }}</td><td>{{ $delivery->responsable }}</td></tr>
            @empty<tr><td colspan="8" class="empty-state">No hay entregas confirmadas para este período y búsqueda.</td></tr>@endforelse
        </tbody></table></div>
        <x-record-pagination :paginator="$deliveries" />
    </section>
    @endif

    @if ($screen === 'stock')
    <section class="card" id="stock-por-categoria">
        <div class="card-header"><h2 class="card-title"><i class="fa-solid fa-chart-pie text-primary"></i> Productos con existencias por categoría</h2><span class="muted">{{ number_format($stockByCategory->total(), 0, ',', '.') }} categorías</span></div>
        <div class="card-body">
            <form action="{{ request()->url() }}" method="get" class="form-grid">
                <div class="form-group"><label for="buscar-categoria">Buscar categoría</label><input class="form-control" id="buscar-categoria" name="buscar_categoria" value="{{ $stockFilters['buscar_categoria'] ?? '' }}" placeholder="Nombre de la categoría"></div>
                <div class="form-group"><label for="estado-producto-categoria">Estado del producto</label><select class="form-control" id="estado-producto-categoria" name="estado_producto"><option value="activo" @selected(($stockFilters['estado_producto'] ?? 'activo') === 'activo')>Activos</option><option value="inactivo" @selected(($stockFilters['estado_producto'] ?? 'activo') === 'inactivo')>Inactivos</option><option value="todos" @selected(($stockFilters['estado_producto'] ?? 'activo') === 'todos')>Todos</option></select></div>
                <div class="form-group"><label for="disponibilidad-categoria">Disponibilidad</label><select class="form-control" id="disponibilidad-categoria" name="disponibilidad"><option value="" @selected(($stockFilters['disponibilidad'] ?? null) === null)>Todas</option><option value="con_existencias" @selected(($stockFilters['disponibilidad'] ?? null) === 'con_existencias')>Con existencias</option><option value="sin_existencias" @selected(($stockFilters['disponibilidad'] ?? null) === 'sin_existencias')>Sin existencias</option></select></div>
                <div class="form-actions"><button class="btn btn-primary" type="submit"><i class="fa-solid fa-magnifying-glass"></i> Buscar</button><a class="btn btn-outline" href="{{ request()->url() }}">Limpiar</a></div>
            </form>
        </div>
        <div class="table-responsive"><table class="data-table">
            <thead><tr><th>CATEGORÍA</th><th>DISPONIBILIDAD</th><th>PRODUCTOS CON EXISTENCIAS</th></tr></thead>
            <tbody>@forelse ($stockByCategory as $category)<tr><td>{{ $category->nombre }}</td><td><span class="badge {{ (int) $category->total > 0 ? 'badge-success' : 'badge-warning' }}">{{ (int) $category->total > 0 ? 'Con existencias' : 'Sin existencias' }}</span></td><td><strong>{{ \App\Support\Formatting\NumberFormat::format($category->total) }}</strong></td>@empty<tr><td colspan="3" class="empty-state">No se encontraron categorías con estos filtros.</td></tr>@endforelse</tbody>
        </table></div>
        <x-record-pagination :paginator="$stockByCategory" />
    </section>
    @endif

    @if ($screen === 'movements')
    <section class="card report-table" id="movimientos-inventario">
        <div class="card-header"><h2 class="card-title"><i class="fa-solid fa-clock-rotate-left text-primary"></i> Movimientos de inventario</h2><span class="muted">{{ number_format($movements->total(), 0, ',', '.') }} resultados · CSV disponible para el período completo</span></div>
        <div class="card-body">
            <form action="{{ request()->url() }}" method="get" class="form-grid report-movement-filters">
                <div class="form-group"><label for="buscar-movimiento">Buscar movimiento</label><input class="form-control" id="buscar-movimiento" name="buscar_movimiento" value="{{ $movementFilters['buscar_movimiento'] ?? '' }}" placeholder="Producto, código, lote, ubicación o responsable"></div>
                <div class="form-group"><label for="tipo-movimiento">Tipo de movimiento</label><select class="form-control" id="tipo-movimiento" name="tipo_movimiento"><option value="">Todos</option>@foreach (['RECEPCION' => 'Recepción', 'SALIDA' => 'Salida', 'TRASPASO_ENTRADA' => 'Traspaso de entrada', 'TRASPASO_SALIDA' => 'Traspaso de salida', 'AJUSTE_ENTRADA' => 'Ajuste de entrada', 'AJUSTE_SALIDA' => 'Ajuste de salida'] as $value => $label)<option value="{{ $value }}" @selected(($movementFilters['tipo_movimiento'] ?? null) === $value)>{{ $label }}</option>@endforeach</select></div>
                <div class="form-group"><label for="desde">Desde</label><input class="form-control" id="desde" name="desde" type="date" value="{{ $filters['desde'] ?? '' }}"></div>
                <div class="form-group"><label for="hasta">Hasta</label><input class="form-control" id="hasta" name="hasta" type="date" value="{{ $filters['hasta'] ?? '' }}"></div>
                <div class="form-actions"><button class="btn btn-primary" type="submit"><i class="fa-solid fa-magnifying-glass"></i> Buscar</button><a class="btn btn-outline" href="{{ request()->url() }}">Limpiar</a></div>
            </form>
        </div>
        <div class="table-responsive"><table class="data-table">
            <thead><tr><th>FECHA / HORA</th><th>PRODUCTO</th><th>CÓDIGO</th><th>LOTE</th><th>MOVIMIENTO</th><th>CANTIDAD / UNIDAD BASE</th><th>UBICACIÓN</th><th>RESPONSABLE</th><th>OBSERVACIÓN</th></tr></thead>
            <tbody>
            @forelse ($movements as $movement)
                <tr><td>{{ \Illuminate\Support\Carbon::parse($movement->fecha_hora)->format('d/m/Y H:i') }}</td><td>{{ $movement->producto }}</td><td>{{ $movement->codigo_producto }}</td><td>{{ $movement->numero_lote }}</td><td><span class="badge badge-info">{{ str_replace('_', ' ', $movement->tipo_movimiento) }}</span></td><td>{{ \App\Support\Formatting\NumberFormat::format($movement->cantidad) }} {{ $movement->unidad_base_codigo ?? 'unidad no registrada' }}@if ($movement->cantidad_original !== null)<small>Ingresado: {{ \App\Support\Formatting\NumberFormat::format($movement->cantidad_original) }} {{ $movement->unidad_ingresada }} · factor {{ \App\Support\Formatting\NumberFormat::format($movement->factor_conversion) }}</small>@endif</td><td>{{ $movement->ubicacion }}</td><td>{{ $movement->responsable }}</td><td>{{ $movement->observacion ?? '—' }}</td></tr>
            @empty
                <tr><td colspan="9" class="empty-state">No existen movimientos para los filtros seleccionados.</td></tr>
            @endforelse
            </tbody>
        </table></div>
        <x-record-pagination :paginator="$movements" />
    </section>
    @endif
@endsection
