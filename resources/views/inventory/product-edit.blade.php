@extends('layouts.app')
@section('content')
    <x-page-header title="Editar producto" description="Actualiza los datos del catálogo sin alterar los lotes ni el historial de movimientos." />
    <section class="card">
        <div class="card-body">
            <form action="{{ route('products.update', $product->producto_id) }}" method="post" class="form-grid" data-product-catalog-form>
                @csrf
                @method('put')
                @include('inventory.partials.product-form-fields', ['ingredients' => $ingredients])
                <label class="check-label"><input type="checkbox" name="activo" value="1" @checked(old('activo', $product->activo))> Producto activo</label>
                <div class="form-actions"><button class="btn btn-primary" type="submit">Guardar cambios</button><a class="btn btn-outline" href="{{ route('inventory.index') }}">Cancelar</a></div>
            </form>
        </div>
    </section>
@endsection
