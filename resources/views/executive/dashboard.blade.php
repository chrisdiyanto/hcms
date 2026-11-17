@extends('layouts.app')

@section('content')
@php
    $fmt = fn ($value, $decimals = 0) => number_format((float) $value, $decimals, ',', '.');
    $pct = fn ($part, $total) => $total > 0 ? round($part / $total * 100, 1) : 0;
    $endLabel = \Carbon\Carbon::parse($end)->translatedFormat('d F Y');
    $prevLabel = \Carbon\Carbon::parse($start)->subDay()->translatedFormat('F Y');

    $delta = $headcount - $prevHeadcount;
    $deltaPct = $prevHeadcount > 0 ? round($delta / $prevHeadcount * 100, 1) : null;

    // Komposisi (urutan tetap = urutan slot warna; "Belum diisi" selalu abu-abu)
    $genderItems = collect([
        ['label' => 'Perempuan', 'value' => $female],
        ['label' => 'Laki-laki', 'value' => $male],
    ]);
    if ($unknownGender) {
        $genderItems->push(['label' => 'Belum diisi', 'value' => $unknownGender, 'neutral' => true]);
    }
    $statusItems = $employment->values()->map(fn ($e) => ['label' => $e['label'], 'value' => $e['count']]);
    $statusTotal = (int) $statusItems->sum('value');

    // Peringkat unit (10 teratas); tabel lengkap tetap tersedia di bawah grafik
    $topUnits = $byUnit->sortByDesc('total')->take(10)->values();
    $openRows = $openByUnit->filter(fn ($n) => $n > 0)->sortDesc()->take(10);
    $topOpen = $openRows->map(fn ($n, $unitId) => ['label' => $positions[$unitId]->unit_name, 'value' => (int) $n])->values();

    // Sorotan otomatis dari data
    $biggest = $topUnits->first();
    $mostOpen = $topOpen->first();
    $contractShare = $pct(($employment['pkwt']['count'] ?? 0) + ($employment['probation']['count'] ?? 0), $statusTotal);
    $femaleShare = $pct($female, $female + $male);
    $trendHires = array_sum(array_column($trend, 'hires'));
    $trendTerms = array_sum(array_column($trend, 'terminations'));
    $insights = array_values(array_filter([
        $biggest ? ['fa fa-building', 'Unit terbesar', $biggest->unit_name.' dengan '.$fmt($biggest->total).' pegawai ('.$fmt($pct($biggest->total, $headcount), 1).'%).'] : null,
        $mostOpen ? ['fa fa-briefcase', 'Kebutuhan terbesar', $mostOpen['label'].' masih memiliki '.$fmt($mostOpen['value']).' posisi terbuka.'] : null,
        $statusTotal ? ['fa fa-file-alt', 'Komposisi kontrak', $fmt($contractShare, 1).'% pegawai berstatus kontrak atau masa percobaan.'] : null,
        ($female + $male) ? ['fa fa-venus-mars', 'Keseimbangan gender', 'Perempuan '.$fmt($femaleShare, 1).'% dari pegawai yang gendernya tercatat.'] : null,
        ['fa fa-exchange-alt', '12 bulan terakhir', $fmt($trendHires).' pegawai bergabung dan '.$fmt($trendTerms).' keluar (neto '.($trendHires - $trendTerms >= 0 ? '+' : '').$fmt($trendHires - $trendTerms).').'],
    ]));

    $trendLabels = array_column($trend, 'label');
    $charts = [
        'trend' => ['type' => 'area', 'name' => 'Pegawai aktif', 'labels' => $trendLabels, 'values' => array_column($trend, 'headcount')],
        'flows' => ['type' => 'flows', 'labels' => $trendLabels, 'series' => [
            ['name' => 'Bergabung', 'values' => array_column($trend, 'hires'), 'slot' => 2],
            ['name' => 'Keluar', 'values' => array_column($trend, 'terminations'), 'slot' => 1],
        ]],
        'gender' => ['type' => 'donut', 'items' => $genderItems->all()],
        'status' => ['type' => 'donut', 'items' => $statusItems->all()],
        'units' => ['type' => 'hbar', 'name' => 'Pegawai', 'slot' => 0, 'labels' => $topUnits->pluck('unit_name')->all(), 'values' => $topUnits->pluck('total')->map(fn ($v) => (int) $v)->all()],
        'open' => ['type' => 'hbar', 'name' => 'Posisi terbuka', 'slot' => 3, 'labels' => $topOpen->pluck('label')->all(), 'values' => $topOpen->pluck('value')->all()],
        'fill' => ['type' => 'ring', 'value' => $fillRate ?? 0, 'slot' => 2],
    ];
