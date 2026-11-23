@extends('layouts.app')
@section('content')
    <x-page-header :title="$screen === 'request-create' ? 'Solicitar reposición' : ($screen === 'receive' ? 'Recepcionar remesa' : ($screen === 'requests' ? 'Solicitudes de reposición' : 'Remesas y CENABAST'))" description="Recepción de abastecimiento con lote, fabricación, vencimiento y movimiento de bodega">
        <x-slot:actions>@if ($screen === 'shipments' && $canReceive)<a class="btn btn-primary" href="{{ route('supplies.receive.form') }}"><i class="fa-solid fa-truck-ramp-box"></i> Registrar recepción</a>@elseif ($screen === 'requests' && $canRequest)<a class="btn btn-primary" href="{{ route('supplies.requests.create') }}"><i class="fa-solid fa-file-circle-plus"></i> Solicitar reposición</a>@endif</x-slot:actions>
    </x-page-header>

    @if ($screen === 'shipments')
    <section class="card" id="remesas-recibidas">
        <div class="card-header"><h2 class="card-title"><i class="fa-solid fa-truck text-primary"></i> Consultar remesas</h2><span class="muted">{{ number_format($shipments->total(), 0, ',', '.') }} resultados</span></div>
        <div class="card-body">
            <form action="{{ route('supplies.index') }}" method="get" class="form-grid">
                <div class="form-group"><label for="buscar-remesa">Buscar remesa</label><input class="form-control" id="buscar-remesa" name="buscar_remesa" value="{{ $shipmentFilters['buscar_remesa'] ?? '' }}" placeholder="Código, origen, producto o responsable"></div>
                <div class="form-group"><label for="estado-remesa">Estado de solicitud</label><select class="form-control" id="estado-remesa" name="estado_solicitud"><option value="">Todos</option><option value="sin_solicitud" @selected(($shipmentFilters['estado_solicitud'] ?? null) === 'sin_solicitud')>Sin solicitud asociada</option>@foreach (['PENDIENTE', 'SOLICITADA', 'RECIBIDA', 'CANCELADA'] as $state)<option value="{{ $state }}" @selected(($shipmentFilters['estado_solicitud'] ?? null) === $state)>{{ $state }}</option>@endforeach</select></div>
                <div class="form-group"><label for="desde-remesa">Desde</label><input class="form-control" id="desde-remesa" name="desde_remesa" type="date" value="{{ $shipmentFilters['desde_remesa'] ?? '' }}"></div>
                <div class="form-group"><label for="hasta-remesa">Hasta</label><input class="form-control" id="hasta-remesa" name="hasta_remesa" type="date" value="{{ $shipmentFilters['hasta_remesa'] ?? '' }}"></div>
                <div class="form-actions"><button class="btn btn-primary" type="submit"><i class="fa-solid fa-magnifying-glass"></i> Buscar</button><a class="btn btn-outline" href="{{ route('supplies.index') }}">Limpiar</a></div>
            </form>
        </div>
        <div class="person-card-grid">
            @forelse ($shipments as $shipment)
                <x-person-card
                    :name="$shipment->codigo_remesa"
                    :subtitle="$shipment->origen"
                    :status="$shipment->estado_solicitud ?? 'Sin solicitud'"
                    :active="$shipment->estado_solicitud === 'RECIBIDA'"
                    :status-class="$shipment->estado_solicitud === 'RECIBIDA' ? 'badge-success' : ($shipment->estado_solicitud === 'CANCELADA' ? 'badge-danger' : ($shipment->estado_solicitud ? 'badge-warning' : 'badge-info'))"
                >
                    <x-slot:details>
                        <div class="person-card-meta-row">
                            <i class="fa-solid fa-calendar-days" aria-hidden="true"></i>
                            <span>Recepción <strong>{{ \Illuminate\Support\Carbon::parse($shipment->fecha_recepcion)->format('d/m/Y H:i') }}</strong></span>
                        </div>
                        <div class="person-card-meta-row">
                            <i class="fa-solid fa-boxes-stacked" aria-hidden="true"></i>
                            <span>{{ $shipment->lineas }} líneas · <strong>{{ $shipment->productos ?: '—' }}</strong></span>
                        </div>
                        <div class="person-card-meta-row">
                            <i class="fa-solid fa-user" aria-hidden="true"></i>
                            <span>Responsable <strong>{{ $shipment->responsable }}</strong></span>
                        </div>
                    </x-slot:details>
                </x-person-card>
            @empty
                <div class="empty-panel" role="status">
                    <span class="empty-icon"><i class="fa-regular fa-folder-open"></i></span>
                    <h2>No se encontraron remesas</h2>
                    <p>Prueba con otros términos de búsqueda o filtros.</p>
                </div>
            @endforelse
        </div>
        <x-record-pagination :paginator="$shipments" />
    </section>
    @endif

    @if ($screen === 'requests')
    <section class="card" id="solicitudes-reposicion">
        <div class="card-header"><h2 class="card-title"><i class="fa-solid fa-clipboard-list text-primary"></i> Consultar solicitudes</h2><span class="muted">{{ number_format($reorderRequests->total(), 0, ',', '.') }} resultados</span></div>
        <div class="card-body">
            <form action="{{ route('supplies.requests.index') }}" method="get" class="form-grid">
                <div class="form-group"><label for="buscar-solicitud">Buscar solicitud</label><input class="form-control" id="buscar-solicitud" name="buscar_solicitud" value="{{ $requestFilters['buscar_solicitud'] ?? '' }}" placeholder="Número, producto o responsable"></div>
                <div class="form-group"><label for="estado-solicitud-lista">Estado</label><select class="form-control" id="estado-solicitud-lista" name="estado_solicitud_lista"><option value="">Todos</option>@foreach (['PENDIENTE', 'SOLICITADA', 'RECIBIDA', 'CANCELADA'] as $state)<option value="{{ $state }}" @selected(($requestFilters['estado_solicitud_lista'] ?? null) === $state)>{{ $state }}</option>@endforeach</select></div>
                <div class="form-group"><label for="desde-solicitud">Desde</label><input class="form-control" id="desde-solicitud" name="desde_solicitud" type="date" value="{{ $requestFilters['desde_solicitud'] ?? '' }}"></div>
                <div class="form-group"><label for="hasta-solicitud">Hasta</label><input class="form-control" id="hasta-solicitud" name="hasta_solicitud" type="date" value="{{ $requestFilters['hasta_solicitud'] ?? '' }}"></div>
                <div class="form-actions"><button class="btn btn-primary" type="submit"><i class="fa-solid fa-magnifying-glass"></i> Buscar</button><a class="btn btn-outline" href="{{ route('supplies.requests.index') }}">Limpiar</a></div>
            </form>
        </div>
        <div class="person-card-grid">
            @forelse ($reorderRequests as $reorderRequest)
                <x-person-card
                    :name="'Solicitud #'.$reorderRequest->solicitud_id"
                    subtitle="Reposición de productos"
                    :status="$reorderRequest->estado"
                    :active="$reorderRequest->estado === 'RECIBIDA'"
                    :status-class="$reorderRequest->estado === 'RECIBIDA' ? 'badge-success' : ($reorderRequest->estado === 'CANCELADA' ? 'badge-danger' : 'badge-warning')"
                >
                    <x-slot:details>
                        <div class="person-card-meta-row">
                            <i class="fa-solid fa-calendar-days" aria-hidden="true"></i>
                            <span>Fecha <strong>{{ \Illuminate\Support\Carbon::parse($reorderRequest->fecha_solicitud)->format('d/m/Y H:i') }}</strong></span>
                        </div>
                        <div class="person-card-meta-row">
                            <i class="fa-solid fa-boxes-stacked" aria-hidden="true"></i>
                            <span>Productos <strong>{{ $reorderRequest->lineas }}</strong></span>
                        </div>
                        <div class="person-card-meta-row">
                            <i class="fa-solid fa-cubes-stacked" aria-hidden="true"></i>
                            <span>Unidades solicitadas <strong>{{ \App\Support\Formatting\NumberFormat::format($reorderRequest->unidades) }}</strong></span>
                        </div>
                        <div class="person-card-meta-row">
                            <i class="fa-solid fa-user" aria-hidden="true"></i>
                            <span>Responsable <strong>{{ $reorderRequest->responsable }}</strong></span>
                        </div>
                    </x-slot:details>
                </x-person-card>
            @empty
                <div class="empty-panel" role="status">
                    <span class="empty-icon"><i class="fa-regular fa-folder-open"></i></span>
                    <h2>No se encontraron solicitudes</h2>
                    <p>Prueba con otros términos de búsqueda o filtros.</p>
                </div>
            @endforelse
        </div>
        <x-record-pagination :paginator="$reorderRequests" />
    </section>
    @endif

    @if ($screen === 'request-create')
    <section class="card" id="solicitud-reposicion">
        <div class="card-header"><h2 class="card-title"><i class="fa-solid fa-cart-plus text-primary"></i> Crear solicitud de reposición</h2></div>
        <div class="card-body">
            <form action="{{ route('supplies.requests.store') }}" method="post" class="stack-form">
                @csrf
                <div class="detail-heading"><h3>Productos solicitados</h3><button class="btn btn-outline btn-small" type="button" data-add-row="shipment-request-details"><i class="fa-solid fa-plus"></i> Agregar producto</button></div>
                <div id="shipment-request-details" class="detail-rows" data-distinct-products-list>
                    <div class="detail-row request-row supply-detail-row">
                        <x-product-search
                            id="shipment-request-product-0"
                            name="detalles[0][producto_id]"
                            search-label="Producto *"
                            search-placeholder="Buscar producto por nombre"
                            search-help="La cantidad se expresa en la unidad base indicada para el producto."
                            :search-url="route('supplies.requests.products.search')"
                            field-class="supply-request-product"
                        />
                        <div class="form-group"><label>Cantidad solicitada *</label><input class="form-control" name="detalles[0][cantidad_solicitada]" type="number" min="0.01" step="0.01" value="{{ old('detalles.0.cantidad_solicitada') }}" required></div>
                        <button class="icon-btn detail-remove" type="button" aria-label="Eliminar producto" disabled><i class="fa-solid fa-trash"></i></button>
                    </div>
                </div>
                <button class="btn btn-outline" type="submit"><i class="fa-solid fa-paper-plane"></i> Crear solicitud</button>
            </form>
            <template id="shipment-request-detail-template">
                <div class="detail-row request-row supply-detail-row">
                    <x-product-search
                        id="shipment-request-product-template"
                        field-key="producto_id"
                        search-label="Producto *"
                        search-placeholder="Buscar producto por nombre"
                        search-help="La cantidad se expresa en la unidad base indicada para el producto."
                        :search-url="route('supplies.requests.products.search')"
                        :show-availability="false"
                        field-class="supply-request-product"
                    />
                    <div class="form-group"><label>Cantidad solicitada *</label><input class="form-control" data-name="cantidad_solicitada" type="number" min="0.01" step="0.01" required></div>
                    <button class="icon-btn detail-remove" type="button" aria-label="Eliminar producto"><i class="fa-solid fa-trash"></i></button>
                </div>
            </template>
        </div>
    </section>
    @endif

    @if ($screen === 'receive')
    <section class="card" id="recepcion-remesa">
        <div class="card-header"><h2 class="card-title"><i class="fa-solid fa-boxes-packing text-primary"></i> Recepcionar remesa</h2></div>
        <div class="card-body">
            @if ($locations->isEmpty() || $products->isEmpty())
                <div class="notice notice-warning"><i class="fa-solid fa-circle-exclamation"></i><span>Registra al menos una ubicación y un producto para poder recibir remesas.</span></div>
            @else
                <form action="{{ route('supplies.receive') }}" method="post" class="stack-form">
                    @csrf
                    <div class="form-grid">
                        <div class="form-group"><label for="codigo-remesa">Documento / código de remesa *</label><input class="form-control" id="codigo-remesa" name="codigo_remesa" value="{{ old('codigo_remesa') }}" maxlength="100" required placeholder="Factura, guía o código de remesa"></div>
                        <div class="form-group"><label for="origen-remesa">Proveedor / origen *</label><input class="form-control" id="origen-remesa" name="origen" value="{{ old('origen') }}" maxlength="150" required placeholder="CENABAST o proveedor"></div>
                        <div class="form-group"><label for="fecha-remesa">Fecha y hora de recepción *</label><input class="form-control" id="fecha-remesa" name="fecha_recepcion" type="datetime-local" max="{{ now()->format('Y-m-d\TH:i') }}" value="{{ now()->format('Y-m-d\TH:i') }}" required></div>
                        <div class="form-group"><label for="solicitud-remesa">Solicitud asociada</label><select class="form-control" id="solicitud-remesa" name="solicitud_id"><option value="">Sin solicitud</option>@foreach ($requests as $supplyRequest)<option value="{{ $supplyRequest->solicitud_id }}">#{{ $supplyRequest->solicitud_id }} · {{ $supplyRequest->estado }}</option>@endforeach</select></div>
                    </div>
                    <div class="notice notice-info"><i class="fa-solid fa-warehouse"></i><span>Selecciona la ubicación de destino para cada producto. Registra los envases y unidades realmente recibidos; el sistema los convertirá a la unidad de cada producto, aunque la cantidad difiera de la solicitud asociada.</span></div>
                    <div class="detail-heading"><h3>Productos recibidos</h3><button class="btn btn-outline btn-small" type="button" data-add-row="shipment-details"><i class="fa-solid fa-plus"></i> Agregar producto</button></div>
                    <x-barcode-scanner-test />
                    <div id="shipment-details" class="detail-rows">
                        <div class="detail-row shipment-row supply-detail-row">
                            <div class="form-group"><label>Producto *</label><select class="form-control" name="detalles[0][producto_id]" required><option value="">Selecciona</option>@foreach ($products as $product)<option value="{{ $product->producto_id }}">{{ $product->nombre }} · {{ $product->unidad_base_codigo }}</option>@endforeach</select></div>
                            <div class="form-group"><label>Lote *</label><input class="form-control" name="detalles[0][numero_lote]" maxlength="100" required></div>
                            <div class="form-group"><label>Fabricación</label><input class="form-control" name="detalles[0][fecha_fabricacion]" type="date" max="{{ now()->toDateString() }}"></div>
                            <div class="form-group"><label>Vencimiento *</label><input class="form-control" name="detalles[0][fecha_vencimiento]" type="date" min="{{ now()->toDateString() }}" required></div>
                            <div class="form-group"><label>Cantidad de envases recibidos *</label><input class="form-control" name="detalles[0][cantidad_envases]" type="number" min="1" step="1" value="0" required></div>
                            <div class="form-group"><label>Cantidad de unidades por envase *</label><input class="form-control" name="detalles[0][unidades_por_envase]" type="number" min="0.0001" step="0.0001" value="{{ old('detalles.0.unidades_por_envase') }}" required></div>
                            <div class="form-group"><label>Ubicación *</label><select class="form-control" name="detalles[0][ubicacion_id]" required><option value="">Selecciona ubicación</option>@foreach ($locations as $location)<option value="{{ $location->ubicacion_id }}" @selected(old('detalles.0.ubicacion_id') == $location->ubicacion_id)>{{ $location->nombre }}</option>@endforeach</select></div>
                            <button class="icon-btn detail-remove" type="button" aria-label="Eliminar producto" disabled><i class="fa-solid fa-trash"></i></button>
                        </div>
                    </div>
                    <button class="btn btn-primary" type="submit"><i class="fa-solid fa-circle-check"></i> Confirmar recepción</button>
                </form>
                <template id="shipment-detail-template">
                    <div class="detail-row shipment-row supply-detail-row">
                        <div class="form-group"><label>Producto *</label><select class="form-control" data-name="producto_id" required><option value="">Selecciona</option>@foreach ($products as $product)<option value="{{ $product->producto_id }}">{{ $product->nombre }} · {{ $product->unidad_base_codigo }}</option>@endforeach</select></div>
                        <div class="form-group"><label>Lote *</label><input class="form-control" data-name="numero_lote" maxlength="100" required></div>
                        <div class="form-group"><label>Fabricación</label><input class="form-control" data-name="fecha_fabricacion" type="date" max="{{ now()->toDateString() }}"></div>
                        <div class="form-group"><label>Vencimiento *</label><input class="form-control" data-name="fecha_vencimiento" type="date" min="{{ now()->toDateString() }}" required></div>
                        <div class="form-group"><label>Cantidad de envases recibidos *</label><input class="form-control" data-name="cantidad_envases" type="number" min="1" step="1" value="0" required></div>
                        <div class="form-group"><label>Cantidad de unidades por envase *</label><input class="form-control" data-name="unidades_por_envase" type="number" min="0.0001" step="0.0001" required></div>
                        <div class="form-group"><label>Ubicación *</label><select class="form-control" data-name="ubicacion_id" required><option value="">Selecciona ubicación</option>@foreach ($locations as $location)<option value="{{ $location->ubicacion_id }}">{{ $location->nombre }}</option>@endforeach</select></div>
                        <button class="icon-btn detail-remove" type="button" aria-label="Eliminar producto"><i class="fa-solid fa-trash"></i></button>
                    </div>
                </template>
            @endif
        </div>
    </section>
    @endif
@endsection
