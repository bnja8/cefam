@extends('layouts.app')
@section('content')
    <x-page-header :title="$screen === 'stock' ? 'Existencias en carros' : ($screen === 'usage' ? 'Uso del Carro de Paro' : ($screen === 'paro-create' ? 'Registrar uso de emergencia' : ($screen === 'treatment-create' ? 'Registrar tratamiento' : ($screen === 'unidosis-create' ? 'Preparar Unidosis' : 'Preparaciones Unidosis'))))" description="Existencias críticas, uso de emergencia y preparación trazable de dosis unitarias">
        <x-slot:actions>
            @if ($screen === 'stock')
                <a class="btn btn-outline" href="{{ route('carts.usage.index') }}">Historial de uso</a>
                <a class="btn btn-primary" href="{{ route('carts.paro.create') }}">Registrar salida</a>
            @elseif ($screen === 'preparations')
                <a class="btn btn-primary" href="{{ route('carts.unidosis.create') }}">Preparar Unidosis</a>
            @endif
        </x-slot:actions>
    </x-page-header>

    @if ($screen === 'stock')
    <section class="card" id="existencias-carros">
        <div class="card-header"><h2 class="card-title"><i class="fa-solid fa-truck-medical text-danger"></i> Consultar existencias en carros</h2><span class="muted">{{ number_format($stock->total(), 0, ',', '.') }} lotes</span></div>
        <div class="card-body">
            <form action="{{ route('carts.index') }}" method="get" class="form-grid">
                <div class="form-group"><label for="buscar-stock-carro">Buscar producto, lote o carro</label><input class="form-control" id="buscar-stock-carro" name="buscar_stock" value="{{ $stockFilters['buscar_stock'] ?? '' }}" placeholder="Nombre, código, lote o carro"></div>
                <div class="form-group"><label for="tipo-carro-filtro">Tipo de carro</label><select class="form-control" id="tipo-carro-filtro" name="tipo_carro"><option value="">Todos</option><option value="PARO" @selected(($stockFilters['tipo_carro'] ?? null) === 'PARO')>Carro de Paro</option><option value="UNIDOSIS" @selected(($stockFilters['tipo_carro'] ?? null) === 'UNIDOSIS')>Carro Unidosis</option></select></div>
                <div class="form-group"><label for="estado-producto-carro">Estado del producto</label><select class="form-control" id="estado-producto-carro" name="estado_producto"><option value="activo" @selected(($stockFilters['estado_producto'] ?? 'activo') === 'activo')>Activos</option><option value="inactivo" @selected(($stockFilters['estado_producto'] ?? 'activo') === 'inactivo')>Inactivos</option><option value="todos" @selected(($stockFilters['estado_producto'] ?? 'activo') === 'todos')>Todos</option></select></div>
                <div class="form-group"><label for="vence-desde-carro">Vencimiento desde</label><input class="form-control" id="vence-desde-carro" name="vence_desde" type="date" value="{{ $stockFilters['vence_desde'] ?? '' }}"></div>
                <div class="form-group"><label for="vence-hasta-carro">Vencimiento hasta</label><input class="form-control" id="vence-hasta-carro" name="vence_hasta" type="date" value="{{ $stockFilters['vence_hasta'] ?? '' }}"></div>
                <div class="form-actions"><button class="btn btn-primary" type="submit"><i class="fa-solid fa-magnifying-glass"></i> Buscar</button><a class="btn btn-outline" href="{{ route('carts.index') }}">Limpiar</a></div>
            </form>
        </div>
        <div class="table-responsive"><table class="data-table">
            <thead><tr><th>CARRO</th><th>MEDICAMENTO / INSUMO</th><th>ESTADO PRODUCTO</th><th>LOTE</th><th>VENCIMIENTO</th><th>DISPONIBLE</th></tr></thead>
            <tbody>
            @forelse ($stock as $item)
                <tr>
                    <td><span class="badge {{ $item->tipo_carro === 'PARO' ? 'badge-danger' : 'badge-info' }}">{{ $item->carro }}</span></td><td>{{ $item->producto }}</td><td><span class="badge {{ $item->producto_activo ? 'badge-success' : 'badge-danger' }}">{{ $item->producto_activo ? 'Activo' : 'Inactivo' }}</span></td><td>{{ $item->numero_lote }}</td>
                    <td>{{ \Illuminate\Support\Carbon::parse($item->fecha_vencimiento)->format('d/m/Y') }}</td><td><strong>{{ \App\Support\Formatting\NumberFormat::format($item->cantidad) }}</strong></td>
                </tr>
            @empty
                <tr><td colspan="6" class="empty-state">No se encontraron existencias con los filtros seleccionados.</td></tr>
            @endforelse
            </tbody>
        </table></div>
        <x-record-pagination :paginator="$stock" />
    </section>
    @endif

    @if (in_array($screen, ['paro-create', 'treatment-create', 'usage'], true))
    <div class="dashboard-grid">
        @if ($screen === 'paro-create')
        <section class="card" id="registrar-uso-paro">
            <div class="card-header"><h2 class="card-title"><i class="fa-solid fa-kit-medical text-danger"></i> Registrar uso del Carro de Paro</h2></div>
            <div class="card-body">
                @if ($paroLots->isEmpty())
                    <div class="notice notice-warning"><i class="fa-solid fa-circle-exclamation"></i><span>No hay lotes disponibles para uso. Repón el carro mediante un traspaso desde Inventario.</span></div>
                @else
                    <form action="{{ route('carts.paro.dispense') }}" method="post" class="stack-form">
                        @csrf
                        <div class="form-group"><label for="paro-lot">Insumo / lote *</label><select class="form-control" id="paro-lot" name="lote_id" required><option value="">Selecciona insumo</option>@foreach ($paroLots as $lot)<option value="{{ $lot->lote_id }}">{{ $lot->producto }} · {{ $lot->numero_lote }} · Disponible {{ \App\Support\Formatting\NumberFormat::format($lot->cantidad) }}</option>@endforeach</select></div>
                        <div class="form-group"><label for="paro-quantity">Cantidad utilizada *</label><input class="form-control" id="paro-quantity" name="cantidad" type="number" min="0.01" step="0.01" required></div>
                        <div class="form-group"><label for="paro-observation">Motivo / referencia clínica *</label><textarea class="form-control" id="paro-observation" name="observacion" rows="2" maxlength="1000" required></textarea></div>
                        <button class="btn btn-primary" type="submit"><i class="fa-solid fa-arrow-right-from-bracket"></i> Registrar salida de emergencia</button>
                    </form>
                @endif
            </div>
        </section>
        @endif
        @if ($screen === 'treatment-create')
        <section class="card" id="nuevo-tratamiento">
            <div class="card-header"><h2 class="card-title"><i class="fa-solid fa-notes-medical text-primary"></i> Registrar tratamiento</h2></div>
            <div class="card-body">
                @if ($activePatientCount === 0)
                    <div class="notice notice-warning"><i class="fa-solid fa-circle-exclamation"></i><span>Registra primero el paciente.</span></div>
                @else
                    <form action="{{ route('carts.treatments.store') }}" method="post" class="stack-form">
                        @csrf
                        <x-patient-search id="tratamiento-paciente" :selected-patient="$selectedPatient" required />
                        <div class="form-group"><label for="tratamiento-descripcion">Tratamiento / indicación *</label><textarea class="form-control" id="tratamiento-descripcion" name="descripcion" rows="2" required maxlength="2000"></textarea></div>
                        <div class="form-grid"><div class="form-group"><label for="tratamiento-inicio">Inicio *</label><input class="form-control" id="tratamiento-inicio" name="fecha_inicio" type="date" required></div><div class="form-group"><label for="tratamiento-fin">Término</label><input class="form-control" id="tratamiento-fin" name="fecha_termino" type="date"></div></div>
                        <button class="btn btn-outline" type="submit"><i class="fa-solid fa-floppy-disk"></i> Guardar tratamiento</button>
                    </form>
                @endif
            </div>
        </section>
        @endif

        @if ($screen === 'usage')
        <section class="card" id="uso-paro">
                <div class="card-header"><h2 class="card-title"><i class="fa-solid fa-clock-rotate-left text-danger"></i> Consultar uso del Carro de Paro</h2><span class="muted">{{ number_format($paroUsage->total(), 0, ',', '.') }} registros</span></div>
                <div class="card-body">
                    <form action="{{ route('carts.usage.index') }}" method="get" class="form-grid">
                        <div class="form-group"><label for="buscar-uso-paro">Buscar uso</label><input class="form-control" id="buscar-uso-paro" name="buscar_uso" value="{{ $usageFilters['buscar_uso'] ?? '' }}" placeholder="Producto, lote, responsable o motivo"></div>
                        <div class="form-group"><label for="uso-desde">Desde</label><input class="form-control" id="uso-desde" name="uso_desde" type="date" value="{{ $usageFilters['uso_desde'] ?? '' }}"></div>
                        <div class="form-group"><label for="uso-hasta">Hasta</label><input class="form-control" id="uso-hasta" name="uso_hasta" type="date" value="{{ $usageFilters['uso_hasta'] ?? '' }}"></div>
                        <div class="form-actions"><button class="btn btn-primary" type="submit"><i class="fa-solid fa-magnifying-glass"></i> Buscar</button><a class="btn btn-outline" href="{{ route('carts.usage.index') }}">Limpiar</a></div>
                    </form>
                </div>
                <div class="table-responsive"><table class="data-table">
                    <thead><tr><th>FECHA / HORA</th><th>CARRO</th><th>PRODUCTO</th><th>CÓDIGO</th><th>LOTE</th><th>CANTIDAD</th><th>RESPONSABLE</th><th>MOTIVO</th></tr></thead>
                    <tbody>
                    @forelse ($paroUsage as $usage)
                        <tr><td>{{ \Illuminate\Support\Carbon::parse($usage->fecha_hora)->format('d/m/Y H:i') }}</td><td>{{ $usage->carro }}</td><td>{{ $usage->producto }}</td><td>{{ $usage->codigo }}</td><td>{{ $usage->numero_lote }}</td><td>{{ \App\Support\Formatting\NumberFormat::format($usage->cantidad) }}</td><td>{{ $usage->responsable }}</td><td>{{ $usage->observacion }}</td></tr>
                    @empty
                        <tr><td colspan="8" class="empty-state">No se encontraron usos del Carro de Paro con estos filtros.</td></tr>
                    @endforelse
                    </tbody>
                </table></div>
                <x-record-pagination :paginator="$paroUsage" />
        </section>
        @endif
    </div>
    @endif

    @if ($screen === 'unidosis-create')
    <section class="card" id="preparacion-unidosis">
        <div class="card-header"><h2 class="card-title"><i class="fa-solid fa-pills text-primary"></i> Preparar Carro Unidosis</h2></div>
        <div class="card-body">
            @if (! $unidosisCart || $treatments->isEmpty() || $unidosisProducts->isEmpty())
                <div class="notice notice-info"><i class="fa-solid fa-circle-info"></i><span>Para preparar dosis se requiere un Carro Unidosis configurado, un tratamiento vigente y stock trasladado a ese carro desde Inventario.</span></div>
            @else
                <form action="{{ route('carts.unidosis.prepare') }}" method="post" class="stack-form">
                    @csrf
                    <div class="form-grid">
                        <div class="form-group"><label for="unidosis-tratamiento">Paciente / tratamiento *</label><select class="form-control" id="unidosis-tratamiento" name="tratamiento_id" required><option value="">Selecciona tratamiento</option>@foreach ($treatments as $treatment)<option value="{{ $treatment->tratamiento_id }}">{{ $treatment->paciente }} · {{ $treatment->descripcion }}</option>@endforeach</select></div>
                        <div class="form-group"><label for="unidosis-date">Fecha de preparación *</label><input class="form-control" id="unidosis-date" name="fecha_preparacion" type="date" min="{{ now()->toDateString() }}" required></div>
                        <div class="form-group"><label for="unidosis-period">Período *</label><select class="form-control" id="unidosis-period" name="periodo" required><option value="MANANA">Mañana</option><option value="TARDE">Tarde</option><option value="NOCHE">Noche</option><option value="24_HORAS">24 horas</option></select></div>
                    </div>
                    <div class="detail-heading"><h3>Medicamentos y horario</h3><button class="btn btn-outline btn-small" type="button" data-add-row="unidosis-details"><i class="fa-solid fa-plus"></i> Agregar medicamento</button></div>
                    <div id="unidosis-details" class="detail-rows" data-distinct-products-list>
                        <div class="detail-row request-row unidosis-detail-row">
                            <x-product-search
                                id="unidosis-product-0"
                                name="detalles[0][producto_id]"
                                search-url="{{ route('carts.unidosis.products.search') }}"
                                search-label="Medicamento *"
                                search-placeholder="Buscar medicamento por nombre"
                                search-help="Busca el medicamento disponible en el Carro Unidosis y selecciónalo."
                                :show-availability="false"
                                :show-result-details="false"
                            />
                            <div class="form-group"><label>Cantidad *</label><input class="form-control" name="detalles[0][cantidad]" type="number" min="0.01" step="0.01" required></div>
                            <div class="form-group"><label>Horario</label><input class="form-control" name="detalles[0][horario]" type="time" step="60"></div>
                            <button class="icon-btn detail-remove" type="button" aria-label="Eliminar medicamento" disabled><i class="fa-solid fa-trash"></i></button>
                        </div>
                    </div>
                    <div class="notice notice-info"><i class="fa-solid fa-calendar-check"></i><span>La salida se distribuye entre los lotes vigentes por prioridad FEFO. Se generan rótulos con paciente, medicamento, horario, lote y vencimiento.</span></div>
                    <button class="btn btn-primary" type="submit"><i class="fa-solid fa-box-open"></i> Crear preparación y descontar stock</button>
                </form>
                <template id="unidosis-detail-template">
                    <div class="detail-row request-row unidosis-detail-row">
                        <x-product-search
                            id="unidosis-product-template"
                            field-key="producto_id"
                            search-url="{{ route('carts.unidosis.products.search') }}"
                            search-label="Medicamento *"
                            search-placeholder="Buscar medicamento por nombre"
                            search-help="Busca el medicamento disponible en el Carro Unidosis y selecciónalo."
                            :show-availability="false"
                            :show-result-details="false"
                        />
                        <div class="form-group"><label>Cantidad *</label><input class="form-control" data-name="cantidad" type="number" min="0.01" step="0.01" required></div>
                        <div class="form-group"><label>Horario</label><input class="form-control" data-name="horario" type="time" step="60"></div>
                        <button class="icon-btn detail-remove" type="button" aria-label="Eliminar medicamento"><i class="fa-solid fa-trash"></i></button>
                    </div>
                </template>
            @endif
        </div>
    </section>
    @endif

    @if ($screen === 'preparations')
    <section class="card" id="preparaciones-unidosis">
        <div class="card-header"><h2 class="card-title"><i class="fa-solid fa-clipboard-check text-primary"></i> Preparaciones y rótulos unidosis</h2><span class="muted">{{ number_format($preparations->total(), 0, ',', '.') }} registros</span></div>
        <div class="card-body">
            <form action="{{ route('carts.unidosis.index') }}" method="get" class="form-grid">
                <div class="form-group"><label for="buscar-preparacion">Buscar preparación</label><input class="form-control" id="buscar-preparacion" name="buscar_preparacion" value="{{ $preparationFilters['buscar_preparacion'] ?? '' }}" placeholder="Paciente, tratamiento, carro o preparador"></div>
                <div class="form-group"><label for="estado-preparacion">Estado</label><select class="form-control" id="estado-preparacion" name="estado_preparacion"><option value="">Todos</option>@foreach (['PREPARANDO', 'CONFIRMADA', 'ANULADA'] as $state)<option value="{{ $state }}" @selected(($preparationFilters['estado_preparacion'] ?? null) === $state)>{{ $state }}</option>@endforeach</select></div>
                <div class="form-group"><label for="preparacion-desde">Desde</label><input class="form-control" id="preparacion-desde" name="preparacion_desde" type="date" value="{{ $preparationFilters['preparacion_desde'] ?? '' }}"></div>
                <div class="form-group"><label for="preparacion-hasta">Hasta</label><input class="form-control" id="preparacion-hasta" name="preparacion_hasta" type="date" value="{{ $preparationFilters['preparacion_hasta'] ?? '' }}"></div>
                <div class="form-actions"><button class="btn btn-primary" type="submit"><i class="fa-solid fa-magnifying-glass"></i> Buscar</button><a class="btn btn-outline" href="{{ route('carts.unidosis.index') }}">Limpiar</a></div>
            </form>
        </div>
        <div class="table-responsive"><table class="data-table">
            <thead><tr><th>FECHA / PERÍODO</th><th>PACIENTE</th><th>TRATAMIENTO</th><th>CARRO</th><th>PREPARA / CONFIRMA</th><th>ESTADO</th><th>RÓTULOS</th></tr></thead>
            <tbody>
            @forelse ($preparations as $preparation)
                <tr>
                    <td>{{ \Illuminate\Support\Carbon::parse($preparation->fecha_preparacion)->format('d/m/Y') }}<small class="table-subtitle">{{ $preparation->periodo }}</small></td>
                    <td>{{ $preparation->paciente }}</td><td>{{ $preparation->tratamiento }}</td><td>{{ $preparation->carro }}</td>
                    <td>{{ $preparation->preparador }}<small class="table-subtitle">Confirma: {{ $preparation->confirmador ?? 'Pendiente' }}</small></td>
                    <td>
                        <span class="badge {{ $preparation->estado === 'CONFIRMADA' ? 'badge-success' : 'badge-warning' }}">{{ $preparation->estado }}</span>
                        @if ($preparation->estado === 'PREPARANDO' && $isPharmacist)
                            <form action="{{ route('carts.unidosis.confirm', $preparation->preparacion_id) }}" method="post" class="table-action">@csrf @method('patch')<button class="btn btn-outline btn-small" type="submit">Confirmar QF</button></form>
                        @endif
                    </td>
                    <td><button class="btn btn-outline btn-small" type="button" data-page-print><i class="fa-solid fa-print"></i> Imprimir</button></td>
                </tr>
                @foreach ($preparation->labels as $label)
                    <tr class="label-row"><td colspan="7"><div class="unit-label"><strong>{{ $label->identificador }}</strong><span>{{ $label->contenido_generado }}</span></div></td></tr>
                @endforeach
            @empty
                <tr><td colspan="7" class="empty-state">No hay preparaciones de unidosis registradas.</td></tr>
            @endforelse
            </tbody>
        </table></div>
        <x-record-pagination :paginator="$preparations" />
    </section>
    @endif
@endsection
