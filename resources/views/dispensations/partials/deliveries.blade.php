    @if ($screen === 'deliveries')
    <section class="card" id="entregas-recientes">
        <div class="card-header"><h2 class="card-title"><i class="fa-solid fa-clock-rotate-left text-primary"></i> Consultar entregas recientes</h2></span></div>
        <div class="card-body">
            <form action="{{ route('dispensations.deliveries') }}" method="get" class="form-grid">
                <div class="form-group form-span"><label for="buscar-entrega">Buscar entrega</label><input class="form-control" id="buscar-entrega" name="buscar" value="{{ $filters['buscar'] ?? '' }}" placeholder="Paciente, RUT, producto, responsable u orden"></div>
                <div class="form-group"><label for="fecha-entrega">Fecha específica</label><input class="form-control" id="fecha-entrega" name="fecha" type="date" value="{{ $filters['fecha'] ?? '' }}"></div>
                <div class="form-group"><label for="desde-entrega">Desde</label><input class="form-control" id="desde-entrega" name="desde" type="date" value="{{ $filters['desde'] ?? '' }}"></div>
                <div class="form-group"><label for="hasta-entrega">Hasta</label><input class="form-control" id="hasta-entrega" name="hasta" type="date" value="{{ $filters['hasta'] ?? '' }}"></div>
                <div class="form-group"><label for="estado-entrega">Estado</label><select class="form-control" id="estado-entrega" name="estado"><option value="" @selected($filters['estado'] === null)>Todos</option><option value="CONFIRMADA" @selected($filters['estado'] === 'CONFIRMADA')>Confirmada</option><option value="PENDIENTE" @selected($filters['estado'] === 'PENDIENTE')>Pendiente</option><option value="RECHAZADA" @selected($filters['estado'] === 'RECHAZADA')>Rechazada</option><option value="ANULADA" @selected($filters['estado'] === 'ANULADA')>Anulada</option></select></div>
                <div class="form-actions"><button class="btn btn-primary" type="submit"><i class="fa-solid fa-magnifying-glass"></i> Buscar</button><a class="btn btn-outline" href="{{ route('dispensations.deliveries') }}">Limpiar</a></div>
            </form>
        </div>
        <div class="person-card-grid searchable-cards">
                @forelse ($deliveries as $delivery)
                    <x-person-card
                        :name="$delivery->paciente"
                        :subtitle="'Entrega · '.\Illuminate\Support\Carbon::parse($delivery->fecha_hora)->format('d/m/Y H:i')"
                        :status="$delivery->estado"
                        :active="$delivery->estado === 'CONFIRMADA'"
                        :status-class="$delivery->estado === 'CONFIRMADA' ? 'badge-success' : ($delivery->estado === 'PENDIENTE' ? 'badge-warning' : 'badge-danger')"
                    >
                        <x-slot:details>
                            <div class="person-card-meta-row">
                                <i class="fa-solid fa-id-card" aria-hidden="true"></i>
                                <span>RUT <strong>{{ $delivery->rut }}</strong></span>
                            </div>
                            <div class="person-card-meta-row">
                                <i class="fa-solid fa-user-check" aria-hidden="true"></i>
                                <span>Retira <strong>{{ $delivery->persona_autorizada ?: 'Paciente' }}</strong></span>
                            </div>
                            <div class="person-card-meta-row">
                                <i class="fa-solid fa-user" aria-hidden="true"></i>
                                <span>Responsable <strong>{{ $delivery->responsable }}</strong></span>
                            </div>
                            <div class="person-card-meta-row">
                                <i class="fa-solid fa-file-prescription" aria-hidden="true"></i>
                                <span>Orden <strong>{{ $delivery->numero_orden ?: 'Sin orden vinculada' }}</strong></span>
                            </div>
                        </x-slot:details>
                        <x-slot:actions>
                            @if ($delivery->orden_entrega_id)
                                <a class="btn btn-outline btn-small" href="{{ route('dispensations.deliveries', array_merge(request()->query(), ['comprobante' => $delivery->orden_entrega_id])) }}"><i class="fa-solid fa-receipt" aria-hidden="true"></i> Ver comprobante</a>
                            @else
                                <span class="person-card-unavailable">Sin orden disponible</span>
                            @endif
                        </x-slot:actions>
                    </x-person-card>
                @empty
                    <div class="empty-panel" role="status">
                        <span class="empty-icon"><i class="fa-regular fa-folder-open"></i></span>
                        <h2>No se encontraron entregas</h2>
                        <p>Prueba con otros términos de búsqueda o filtros.</p>
                    </div>
                @endforelse
        </div>
        <p class="muted" data-print-status aria-live="polite" hidden></p>
        <x-record-pagination :paginator="$deliveries" />
    </section>
    @endif
