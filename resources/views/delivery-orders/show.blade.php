@extends('layouts.app')
@section('content')
    <x-page-header title="Detalle de dispensación" description="Consulta los medicamentos entregados e imprime las etiquetas de la orden">
        <x-slot:actions>
            <button class="btn btn-primary" type="button" data-print-label-url="{{ route('delivery-orders.labels', $order->orden_entrega_id) }}">
                <i class="fa-solid fa-print" aria-hidden="true"></i> Imprimir etiquetas
            </button>
            <a class="btn btn-outline" href="{{ route('dispensations.index') }}">Volver</a>
        </x-slot:actions>
    </x-page-header>

    <section class="card delivery-order">
        <div class="card-body">
            <div class="detail-heading">
                <div>
                    <h2>Orden {{ $order->numero_orden }}</h2>
                    <span class="muted">Medicamentos dispensados</span>
                </div>
                <div class="order-number"><span>{{ \Illuminate\Support\Carbon::parse($order->fecha_generacion)->format('d/m/Y H:i') }}</span></div>
            </div>

            <div class="form-grid order-patient">
                <div><span class="muted">Paciente</span><strong>{{ $order->paciente }}</strong></div>
                <div><span class="muted">RUT</span><strong>{{ $order->rut }}</strong></div>
                <div><span class="muted">Retira</span><strong>{{ $order->persona_autorizada ? $order->persona_autorizada.($order->persona_autorizada_rut ? ' · '.$order->persona_autorizada_rut : '') : 'Paciente' }}</strong></div>
                <div><span class="muted">Dispensado por</span><strong>{{ $order->responsable }}</strong></div>
                <div><span class="muted">Fecha de entrega</span><strong>{{ \Illuminate\Support\Carbon::parse($order->fecha_hora)->format('d/m/Y H:i') }}</strong></div>
            </div>

            <div class="table-responsive">
                <table class="data-table">
                    <thead><tr><th>PRODUCTO</th><th>LOTE FEFO</th><th>VENCIMIENTO</th><th>CANTIDAD PRESCRITA</th><th>ENTREGADA EN ESTA ORDEN</th><th>SALDO PENDIENTE</th><th>DOSIS</th><th>FRECUENCIA</th><th>INDICACIONES</th></tr></thead>
                    <tbody>
                    @foreach ($details as $detail)
                        <tr>
                            <td>{{ $detail->producto }}</td>
                            <td>{{ $detail->numero_lote }}</td>
                            <td>{{ \Illuminate\Support\Carbon::parse($detail->fecha_vencimiento)->format('d/m/Y') }}</td>
                            <td>{{ $detail->cantidad_prescrita === null ? '—' : \App\Support\Formatting\NumberFormat::format($detail->cantidad_prescrita).' '.$detail->unidad_base_codigo }}</td>
                            <td>{{ \App\Support\Formatting\NumberFormat::format($detail->cantidad) }} {{ $detail->unidad_base_codigo }}</td>
                            <td>{{ $detail->cantidad_pendiente === null ? '—' : \App\Support\Formatting\NumberFormat::format($detail->cantidad_pendiente).' '.$detail->unidad_base_codigo }}</td>
                            <td>{{ $detail->dosis ?: '—' }}</td>
                            <td>{{ \App\Support\Prescriptions\MedicationFrequency::label($detail->horario) }}</td>
                            <td>{{ $detail->indicaciones ?: '—' }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            <p class="muted" data-print-status aria-live="polite">La impresión genera una etiqueta por medicamento y lote. Las reimpresiones quedan registradas.</p>

            @if ($order->observacion)
                <p><strong>Observación:</strong> {{ $order->observacion }}</p>
            @endif
        </div>
    </section>
@endsection
