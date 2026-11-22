@props(['title', 'description', 'icon' => 'fa-layer-group'])
<div class="page-header">
    <div class="page-title">
        <h1>{{ $title }}</h1>
        <p>{{ $description }}</p>
    </div>
    @if (isset($actions))
        <div class="header-actions">{{ $actions }}</div>
    @endif
</div>
