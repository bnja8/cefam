@extends('layouts.app')
@section('content')
    <x-page-header :title="$screen === 'create' ? 'Registrar movimiento controlado' : ($screen === 'reconcile' ? 'Conciliar existencias controladas' : 'Libro Digital de Controlados')" description="Registro cronológico de movimientos con confirmación de identidad del Químico Farmacéutico">
        <x-slot:actions>
            @if ($screen === 'movements')
                <a class="btn btn-primary" href="{{ route('controlled-ledger.create') }}">Registrar movimiento</a>
                <a class="btn btn-outline" href="{{ route('controlled-ledger.reconcile.form') }}">Conciliar existencias</a>
            @else
                <a class="btn btn-outline" href="{{ route('controlled-ledger.index') }}">Consultar movimientos</a>
            @endif
            <span class="badge badge-warning"><i class="fa-solid fa-user-shield"></i> Acceso exclusivo QF</span>
        </x-slot:actions>
    </x-page-header>

    <div class="notice notice-warning controlled-notice"><i class="fa-solid fa-triangle-exclamation"></i><span>La confirmación mediante contraseña registra quién autoriza el movimiento, pero no constituye una firma electrónica avanzada o certificada. Cumple los procedimientos regulatorios vigentes antes de usar este registro como libro oficial.</span></div>

    @if ($screen === 'reconcile')
    <section class="card" id="conciliar-controlados">
        <div class="card-header"><h2 class="card-title"><i class="fa-solid fa-scale-balanced text-primary"></i> Conciliar stock físico de controlados</h2></div>
        <div class="card-body">
            <p class="muted">Compara el conteo físico con el saldo del sistema. Si hay diferencia se registra una alerta y no se ajusta el inventario automáticamente.</p>
            @if ($countLots->isEmpty())
                <div class="notice notice-info"><i class="fa-solid fa-circle-info"></i><span>No hay lotes de medicamentos controlados activos para conciliar.</span></div>
            @else
                <form action="{{ route('controlled-ledger.reconcile') }}" method="post" class="form-grid">
                    @csrf
                    <div class="form-group"><label for="count-control-product">Medicamento controlado *</label><select class="form-control" id="count-control-product" name="producto_id" required><option value="">Selecciona medicamento</option>@foreach ($products as $product)<option value="{{ $product->producto_id }}">{{ $product->nombre }}</option>@endforeach</select></div>
                    <div class="form-group"><label for="count-control-lot">Lote *</label><select class="form-control" id="count-control-lot" name="lote_id" required><option value="">Selecciona lote</option>@foreach ($countLots as $lot)<option value="{{ $lot->lote_id }}" data-product="{{ $lot->producto_id }}">{{ $lot->numero_lote }} · Vence {{ \Illuminate\Support\Carbon::parse($lot->fecha_vencimiento)->format('d/m/Y') }}</option>@endforeach</select></div>
                    <div class="form-group"><label for="count-control-location">Ubicación *</label><select class="form-control" id="count-control-location" name="ubicacion_id" required><option value="">Selecciona ubicación</option>@foreach ($locations as $location)<option value="{{ $location->ubicacion_id }}">{{ $location->nombre }}</option>@endforeach</select></div>
                    <div class="form-group"><label for="cantidad_contada">Cantidad contada *</label><input class="form-control" id="cantidad_contada" name="cantidad_contada" type="number" min="0" step="0.01" required></div>
                    <div class="form-group"><label for="count-control-password">Confirma tu contraseña QF *</label><input class="form-control" id="count-control-password" name="password" type="password" required autocomplete="current-password"></div>
                    <div class="form-group"><label for="count-control-observation">Acta / observación *</label><input class="form-control" id="count-control-observation" name="observacion" maxlength="1000" required></div>
                    <div class="form-actions"><button class="btn btn-primary" type="submit"><i class="fa-solid fa-clipboard-check"></i> Registrar conteo</button></div>
                </form>
            @endif
        </div>
    </section>
    @endif

    @if ($screen === 'movements')
    <section class="card" id="movimientos-controlados">
        <div class="card-header"><h2 class="card-title"><i class="fa-solid fa-book-bookmark text-primary"></i> Movimientos registrados</h2><span class="muted">{{ number_format($movements->total(), 0, ',', '.') }} registros · Los registros no se pueden editar desde esta aplicación</span></div>
        <div class="table-responsive"><table class="data-table searchable-table">
            <thead><tr><th>FECHA</th><th>MEDICAMENTO</th><th>LOTE / VENCIMIENTO</th><th>MOVIMIENTO</th><th>CANTIDAD</th><th>RESPONSABLE</th><th>AUTORIZA</th><th>OBSERVACIÓN</th></tr></thead>
            <tbody>
            @forelse ($movements as $movement)
                <tr><td>{{ \Illuminate\Support\Carbon::parse($movement->fecha_hora)->format('d/m/Y H:i:s') }}</td><td>{{ $movement->producto }}</td><td>{{ $movement->numero_lote }}<small class="table-subtitle">{{ \Illuminate\Support\Carbon::parse($movement->fecha_vencimiento)->format('d/m/Y') }}</small></td><td><span class="badge {{ $movement->tipo_movimiento === 'INGRESO' ? 'badge-success' : 'badge-warning' }}">{{ $movement->tipo_movimiento }}</span></td><td><strong>{{ \App\Support\Formatting\NumberFormat::format($movement->cantidad) }}</strong></td><td>{{ $movement->responsable }}</td><td>{{ $movement->autorizador ?? '—' }}<small class="table-subtitle">{{ $movement->firmado ? 'Identidad confirmada' : 'Pendiente' }}</small></td><td>{{ $movement->observacion }}</td></tr>
            @empty
                <tr><td colspan="8" class="empty-state">No hay movimientos de productos controlados registrados.</td></tr>
            @endforelse
            </tbody>
        </table></div>
        <x-record-pagination :paginator="$movements" />
    </section>
    @endif

    @if ($screen === 'create')
    <section class="card" id="nuevo-movimiento-controlado">
        <div class="card-header"><h2 class="card-title"><i class="fa-solid fa-file-signature text-primary"></i> Registrar ingreso o salida</h2></div>
        <div class="card-body">
            @if ($products->isEmpty() || $lots->isEmpty())
                <div class="notice notice-info"><i class="fa-solid fa-circle-info"></i><span>No hay productos controlados con lotes vigentes. Registra el producto y recibe el lote mediante Remesas / CENABAST antes de registrar un movimiento.</span></div>
            @else
                <form action="{{ route('controlled-ledger.store') }}" method="post" class="form-grid">
                    @csrf
                    <div class="form-group"><label for="control-product">Medicamento controlado *</label><select class="form-control" id="control-product" name="producto_id" required><option value="">Selecciona medicamento</option>@foreach ($products as $product)<option value="{{ $product->producto_id }}">{{ $product->nombre }}</option>@endforeach</select></div>
                    <div class="form-group"><label for="control-lot">Lote vigente *</label><select class="form-control" id="control-lot" name="lote_id" required><option value="">Selecciona lote</option>@foreach ($lots as $lot)<option value="{{ $lot->lote_id }}" data-product="{{ $lot->producto_id }}">{{ $lot->numero_lote }} · Vence {{ \Illuminate\Support\Carbon::parse($lot->fecha_vencimiento)->format('d/m/Y') }}</option>@endforeach</select></div>
                    <div class="form-group"><label for="control-location">Ubicación *</label><select class="form-control" id="control-location" name="ubicacion_id" required><option value="">Selecciona ubicación</option>@foreach ($locations as $location)<option value="{{ $location->ubicacion_id }}">{{ $location->nombre }}</option>@endforeach</select></div>
                    <div class="form-group"><label for="control-type">Tipo de movimiento *</label><select class="form-control" id="control-type" name="tipo_movimiento" required><option value="INGRESO">Ingreso</option><option value="SALIDA">Salida</option></select></div>
                    <div class="form-group"><label for="control-quantity">Cantidad *</label><input class="form-control" id="control-quantity" name="cantidad" type="number" min="0.01" step="0.01" required></div>
                    <div class="form-group"><label for="control-password">Confirma tu contraseña *</label><input class="form-control" id="control-password" name="password" type="password" required autocomplete="current-password"></div>
                    <div class="form-group form-span"><label for="control-observation">Motivo / observación *</label><textarea class="form-control" id="control-observation" name="observacion" rows="2" maxlength="1000" required></textarea></div>
                    <div class="form-actions"><button class="btn btn-primary" type="submit"><i class="fa-solid fa-file-signature"></i> Confirmar y registrar</button></div>
                </form>
            @endif
        </div>
    </section>
    @endif
@endsection
