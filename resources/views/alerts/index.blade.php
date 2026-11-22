{{-- Renderiza el centro de alertas operativas y su historial. --}}
@extends('layouts.app')
@section('content')
    @if ($section === 'map')
        <x-page-header
            :title="$isTechnician ? 'Mis alertas asignadas' : 'Alertas'"
            :description="$isTechnician ? 'Alertas activas bajo tu responsabilidad' : 'Supervisa alertas activas y consulta las acciones registradas'"
        >
        </x-page-header>
    @else
        <x-page-header title="Alertas" description="Supervisa alertas activas y consulta las acciones registradas" />
    @endif

    @if ($section === 'map')
    <section class="card alert-map-card" id="mapa-operativo">
        <div class="card-header alert-section-header">
            <div>
                <h2 class="card-title"><i class="fa-solid fa-map-location-dot"></i> {{ $isTechnician ? 'Alertas asignadas' : 'Mapa operativo' }}</h2>
                <p>{{ $isTechnician ? 'Revisa y gestiona las alertas que tienes asignadas.' : 'Alertas activas que requieren revisión o seguimiento.' }}</p>
            </div>
            <span class="badge badge-danger">{{ number_format($alerts->total(), 0, ',', '.') }} alertas</span>
        </div>

        <div class="card-body">
            <form action="{{ route('alerts.map') }}" method="get" class="form-grid alert-search-form">
                <div class="form-group">
                    <label for="buscar-alerta">Producto o número de lote</label>
                    <input class="form-control" id="buscar-alerta" name="buscar_alerta" value="{{ $alertFilters['buscar_alerta'] ?? '' }}" placeholder="Nombre del producto o lote">
                </div>
                <div class="form-group">
                    <label for="prioridad-alerta">Prioridad</label>
                    <select class="form-control" id="prioridad-alerta" name="prioridad">
                        <option value="">Todas</option>
                        <option value="CRITICA" @selected(($alertFilters['prioridad'] ?? '') === 'CRITICA')>Crítica</option>
                        <option value="ALTA" @selected(($alertFilters['prioridad'] ?? '') === 'ALTA')>Alta</option>
                        <option value="MEDIA" @selected(($alertFilters['prioridad'] ?? '') === 'MEDIA')>Media</option>
                        <option value="VENCIDO" @selected(($alertFilters['prioridad'] ?? '') === 'VENCIDO')>Vencimiento vencido</option>
                        <option value="VENCIMIENTO" @selected(($alertFilters['prioridad'] ?? '') === 'VENCIMIENTO')>Producto próximo a vencer</option>
                        <option value="CONCILIACION" @selected(($alertFilters['prioridad'] ?? '') === 'CONCILIACION')>Conciliación</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="tipo-alerta">Tipo de alerta</label>
                    <select class="form-control" id="tipo-alerta" name="tipo_alerta">
                        <option value="">Todos</option>
                        <option value="SIN_EXISTENCIAS" @selected(($alertFilters['tipo_alerta'] ?? '') === 'SIN_EXISTENCIAS')>Sin existencias</option>
                        <option value="STOCK_CRITICO" @selected(($alertFilters['tipo_alerta'] ?? '') === 'STOCK_CRITICO')>Stock crítico</option>
                        <option value="STOCK_BAJO" @selected(($alertFilters['tipo_alerta'] ?? '') === 'STOCK_BAJO')>Stock bajo</option>
                        <option value="VENCIMIENTO" @selected(($alertFilters['tipo_alerta'] ?? '') === 'VENCIMIENTO')>Vencimiento</option>
                        <option value="VENCIDO" @selected(($alertFilters['tipo_alerta'] ?? '') === 'VENCIDO')>Lote vencido</option>
                        <option value="DESCUADRE_CONTROLADO" @selected(($alertFilters['tipo_alerta'] ?? '') === 'DESCUADRE_CONTROLADO')>Descuadre controlado</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="ubicacion-alerta">Ubicación</label>
                    <select class="form-control" id="ubicacion-alerta" name="ubicacion_id">
                        <option value="">Todas</option>
                        @foreach ($locations as $location)
                            <option value="{{ $location->ubicacion_id }}" @selected((string) ($alertFilters['ubicacion_id'] ?? '') === (string) $location->ubicacion_id)>{{ $location->nombre }}</option>
                        @endforeach
                    </select>
                </div>
                @unless ($isTechnician)
                    <div class="form-group">
                        <label for="responsable-alerta">Responsable</label>
                        <select class="form-control" id="responsable-alerta" name="responsable_rut">
                            <option value="">Todos</option>
                            @foreach ($responsibles as $responsible)
                                @php $responsibleName = trim(($responsible->nombre ?? '').' '.($responsible->apellidoPaterno ?? '').' '.($responsible->apellidoMaterno ?? '')); @endphp
                                <option value="{{ $responsible->rut }}" @selected(($alertFilters['responsable_rut'] ?? '') === $responsible->rut)>{{ $responsibleName ?: $responsible->rut }}</option>
                            @endforeach
                        </select>
                    </div>
                @endunless
                <div class="form-group">
                    <label for="estado-operativo-alerta">Estado operativo</label>
                    <select class="form-control" id="estado-operativo-alerta" name="estado_operativo">
                        <option value="">Todos</option>
                        @foreach (['ACTIVA' => 'Activa', 'EN_SEGUIMIENTO' => 'En seguimiento'] as $value => $label)
                            <option value="{{ $value }}" @selected(($alertFilters['estado_operativo'] ?? '') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group">
                    <label for="fecha-alerta-desde">Fecha desde</label>
                    <input class="form-control" id="fecha-alerta-desde" name="fecha_desde" type="date" value="{{ $alertFilters['fecha_desde'] ?? '' }}">
                </div>
                <div class="form-group">
                    <label for="fecha-alerta-hasta">Fecha hasta</label>
                    <input class="form-control" id="fecha-alerta-hasta" name="fecha_hasta" type="date" value="{{ $alertFilters['fecha_hasta'] ?? '' }}">
                </div>
                <div class="form-actions">
                    <button class="btn btn-primary" type="submit"><i class="fa-solid fa-magnifying-glass"></i> Buscar alertas</button>
                    <a class="btn btn-outline" href="{{ route('alerts.map') }}">Limpiar</a>
                </div>
            </form>

            <div class="alert-card-grid">
                @forelse ($alerts as $alert)
                    @php
                        $isCritical = in_array($alert->tipo_alerta, ['SIN_EXISTENCIAS', 'STOCK_CRITICO', 'STOCK_BAJO'], true);
                        $isExpiry = in_array($alert->tipo_alerta, ['VENCIMIENTO', 'VENCIDO'], true);
                        $isExpired = $alert->tipo_alerta === 'VENCIDO'
                            || ($alert->tipo_alerta === 'VENCIMIENTO' && $alert->fecha_vencimiento && $alert->fecha_vencimiento < now()->toDateString());
                        $priorityLabel = match ($alert->tipo_alerta) {
                            'SIN_EXISTENCIAS', 'VENCIDO' => 'Crítica',
                            'STOCK_CRITICO' => 'Alta',
                            'STOCK_BAJO' => 'Media',
                            'VENCIMIENTO' => $isExpired ? 'Vencido' : 'Próximo a vencer',
                            default => 'Revisión',
                        };
                        $priorityClass = $isCritical || $isExpired || $alert->tipo_alerta === 'DESCUADRE_CONTROLADO' ? 'critical' : ($isExpiry ? 'medium' : 'low');
                        $detectedAt = $alert->detectada_en ?? $alert->fecha_generacion;
                    @endphp
                    <article class="alert-card {{ $priorityClass }}">
                        <div class="alert-card-top">
                            <span class="alert-priority"><i class="fa-solid {{ $priorityClass === 'critical' ? 'fa-triangle-exclamation' : ($priorityClass === 'medium' ? 'fa-clock' : 'fa-circle-info') }}"></i> {{ $priorityLabel }}</span>
                            <span class="alert-state">{{ str_replace('_', ' ', $alert->estado ?? 'ACTIVA') }}</span>
                        </div>
                        <h3>{{ $alert->producto ?? 'Producto sin identificación' }}</h3>
                        <p class="alert-product-type">{{ str_replace('_', ' ', $alert->tipo_alerta) }}{{ $alert->numero_lote ? ' · Lote '.$alert->numero_lote : '' }}</p>
                        <p class="alert-message">{{ $alert->mensaje }}</p>
                        <div class="alert-card-meta">
                            <span><i class="fa-solid fa-location-dot" aria-hidden="true"></i> {{ $alert->ubicacion ?? 'Sin ubicación' }}</span>
                            <span><i class="fa-regular fa-calendar" aria-hidden="true"></i> Detectada: {{ \Illuminate\Support\Carbon::parse($detectedAt)->format('d/m/Y - H:i') }}</span>
                            <span><i class="fa-solid fa-user" aria-hidden="true"></i> Responsable: {{ $alert->responsable ?? 'Sin asignar' }}</span>
                        </div>
                        <a class="btn btn-outline btn-small alert-enter-link" href="{{ route('alerts.show', $alert->alerta_id) }}">
                            <span>Ingresar</span><i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
                        </a>
                    </article>
                @empty
                    <p class="alert-empty-state">No hay alertas activas que coincidan con los filtros seleccionados.</p>
                @endforelse
            </div>
            <x-record-pagination :paginator="$alerts" />
        </div>
    </section>
    @elseif ($section === 'history')
    <section class="card alert-history-card" id="historial-alertas">
        <div class="card-header alert-section-header">
            <div>
                <h2 class="card-title"><i class="fa-solid fa-clock-rotate-left"></i> Historial de Alertas</h2>
                <p>Registro de responsables, cambios, seguimientos y fecha de cada acción.</p>
            </div>
        </div>
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr><th>FECHA Y HORA</th><th>ALERTA</th><th>ACCIÓN</th><th>REALIZADA POR</th><th>CAMBIO DE RESPONSABLE</th><th>RESPONSABLE ACTUAL</th><th>DETALLE / ESTADO</th></tr>
                </thead>
                <tbody>
                    @forelse ($alertHistory as $history)
                        @php
                            $eventLabel = match ($history->tipo_evento) {
                                'ASIGNACION' => 'Asignación / reasignación',
                                'RECONOCIMIENTO' => 'Reconocimiento',
                                'SEGUIMIENTO' => 'Seguimiento',
                                'GENERACION' => 'Alerta generada',
                                'RESOLUCION' => 'Alerta resuelta',
                                default => str_replace('_', ' ', $history->tipo_evento),
                            };
                            $actorName = $history->usuario ?: ($history->usuario_rut ? 'Usuario '.$history->usuario_rut : 'Sistema');
                            $previousResponsible = $history->responsable_anterior ?: ($history->responsable_anterior_rut ?: 'Sin asignar');
                            $newResponsible = $history->responsable_nuevo ?: ($history->responsable_nuevo_rut ?: 'Sin asignar');
                            $currentResponsible = $history->responsable_actual ?: ($history->responsable_actual_rut ?: 'Sin asignar');
                            $historyDate = \Illuminate\Support\Carbon::parse($history->created_at);
                        @endphp
                        <tr>
                            <td>
                                <time datetime="{{ $historyDate->toIso8601String() }}">{{ $historyDate->format('d/m/Y') }}</time>
                                <small class="table-subtitle">{{ $historyDate->format('H:i') }}</small>
                            </td>
                            <td>
                                <a class="text-link" href="{{ route('alerts.show', $history->alerta_id) }}">{{ $history->producto ?? 'Alerta sin producto' }}</a>
                                <small class="table-subtitle">Alerta #{{ $history->alerta_id }}</small>
                            </td>
                            <td><span class="alert-history-event">{{ $eventLabel }}</span></td>
                            <td>{{ $actorName }}</td>
                            <td>
                                <div class="alert-history-change">
                                    <div><small>Responsable anterior</small><span>{{ $previousResponsible }}</span></div>
                                    <div><small>{{ $history->tipo_evento === 'ASIGNACION' ? 'Designado a' : 'Responsable después' }}</small><strong>{{ $newResponsible }}</strong></div>
                                </div>
                            </td>
                            <td><span class="alert-history-current">{{ $currentResponsible }}</span></td>
                            <td>
                                <span>{{ $history->descripcion ?? 'Sin detalle adicional.' }}</span>
                                @if ($history->estado_anterior || $history->estado_nuevo)
                                    <small class="table-subtitle">Estado: {{ $history->estado_anterior ?? '—' }} → {{ $history->estado_nuevo ?? '—' }}</small>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="empty-state">Todavía no hay acciones registradas en el historial.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <x-record-pagination :paginator="$alertHistory" />
    </section>
    @endif
@endsection
