{{-- Kartu donut + legenda bernilai. Parameter: $title, $icon, $tone, $items (label, value, neutral?), $unit, $empty --}}
@php
    $items = collect($items)->values();
    $total = (int) $items->sum('value');
@endphp
<div class="card exec-card h-100 mb-0"><div class="card-body">
    <div class="exec-section-header">
        <h5 class="exec-section-title"><span class="exec-icon-box {{ $tone }}" aria-hidden="true"><i class="{{ $icon }}"></i></span><span>{{ $title }}</span></h5>
    </div>
    @if($total > 0)
        <div class="exec-donut">
            <div class="exec-donut-chart">
                <div data-chart="{{ json_encode(['type' => 'donut', 'items' => $items->all()]) }}" style="height: 168px" role="img"
                    aria-label="{{ $title }}: {{ $items->map(fn ($i) => $i['label'].' '.$i['value'])->implode(', ') }}"></div>
                <div class="exec-donut-center"><span class="tabular-nums">{{ number_format($total, 0, ',', '.') }}</span><small>{{ $unit }}</small></div>
            </div>
            <ul class="exec-legend" aria-label="Legenda {{ $title }}">
                @foreach($items as $i => $item)
                    <li>
                        <span class="exec-swatch" style="background: var({{ !empty($item['neutral']) ? '--viz-neutral' : '--viz-'.($i % 5 + 1) }})"></span>
                        <span class="exec-legend-label" title="{{ $item['label'] }}">{{ $item['label'] }}</span>
                        <span class="exec-legend-value tabular-nums">{{ number_format($item['value'], 0, ',', '.') }}</span>
                        <span class="exec-legend-pct tabular-nums">{{ number_format($item['value'] / $total * 100, 1, ',', '.') }}%</span>
                    </li>
                @endforeach
            </ul>
        </div>
    @else
        <div class="exec-empty-state"><p class="small mb-0">{{ $empty }}</p></div>
    @endif
</div></div>