@endphp

<div class="exec-dashboard">
@include('executive._header', ['active' => 'dashboard', 'subtitle' => 'Posisi per '.$endLabel.' · pergerakan selama '.$pageNote])

{{-- ============ HERO ============ --}}
<section class="exec-hero mb-4" aria-label="Angka utama">
    <div class="exec-hero-main">
        <div class="exec-hero-kicker"><i class="fa fa-users" aria-hidden="true"></i> Pegawai aktif</div>
        <div class="exec-hero-number tabular-nums">{{ $fmt($headcount) }}</div>
        <div class="exec-hero-sub">
            @if($delta > 0)
                <span class="exec-delta exec-delta-up"><i class="fa fa-arrow-up" aria-hidden="true"></i> +{{ $fmt($delta) }}@if($deltaPct !== null) ({{ $fmt($deltaPct, 1) }}%)@endif</span>
            @elseif($delta < 0)
                <span class="exec-delta exec-delta-down"><i class="fa fa-arrow-down" aria-hidden="true"></i> {{ $fmt($delta) }}@if($deltaPct !== null) ({{ $fmt($deltaPct, 1) }}%)@endif</span>
            @else
                <span class="exec-delta">tetap</span>
            @endif
            <span>dibanding akhir {{ $prevLabel }}</span>
        </div>
    </div>

    <div class="exec-hero-stats">
        @foreach([
            ['Bergabung', $fmt($hires), 'pegawai baru bulan ini', 'fa fa-user-plus'],
            ['Keluar', $fmt($terminations), 'terminasi diproses', 'fa fa-user-times'],
            ['Turnover', $turnoverRate !== null ? $fmt($turnoverRate, 1).'%' : '—', 'keluar ÷ rata-rata pegawai', 'fa fa-sync-alt'],
        ] as [$label, $value, $note, $icon])
            <div class="exec-hero-stat">
                <div class="exec-hero-stat-label"><i class="{{ $icon }}" aria-hidden="true"></i> {{ $label }}</div>
                <div class="exec-hero-stat-value tabular-nums">{{ $value }}</div>
                <div class="exec-hero-stat-note">{{ $note }}</div>
            </div>
        @endforeach
    </div>

    <div class="exec-hero-ring">
        <div class="exec-ring-wrap">
            <div data-chart="{{ json_encode($charts['fill']) }}" class="exec-ring-chart" role="img"
                aria-label="Keterisian posisi {{ $fillRate !== null ? $fmt($fillRate, 1).' persen' : 'belum ada kapasitas' }}"></div>
            <div class="exec-ring-center"><span class="tabular-nums">{{ $fillRate !== null ? $fmt($fillRate, 0).'%' : '—' }}</span><small>terisi</small></div>
        </div>
        <div>
            <div class="exec-hero-stat-label"><i class="fa fa-briefcase" aria-hidden="true"></i> Keterisian posisi</div>
            <div class="exec-hero-stat-note tabular-nums">{{ $fmt($filledTotal) }} dari {{ $fmt($capacityTotal) }} kapasitas</div>
            <div class="exec-open-chip tabular-nums">{{ $fmt($openTotal) }} posisi terbuka</div>
        </div>
    </div>
</section>

{{-- ============ TREN ============ --}}
<div class="row g-3 mb-4">
    <div class="col-lg-7">
        <div class="card exec-card h-100 mb-0"><div class="card-body">
            <div class="exec-section-header">
                <h5 class="exec-section-title"><span class="exec-icon-box exec-tone-primary" aria-hidden="true"><i class="fa fa-chart-line"></i></span><span>Tren pegawai aktif</span></h5>
                <span class="exec-pill exec-tone-neutral">12 bulan</span>
            </div>
            <div data-chart="{{ json_encode($charts['trend']) }}" style="height: 240px" role="img"
                aria-label="Pegawai aktif akhir bulan dari {{ $trend[0]['label'] }} sampai {{ end($trend)['label'] }}: {{ collect($trend)->map(fn ($t) => $t['label'].' '.$t['headcount'])->implode(', ') }}"></div>
        </div></div>
    </div>
    <div class="col-lg-5">
        <div class="card exec-card h-100 mb-0"><div class="card-body">
            <div class="exec-section-header">
                <h5 class="exec-section-title"><span class="exec-icon-box exec-tone-success" aria-hidden="true"><i class="fa fa-exchange-alt"></i></span><span>Arus pegawai</span></h5>
                <span class="exec-pill exec-tone-neutral">bergabung vs keluar</span>
            </div>
            <div data-chart="{{ json_encode($charts['flows']) }}" style="height: 240px" role="img"
                aria-label="Pegawai bergabung dan keluar per bulan: {{ collect($trend)->map(fn ($t) => $t['label'].' +'.$t['hires'].'/−'.$t['terminations'])->implode(', ') }}"></div>
        </div></div>
    </div>
