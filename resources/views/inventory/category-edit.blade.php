@extends('layouts.app')
@section('content')
    <x-page-header title="Editar categoría" description="Actualiza los datos de la categoría del catálogo"></x-page-header>

    <section class="card">
        <div class="card-header">
            <h2 class="card-title"><i class="fa-solid fa-tags text-primary"></i> Editar categoría</h2>
        </div>
        <div class="card-body">
            <form action="{{ route('categories.update', $category->categoria_id) }}" method="post" class="form-grid">
                @csrf
                @method('put')
                <div class="form-group form-span">
                    <label for="nombre_categoria">Nombre de categoría *</label>
                    <input class="form-control" id="nombre_categoria" name="nombre_categoria" value="{{ old('nombre_categoria', $category->nombre) }}" required maxlength="100">
                </div>
                <div class="form-group form-span">
                    <label for="descripcion_categoria">Descripción</label>
                    <textarea class="form-control" id="descripcion_categoria" name="descripcion_categoria" rows="3" maxlength="255">{{ old('descripcion_categoria', $category->descripcion) }}</textarea>
                </div>
                <div class="form-actions">
                    <button class="btn btn-primary" type="submit"><i class="fa-solid fa-floppy-disk"></i> Guardar cambios</button>
                    <a class="btn btn-outline" href="{{ route('inventory.index') }}">Cancelar</a>
                </div>
            </form>
        </div>
    </section>
@endsection
