@props([
    'id',
    'name' => 'paciente_rut',
    'label' => 'Paciente',
    'selectedPatient' => null,
    'selectedRutOnly' => false,
    'required' => false,
    'valueId' => null,
    'optionsUrl' => null,
    'placeholder' => 'Buscar por nombre o RUT',
    'helpText' => 'Escribe al menos dos caracteres y selecciona un resultado.',
])

@php
    $valueId ??= $id.'-value';
    $helpId = $id.'-help';
    $resultsId = $id.'-results';
    $selectedRut = old($name, $selectedPatient->rut ?? '');
    $selectedLabel = $selectedPatient
        ? ($selectedRutOnly ? $selectedPatient->rut : $selectedPatient->nombre_completo.' · '.$selectedPatient->rut)
        : old($name, '');
@endphp

<div {{ $attributes->merge(['class' => 'form-group patient-picker']) }}>
    <label for="{{ $id }}">{{ $label }}@if ($required) *@endif</label>
    <div class="patient-picker-control">
        <input
            class="form-control"
            id="{{ $id }}"
            type="search"
            role="combobox"
            aria-autocomplete="list"
            aria-expanded="false"
            aria-controls="{{ $resultsId }}"
            aria-describedby="{{ $helpId }}"
            autocomplete="off"
            placeholder="{{ $placeholder }}"
            value="{{ $selectedLabel }}"
            data-patient-search-url="{{ route('patients.api.search') }}"
            data-patient-value-target="{{ $valueId }}"
            data-patient-selected-rut-only="{{ $selectedRutOnly ? 'true' : 'false' }}"
            @if ($required) required @endif
        >
        <input
            id="{{ $valueId }}"
            name="{{ $name }}"
            type="hidden"
            value="{{ $selectedRut }}"
            @if ($optionsUrl) data-dispensation-options-url="{{ $optionsUrl }}" @endif
        >
        <div class="search-results patient-search-results" id="{{ $resultsId }}" role="listbox" aria-label="Pacientes encontrados" hidden></div>
    </div>
    <small id="{{ $helpId }}" aria-live="polite">{{ $helpText }}</small>
</div>
