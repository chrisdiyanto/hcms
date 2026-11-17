{{-- Hero infografis. Parameter: $kicker, $icon, $number, $sub, $stats ([label, nilai, catatan, ikon]),
     $ring? (['value' => persen|null, 'center' => 'terisi', 'title', 'note', 'chip'?, 'slot'?]) --}}
<section class="exec-hero {{ empty($ring) ? 'exec-hero-plain' : '' }} mb-4" aria-label="Angka utama">
    <div class="exec-hero-main">
        <div class="exec-hero-kicker"><i class="{{ $icon }}" aria-hidden="true"></i> {{ $kicker }}</div>
        <div class="exec-hero-number tabular-nums">{{ $number }}</div>
        <div class="exec-hero-sub">{{ $sub }}</div>
    </div>

    <div class="exec-hero-stats">
        @foreach($stats as [$label, $value, $note, $statIcon])
            <div class="exec-hero-stat">
                <div class="exec-hero-stat-label"><i class="{{ $statIcon }}" aria-hidden="true"></i> {{ $label }}</div>
                <div class="exec-hero-stat-value tabular-nums">{{ $value }}</div>
                <div class="exec-hero-stat-note">{{ $note }}</div>
            </div>
        @endforeach
    </div>

    @if(!empty($ring))
        <div class="exec-hero-ring">
            <div class="exec-ring-wrap">
                <div data-chart="{{ json_encode(['type' => 'ring', 'value' => $ring['value'] ?? 0, 'slot' => $ring['slot'] ?? 2]) }}" class="exec-ring-chart" role="img"
                    aria-label="{{ $ring['title'] }} {{ $ring['value'] !== null ? number_format($ring['value'], 1, ',', '.').' persen' : 'belum tersedia' }}"></div>
                <div class="exec-ring-center"><span class="tabular-nums">{{ $ring['value'] !== null ? number_format($ring['value'], 0, ',', '.').'%' : '—' }}</span><small>{{ $ring['center'] }}</small></div>
            </div>
            <div>
                <div class="exec-hero-stat-label"><i class="{{ $ring['icon'] ?? 'fa fa-check-circle' }}" aria-hidden="true"></i> {{ $ring['title'] }}</div>
                <div class="exec-hero-stat-note">{{ $ring['note'] }}</div>
                @if(!empty($ring['chip']))<div class="exec-open-chip tabular-nums">{{ $ring['chip'] }}</div>@endif
            </div>
        </div>
    @endif
</section>
