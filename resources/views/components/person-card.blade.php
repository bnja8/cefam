@props(['name', 'subtitle', 'status', 'active' => true, 'statusClass' => null])
<article class="person-card">
    <div class="person-card-heading">
        <span class="person-card-avatar" aria-hidden="true">{{ collect(explode(' ', trim($name)))->filter()->take(2)->map( fn ($part) => mb_substr($part, 0, 1))->join('') }}</span>
        <div class="person-card-title">
            <h3>{{ $name }}</h3>
            <p>{{ $subtitle }}</p>
        </div>
    </div>
    <div class="person-card-status">
        <span class="badge {{ $statusClass ?? ($active ? 'badge-success' : 'badge-danger') }}">
            <span class="person-card-status-dot" aria-hidden="true"></span>{{ $status }}
        </span>
    </div>
    <div class="person-card-details">
        {{ $details ?? '' }}
    </div>
    @if (isset($actions))
        <div class="person-card-actions">{{ $actions }}</div>
    @endif
</article>