</div>

{{-- ============ KOMPOSISI & SOROTAN ============ --}}
<div class="row g-3 mb-4">
    @foreach([['gender', 'Gender', 'fa fa-venus-mars', 'exec-tone-primary', $genderItems, $headcount, 'pegawai'],
              ['status', 'Status kerja', 'fa fa-id-badge', 'exec-tone-info', $statusItems, $statusTotal, 'pegawai']] as [$key, $title, $icon, $tone, $items, $total, $unit])
        <div class="col-md-6 col-xl-4">
            <div class="card exec-card h-100 mb-0"><div class="card-body">
                <div class="exec-section-header">
                    <h5 class="exec-section-title"><span class="exec-icon-box {{ $tone }}" aria-hidden="true"><i class="{{ $icon }}"></i></span><span>{{ $title }}</span></h5>
                </div>
                @if($total > 0)
                    <div class="exec-donut">
                        <div class="exec-donut-chart">
                            <div data-chart="{{ json_encode($charts[$key]) }}" style="height: 168px" role="img"
                                aria-label="{{ $title }}: {{ $items->map(fn ($i) => $i['label'].' '.$i['value'])->implode(', ') }}"></div>
                            <div class="exec-donut-center"><span class="tabular-nums">{{ $fmt($total) }}</span><small>{{ $unit }}</small></div>
                        </div>
                        <ul class="exec-legend" aria-label="Legenda {{ $title }}">
                            @foreach($items as $i => $item)
                                <li>
                                    <span class="exec-swatch" style="background: var({{ !empty($item['neutral']) ? '--viz-neutral' : '--viz-'.($i + 1) }})"></span>
                                    <span class="exec-legend-label">{{ $item['label'] }}</span>
                                    <span class="exec-legend-value tabular-nums">{{ $fmt($item['value']) }}</span>
                                    <span class="exec-legend-pct tabular-nums">{{ $fmt($pct($item['value'], $total), 1) }}%</span>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @else
                    <div class="exec-empty-state"><p class="small mb-0">Belum ada pegawai aktif pada periode ini.</p></div>
                @endif
            </div></div>
        </div>
    @endforeach

    <div class="col-xl-4">
        <div class="card exec-card h-100 mb-0"><div class="card-body">
            <div class="exec-section-header">
                <h5 class="exec-section-title"><span class="exec-icon-box exec-tone-warning" aria-hidden="true"><i class="fa fa-lightbulb"></i></span><span>Sorotan</span></h5>
            </div>
            <ul class="exec-insights">
                @foreach($insights as [$icon, $label, $text])
                    <li><span class="exec-insight-icon" aria-hidden="true"><i class="{{ $icon }}"></i></span>
                        <div><div class="exec-insight-label">{{ $label }}</div><div class="exec-insight-text">{{ $text }}</div></div></li>
                @endforeach
            </ul>
        </div></div>
    </div>
</div>

