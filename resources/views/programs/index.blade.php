@extends('layouts.app')
@section('content')
    <x-page-header :title="in_array($screen, ['create', 'edit'], true) ? ($screen === 'edit' ? 'Editar programa' : 'Crear programa') : ($screen === 'enroll' ? 'Afiliar paciente' : ($screen === 'affiliations' ? 'Afiliaciones de pacientes' : 'Programas de Salud'))" description="Cobertura asistencial, pacientes inscritos y cuotas de dispensación">
        <x-slot:actions>@if ($screen === 'programs' && $canEnroll)<a class="btn btn-primary" href="{{ route('programs.enroll.create') }}"><i class="fa-solid fa-user-plus"></i> Afiliar paciente</a>@endif</x-slot:actions>
    </x-page-header>

    @if ($screen === 'programs')
    <section class="card" id="programas-disponibles">
        <div class="card-header"><h2 class="card-title"><i class="fa-solid fa-heart-pulse text-primary"></i> Consultar programas</h2><span class="muted">{{ number_format($programs->total(), 0, ',', '.') }} resultados</span></div>
        <div class="card-body">
            <form action="{{ route('programs.index') }}" method="get" class="form-grid">
                <div class="form-group"><label for="buscar-programa">Buscar programa</label><input class="form-control" id="buscar-programa" name="buscar_programa" value="{{ $programFilters['buscar_programa'] ?? '' }}" placeholder="Nombre o descripción"></div>
                <div class="form-group"><label for="estado-programa">Estado</label><select class="form-control" id="estado-programa" name="estado_programa"><option value="" @selected(($programFilters['estado_programa'] ?? null) === null)>Todos</option><option value="activo" @selected(($programFilters['estado_programa'] ?? null) === 'activo')>Activo</option><option value="inactivo" @selected(($programFilters['estado_programa'] ?? null) === 'inactivo')>Inactivo</option></select></div>
                <div class="form-actions"><button class="btn btn-primary" type="submit"><i class="fa-solid fa-magnifying-glass"></i> Buscar</button><a class="btn btn-outline" href="{{ route('programs.index') }}">Limpiar</a></div>
            </form>
        </div>
        <div class="person-card-grid">
            @forelse ($programs as $program)
                <x-person-card
                    :name="$program->nombre"
                    subtitle="Programa de salud"
                    :status="$program->activo ? 'Activo' : 'Inactivo'"
                    :active="$program->activo"
                >
                    <x-slot:details>
                        <div class="person-card-meta-row">
                            <i class="fa-solid fa-align-left" aria-hidden="true"></i>
                            <span>{{ $program->descripcion ?: 'Sin descripción disponible' }}</span>
                        </div>
                        <div class="person-card-meta-row">
                            <i class="fa-solid fa-users" aria-hidden="true"></i>
                            <span>Pacientes activos <strong>{{ $program->pacientes_activos }}</strong></span>
                        </div>
                    </x-slot:details>
                    @if ($canCreateProgram)
                        <x-slot:actions>
                            <a class="btn btn-outline btn-small" href="{{ route('programs.edit', $program->programa_id) }}">Editar</a>
                            <form action="{{ route('programs.toggle', $program->programa_id) }}" method="post">
                                @csrf @method('patch')
                                <button class="btn btn-small {{ $program->activo ? 'btn-outline' : 'btn-primary' }}" type="submit">{{ $program->activo ? 'Desactivar' : 'Activar' }}</button>
                            </form>
                        </x-slot:actions>
                    @endif
                </x-person-card>
            @empty
                <div class="empty-panel" role="status">
                    <span class="empty-icon"><i class="fa-regular fa-folder-open"></i></span>
                    <h2>No se encontraron programas</h2>
                    <p>Prueba con otros términos de búsqueda o filtros.</p>
                </div>
            @endforelse
        </div>
        <x-record-pagination :paginator="$programs" />
    </section>
    @endif

    @if ($screen === 'affiliations')
    <section class="card" id="afiliaciones-recientes">
        <div class="card-header"><h2 class="card-title"><i class="fa-solid fa-users text-primary"></i> Consultar afiliaciones</h2><span class="muted">{{ number_format($enrollments->total(), 0, ',', '.') }} resultados</span></div>
        <div class="card-body">
            <form action="{{ route('programs.affiliations.index') }}" method="get" class="form-grid">
                <div class="form-group"><label for="buscar-afiliacion">Buscar afiliación</label><input class="form-control" id="buscar-afiliacion" name="buscar_afiliacion" value="{{ $enrollmentFilters['buscar_afiliacion'] ?? '' }}" placeholder="Paciente, RUT, programa o cobertura"></div>
                <div class="form-group"><label for="filtro-programa-afiliacion">Programa</label><select class="form-control" id="filtro-programa-afiliacion" name="programa_id_afiliacion"><option value="">Todos los programas</option>@foreach ($allPrograms as $program)<option value="{{ $program->programa_id }}" @selected((string) ($enrollmentFilters['programa_id_afiliacion'] ?? '') === (string) $program->programa_id)>{{ $program->nombre }}</option>@endforeach</select></div>
                <div class="form-group"><label for="estado-afiliacion">Estado</label><select class="form-control" id="estado-afiliacion" name="estado_afiliacion"><option value="">Todos</option>@foreach (['VIGENTE' => 'Vigente', 'PROGRAMADA' => 'Programada', 'FINALIZADA' => 'Finalizada', 'INACTIVA' => 'Inactiva'] as $value => $label)<option value="{{ $value }}" @selected(($enrollmentFilters['estado_afiliacion'] ?? null) === $value)>{{ $label }}</option>@endforeach</select></div>
                <div class="form-group"><label for="desde-afiliacion">Inicio desde</label><input class="form-control" id="desde-afiliacion" name="desde_afiliacion" type="date" value="{{ $enrollmentFilters['desde_afiliacion'] ?? '' }}"></div>
                <div class="form-group"><label for="hasta-afiliacion">Inicio hasta</label><input class="form-control" id="hasta-afiliacion" name="hasta_afiliacion" type="date" value="{{ $enrollmentFilters['hasta_afiliacion'] ?? '' }}"></div>
                <div class="form-actions"><button class="btn btn-primary" type="submit"><i class="fa-solid fa-magnifying-glass"></i> Buscar</button><a class="btn btn-outline" href="{{ route('programs.affiliations.index') }}">Limpiar</a></div>
            </form>
        </div>
        <div class="person-card-grid">
            @forelse ($enrollments as $enrollment)
                <x-person-card
                    :name="$enrollment->paciente"
                    :subtitle="'RUT '.$enrollment->rut"
                    :status="$enrollment->estado"
                    :active="$enrollment->estado === 'VIGENTE'"
                    :status-class="$enrollment->estado === 'VIGENTE' ? 'badge-success' : ($enrollment->estado === 'INACTIVA' ? 'badge-danger' : 'badge-warning')"
                >
                    <x-slot:details>
                        <div class="person-card-programs">
                            <span class="badge badge-info">{{ $enrollment->programa }}</span>
                        </div>
                        <div class="person-card-meta-row">
                            <i class="fa-solid fa-calendar-days" aria-hidden="true"></i>
                            <span>Inicio <strong>{{ \Illuminate\Support\Carbon::parse($enrollment->fecha_inicio)->format('d/m/Y') }}</strong></span>
                        </div>
                        <div class="person-card-meta-row">
                            <i class="fa-solid fa-calendar-check" aria-hidden="true"></i>
                            <span>Término <strong>{{ $enrollment->fecha_termino ? \Illuminate\Support\Carbon::parse($enrollment->fecha_termino)->format('d/m/Y') : 'Sin término' }}</strong></span>
                        </div>
                        <div class="person-card-meta-row">
                            <i class="fa-solid fa-note-sticky" aria-hidden="true"></i>
                            <span>Cobertura <strong>{{ $enrollment->cobertura ?: '—' }}</strong></span>
                        </div>
                    </x-slot:details>
                </x-person-card>
            @empty
                <div class="empty-panel" role="status">
                    <span class="empty-icon"><i class="fa-regular fa-folder-open"></i></span>
                    <h2>No se encontraron afiliaciones</h2>
                    <p>Prueba con otros términos de búsqueda o filtros.</p>
                </div>
            @endforelse
        </div>
        <x-record-pagination :paginator="$enrollments" />
    </section>
    @endif

        @if ($screen === 'enroll')
        <section class="card" id="afiliar-paciente">
            <div class="card-header"><h2 class="card-title"><i class="fa-solid fa-user-plus text-primary"></i> Afiliar paciente</h2></div>
            <div class="card-body">
                @if ($activePatientCount === 0 || $programs->where('activo', true)->isEmpty())
                    <div class="notice notice-warning"><i class="fa-solid fa-circle-exclamation"></i><span>Registra primero un paciente y un programa activo.</span></div>
                @else
                    <form action="{{ route('programs.enroll') }}" method="post" class="stack-form">
                        @csrf
                        <x-patient-search id="afiliacion-paciente" :selected-patient="$selectedPatient" required />
                        <div class="form-group"><label for="afiliacion-programa">Programa *</label><select class="form-control" id="afiliacion-programa" name="programa_id" required><option value="">Selecciona programa</option>@foreach ($programs->where('activo', true) as $program)<option value="{{ $program->programa_id }}" @selected(old('programa_id') == $program->programa_id)>{{ $program->nombre }}</option>@endforeach</select></div>
                        <div class="form-grid">
                            <div class="form-group"><label for="inicio-programa">Inicio *</label><input class="form-control" id="inicio-programa" name="fecha_inicio" type="date" value="{{ old('fecha_inicio') }}" required></div>
                            <div class="form-group"><label for="termino-programa">Término</label><input class="form-control" id="termino-programa" name="fecha_termino" type="date" value="{{ old('fecha_termino') }}"></div>
                        </div>
                        <div class="form-group"><label for="cobertura-programa">Cobertura / observación</label><input class="form-control" id="cobertura-programa" name="cobertura" value="{{ old('cobertura') }}" maxlength="255"></div>
                        <div class="detail-heading">
                            <h3>Productos autorizados</h3>
                            <button class="btn btn-outline btn-small" type="button" data-add-row="program-cuota-details"><i class="fa-solid fa-plus"></i> Agregar producto</button>
                        </div>
                        <p class="muted">Puedes autorizar varios productos; cada uno tiene su propia cantidad y período de vigencia.</p>
                        <div id="program-cuota-details" class="detail-rows" data-distinct-products-list data-next-index="{{ count($quotaRows) }}">
                            @foreach ($quotaRows as $index => $quota)
                                <div class="detail-row program-quota-row" data-program-quota-row>
                                    @php
                                        $quotaProductId = $quota['producto_id'] ?? null;
                                        $quotaProduct = is_scalar($quotaProductId)
                                            ? $products->firstWhere('producto_id', (int) $quotaProductId)
                                            : null;
                                    @endphp
                                    <x-product-search
                                        id="program-quota-product-{{ $index }}"
                                        name="detalles[{{ $index }}][producto_id]"
                                        :product="$quotaProduct"
                                        search-label="Producto"
                                        search-placeholder="Buscar producto por nombre"
                                        search-help="Escribe el nombre y selecciona el producto."
                                        :show-availability="false"
                                        :show-result-details="false"
                                        :required="false"
                                        field-class="program-quota-product"
                                    />
                                    <div class="form-group program-quota-quantity">
                                        <label>Cantidad autorizada</label>
                                        <input class="form-control" name="detalles[{{ $index }}][cantidad_autorizada]" type="number" min="0" step="0.01" value="{{ is_scalar($quota['cantidad_autorizada'] ?? null) ? $quota['cantidad_autorizada'] : '' }}" data-quota-field>
                                    </div>
                                    <div class="form-group program-quota-period-start">
                                        <label>Período desde</label>
                                        <input class="form-control" name="detalles[{{ $index }}][periodo_inicio]" type="date" value="{{ is_scalar($quota['periodo_inicio'] ?? null) ? $quota['periodo_inicio'] : '' }}" data-quota-field>
                                    </div>
                                    <div class="form-group program-quota-period-end">
                                        <label>Período hasta</label>
                                        <input class="form-control" name="detalles[{{ $index }}][periodo_termino]" type="date" value="{{ is_scalar($quota['periodo_termino'] ?? null) ? $quota['periodo_termino'] : '' }}" data-quota-field>
                                    </div>
                                    <button class="icon-btn detail-remove program-quota-remove" type="button" aria-label="Eliminar producto"><i class="fa-solid fa-trash"></i></button>
                                </div>
                            @endforeach
                        </div>
                        <template id="program-cuota-detail-template">
                            <div class="detail-row program-quota-row" data-program-quota-row>
                                <x-product-search
                                    id="program-quota-product-template"
                                    field-key="producto_id"
                                    search-label="Producto"
                                    search-placeholder="Buscar producto por nombre"
                                    search-help="Escribe el nombre y selecciona el producto."
                                    :show-availability="false"
                                    :show-result-details="false"
                                    :required="false"
                                    field-class="program-quota-product"
                                />
                                <div class="form-group program-quota-quantity">
                                    <label>Cantidad autorizada</label>
                                    <input class="form-control" data-name="cantidad_autorizada" type="number" min="0" step="0.01" data-quota-field>
                                </div>
                                <div class="form-group program-quota-period-start">
                                    <label>Período desde</label>
                                    <input class="form-control" data-name="periodo_inicio" type="date" data-quota-field>
                                </div>
                                <div class="form-group program-quota-period-end">
                                    <label>Período hasta</label>
                                    <input class="form-control" data-name="periodo_termino" type="date" data-quota-field>
                                </div>
                                <button class="icon-btn detail-remove program-quota-remove" type="button" aria-label="Eliminar producto"><i class="fa-solid fa-trash"></i></button>
                            </div>
                        </template>
                        <button class="btn btn-primary" type="submit"><i class="fa-solid fa-floppy-disk"></i> Guardar afiliación</button>
                    </form>
                @endif
            </div>
        </section>
        @endif
        @if (in_array($screen, ['create', 'edit'], true))
        @if ($canCreateProgram)
            <section class="card" id="crear-programa">
                <div class="card-header"><h2 class="card-title"><i class="fa-solid fa-folder-plus text-primary"></i> {{ $screen === 'edit' ? 'Editar programa' : 'Crear programa' }}</h2></div>
                <div class="card-body">
                    <form action="{{ $screen === 'edit' ? route('programs.update', $program->programa_id) : route('programs.store') }}" method="post" class="stack-form">
                        @csrf
                        @if ($screen === 'edit') @method('put') @endif
                        <div class="form-group"><label for="programa-nombre">Nombre del programa *</label><input class="form-control" id="programa-nombre" name="nombre" value="{{ old('nombre', $program->nombre ?? '') }}" required maxlength="100" placeholder="Programa cardiovascular"></div>
                        <div class="form-group"><label for="programa-descripcion">Descripción</label><textarea class="form-control" id="programa-descripcion" name="descripcion" rows="3" maxlength="255">{{ old('descripcion', $program->descripcion ?? '') }}</textarea></div>
                        <button class="btn btn-outline" type="submit"><i class="fa-solid fa-{{ $screen === 'edit' ? 'floppy-disk' : 'plus' }}"></i> {{ $screen === 'edit' ? 'Guardar cambios' : 'Crear programa' }}</button>
                    </form>
                </div>
            </section>
        @endif
        @endif
@endsection
