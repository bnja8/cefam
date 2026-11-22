@extends('layouts.app')
@section('content')
    <x-page-header title="Receta Médica" description="Comprobante imprimible de la dispensación">
        <x-slot:actions>
            <button class="btn btn-primary label-page-action" type="button" data-page-print><i class="fa-solid fa-print"></i> Imprimir Orden</button>
            <button class="btn btn-outline label-page-action" type="button" data-window-close>Cerrar</button>
        </x-slot:actions>
    </x-page-header>

    <article class="delivery-order-print-sheet">
        <header class="delivery-order-print-header">
            <span class="delivery-order-print-icon" aria-hidden="true"><img src="{{ asset('logo.jpg') }}" alt=""></span>
            <div>
                <h1>Receta Médica</h1>
                <p>CESFAM Central · Atención Primaria de Salud · Operación auditada</p>
            </div>
        </header>
        <section class="delivery-order-print-summary">
            <div><span>N° ORDEN ENTREGA</span><strong>{{ $order->numero_orden }}</strong></div>
            <div><span>FOLIO RECETA COTEJADA</span><strong>{{ $order->numero_receta ?: 'Sin folio' }}</strong></div>
            <div><span>FECHA Y HORA DESPACHO</span><strong>{{ \Illuminate\Support\Carbon::parse($order->fecha_hora)->format('d/m/Y H:i') }}</strong></div>
        </section>
        <section class="delivery-order-print-patient">
            <div><span>PACIENTE TITULAR</span><strong>{{ $order->paciente }} ({{ $order->rut }})</strong></div>
            <div>
                <span>PERSONA QUE RETIRA</span>
                <strong>{{ $order->persona_autorizada ? $order->persona_autorizada.($order->persona_autorizada_rut ? ' ('.$order->persona_autorizada_rut.')' : '') : 'Titular: '.$order->paciente.' (RUT '.$order->rut.')' }}</strong>
            </div>
        </section>
        <table class="delivery-order-print-items">
            <thead>
                <tr><th>Medicamento / Insumo</th><th>Lote FEFO</th><th>Vencimiento</th><th>Entregado</th><th>Dosis / Frecuencia</th><th>Indicaciones</th></tr>
            </thead>
            <tbody>
                @foreach ($details as $detail)
                    <tr>
                        <td>{{ $detail->producto }}</td>
                        <td>{{ $detail->numero_lote }}</td>
                        <td>{{ \Illuminate\Support\Carbon::parse($detail->fecha_vencimiento)->format('d/m/Y') }}</td>
                        <td>{{ \App\Support\Formatting\NumberFormat::format($detail->cantidad) }} {{ $detail->unidad_base_codigo }}</td>
                        <td>{{ $detail->dosis ?: 'Según indicación profesional' }} · {{ \App\Support\Prescriptions\MedicationFrequency::label($detail->horario) }}</td>
                        <td>{{ $detail->indicaciones ?: '—' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        <footer class="delivery-order-print-footer">
            <span>Dispensación confirmada por <strong>{{ $order->responsable }}</strong></span>
            <strong>Mantener los medicamentos fuera del alcance de los niños.</strong>
        </footer>
    </article>
@endsection