{{-- ============ UNIT ============ --}}
<div class="row g-3">
    <div class="col-lg-6">
        <div class="card exec-card h-100 mb-0"><div class="card-body d-flex flex-column">
            <div class="exec-section-header">
                <h5 class="exec-section-title"><span class="exec-icon-box exec-tone-success" aria-hidden="true"><i class="fa fa-sitemap"></i></span><span>10 unit dengan pegawai terbanyak</span></h5>
                <span class="exec-pill exec-tone-neutral tabular-nums">{{ $fmt($byUnit->count()) }} unit</span>
            </div>
            @if($byUnit->isEmpty())
                <div class="exec-empty-state"><p class="small mb-0">Belum ada penempatan pegawai pada periode ini.</p></div>
            @else
                <div data-chart="{{ json_encode($charts['units']) }}" style="height: {{ 34 * $topUnits->count() + 16 }}px" role="img"
                    aria-label="Pegawai per unit: {{ $topUnits->map(fn ($u) => $u->unit_name.' '.$u->total)->implode(', ') }}"></div>
                <details class="exec-details mt-3">
                    <summary>Lihat semua unit ({{ $fmt($byUnit->count()) }})</summary>
                    <input class="form-control form-control-sm exec-unit-search my-2" type="search" data-unit-filter placeholder="Cari unit..." autocomplete="off" aria-label="Cari unit pegawai">
                    <div class="table-responsive exec-table-scroll"><table class="table exec-table mb-0">
                        <thead><tr><th>Unit organisasi</th><th class="text-end">Pegawai</th><th class="text-end">Porsi</th></tr></thead>
                        <tbody>
                        @foreach($byUnit as $unit)
                            <tr data-unit-row data-unit-name="{{ mb_strtolower($unit->unit_name) }}">
                                <td>{{ $unit->unit_name }}</td><td class="text-end tabular-nums">{{ $fmt($unit->total) }}</td>
                                <td class="text-end tabular-nums text-muted">{{ $fmt($pct($unit->total, max(1, (int) $byUnit->sum('total'))), 1) }}%</td>
                            </tr>
                        @endforeach
                        <tr class="exec-filter-empty"><td colspan="3" class="text-center text-muted small py-3">Tidak ada unit yang cocok.</td></tr>
                        </tbody>
                    </table></div>
                </details>
            @endif
            <div class="exec-footnote mt-auto"><i class="fa fa-user-circle" aria-hidden="true"></i><span>Penempatan primer pegawai aktif pada akhir periode.</span></div>
        </div></div>
    </div>

    <div class="col-lg-6">
        <div class="card exec-card h-100 mb-0"><div class="card-body d-flex flex-column">
            <div class="exec-section-header">
                <h5 class="exec-section-title"><span class="exec-icon-box exec-tone-warning" aria-hidden="true"><i class="fa fa-briefcase"></i></span><span>Unit dengan posisi terbuka terbanyak</span></h5>
                <span class="exec-pill {{ $openTotal > 0 ? 'exec-tone-warning' : 'exec-tone-success' }} tabular-nums">{{ $fmt($openTotal) }} terbuka</span>
            </div>
            @if($topOpen->isEmpty())
                <div class="exec-empty-state"><i class="fa fa-check-circle d-block mb-2 fs-4 opacity-50" aria-hidden="true"></i>
                    <p class="small mb-0">{{ $positions->isEmpty() ? 'Belum ada kapasitas posisi aktif pada periode ini.' : 'Semua kapasitas posisi sudah terisi.' }}</p></div>
            @else
                <div data-chart="{{ json_encode($charts['open']) }}" style="height: {{ 34 * $topOpen->count() + 16 }}px" role="img"
                    aria-label="Posisi terbuka per unit: {{ $topOpen->map(fn ($u) => $u['label'].' '.$u['value'])->implode(', ') }}"></div>
            @endif
            @if($positions->isNotEmpty())
                <details class="exec-details mt-3">
                    <summary>Lihat keterisian semua unit ({{ $fmt($positions->count()) }})</summary>
                    <input class="form-control form-control-sm exec-unit-search my-2" type="search" data-unit-filter placeholder="Cari unit..." autocomplete="off" aria-label="Cari unit dengan posisi terbuka">
                    <div class="table-responsive exec-table-scroll"><table class="table exec-table mb-0">
                        <thead><tr><th>Unit organisasi</th><th class="text-end">Terisi / kapasitas</th><th class="text-end">Terbuka</th></tr></thead>
                        <tbody>
                        @foreach($openByUnit as $unitId => $count)
                            @php($posRow = $positions->get($unitId))
                            <tr data-unit-row data-unit-name="{{ mb_strtolower($posRow->unit_name) }}">
                                <td>{{ $posRow->unit_name }}</td>
                                <td class="text-end tabular-nums text-muted">{{ $fmt(max(0, (int) $posRow->capacity - (int) $count)) }} / {{ $fmt((int) $posRow->capacity) }}</td>
                                <td class="text-end"><span class="exec-pill {{ $count > 0 ? 'exec-tone-warning' : 'exec-tone-success' }} tabular-nums">{{ $fmt($count) }}</span></td>
                            </tr>
                        @endforeach
                        <tr class="exec-filter-empty"><td colspan="3" class="text-center text-muted small py-3">Tidak ada unit yang cocok.</td></tr>
                        </tbody>
                    </table></div>
                </details>
            @endif
            <div class="exec-footnote mt-auto"><i class="fa fa-calculator" aria-hidden="true"></i><span>Kapasitas posisi aktif dikurangi penempatan primer yang berlaku.</span></div>
        </div></div>
    </div>
</div>

@if($scoped)
    <p class="small text-muted mt-3 mb-0"><i class="fa fa-info-circle" aria-hidden="true"></i> Angka dibatasi unit dalam cakupan data Anda; pegawai yang keluar atau pindah tetap dihitung di unit asalnya selama periode.</p>
@endif
</div>

@include('executive._filter-script')
@vite('resources/js/charts.js')
@endsection
