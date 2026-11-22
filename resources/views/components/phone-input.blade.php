@props(['id', 'value' => null])
<div class="form-group">
    <label for="{{ $id }}">Teléfono</label>
    <div class="mobile-phone-input">
        <span aria-hidden="true">+56 9</span>
        <input
            class="form-control"
            id="{{ $id }}"
            name="telefono"
            type="text"
            inputmode="numeric"
            pattern="[0-9]{8}"
            maxlength="8"
            autocomplete="tel-national"
            placeholder="12345678"
            aria-label="Teléfono; ingresa los 8 dígitos después de +56 9"
            data-chilean-mobile-phone
            value="{{ \App\Support\People\ChileanMobilePhone::inputValue(old('telefono', $value)) }}"
        >
    </div>
    <small>Ingresa los 8 dígitos del número móvil.</small>
    @error('telefono')<small class="field-error">{{ $message }}</small>@enderror
</div>
