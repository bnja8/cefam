@props([
    'id',
    'name' => null,
    'fieldKey' => null,
    'product' => null,
    'barcodeUrl' => null,
    'fefoUrl' => null,
    'searchUrl' => null,
    'searchLabel' => 'Producto *',
    'searchPlaceholder' => 'Buscar medicamento por nombre',
    'searchHelp' => 'Escribe para buscar y seleccionar un producto.',
    'showAvailability' => true,
    'showResultDetails' => true,
    'required' => true,
    'fieldClass' => '',
    'prescription' => false,
])

@php
    $valueId = $id.'-value';
    $helpId = $id.'-help';
    $resultsId = $id.'-results';
    $stockId = $id.'-stock';
@endphp

<div class="form-group product-picker {{ $fieldClass }}" data-product-picker>
    <label for="{{ $id }}">{{ $searchLabel }}</label>
    <div class="patient-picker-control">
        <input
            class="form-control"
            id="{{ $id }}"
            type="search"
            role="combobox"
            aria-autocomplete="list"
            aria-expanded="false"
            aria-controls="{{ $resultsId }}"
            aria-describedby="{{ $showAvailability ? $helpId.' '.$stockId : $helpId }}"
            autocomplete="off"
            placeholder="{{ $searchPlaceholder }}"
            value="{{ $product->nombre ?? '' }}"
            data-product-search-input
            data-product-show-result-details="{{ $showResultDetails ? 'true' : 'false' }}"
            data-product-value-target="{{ $valueId }}"
            data-product-search-url="{{ $searchUrl ?? route('dispensations.products.search') }}"
            @if ($required) required @endif
        >
        <select
            id="{{ $valueId }}"
            class="product-picker-value"
            @if ($name) name="{{ $name }}" @endif
            @if ($fieldKey) data-name="{{ $fieldKey }}" @endif
            data-product-select
            @if ($prescription) data-prescription-product @endif
            data-distinct-products
            @if ($barcodeUrl) data-barcode-url="{{ $barcodeUrl }}" @endif
            @if ($fefoUrl) data-fefo-url="{{ $fefoUrl }}" @endif
            aria-hidden="true"
            tabindex="-1"
            hidden
        >
            <option value="">Selecciona un producto</option>
            @if ($product)
                <option
                    value="{{ $product->producto_id }}"
                    data-controlled="{{ (int) ($product->es_controlado ?? false) }}"
                    data-stock="{{ $product->stock_disponible ?? '' }}"
                    selected
                >{{ $product->nombre }}</option>
            @endif
        </select>
        <div class="search-results product-search-results" id="{{ $resultsId }}" role="listbox" aria-label="Productos encontrados" hidden></div>
    </div>
    <small id="{{ $helpId }}" class="product-search-help">{{ $searchHelp }}</small>
    @if ($prescription)
        <small data-prescription-context aria-live="polite"></small>
    @endif
    @if ($showAvailability)
        <small id="{{ $stockId }}" data-product-availability role="status" aria-live="polite">
            @if ($product)
                @if ((float) $product->stock_disponible > 0)
                    Disponible en vitrina: {{ \App\Support\Formatting\NumberFormat::format($product->stock_disponible) }} unid.
                @else
                    Sin stock vigente disponible en vitrina.
                @endif
            @endif
        </small>
    @endif
    @if ($showAvailability || $prescription)
        <small class="scanner-fefo" data-product-status role="status" aria-live="polite" hidden></small>
    @endif
</div>
