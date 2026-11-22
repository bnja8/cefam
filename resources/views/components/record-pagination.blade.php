@props(['paginator'])

<nav class="pagination-wrap record-pagination" aria-label="Paginación de registros">
    @if ($paginator->onFirstPage())
        <span class="btn btn-outline btn-small pagination-disabled" aria-disabled="true">Anterior</span>
    @else
        <a class="btn btn-outline btn-small" href="{{ $paginator->previousPageUrl() }}">Anterior</a>
    @endif

    <span class="record-pagination-label">Página {{ $paginator->currentPage() }} de {{ $paginator->lastPage() }}</span>

    @if ($paginator->hasMorePages())
        <a class="btn btn-outline btn-small" href="{{ $paginator->nextPageUrl() }}">Siguiente</a>
    @else
        <span class="btn btn-outline btn-small pagination-disabled" aria-disabled="true">Siguiente</span>
    @endif
</nav>
