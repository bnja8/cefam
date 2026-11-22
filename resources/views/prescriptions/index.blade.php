@extends('layouts.app')
@section('content')
    <x-page-header :title="$screen === 'create' ? 'Registrar receta' : 'Recetas Médicas'" description="Registro y consulta de prescripciones estándar y retenidas">
        <x-slot:actions>@if ($screen === 'list')<a class="btn btn-primary" href="{{ route('recipes.create') }}"><i class="fa-solid fa-file-circle-plus"></i> Registrar receta</a>@endif</x-slot:actions>
    </x-page-header>

    @if ($screen === 'list')
    <section class="card" id="recetas-registradas">
        <div class="card-header"><h2 class="card-title"><i class="fa-solid fa-file-prescription text-primary"></i> Recetas registradas</h2><span class="muted">{{ number_format($prescriptions->total(), 0, ',', '.') }} resultados</span></div>
        <div class="card-body">
            <form action="{{ route('recipes.index') }}" method="get" class="form-grid">
                <div class="form-group form-span"><label for="buscar-receta">Buscar receta</label><input class="form-control" id="buscar-receta" name="buscar" value="{{ $filters['buscar'] ?? '' }}" placeholder="Paciente, profesional, número o estado"></div>
                <div class="form-group"><label for="estado-receta">Estado</label><select class="form-control" id="estado-receta" name="estado"><option value="">Todos</option>@foreach (['VIGENTE', 'VENCIDA', 'UTILIZADA', 'ANULADA'] as $state)<option value="{{ $state }}" @selected(($filters['estado'] ?? '') === $state)>{{ $state }}</option>@endforeach</select></div>
                <div class="form-group"><label for="tipo-receta-filtro">Tipo</label><select class="form-control" id="tipo-receta-filtro" name="tipo_receta"><option value="">Todos</option>@foreach (['ESTANDAR' => 'Estándar', 'RETENIDA' => 'Retenida'] as $type => $label)<option value="{{ $type }}" @selected(($filters['tipo_receta'] ?? '') === $type)>{{ $label }}</option>@endforeach</select></div>
                <div class="form-actions"><button class="btn btn-primary" type="submit"><i class="fa-solid fa-magnifying-glass"></i> Buscar</button><a class="btn btn-outline" href="{{ route('recipes.index') }}">Limpiar</a></div>
            </form>
            <p class="muted" data-print-status role="status" aria-live="polite" hidden></p>
        </div>
        <div class="person-card-grid searchable-cards">
                @forelse ($prescriptions as $prescription)
                    <x-person-card
                        :name="$prescription->paciente"
                        :subtitle="'Receta '.$prescription->numero_receta"
                        :status="$prescription->estado"
                        :active="$prescription->estado === 'VIGENTE' && $prescription->fecha_vencimiento >= now()->toDateString()"
                        :status-class="match ($prescription->estado) {
                            'VIGENTE' => $prescription->fecha_vencimiento >= now()->toDateString() ? 'badge-success' : 'badge-warning',
                            'VENCIDA' => 'badge-warning',
                            'UTILIZADA' => 'badge-info',
                            default => 'badge-danger',
                        }"
                    >
                        <x-slot:details>
                            <div class="person-card-meta-row">
                                <i class="fa-solid fa-id-card" aria-hidden="true"></i>
                                <span>RUT <strong>{{ $prescription->rut }}</strong></span>
                            </div>
                            <div class="person-card-meta-row">
                                <i class="fa-solid fa-user-doctor" aria-hidden="true"></i>
                                <span>Profesional <strong>{{ $prescription->profesional }}</strong></span>
                            </div>
                            <div class="person-card-meta-row">
                                <i class="fa-solid fa-prescription-bottle-medical" aria-hidden="true"></i>
                                <span>Tipo <span class="badge {{ $prescription->tipo_receta === 'RETENIDA' ? 'badge-warning' : 'badge-info' }}">{{ $prescription->tipo_receta }}</span></span>
                            </div>
                            <div class="person-card-meta-row">
                                <i class="fa-regular fa-calendar" aria-hidden="true"></i>
                                <span>Emisión <strong>{{ \Illuminate\Support\Carbon::parse($prescription->fecha_emision)->format('d/m/Y') }}</strong></span>
                            </div>
                            <div class="person-card-meta-row">
                                <i class="fa-regular fa-calendar-check" aria-hidden="true"></i>
                                <span>Vencimiento <strong>{{ \Illuminate\Support\Carbon::parse($prescription->fecha_vencimiento)->format('d/m/Y') }}</strong></span>
                            </div>
                            <details class="prescription-card-medicines">
                                <summary class="btn btn-outline btn-small">Ver medicamentos ({{ $prescription->medicamentos->count() }})</summary>
                                <div class="prescription-card-medicine-list">
                                    @foreach ($prescription->medicamentos as $medicine)
                                        <article class="prescription-card-medicine">
                                            <strong>{{ $medicine->producto }}</strong>
                                            <span>{{ \App\Support\Formatting\NumberFormat::format($medicine->cantidad_indicada) }} {{ $medicine->unidad_base_codigo }} prescritas · {{ $medicine->estado_dispensacion }}</span>
                                            <span>Vía: {{ $medicine->via_administracion ?: '—' }} · Dosis: {{ $medicine->dosis ?: '—' }}</span>
                                            <span>Frecuencia: {{ \App\Support\Prescriptions\MedicationFrequency::label($medicine->frecuencia) }} · {{ $medicine->es_sos ? 'SOS · máximo prescrito' : ($medicine->duracion_dias ? $medicine->duracion_dias.' días' : '—') }}</span>
                                            <span>{{ \App\Support\Formatting\NumberFormat::format($medicine->cantidad_dispensada) }} entregadas · {{ \App\Support\Formatting\NumberFormat::format($medicine->cantidad_pendiente) }} pendientes</span>
                                            @if ($medicine->indicaciones)
                                                <span>Indicaciones: {{ $medicine->indicaciones }}</span>
                                            @endif
                                        </article>
                                    @endforeach
                                </div>
                            </details>
                        </x-slot:details>
                        <x-slot:actions>
                            <button class="btn btn-outline btn-small" type="button" data-print-prescription-url="{{ route('recipes.print', $prescription->receta_id) }}"><i class="fa-solid fa-print" aria-hidden="true"></i> Imprimir</button>
                        </x-slot:actions>
                    </x-person-card>
                @empty
                    <div class="empty-panel" role="status">
                        <span class="empty-icon"><i class="fa-regular fa-folder-open"></i></span>
                        <h2>No se encontraron recetas</h2>
                        <p>Prueba con otros términos de búsqueda o filtros.</p>
                    </div>
                @endforelse
        </div>
        <x-record-pagination :paginator="$prescriptions" />
    </section>
    @endif

    @if ($screen === 'create')
    <section class="card" id="nueva-receta">
        <div class="card-header"><h2 class="card-title"><i class="fa-solid fa-file-circle-plus text-primary"></i> Registrar receta y detalle</h2></div>
        <div class="card-body">
            @if ($activePatientCount === 0 || $professionals->isEmpty())
                <div class="notice notice-warning"><i class="fa-solid fa-circle-exclamation"></i><span>Para registrar una receta necesitas pacientes y profesionales de salud activos.</span></div>
            @else
                <form action="{{ route('recipes.store') }}" method="post" class="stack-form">
                    @csrf
                    <div class="form-grid">
                        <x-patient-search id="receta-paciente" :selected-patient="$selectedPatient" selected-rut-only required />
                        <div class="form-group"><label for="receta-profesional">Profesional emisor *</label><select class="form-control" id="receta-profesional" name="profesional_rut" required><option value="">Selecciona profesional</option>@foreach ($professionals as $professional)<option value="{{ $professional->rut }}">{{ $professional->nombre_completo }} · {{ $professional->tipo_profesional }}</option>@endforeach</select></div>
                        <div class="form-group"><label for="numero-receta">Número de receta *</label><input class="form-control" id="numero-receta" name="numero_receta" required maxlength="100"></div>
                        <div class="form-group"><label for="tipo-receta">Tipo *</label><select class="form-control" id="tipo-receta" name="tipo_receta" required><option value="ESTANDAR">Estándar</option><option value="RETENIDA">Retenida</option></select></div>
                        <div class="form-group"><label for="fecha-emision">Fecha de emisión *</label><input class="form-control" id="fecha-emision" name="fecha_emision" type="date" max="{{ now()->toDateString() }}" value="{{ old('fecha_emision', now()->toDateString()) }}" required></div>
                        <div class="form-group"><label for="fecha-vencimiento">Fecha de vencimiento *</label><input class="form-control" id="fecha-vencimiento" name="fecha_vencimiento" type="date" min="{{ now()->toDateString() }}" value="{{ old('fecha_vencimiento', now()->toDateString()) }}" required></div>
                        <div class="form-group form-span"><label for="receta-observacion">Observaciones</label><textarea class="form-control" id="receta-observacion" name="observacion" rows="2"></textarea></div>
                    </div>
                    <section class="prescription-treatment-card" aria-labelledby="prescription-treatment-title">
                        <header class="prescription-treatment-header">
                            <span class="prescription-treatment-icon" aria-hidden="true"><i class="fa-solid fa-notes-medical"></i></span>
                            <div class="prescription-treatment-copy">
                                <div class="prescription-treatment-title-row">
                                    <h3 id="prescription-treatment-title">Tratamiento clínico vinculado</h3>
                                    <span class="prescription-treatment-badge">Integrado · Sin salir de receta</span>
                                </div>
                                <p>Hereda el paciente y el periodo de vigencia de la receta. La cantidad prescrita de cada medicamento será el máximo dispensable por mes calendario.</p>
                            </div>
                            <label class="prescription-treatment-toggle" for="indicar-tratamiento">
                                <input
                                    id="indicar-tratamiento"
                                    name="indicar_tratamiento"
                                    type="checkbox"
                                    value="1"
                                    data-prescription-treatment-toggle
                                    aria-controls="prescription-treatment-fields"
                                    @checked(old('indicar_tratamiento'))
                                >
                                Indicar tratamiento al paciente
                            </label>
                        </header>
                        <div
                            class="prescription-treatment-fields"
                            id="prescription-treatment-fields"
                            data-prescription-treatment-fields
                            @unless (old('indicar_tratamiento')) hidden @endunless
                        >
                            <p class="prescription-treatment-patient" role="status" aria-live="polite" data-prescription-treatment-patient>
                                @if (old('paciente_rut'))
                                    Tratamiento vinculado al paciente {{ old('paciente_rut') }}.
                                @else
                                    Selecciona un paciente en la receta para vincular el tratamiento.
                                @endif
                            </p>
                            <div class="form-group form-span">
                                <label for="tratamiento-descripcion">Tratamiento / indicación *</label>
                                <textarea
                                    class="form-control"
                                    id="tratamiento-descripcion"
                                    name="tratamiento[descripcion]"
                                    rows="2"
                                    maxlength="2000"
                                    placeholder="Describe el esquema terapéutico, objetivo clínico, cuidados o preparación unidosis asociada..."
                                    data-treatment-required
                                    @required(old('indicar_tratamiento'))
                                    @disabled(! old('indicar_tratamiento'))
                                >{{ old('tratamiento.descripcion') }}</textarea>
                                @error('tratamiento.descripcion')<small class="field-error">{{ $message }}</small>@enderror
                            </div>
                        </div>
                    </section>
                    <div class="detail-heading"><h3>Medicamentos prescritos</h3><button class="btn btn-outline btn-small" type="button" data-add-row="prescription-details"><i class="fa-solid fa-plus"></i> Agregar medicamento</button></div>
                    <div id="prescription-details" class="detail-rows" data-distinct-products-list>
                        <div class="detail-row prescription-detail-row" data-prescription-row>
                            <x-product-search
                                id="prescription-product-0"
                                name="detalles[0][producto_id]"
                                field-class="prescription-field-product"
                                search-url="{{ route('recipes.products.search') }}"
                                search-label="Medicamento *"
                                search-help="Escribe el nombre del medicamento y selecciona un resultado."
                                :prescription="true"
                                :show-availability="false"
                            />
                            <div class="form-group prescription-field-dose"><label>Dosis por toma *</label><input class="form-control" name="detalles[0][unidades_por_toma]" type="number" min="0.01" step="0.01" required data-dose-amount><small>Cantidad administrada en cada toma; la frecuencia indica el intervalo entre dosis.</small><small data-dose-unit></small></div>
                            <div class="form-group prescription-field-duration"><label>Duración (días) *</label><input class="form-control" name="detalles[0][duracion_dias]" type="number" min="1" max="365" step="1" required data-treatment-duration></div>
                            <div class="form-group prescription-field-frequency"><label>Frecuencia *</label><input class="form-control" name="detalles[0][frecuencia]" maxlength="100" placeholder="Cada 8 horas" required data-prescription-frequency></div>
                            <div class="form-group prescription-field-quantity"><label data-prescribed-quantity-label>Cantidad prescrita *</label><input class="form-control" name="detalles[0][cantidad_indicada]" type="number" min="0.01" step="0.01" required data-prescribed-quantity><small data-quantity-suggestion aria-live="polite"></small><button class="btn btn-outline btn-small" type="button" data-apply-quantity-suggestion hidden>Usar sugerencia</button></div>
                            <div class="form-group prescription-field-sos"><label class="check-label"><input type="checkbox" name="detalles[0][es_sos]" value="1" data-sos-treatment> Tratamiento SOS</label><small>La cantidad prescrita será el máximo autorizado.</small></div>
                            <div class="form-group detail-instructions prescription-field-instructions"><label>Indicaciones</label><input class="form-control" name="detalles[0][indicaciones]" maxlength="1000"></div>
                            <button class="icon-btn detail-remove prescription-field-remove" type="button" aria-label="Eliminar medicamento" disabled><i class="fa-solid fa-trash"></i></button>
                        </div>
                    </div>
                    <button class="btn btn-primary" type="submit"><i class="fa-solid fa-floppy-disk"></i> Guardar receta</button>
                </form>
                <template id="prescription-detail-template">
                    <div class="detail-row prescription-detail-row" data-prescription-row>
                            <x-product-search
                                id="prescription-product-template"
                                field-key="producto_id"
                                field-class="prescription-field-product"
                                search-url="{{ route('recipes.products.search') }}"
                                search-label="Medicamento *"
                                search-help="Escribe el nombre del medicamento y selecciona un resultado."
                                :prescription="true"
                                :show-availability="false"
                            />
                        <div class="form-group prescription-field-dose"><label>Dosis por toma *</label><input class="form-control" data-name="unidades_por_toma" type="number" min="0.01" step="0.01" required data-dose-amount><small>Cantidad administrada en cada toma; la frecuencia indica el intervalo entre dosis.</small><small data-dose-unit></small></div>
                        <div class="form-group prescription-field-duration"><label>Duración (días) *</label><input class="form-control" data-name="duracion_dias" type="number" min="1" max="365" step="1" required data-treatment-duration></div>
                        <div class="form-group prescription-field-frequency"><label>Frecuencia *</label><input class="form-control" data-name="frecuencia" maxlength="100" placeholder="Cada 8 horas" required data-prescription-frequency></div>
                        <div class="form-group prescription-field-quantity"><label data-prescribed-quantity-label>Cantidad prescrita *</label><input class="form-control" data-name="cantidad_indicada" type="number" min="0.01" step="0.01" required data-prescribed-quantity><small data-quantity-suggestion aria-live="polite"></small><button class="btn btn-outline btn-small" type="button" data-apply-quantity-suggestion hidden>Usar sugerencia</button></div>
                        <div class="form-group prescription-field-sos"><label class="check-label"><input type="checkbox" data-name="es_sos" value="1" data-sos-treatment> Tratamiento SOS</label><small>La cantidad prescrita será el máximo autorizado.</small></div>
                        <div class="form-group detail-instructions prescription-field-instructions"><label>Indicaciones</label><input class="form-control" data-name="indicaciones" maxlength="1000"></div>
                        <button class="icon-btn detail-remove prescription-field-remove" type="button" aria-label="Eliminar medicamento"><i class="fa-solid fa-trash"></i></button>
                    </div>
                </template>
            @endif
        </div>
    </section>
    @endif
@endsection
