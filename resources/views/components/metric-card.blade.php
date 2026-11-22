@props(['label', 'value', 'icon', 'tone' => 'blue'])
<article class="metric-card">
    <div class="metric-info"><label>{{ $label }}</label><div class="value">{{ $value }}</div></div>
    <span class="metric-icon {{ $tone }}"><i class="fa-solid {{ $icon }}"></i></span>
</article>
