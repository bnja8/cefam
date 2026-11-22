@php
    $ingredientRows = old('principios_activos', $ingredients ?? []);
    if (! is_array($ingredientRows) || $ingredientRows === []) {
        $ingredientRows = [[]];
    }
    $legacyMedicineChoice = old(
        'es_medicamento',
        isset($product) ? ($product->es_medicamento === null ? '' : (int) $product->es_medicamento) : '1'
    );
    $medicineChoice = old(
        'tipo_producto',
        isset($product)
            ? ($product->tipo_producto ?? ((int) $product->es_medicamento === 1 ? 'MEDICAMENTO' : 'INSUMO_CLINICO'))
            : ((string) $legacyMedicineChoice === '1' ? 'MEDICAMENTO' : 'INSUMO_CLINICO')
    );
    $defaultUnit = $measurementUnits->firstWhere('codigo', 'unidad');
    $baseUnitChoice = old(
        'unidad_base_inventario_id',
        $product->unidad_base_inventario_id ?? ($medicineChoice === 'MEDICAMENTO'
            ? old('contenido_envase_unidad_id', '')
            : ($defaultUnit->unidad_medida_id ?? ''))
    );
    $presentationRows = old('presentaciones', $presentations ?? []);
    if (! is_array($presentationRows)) {
        $presentationRows = [];
    }
    $nextPresentationIndex = $presentationRows === [] ? 0 : max(array_keys($presentationRows)) + 1;
    $commercialPresentationRows = old('presentaciones_comerciales', $commercialPresentations ?? []);
    if (! is_array($commercialPresentationRows)) {
        $commercialPresentationRows = [];
    }
    $nextCommercialPresentationIndex = $commercialPresentationRows === []
        ? 0
        : max(array_keys($commercialPresentationRows)) + 1;
    $controlled = old('es_controlado', $product->es_controlado ?? false);
@endphp
<fieldset class="product-section form-span">
    <legend>Información general</legend>
    <div class="form-grid">
        <div class="form-group">
            <label for="codigo_producto">Código único *</label>
            <input class="form-control" id="codigo_producto" name="codigo_producto" value="{{ old('codigo_producto', $product->numero_serie ?? '') }}" required maxlength="50">
            @error('codigo_producto')<small class="field-error">{{ $message }}</small>@enderror
        </div>
        <div class="form-group">
            <label for="nombre">Nombre *</label>
            <input class="form-control" id="nombre" name="nombre" value="{{ old('nombre', $product->nombre ?? '') }}" required maxlength="150">
            @error('nombre')<small class="field-error">{{ $message }}</small>@enderror
        </div>
        <div class="form-group">
            <label for="categoria_id">Categoría *</label>
            <select class="form-control" id="categoria_id" name="categoria_id" required>
                <option value="">Selecciona categoría</option>
                @foreach ($categories as $category)
                    <option value="{{ $category->categoria_id }}" @selected((string) old('categoria_id', $product->categoria_id ?? '') === (string) $category->categoria_id)>{{ $category->nombre }}</option>
                @endforeach
            </select>
            @error('categoria_id')<small class="field-error">{{ $message }}</small>@enderror
        </div>
        <div class="form-group">
            <label for="codigo_barra">Código de barras</label>
            <input class="form-control" id="codigo_barra" name="codigo_barra" value="{{ old('codigo_barra', $barcode->codigo_barra ?? '') }}" maxlength="100">
            @error('codigo_barra')<small class="field-error">{{ $message }}</small>@enderror
        </div>
        <div class="form-group form-span">
            <label for="descripcion">Descripción técnica</label>
            <textarea class="form-control" id="descripcion" name="descripcion" rows="2">{{ old('descripcion', $product->descripcion ?? '') }}</textarea>
            @error('descripcion')<small class="field-error">{{ $message }}</small>@enderror
        </div>
        <div class="form-group form-span">
            <label for="tipo_producto">Tipo de producto *</label>
            <select class="form-control" id="tipo_producto" name="tipo_producto" required data-product-kind>
                <option value="">Selecciona el tipo de producto</option>
                <option value="MEDICAMENTO" @selected($medicineChoice === 'MEDICAMENTO')>Medicamento</option>
                <option value="INSUMO_CLINICO" @selected($medicineChoice === 'INSUMO_CLINICO')>Insumo clínico</option>
                <option value="DISPOSITIVO_MEDICO" @selected($medicineChoice === 'DISPOSITIVO_MEDICO')>Dispositivo médico</option>
            </select>
            @error('tipo_producto')<small class="field-error">{{ $message }}</small>@enderror
        </div>
        <div class="form-group">
            <label for="unidad_base_inventario_id">Unidad base de inventario y dispensación *</label>
            <select class="form-control" id="unidad_base_inventario_id" name="unidad_base_inventario_id" data-form-dependent-unit data-unit-role="base-inventory" required>
                <option value="">Selecciona unidad base</option>
                @foreach ($measurementUnits as $unit)
                    <option value="{{ $unit->unidad_medida_id }}" data-unit-code="{{ $unit->codigo }}" @selected((string) $baseUnitChoice === (string) $unit->unidad_medida_id)>{{ $unit->codigo }} · {{ $unit->nombre }}</option>
                @endforeach
            </select>
            <small>Las cantidades de stock, receta y entrega se registrarán en esta unidad.</small>
            @error('unidad_base_inventario_id')<small class="field-error">{{ $message }}</small>@enderror
        </div>
    </div>
