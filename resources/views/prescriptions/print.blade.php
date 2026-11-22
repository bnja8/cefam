@extends('layouts.app')
@section('content')
    <article class="prescription-print-sheet">
        <header class="prescription-print-header">
            <div class="prescription-print-brand">
                <span class="prescription-print-brand-mark" aria-hidden="true">
                    <img src="{{ asset('logo.jpg') }}" alt="">
                </span>
                <div>
                    <span>Farmacia asistencial</span>
                    <strong>CESFAM Central</strong>
                </div>
            </div>
            <div class="prescription-print-title">
                <span>Documento clínico</span>
                <h1>Receta médica</h1>
            </div>
            <div class="prescription-print-folio">
                <span>Folio</span>
                <strong>{{ $prescription->numero_receta }}</strong>
            </div>
        </header>

        <section class="prescription-print-facts" aria-label="Datos de la receta y del paciente">
            <div class="prescription-print-fact prescription-print-fact--wide">
                <span>Paciente</span>
                <strong>{{ $prescription->paciente }}</strong>
            </div>
            <div class="prescription-print-fact prescription-print-fact--wide">
                <span>Profesional</span>
                <strong>{{ $prescription->profesional }}</strong>
            </div>
            <div class="prescription-print-fact">
                <span>RUT</span>
                <strong>{{ $prescription->paciente_rut }}</strong>
            </div>
            <div class="prescription-print-fact">
                <span>Tipo</span>
                <strong>{{ $prescription->tipo_receta }}</strong>
            </div>
            <div class="prescription-print-fact">
                <span>Emisión</span>
                <strong>{{ \Illuminate\Support\Carbon::parse($prescription->fecha_emision)->format('d/m/Y') }}</strong>
            </div>
            <div class="prescription-print-fact">
                <span>Vencimiento</span>
                <strong>{{ \Illuminate\Support\Carbon::parse($prescription->fecha_vencimiento)->format('d/m/Y') }}</strong>
            </div>
        </section>

        <section class="prescription-print-medicines" aria-labelledby="prescription-print-medicines-title">
            <div class="prescription-print-section-heading">
                <h2 id="prescription-print-medicines-title">Medicamentos prescritos</h2>
                <span>{{ count($details) }} {{ count($details) === 1 ? 'medicamento' : 'medicamentos' }}</span>
            </div>
            <table class="prescription-print-table" aria-label="Detalle de medicamentos prescritos">
                <colgroup>
                    <col class="prescription-print-column-product">
                    <col class="prescription-print-column-route">
                    <col class="prescription-print-column-quantity">
                    <col class="prescription-print-column-dose">
                    <col class="prescription-print-column-frequency">
                    <col class="prescription-print-column-duration">
                    <col class="prescription-print-column-instructions">
                </colgroup>
                <thead>
                    <tr>
                        <th scope="col">Medicamento</th>
                        <th scope="col">Vía</th>
                        <th scope="col">Cantidad</th>
                        <th scope="col">Dosis</th>
                        <th scope="col">Frecuencia</th>
                        <th scope="col">Duración</th>
                        <th scope="col">Indicaciones</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($details as $detail)
                        <tr>
                            <td data-label="Medicamento"><strong>{{ $detail->producto }}</strong></td>
                            <td data-label="Vía">{{ $detail->via_administracion ?: '—' }}</td>
                            <td data-label="Cantidad">{{ \App\Support\Formatting\NumberFormat::format($detail->cantidad_indicada) }} {{ $detail->unidad_base_codigo }}</td>
                            <td data-label="Dosis">{{ $detail->dosis ?: '—' }}</td>
                            <td data-label="Frecuencia">{{ \App\Support\Prescriptions\MedicationFrequency::label($detail->frecuencia) }}</td>
                            <td data-label="Duración">{{ $detail->es_sos ? 'SOS · máximo prescrito' : ($detail->duracion_dias ? $detail->duracion_dias.' días' : '—') }}</td>
                            <td data-label="Indicaciones">{{ $detail->indicaciones ?: '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </section>
    </article>
@endsection
