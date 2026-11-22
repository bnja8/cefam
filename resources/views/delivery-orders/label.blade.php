@extends('layouts.app')
@section('content')
    @if ($printSettings['configured'])
        <style>
            @page {
                size: {{ number_format($printSettings['width_mm'], 2, '.', '') }}mm {{ number_format($printSettings['height_mm'], 2, '.', '') }}mm;
                margin: 0;
            }

            body.print-delivery-label .dispensed-label-sheet {
                --label-width: {{ number_format($printSettings['width_mm'], 2, '.', '') }}mm;
                --label-height: {{ number_format($printSettings['height_mm'], 2, '.', '') }}mm;
                --label-margin: {{ number_format($printSettings['margin_mm'], 2, '.', '') }}mm;
                --label-font-family: {{ $printSettings['font_family'] }};
                --label-minimum-font-size: {{ number_format($printSettings['minimum_font_size_pt'], 2, '.', '') }}pt;
            }
        </style>
    @else
        <style>
            @media print {
                body.print-delivery-label .dispensed-label-sheet { display: none !important; }
                body.print-delivery-label .label-config-warning { display: block !important; }
            }
        </style>
    @endif

    <x-page-header title="Etiquetas farmacéuticas" description="Una etiqueta por medicamento y lote dispensado">
        <x-slot:actions>
            @if ($printSettings['configured'])
                <button class="btn btn-primary label-page-action" type="button" data-page-print>
                    <i class="fa-solid fa-print"></i> Imprimir etiquetas
                </button>
            @endif
            <button class="btn btn-outline label-page-action" type="button" data-window-close>Cerrar</button>
        </x-slot:actions>
    </x-page-header>

    @unless ($printSettings['configured'])
        <div class="notice notice-warning label-config-warning" data-label-print-ready="false" data-label-config-warning>
            <i class="fa-solid fa-circle-exclamation" aria-hidden="true"></i>
            <span>Configura LABEL_WIDTH_MM y LABEL_HEIGHT_MM con las dimensiones reales del rollo antes de imprimir. No se usará un tamaño supuesto.</span>
        </div>
    @else
        @if ($printSettings['provisional'])
            <div class="notice notice-warning label-preview-warning">
                <i class="fa-solid fa-ruler-combined" aria-hidden="true"></i>
                <span>Medida provisional para vista previa y PDF: {{ number_format($printSettings['width_mm'], 2, ',', '.') }} × {{ number_format($printSettings['height_mm'], 2, ',', '.') }} mm. Reemplázala en LABEL_WIDTH_MM y LABEL_HEIGHT_MM cuando conozcas el área real.</span>
            </div>
        @endif
        <p class="label-page-instructions" data-label-print-ready="true">
            {{ $printSettings['provisional'] ? 'Vista previa provisional' : 'Vista previa' }} · {{ number_format($printSettings['width_mm'], 2, ',', '.') }} × {{ number_format($printSettings['height_mm'], 2, ',', '.') }} mm ·
            {{ $details->count() }} etiqueta(s) · 1 copia por etiqueta.
            Configura el tipo de soporte y método térmico en el controlador de la impresora.
        </p>
    @endunless

    <section class="dispensed-label-sheet" aria-label="Vista previa de etiquetas">
        @foreach ($details as $detail)
            <article class="dispensed-label">
                <header class="dispensed-label-header">
                    <div class="dispensed-label-brand">
                        <strong>CESFAM CENTRAL · FARMACIA ASISTENCIAL APS</strong>
                        <small>{{ $order->numero_orden }} · {{ \Illuminate\Support\Carbon::parse($order->fecha_hora)->format('d/m/Y H:i') }}</small>
                    </div>
                    <span class="dispensed-label-lot">LOTE {{ $detail->numero_lote }}</span>
                </header>
                <section class="dispensed-label-patient">
                    <span>PACIENTE</span>
                    <strong>{{ $order->paciente }}</strong>
                    <small>RUT: {{ $order->paciente_rut }}</small>
                </section>
                <section class="dispensed-label-medication">
                    <span>MEDICAMENTO DISPENSADO</span>
                    <h2>{{ $detail->producto }}</h2>
                    <div class="dispensed-label-facts">
                        <div><strong>Dosis:</strong> {{ $detail->dosis ?: 'Según indicación profesional' }}</div>
                        <div><strong>Frecuencia:</strong> {{ \App\Support\Prescriptions\MedicationFrequency::label($detail->horario) }}</div>
                        <div><strong>Entregado:</strong> {{ \App\Support\Formatting\NumberFormat::format($detail->cantidad) }} {{ $detail->unidad_base_codigo ?: 'unid.' }}</div>
                        <div><strong>Vence lote:</strong> {{ \Illuminate\Support\Carbon::parse($detail->fecha_vencimiento)->format('d/m/Y') }}</div>
                    </div>
                </section>
                <p class="dispensed-label-instructions"><strong>Indicaciones:</strong> {{ $detail->indicaciones ?: 'Seguir las indicaciones del profesional tratante.' }}</p>
                <footer class="dispensed-label-footer">
                    <span>Retira: {{ $order->persona_autorizada ? $order->persona_autorizada.($order->persona_autorizada_rut ? ' (RUT '.$order->persona_autorizada_rut.')' : '') : 'Titular' }}</span>
                    <strong>Mantener fuera del alcance de los niños</strong>
                </footer>
            </article>
        @endforeach
    </section>
@endsection
