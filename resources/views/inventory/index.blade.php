@extends('layouts.app')
@section('content')
    <x-page-header :title="$screen === 'stock' ? 'Inventario y Existencias' : ($screen === 'catalog' ? 'Catálogo de productos' : ($screen === 'categories' ? 'Categorías de productos' : ($screen === 'category-create' ? 'Nueva categoría' : ($screen === 'product-create' ? 'Agregar producto' : ($screen === 'transfer' ? 'Traspasar existencias' : ($screen === 'adjustments' ? 'Solicitudes de ajustes' : 'Solicitar ajuste'))))))" description="Control de existencias con trazabilidad por lote, ubicación y vencimiento">
        <x-slot:actions>
            @if ($screen === 'stock')
                @if ($canViewCategories)<a class="btn btn-outline" href="{{ route('categories.index') }}"><i class="fa-solid fa-tags"></i> Categorías</a>@endif
                @if ($canViewCategories)<a class="btn btn-outline" href="{{ route('locations.index') }}"><i class="fa-solid fa-location-dot"></i> Ubicaciones</a>@endif
                @if ($canViewProductCatalog)<a class="btn btn-outline" href="{{ route('inventory.catalog') }}"><i class="fa-solid fa-capsules"></i> Catálogo</a>@endif
                @if ($canTransferStock)<a class="btn btn-outline" href="{{ route('inventory.transfer.form') }}"><i class="fa-solid fa-right-left"></i> Trasladar stock</a>@endif
                @if ($canRequestAdjustments)<a class="btn btn-outline" href="{{ route('inventory.adjustments.index') }}"><i class="fa-solid fa-scale-balanced"></i> Solicitudes de ajuste</a>@endif
            @elseif ($screen === 'adjustments')
                <a class="btn btn-primary" href="{{ route('inventory.adjust.form') }}"><i class="fa-solid fa-plus"></i> Nueva solicitud</a>
            @elseif ($screen === 'adjust')
                <a class="btn btn-outline" href="{{ route('inventory.adjustments.index') }}"><i class="fa-solid fa-list"></i> Ver solicitudes</a>
            @elseif ($screen === 'categories' && $canManageCategories)
                <a class="btn btn-primary" href="{{ route('categories.create') }}"><i class="fa-solid fa-tags"></i> Nueva categoría</a>
            @elseif ($screen === 'catalog' && $canManageCatalog)
                <a class="btn btn-primary" href="{{ route('products.create') }}"><i class="fa-solid fa-capsules"></i> Nuevo producto</a>
            @endif
        </x-slot:actions>
    </x-page-header>

    @if ($screen === 'stock')
    <div class="inventory-principle">
        <span class="inventory-principle-icon" aria-hidden="true"><i class="fa-solid fa-layer-group"></i></span>
        <div>
            <strong>Un producto, varios lotes</strong>
            <p>Cada lote conserva su vencimiento y sus cantidades por ubicación. Las existencias se muestran agrupadas por producto y los lotes se gestionan con prioridad FEFO.</p>
        </div>
    </div>

    <section class="card inventory-card" id="existencias-inventario">
        <div class="card-header"><h2 class="card-title"><i class="fa-solid fa-boxes-stacked text-primary"></i> Existencias por medicamento / insumo</h2><span class="badge badge-info">Lotes ordenados por FEFO</span></div>
        <div class="inventory-toolbar">
            <label class="inventory-search" for="inventory-search">
                <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
                <input class="form-control" id="inventory-search" type="search" value="{{ $filters['buscar'] ?? '' }}" placeholder="Buscar producto o código..." autocomplete="off">
            </label>
            <span class="inventory-result-count muted" data-inventory-count>
                @if ($productStock->total() > 0)
                    Mostrando {{ $productStock->firstItem() }}–{{ $productStock->lastItem() }} de {{ $productStock->total() }} productos
                @else
                    0 productos
                @endif
            </span>
        </div>
        <p class="muted inventory-status" data-inventory-status role="status" aria-live="polite"></p>
        <div class="table-responsive">
            <table class="data-table searchable-table inventory-table" data-inventory-api="{{ route('inventory.api.index') }}">
                <thead><tr><th>PRODUCTO / CÓDIGO INTERNO</th><th>STOCK REGISTRADO</th><th>DISPONIBLE</th><th>VENCIDO</th><th>BLOQUEADO</th><th>PRÓXIMO A VENCER (30 DÍAS)</th><th>LOTES</th><th>ESTADO</th><th>ACCIÓN</th></tr></thead>
                <tbody id="inventory-product-rows">
                @forelse ($productStock as $item)
                    <tr>
                        <td><a class="text-link" href="{{ route('products.stock', $item->producto_id) }}"><strong>{{ $item->codigo_producto }} · {{ $item->producto }}</strong></a><small class="table-subtitle">{{ $item->cantidad_lotes }} {{ $item->cantidad_lotes === 1 ? 'lote' : 'lotes' }} · Stock contado en {{ $item->unidad_base_codigo ?? 'unidad sin definir' }} · Ver lotes y ubicaciones</small></td>
                        <td>{{ \App\Support\Formatting\NumberFormat::format($item->cantidad_total) }}</td>
                        <td><strong>{{ \App\Support\Formatting\NumberFormat::format($item->cantidad_disponible) }}</strong></td>
                        <td>{{ \App\Support\Formatting\NumberFormat::format($item->cantidad_vencida) }}</td>
                        <td>{{ \App\Support\Formatting\NumberFormat::format($item->cantidad_bloqueada) }}</td>
                        <td>
                            @if ($item->proximo_lote)
                                <strong>{{ $item->proximo_lote }}</strong>
                                <small class="table-subtitle">{{ \Illuminate\Support\Carbon::parse($item->proximo_vencimiento)->format('d/m/Y') }}</small>
                            @else
                                <span class="muted">—</span>
                            @endif
                        </td>
                        <td><span class="inventory-count-pill">{{ $item->cantidad_lotes }}</span></td>
                        <td><span class="badge {{ ! $item->activo ? 'badge-danger' : ((float) $item->cantidad_disponible > 0 ? 'badge-success' : 'badge-warning') }}">{{ ! $item->activo ? 'Producto inactivo' : ((float) $item->cantidad_disponible > 0 ? 'Disponible' : 'Sin stock vigente') }}</span></td>
                        <td><a class="btn btn-outline btn-small" href="{{ route('products.stock', $item->producto_id) }}">Ver lotes</a></td>
                    </tr>
                @empty
                    <tr><td colspan="9" class="empty-state">Todavía no hay productos en el inventario. Registra una recepción para comenzar.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="pagination-wrap inventory-pagination" data-inventory-pagination data-current-page="{{ $productStock->currentPage() }}" data-last-page="{{ $productStock->lastPage() }}">
            <button class="btn btn-outline btn-small" type="button" data-inventory-previous @disabled($productStock->onFirstPage())>Anterior</button>
            <span class="muted" data-inventory-page-label>Página {{ $productStock->currentPage() }} de {{ $productStock->lastPage() }}</span>
            <button class="btn btn-outline btn-small" type="button" data-inventory-next @disabled(! $productStock->hasMorePages())>Siguiente</button>
        </div>
    </section>
    @endif

    @if ($screen === 'catalog' && $canViewCatalog)
    <section class="card inventory-card" id="catalogo-productos">
        <div class="card-header"><h2 class="card-title"><i class="fa-solid fa-capsules text-primary"></i> Productos del catálogo</h2><span class="muted">{{ $catalogProducts->total() }} productos</span></div>
        <div class="table-responsive">
            <table class="data-table searchable-table">
                <thead><tr><th>CÓDIGO</th><th>PRODUCTO</th><th>PRESENTACIONES COMERCIALES</th><th>TIPO</th><th>ESPECIFICACIÓN</th><th>CATEGORÍA</th><th>CÓDIGO DE BARRAS</th><th>CONTROLADO</th><th>ESTADO</th>@if ($canManageCatalog)<th>ACCIÓN</th>@endif</tr></thead>
                <tbody>
                    @forelse ($catalogProducts as $product)
                        <tr>
                            <td>{{ $product->numero_serie }}</td><td><strong>{{ $product->nombre }}</strong></td>
                            <td>{{ $product->presentaciones_comerciales === [] ? '—' : implode(', ', $product->presentaciones_comerciales) }}</td>
                            <td>{{ ['MEDICAMENTO' => 'Medicamento', 'INSUMO_CLINICO' => 'Insumo clínico', 'DISPOSITIVO_MEDICO' => 'Dispositivo médico'][$product->tipo_producto] ?? 'Sin clasificar' }}</td>
                            <td>
                                @if ($product->es_medicamento)
                                    <strong>{{ $product->principios_activos ?: 'Principio activo pendiente' }}</strong>
                                    <small>{{ $product->forma_farmaceutica ?? 'Forma sin definir' }} · {{ $product->contenido_envase_cantidad !== null ? rtrim(rtrim(number_format((float) $product->contenido_envase_cantidad, 3, '.', ''), '0'), '.') : 'Contenido sin definir' }} {{ $product->unidad_contenido ?? '' }} · {{ $product->tipo_presentacion ?? 'Presentación sin definir' }}</small>
                                @else
                                    {{ $product->tipo_producto === 'DISPOSITIVO_MEDICO' ? 'Dispositivo médico' : 'Insumo clínico' }}
                                @endif
                                <small>Stock en: {{ $product->unidad_base_inventario ?? 'Sin definir' }}@if ($product->presentaciones_resumen) · Envases: {{ $product->presentaciones_resumen }}@endif</small>
                            </td>
                            <td>{{ $product->categoria }}</td>
                            <td>{{ $product->codigo_barra ?? '—' }}</td><td>{{ $product->es_controlado ? 'Sí' : 'No' }}</td>
                            <td><span class="badge {{ $product->activo ? 'badge-success' : 'badge-danger' }}">{{ $product->activo ? 'Activo' : 'Inactivo' }}</span></td>
                            @if ($canManageCatalog)<td><a class="btn btn-outline btn-small" href="{{ route('products.edit', $product->producto_id) }}">Editar</a></td>@endif
                        </tr>
                    @empty
                        <tr><td colspan="{{ $canManageCatalog ? 10 : 9 }}" class="empty-state">No hay productos en el catálogo.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <x-record-pagination :paginator="$catalogProducts" />
    </section>
    @endif

    @if ($screen === 'categories' || ($screen === 'category-create' && $canManageCategories))
    <div class="dashboard-grid">
        @if ($screen === 'categories' || $canManageCategories)
        <section class="card" id="categorias">
            <div class="card-header"><h2 class="card-title"><i class="fa-solid fa-tags text-primary"></i> Categorías de productos</h2>@if ($screen === 'categories')<span class="muted">{{ number_format($categories->total(), 0, ',', '.') }} resultados</span>@endif</div>
            <div class="card-body">
                @if ($screen === 'category-create')
                <form action="{{ route('categories.store') }}" method="post" class="stack-form">
                    @csrf
                    <div class="form-group"><label for="nombre_categoria">Nombre de categoría *</label><input class="form-control" id="nombre_categoria" name="nombre_categoria" value="{{ old('nombre_categoria') }}" required maxlength="100"></div>
                    <div class="form-group"><label for="descripcion_categoria">Descripción</label><textarea class="form-control" id="descripcion_categoria" name="descripcion_categoria" rows="2" maxlength="255">{{ old('descripcion_categoria') }}</textarea></div>
                    @error('nombre_categoria')<small class="field-error">{{ $message }}</small>@enderror
                    @error('descripcion_categoria')<small class="field-error">{{ $message }}</small>@enderror
                    <button class="btn btn-outline" type="submit"><i class="fa-solid fa-plus"></i> Agregar categoría</button>
                </form>
                @endif
                @if ($screen === 'categories')
                <div class="table-responsive">
                    <table class="data-table">
                        <thead><tr><th>Categoría disponible</th>@if ($canManageCategories)<th>ACCIÓN</th>@endif</tr></thead>
                        <tbody>
                            @foreach ($categories as $category)
                                <tr>
                                    <td>{{ $category->nombre }}</td>
                                    @if ($canManageCategories)<td class="table-actions-column"><a class="btn btn-outline btn-small" href="{{ route('categories.edit', $category->categoria_id) }}">Editar</a></td>@endif
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <x-record-pagination :paginator="$categories" />
                @endif
            </div>
        </section>
        @endif
    </div>
    @endif

    @if ($screen === 'product-create' && $canManageCatalog)
    <div class="dashboard-grid">
        <section class="card" id="producto">
            <div class="card-header"><h2 class="card-title"><i class="fa-solid fa-capsules text-primary"></i> Agregar producto al catálogo</h2></div>
            <div class="card-body">
                <form action="{{ route('products.store') }}" method="post" class="form-grid" data-product-catalog-form>
                    @csrf
                    @include('inventory.partials.product-form-fields', ['ingredients' => []])
                    <div class="form-actions"><button class="btn btn-outline" type="submit"><i class="fa-solid fa-plus"></i> Guardar producto</button></div>
                </form>
            </div>
        </section>
    </div>
    @endif

    @if ($screen === 'transfer')
        <section class="card" id="traspaso">
            <div class="card-header"><h2 class="card-title"><i class="fa-solid fa-right-left text-primary"></i> Traspaso entre ubicaciones</h2></div>
            <div class="card-body">
                <form action="{{ route('inventory.transfer') }}" method="post" class="stack-form">
                    @csrf
                    <div class="form-group"><label for="lote_id">Producto / lote disponible *</label><select class="form-control" id="lote_id" name="lote_id" required><option value="">Selecciona lote</option>@foreach ($lots as $lot)<option value="{{ $lot->lote_id }}" data-location="{{ $lot->ubicacion_id }}" @selected(old('lote_id', request('lote_id')) == $lot->lote_id)>{{ $lot->producto }} · {{ $lot->numero_lote }} · {{ $lot->ubicacion }} ({{ \App\Support\Formatting\NumberFormat::format($lot->cantidad) }} {{ $lot->unidad_base_codigo }})</option>@endforeach</select></div>
                    <div class="form-group"><label for="origen_id">Desde *</label><select class="form-control" id="origen_id" name="origen_id" required><option value="">Ubicación de origen</option>@foreach ($locations as $location)<option value="{{ $location->ubicacion_id }}" @selected(old('origen_id', request('ubicacion_id')) == $location->ubicacion_id)>{{ $location->nombre }}</option>@endforeach</select></div>
                    <div class="form-group"><label for="destino_id">Hacia *</label><select class="form-control" id="destino_id" name="destino_id" required><option value="">Ubicación de destino</option>@foreach ($locations as $location)<option value="{{ $location->ubicacion_id }}" @selected(old('destino_id') == $location->ubicacion_id)>{{ $location->nombre }}</option>@endforeach</select></div>
                    <div class="form-group"><label for="transfer-cantidad">Cantidad en la unidad del producto *</label><input class="form-control" id="transfer-cantidad" name="cantidad" type="number" min="0.01" step="0.01" value="{{ old('cantidad') }}" required></div>
                    <div class="form-group"><label for="observacion">Observación</label><input class="form-control" id="observacion" name="observacion" value="{{ old('observacion') }}" maxlength="1000"></div>
                    <button class="btn btn-outline" type="submit"><i class="fa-solid fa-right-left"></i> Registrar traspaso</button>
                </form>
            </div>
        </section>
    @endif

    @if ($screen === 'adjustments')
        <section class="card" id="solicitudes-ajuste">
            <div class="card-header"><h2 class="card-title"><i class="fa-solid fa-clipboard-list text-primary"></i> Seguimiento y autorización</h2><span class="badge badge-info">{{ $adjustmentRequests->total() }} solicitudes</span></div>
            <div class="card-body">
                @if ($canAuthorizeAdjustments)
                    <div class="notice notice-info"><i class="fa-solid fa-user-shield"></i><span>Las solicitudes de un QF deben ser autorizadas por otro QF. Autorizar o rechazar afecta todas las líneas de la solicitud; la autorización aplica cada ajuste al stock y queda vinculada a su movimiento.</span></div>
                @else
                    <div class="notice notice-info"><i class="fa-solid fa-clock"></i><span>Las solicitudes quedan pendientes y no modifican existencias hasta que un Químico Farmacéutico las autorice.</span></div>
                @endif
                <div class="table-responsive">
                    <table class="data-table">
                        <thead><tr><th>FECHA</th><th>PRODUCTO / LOTE</th><th>UBICACIÓN</th><th>AJUSTE</th><th>SOLICITANTE</th><th>ESTADO / RESOLUCIÓN</th><th>ACCIÓN</th></tr></thead>
                        <tbody>
                            @forelse ($adjustmentRequests as $adjustmentRequest)
                                <tr>
                                    <td>{{ \Illuminate\Support\Carbon::parse($adjustmentRequest->fecha_solicitud)->format('d/m/Y H:i') }}</td>
                                    <td>
                                        @foreach ($adjustmentRequest->detalles as $detail)
                                            <div class="adjustment-request-item">
                                                <strong>{{ $detail->producto }}</strong>
                                                <small class="table-subtitle">Lote {{ $detail->numero_lote }} · {{ $detail->motivo }}</small>
                                                @if ($detail->movimiento_id)<small class="table-subtitle">Movimiento #{{ $detail->movimiento_id }}</small>@endif
                                            </div>
                                        @endforeach
                                    </td>
                                    <td>
                                        @foreach ($adjustmentRequest->detalles as $detail)
                                            <div class="adjustment-request-item">{{ $detail->ubicacion }}</div>
                                        @endforeach
                                    </td>
                                    <td>
                                        @foreach ($adjustmentRequest->detalles as $detail)
                                            <div class="adjustment-request-item">{{ $detail->tipo_ajuste === 'ENTRADA' ? 'Entrada' : 'Salida' }} · {{ \App\Support\Formatting\NumberFormat::format($detail->cantidad) }}</div>
                                        @endforeach
                                    </td>
                                    <td>{{ $adjustmentRequest->solicitante }}</td>
                                    <td>
                                        <span class="badge {{ $adjustmentRequest->estado === 'APROBADA' ? 'badge-success' : ($adjustmentRequest->estado === 'RECHAZADA' ? 'badge-danger' : 'badge-warning') }}">{{ ucfirst(strtolower($adjustmentRequest->estado)) }}</span>
                                        @if ($adjustmentRequest->estado !== 'PENDIENTE')
                                            <small class="table-subtitle">{{ $adjustmentRequest->autorizador }}@if ($adjustmentRequest->motivo_resolucion) · {{ $adjustmentRequest->motivo_resolucion }}@endif</small>
                                        @endif
                                    </td>
                                    <td class="table-actions-column">
                                        <a class="btn btn-outline btn-small" href="{{ route('inventory.adjustments.show', $adjustmentRequest->grupo_ajuste_id) }}">
                                            <i class="fa-solid fa-eye"></i> Ver solicitud
                                        </a>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="7" class="empty-state">No hay solicitudes de ajuste registradas.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <x-record-pagination :paginator="$adjustmentRequests" />
            </div>
        </section>
    @endif

    @if ($screen === 'adjust')
        <section class="card" id="ajuste">
            <div class="card-header"><h2 class="card-title"><i class="fa-solid fa-scale-balanced text-primary"></i> Solicitar ajuste de existencias</h2></div>
            <div class="card-body">
                <div class="notice notice-info"><i class="fa-solid fa-circle-info"></i><span>La solicitud no cambia el stock hasta su autorización por un QF. Si la solicita un QF, debe autorizarla otro QF. Toda decisión queda registrada; una salida no puede dejar stock negativo.</span></div>
                <form action="{{ route('inventory.adjust') }}" method="post" class="stack-form">
                    @csrf
                    <div class="detail-heading">
                        <h3>Productos del ajuste</h3>
                        <button class="btn btn-outline btn-small" type="button" data-add-row="adjustment-details"><i class="fa-solid fa-plus"></i> Agregar medicamento</button>
                    </div>
                    @error('detalles')<small class="field-error">{{ $message }}</small>@enderror
                    <div id="adjustment-details" class="detail-rows" data-adjustment-details data-next-index="{{ ($adjustmentDetails->keys()->max() ?? -1) + 1 }}">
                        @foreach ($adjustmentDetails as $index => $detail)
                            <div class="detail-row inventory-adjustment-detail-row" data-adjustment-row>
                                <div class="adjustment-field-product">
                                    <x-product-search
                                        id="adjustment-product-{{ $index }}"
                                        name="detalles[{{ $index }}][producto_id]"
                                        field-class="adjustment-product-picker"
                                        search-url="{{ route('recipes.products.search') }}"
                                        search-label="Buscar medicamento *"
                                        search-placeholder="Buscar por nombre"
                                        search-help="Escribe el nombre del medicamento y selecciona un resultado."
                                        :product="$detail['product']"
                                        :show-availability="false"
                                    />
                                    @error("detalles.$index.producto_id")<small class="field-error">{{ $message }}</small>@enderror
                                </div>
                                <div class="form-group adjustment-field-lot">
                                    <label for="adjustment-lot-{{ $index }}" data-adjustment-label="lot">Lote *</label>
                                    <select class="form-control" id="adjustment-lot-{{ $index }}" name="detalles[{{ $index }}][lote_id]" data-adjustment-field="lot" data-adjustment-lot data-selected-lot="{{ $detail['lote_id'] ?? '' }}" required>
                                        <option value="">Selecciona lote</option>
                                    </select>
                                    @error("detalles.$index.lote_id")<small class="field-error">{{ $message }}</small>@enderror
                                    <small data-adjustment-lot-stock aria-live="polite"></small>
                                </div>
                                <div class="form-group adjustment-field-quantity">
                                    <label for="adjustment-quantity-{{ $index }}" data-adjustment-label="quantity">Cantidad *</label>
                                    <input class="form-control" id="adjustment-quantity-{{ $index }}" name="detalles[{{ $index }}][cantidad]" data-adjustment-field="quantity" type="number" min="0.01" step="0.01" value="{{ $detail['cantidad'] ?? '' }}" required>
                                    @error("detalles.$index.cantidad")<small class="field-error">{{ $message }}</small>@enderror
                                </div>
                                <div class="form-group adjustment-field-location">
                                    <label for="adjustment-location-{{ $index }}" data-adjustment-label="location">Ubicación *</label>
                                    <select class="form-control" id="adjustment-location-{{ $index }}" name="detalles[{{ $index }}][ubicacion_id]" data-adjustment-field="location" data-adjustment-location required>
                                        <option value="">Selecciona ubicación</option>
                                        @foreach ($locations as $location)
                                            <option value="{{ $location->ubicacion_id }}" @selected((string) ($detail['ubicacion_id'] ?? '') === (string) $location->ubicacion_id)>{{ $location->nombre }}</option>
                                        @endforeach
                                    </select>
                                    @error("detalles.$index.ubicacion_id")<small class="field-error">{{ $message }}</small>@enderror
                                </div>
                                <div class="form-group adjustment-field-type">
                                    <label for="adjustment-type-{{ $index }}" data-adjustment-label="type">Tipo de ajuste *</label>
                                    <select class="form-control" id="adjustment-type-{{ $index }}" name="detalles[{{ $index }}][tipo_ajuste]" data-adjustment-field="type" required>
                                        <option value="">Selecciona</option>
                                        <option value="ENTRADA" @selected(($detail['tipo_ajuste'] ?? '') === 'ENTRADA')>Entrada por corrección</option>
                                        <option value="SALIDA" @selected(($detail['tipo_ajuste'] ?? '') === 'SALIDA')>Salida por diferencia o merma</option>
                                    </select>
                                    @error("detalles.$index.tipo_ajuste")<small class="field-error">{{ $message }}</small>@enderror
                                </div>
                                <div class="form-group adjustment-field-reason">
                                    <label for="adjustment-reason-{{ $index }}" data-adjustment-label="reason">Motivo *</label>
                                    <textarea class="form-control" id="adjustment-reason-{{ $index }}" name="detalles[{{ $index }}][motivo]" data-adjustment-field="reason" rows="2" maxlength="1000" required placeholder="Conteo físico, deterioro, pérdida...">{{ $detail['motivo'] ?? '' }}</textarea>
                                    @error("detalles.$index.motivo")<small class="field-error">{{ $message }}</small>@enderror
                                </div>
                                <button class="icon-btn detail-remove adjustment-field-remove" type="button" aria-label="Eliminar medicamento" @disabled($index === 0)><i class="fa-solid fa-trash"></i></button>
                            </div>
                        @endforeach
                    </div>
                    <div class="form-actions"><button class="btn btn-primary" type="submit"><i class="fa-solid fa-paper-plane"></i> Enviar solicitud</button></div>
                </form>
            </div>
        </section>
        <template id="adjustment-detail-template">
            <div class="detail-row inventory-adjustment-detail-row" data-adjustment-row>
                <div class="adjustment-field-product">
                    <x-product-search
                        id="adjustment-product-template"
                        field-key="producto_id"
                        field-class="adjustment-product-picker"
                        search-url="{{ route('recipes.products.search') }}"
                        search-label="Buscar medicamento *"
                        search-placeholder="Buscar por nombre"
                        search-help="Escribe el nombre del medicamento y selecciona un resultado."
                        :show-availability="false"
                    />
                </div>
                <div class="form-group adjustment-field-lot">
                    <label data-adjustment-label="lot">Lote *</label>
                    <select class="form-control" data-name="lote_id" data-adjustment-field="lot" data-adjustment-lot required>
                        <option value="">Selecciona lote</option>
                    </select>
                    <small data-adjustment-lot-stock aria-live="polite"></small>
                </div>
                <div class="form-group adjustment-field-quantity">
                    <label data-adjustment-label="quantity">Cantidad *</label>
                    <input class="form-control" data-name="cantidad" data-adjustment-field="quantity" type="number" min="0.01" step="0.01" required>
                </div>
                <div class="form-group adjustment-field-location">
                    <label data-adjustment-label="location">Ubicación *</label>
                    <select class="form-control" data-name="ubicacion_id" data-adjustment-field="location" data-adjustment-location required>
                        <option value="">Selecciona ubicación</option>
                        @foreach ($locations as $location)
                            <option value="{{ $location->ubicacion_id }}">{{ $location->nombre }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group adjustment-field-type">
                    <label data-adjustment-label="type">Tipo de ajuste *</label>
                    <select class="form-control" data-name="tipo_ajuste" data-adjustment-field="type" required>
                        <option value="">Selecciona</option>
                        <option value="ENTRADA">Entrada por corrección</option>
                        <option value="SALIDA">Salida por diferencia o merma</option>
                    </select>
                </div>
                <div class="form-group adjustment-field-reason">
                    <label data-adjustment-label="reason">Motivo *</label>
                    <textarea class="form-control" data-name="motivo" data-adjustment-field="reason" rows="2" maxlength="1000" required placeholder="Conteo físico, deterioro, pérdida..."></textarea>
                </div>
                <button class="icon-btn detail-remove adjustment-field-remove" type="button" aria-label="Eliminar medicamento"><i class="fa-solid fa-trash"></i></button>
            </div>
        </template>
        <template id="adjustment-lot-options">
            @foreach ($adjustmentLots as $lot)
                <option value="{{ $lot->lote_id }}" data-product="{{ $lot->producto_id }}" data-stock="{{ $lot->existencia_total }}" data-stock-by-location="{{ json_encode($lot->existencia_por_ubicacion, JSON_HEX_APOS | JSON_HEX_QUOT) }}" data-unit="{{ $lot->unidad_base_codigo }}">{{ $lot->numero_lote }} · {{ $lot->estado_lote === 'ACTIVO' ? 'Lote activo' : 'Lote inactivo' }} · vence {{ \Illuminate\Support\Carbon::parse($lot->fecha_vencimiento)->format('d/m/Y') }} · existencia {{ \App\Support\Formatting\NumberFormat::format($lot->existencia_total) }} {{ $lot->unidad_base_codigo }}</option>
            @endforeach
        </template>
    @endif
@endsection
