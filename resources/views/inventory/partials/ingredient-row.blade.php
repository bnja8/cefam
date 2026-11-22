<div class="product-ingredient-row" data-ingredient-row>
    <div class="form-group">
        <label for="principio-{{ $index }}">Principio activo *</label>
        <input class="form-control" id="principio-{{ $index }}" name="principios_activos[{{ $index }}][nombre]" value="{{ data_get($ingredient, 'nombre') }}" maxlength="150" data-required-when-medicine required>
        @error("principios_activos.$index.nombre")<small class="field-error">{{ $message }}</small>@enderror
    </div>
    <div class="form-group">
        <label for="concentracion-{{ $index }}">Concentración *</label>
        <div class="form-grid product-concentration">
            <input class="form-control" id="concentracion-{{ $index }}" name="principios_activos[{{ $index }}][concentracion_cantidad]" type="number" min="0.0001" step="0.0001" value="{{ data_get($ingredient, 'concentracion_cantidad') }}" data-required-when-medicine required>
            <select class="form-control" name="principios_activos[{{ $index }}][concentracion_unidad_id]" aria-label="Unidad de concentración" data-required-when-medicine data-concentration-unit required>
                <option value="">Seleccion Unidad</option>
                @foreach ($measurementUnits->whereIn('tipo', ['MASA', 'PORCENTAJE']) as $unit)
                    <option value="{{ $unit->unidad_medida_id }}" data-unit-code="{{ $unit->codigo }}" @selected((string) data_get($ingredient, 'concentracion_unidad_id') === (string) $unit->unidad_medida_id)>{{ $unit->codigo }}</option>
                @endforeach
            </select>
        </div>
        @error("principios_activos.$index.concentracion_cantidad")<small class="field-error">{{ $message }}</small>@enderror
        @error("principios_activos.$index.concentracion_unidad_id")<small class="field-error">{{ $message }}</small>@enderror
    </div>
    <div class="form-group">
        <label for="concentracion-base-{{ $index }}">Por unidad de referencia (opcional)</label>
        <div class="form-grid product-concentration">
            <input class="form-control" id="concentracion-base-{{ $index }}" name="principios_activos[{{ $index }}][concentracion_base_cantidad]" type="number" min="0.0001" step="0.0001" value="{{ data_get($ingredient, 'concentracion_base_cantidad') }}" placeholder="Ej. 5" data-concentration-base-amount>
            <select class="form-control" name="principios_activos[{{ $index }}][concentracion_base_unidad_id]" aria-label="Unidad de referencia" data-form-dependent-unit data-unit-role="concentration-base">
                <option value="">Seleccion Unidad</option>
                @foreach ($measurementUnits->whereIn('tipo', ['MASA', 'VOLUMEN', 'PRESENTACION']) as $unit)
                    <option value="{{ $unit->unidad_medida_id }}" data-unit-code="{{ $unit->codigo }}" @selected((string) data_get($ingredient, 'concentracion_base_unidad_id') === (string) $unit->unidad_medida_id)>{{ $unit->codigo }}</option>
                @endforeach
            </select>
        </div>
        @error("principios_activos.$index.concentracion_base_cantidad")<small class="field-error">{{ $message }}</small>@enderror
    </div>
    <button class="btn btn-outline btn-small product-ingredient-remove" type="button" aria-label="Quitar principio activo" data-remove-ingredient>Quitar</button>
</div>