</fieldset>

<fieldset class="product-section form-span">
    <legend>Presentaciones comerciales</legend>
    <input type="hidden" name="presentaciones_comerciales_present" value="1">
    <div class="detail-heading">
        <small>Agrega los nombres comerciales o marcas alternativas para encontrar este producto en el inventario.</small>
        <button class="btn btn-outline btn-small" type="button" data-add-commercial-presentation>Agregar nombre comercial</button>
    </div>
    <div class="detail-rows" data-commercial-presentation-list data-next-index="{{ $nextCommercialPresentationIndex }}">
        @foreach ($commercialPresentationRows as $index => $commercialPresentation)
            <div class="detail-row">
                <div class="form-group">
                    <label>Nombre comercial</label>
                    <input class="form-control" name="presentaciones_comerciales[{{ $index }}]" value="{{ $commercialPresentation }}" maxlength="150" required>
                    @error('presentaciones_comerciales.'.$index)<small class="field-error">{{ $message }}</small>@enderror
                </div>
                <button class="icon-btn detail-remove" type="button" data-remove-commercial-presentation aria-label="Eliminar nombre comercial"><i class="fa-solid fa-trash"></i></button>
            </div>
        @endforeach
    </div>
    <template data-commercial-presentation-template>
        <div class="detail-row">
            <div class="form-group">
                <label>Nombre comercial</label>
                <input class="form-control" data-commercial-presentation-name maxlength="150" required>
            </div>
            <button class="icon-btn detail-remove" type="button" data-remove-commercial-presentation aria-label="Eliminar nombre comercial"><i class="fa-solid fa-trash"></i></button>
        </div>
    </template>
    @error('presentaciones_comerciales')<small class="field-error">{{ $message }}</small>@enderror
</fieldset>

<fieldset class="product-section form-span" data-medication-fields @if ($medicineChoice !== 'MEDICAMENTO') hidden disabled @endif>
    <legend>Información del medicamento</legend>
    <div class="form-grid">
        <fieldset class="product-section form-span">
            <legend>Información farmacéutica</legend>
            <div class="form-grid">
                <div class="form-group">
                    <label for="forma_farmaceutica_id">Forma farmacéutica *</label>
                    <select class="form-control" id="forma_farmaceutica_id" name="forma_farmaceutica_id" data-required-when-medicine data-pharmaceutical-form required>
                        <option value="">Selecciona forma farmacéutica</option>
                        @foreach ($dosageForms as $form)
                            <option value="{{ $form->forma_farmaceutica_id }}" data-form-name="{{ $form->nombre }}" @selected((string) old('forma_farmaceutica_id', $product->forma_farmaceutica_id ?? '') === (string) $form->forma_farmaceutica_id)>{{ $form->nombre }}</option>
                        @endforeach
                    </select>
                    @error('forma_farmaceutica_id')<small class="field-error">{{ $message }}</small>@enderror
                </div>
                <div class="form-group">
                    <label for="via_administracion">Vía de administración *</label>
                    <select class="form-control" id="via_administracion" name="via_administracion" data-required-when-medicine required>
                        <option value="">Selecciona vía</option>
                        @foreach (['ORAL' => 'Oral', 'TOPICA' => 'Tópica', 'INTRAVENOSA' => 'Intravenosa', 'INTRAMUSCULAR' => 'Intramuscular', 'SUBCUTANEA' => 'Subcutánea', 'INHALATORIA' => 'Inhalatoria', 'OFTALMICA' => 'Oftálmica', 'OTICA' => 'Ótica', 'RECTAL' => 'Rectal', 'VAGINAL' => 'Vaginal', 'NASAL' => 'Nasal', 'TRANSDERMICA' => 'Transdérmica', 'BUCAL' => 'Bucal', 'SUBLINGUAL' => 'Sublingual'] as $value => $label)
                            <option value="{{ $value }}" @selected(old('via_administracion', $product->via_administracion ?? '') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('via_administracion')<small class="field-error">{{ $message }}</small>@enderror
                </div>
                <div class="form-group form-span">
                    <div class="detail-heading">
                        <h3>Principios activos y concentraciones *</h3>
                        <button class="btn btn-outline btn-small" type="button" data-add-ingredient>Agregar principio activo</button>
                    </div>
                    <small>Registra cada componente por separado. Ejemplos: 500 mg por comprimido o 120 mg por 5 mL.</small>
                    <div class="product-ingredient-list" data-ingredient-list data-next-index="{{ count($ingredientRows) }}">
                        @foreach ($ingredientRows as $index => $ingredient)
                            @include('inventory.partials.ingredient-row', ['index' => $index, 'ingredient' => $ingredient])
                        @endforeach
                    </div>
                    @error('principios_activos')<small class="field-error">{{ $message }}</small>@enderror
                </div>
                <template data-ingredient-template>
                    @include('inventory.partials.ingredient-row', ['index' => '__INDEX__', 'ingredient' => []])
                </template>
            </div>
        </fieldset>

        <fieldset class="product-section form-span">
            <legend>Presentaciones de adquisición</legend>
            <div class="detail-heading">
                <small>Define cuántas unidades base contiene cada presentación comercial; por ejemplo, Caja x 100 comprimidos = 100.</small>
                <button class="btn btn-outline btn-small" type="button" data-add-presentation>Agregar presentación</button>
            </div>
            <div class="detail-rows" data-presentation-list data-next-index="{{ $nextPresentationIndex }}">
                @foreach ($presentationRows as $index => $presentation)
                    <div class="detail-row">
                        <div class="form-group">
                            <label>Nombre de presentación</label>
                            <input class="form-control" name="presentaciones[{{ $index }}][nombre]" value="{{ $presentation['nombre'] ?? '' }}" maxlength="100" required>
                        </div>
                        <div class="form-group">
                            <label>Contenido por envase (unidades base)</label>
                            <input class="form-control" name="presentaciones[{{ $index }}][factor_conversion]" type="number" min="0.0001" step="0.0001" value="{{ $presentation['factor_conversion'] ?? '' }}" required>
                        </div>
                        <button class="icon-btn detail-remove" type="button" data-remove-presentation aria-label="Eliminar presentación"><i class="fa-solid fa-trash"></i></button>
                    </div>
                @endforeach
            </div>
            <template data-presentation-template>
                <div class="detail-row">
                    <div class="form-group">
                        <label>Nombre de presentación</label>
                        <input class="form-control" data-name="nombre" maxlength="100" required>
                    </div>
                    <div class="form-group">
                        <label>Contenido por envase (unidades base)</label>
                        <input class="form-control" data-name="factor_conversion" type="number" min="0.0001" step="0.0001" required>
                    </div>
                    <button class="icon-btn detail-remove" type="button" data-remove-presentation aria-label="Eliminar presentación"><i class="fa-solid fa-trash"></i></button>
                </div>
            </template>
            @error('presentaciones')<small class="field-error">{{ $message }}</small>@enderror
        </fieldset>

        <fieldset class="product-section form-span">
            <legend>Presentación del envase</legend>
            <div class="form-grid">
                <div class="form-group">
                    <label for="contenido_envase_cantidad">Contenido del envase *</label>
                    <input class="form-control" id="contenido_envase_cantidad" name="contenido_envase_cantidad" type="number" min="0.001" step="0.001" value="{{ old('contenido_envase_cantidad', $product->contenido_envase_cantidad ?? '') }}" data-required-when-medicine data-package-content-amount required>
                    <small>El stock se contará por la unidad indicada. Ejemplo: una caja con 16 comprimidos equivale a 16 comprimidos.</small>
                    @error('contenido_envase_cantidad')<small class="field-error">{{ $message }}</small>@enderror
                </div>
                <div class="form-group">
                    <label for="contenido_envase_unidad_id">Formato por Unidades *</label>
                    <select class="form-control" id="contenido_envase_unidad_id" name="contenido_envase_unidad_id" data-required-when-medicine data-form-dependent-unit data-unit-role="package-content" required>
                        <option value="">Selecciona unidad</option>
                        @foreach ($measurementUnits->whereIn('tipo', ['MASA', 'VOLUMEN', 'PRESENTACION']) as $unit)
                            <option value="{{ $unit->unidad_medida_id }}" data-unit-code="{{ $unit->codigo }}" @selected((string) old('contenido_envase_unidad_id', $product->contenido_envase_unidad_id ?? '') === (string) $unit->unidad_medida_id)>{{ $unit->codigo }} · {{ $unit->nombre }}</option>
                        @endforeach
                    </select>
                    @error('contenido_envase_unidad_id')<small class="field-error">{{ $message }}</small>@enderror
                </div>
                <div class="form-group">
                    <label for="tipo_presentacion">Tipo de Empaquetado *</label>
                    <select class="form-control" id="tipo_presentacion" name="tipo_presentacion" data-required-when-medicine required>
                        <option value="">Selecciona presentación</option>
                        @foreach (['CAJA' => 'Caja', 'FRASCO' => 'Frasco', 'TUBO' => 'Tubo', 'BLISTER' => 'Blíster', 'SOBRE' => 'Sobre', 'UNIDAD' => 'Unidad', 'OTRO' => 'Otro'] as $value => $label)
                            <option value="{{ $value }}" @selected(old('tipo_presentacion', $product->tipo_presentacion ?? '') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('tipo_presentacion')<small class="field-error">{{ $message }}</small>@enderror
                </div>
            </div>
        </fieldset>

        <fieldset class="product-section form-span">
            <legend>Información regulatoria</legend>
            <div class="form-grid">
                <div class="form-group">
                    <label for="bioequivalencia_estado">Equivalencia terapéutica *</label>
                    <select class="form-control" id="bioequivalencia_estado" name="bioequivalencia_estado" data-required-when-medicine required>
                        @foreach (['PENDIENTE' => 'Pendiente de verificar', 'SI' => 'Sí', 'NO' => 'No', 'NO_APLICA' => 'No aplica'] as $value => $label)
                            <option value="{{ $value }}" @selected(old('bioequivalencia_estado', $product->bioequivalencia_estado ?? 'PENDIENTE') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    <small>La equivalencia debe corresponder al producto específico y contar con respaldo regulatorio.</small>
                    @error('bioequivalencia_estado')<small class="field-error">{{ $message }}</small>@enderror
                </div>
                <div class="form-group">
                    <label for="registro_sanitario_isp">Registro sanitario ISP</label>
                    <input class="form-control" id="registro_sanitario_isp" name="registro_sanitario_isp" maxlength="80" value="{{ old('registro_sanitario_isp', $product->registro_sanitario_isp ?? '') }}">
                </div>
                <div class="form-group">
                    <label for="estado_registro_sanitario">Estado del registro sanitario *</label>
                    <select class="form-control" id="estado_registro_sanitario" name="estado_registro_sanitario" data-required-when-medicine required>
                        @foreach (['PENDIENTE' => 'Pendiente de verificar', 'VIGENTE' => 'Vigente', 'SUSPENDIDO' => 'Suspendido', 'VENCIDO' => 'Vencido', 'CANCELADO' => 'Cancelado', 'NO_APLICA' => 'No aplica'] as $value => $label)
                            <option value="{{ $value }}" @selected(old('estado_registro_sanitario', $product->estado_registro_sanitario ?? 'PENDIENTE') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('estado_registro_sanitario')<small class="field-error">{{ $message }}</small>@enderror
                </div>
                <div class="form-group form-span">
                    <label class="check-label"><input type="checkbox" name="es_controlado" value="1" @checked($controlled) data-controlled-product> Medicamento controlado</label>
                    <div class="form-group" data-controlled-fields @if (! $controlled) hidden @endif>
                        <label for="tipo_control_legal">Tipo de control legal *</label>
                        <select class="form-control" id="tipo_control_legal" name="tipo_control_legal" data-required-when-controlled>
                            <option value="">Selecciona tipo de control</option>
                            @foreach (['ESTUPEFACIENTE' => 'Estupefaciente', 'PSICOTROPICO' => 'Psicotrópico', 'OTRO' => 'Otro control legal'] as $value => $label)
                                <option value="{{ $value }}" @selected(old('tipo_control_legal', $product->tipo_control_legal ?? '') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('tipo_control_legal')<small class="field-error">{{ $message }}</small>@enderror
                    </div>
                </div>
            </div>
        </fieldset>
    </div>
</fieldset>
