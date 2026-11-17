@extends('layouts.app')

@section('content')
<div class="exec-dashboard">
@include('executive._header', [
    'active' => 'performance', 'showMonth' => false,
    'month' => now()->format('Y-m'),
    'subtitle' => $period ? $period->name.' · '.\App\Domain\Performance\Services\PerformancePeriods::STATUS[$period->status] : 'Tren hasil, kemajuan penilaian, dan tindak lanjut',
])

@if(!$period)
    <div class="exec-empty-state">
        <i class="fa fa-chart-line d-block mb-2 fs-4 opacity-50" aria-hidden="true"></i>
        <p class="mb-1 fw-semibold">Belum ada periode kinerja</p>
        <p class="small mb-0">Buka periode penilaian agar tren dan hasil kinerja dapat dipantau.</p>
    </div>
@else
    <div class="d-flex justify-content-end mb-3">
        <form method="GET" class="d-flex align-items-center gap-2">
            <label for="performance-period" class="small text-muted mb-0">Periode kinerja</label>
            <select id="performance-period" name="period" class="form-select form-select-sm" onchange="this.form.submit()" aria-label="Pilih periode kinerja">
                @foreach($periods as $item)
                    <option value="{{ $item->id }}" @selected($period->id == $item->id)>{{ $item->name }}</option>
                @endforeach
            </select>
        </form>
    </div>

    @php
        $fmt = fn ($value, $decimals = 0) => number_format((float) $value, $decimals, ',', '.');
        $coverageChart = ['type' => 'ring', 'value' => $coverage, 'slot' => 2];
        $phaseItems = [
            ['label' => 'Rencana disetujui', 'value' => $approvedCount, 'total' => $planCounts->sum(), 'note' => 'dari '.$fmt($planCounts->sum()).' rencana'],
            ['label' => 'Mid-year selesai', 'value' => $midyear, 'total' => $approvedCount, 'note' => 'dari '.$fmt($approvedCount).' rencana disetujui'],
            ['label' => 'Penilaian selesai', 'value' => $completed, 'total' => $approvedCount, 'note' => 'dari '.$fmt($approvedCount).' rencana disetujui'],
        ];
    @endphp

    <section class="exec-hero mb-4" aria-label="Ringkasan kinerja periode">
        <div class="exec-hero-main">
            <div class="exec-hero-kicker"><i class="fa fa-chart-line" aria-hidden="true"></i> Rata-rata skor</div>
            <div class="exec-hero-number tabular-nums">{{ $average !== null ? $fmt($average, 2) : '—' }}</div>
            <div class="exec-hero-sub">
                @if($trendChange !== null)
                    <span class="exec-delta {{ $trendChange >= 0 ? 'exec-delta-up' : 'exec-delta-down' }}">
                        <i class="fa {{ $trendChange >= 0 ? 'fa-arrow-up' : 'fa-arrow-down' }}" aria-hidden="true"></i>
                        {{ $trendChange > 0 ? '+' : '' }}{{ $fmt($trendChange, 2) }} poin
                    </span>
                    <span>dibanding periode tertutup sebelumnya</span>
                @else
                    <span>{{ $scored }} pegawai telah memiliki skor</span>
                @endif
            </div>
        </div>

        <div class="exec-hero-stats">
            @foreach([
                ['Penilaian selesai', $fmt($coverage, 1).'%', $fmt($completed).' dari '.$fmt($approvedCount).' rencana disetujui', 'fa fa-check-circle'],
                ['Perlu perhatian', \App\Support\Privacy\SmallGroups::count($riskCount, $mask), 'hasil pada band terbawah', 'fa fa-exclamation-circle'],
                ['PIP berjalan', $fmt($activePips), 'aktif atau diperpanjang', 'fa fa-life-ring'],
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
                <div data-chart="{{ json_encode($coverageChart) }}" class="exec-ring-chart" role="img" aria-label="{{ $fmt($coverage, 1) }} persen penilaian selesai"></div>
                <div class="exec-ring-center"><span class="tabular-nums">{{ $fmt($coverage, 0) }}%</span><small>selesai</small></div>
            </div>
            <div>
                <div class="exec-hero-stat-label"><i class="fa fa-clipboard-check" aria-hidden="true"></i> Kemajuan periode</div>
                <div class="exec-hero-stat-note">{{ $source }}</div>
            </div>
        </div>
    </section>

    <div class="row g-3 mb-4">
        <div class="col-lg-8"><div class="card exec-card h-100 mb-0"><div class="card-body">
            <div class="exec-section-header">
                <h5 class="exec-section-title"><span class="exec-icon-box exec-tone-primary" aria-hidden="true"><i class="fa fa-chart-area"></i></span><span>Tren rating final</span></h5>
                <span class="exec-pill exec-tone-neutral">{{ count($trend) }} periode tertutup</span>
            </div>
            @if($trendChart)
                <div data-chart="{{ json_encode($trendChart) }}" style="height:270px" role="img" aria-label="Rata-rata rating final: {{ collect($trend)->map(fn ($row) => $row['label'].' '.$row['score'])->implode(', ') }}"></div>
                <div class="exec-footnote"><i class="fa fa-lock" aria-hidden="true"></i><span>Hanya rating periode yang sudah ditutup ditampilkan sebagai tren pembanding.</span></div>
            @else
                <div class="exec-empty-state"><p class="small mb-0">Belum ada periode tertutup yang cukup untuk membentuk tren rating final.</p></div>
            @endif
        </div></div></div>

        <div class="col-lg-4"><div class="card exec-card h-100 mb-0"><div class="card-body">
            <div class="exec-section-header">
                <h5 class="exec-section-title"><span class="exec-icon-box exec-tone-warning" aria-hidden="true"><i class="fa fa-layer-group"></i></span><span>Sebaran rating</span></h5>
                <span class="exec-pill exec-tone-neutral">{{ $scored }} pegawai</span>
            </div>
            @if($bandChart)
                <div data-chart="{{ json_encode($bandChart) }}" style="height:250px" role="img" aria-label="Sebaran hasil: {{ $bandCounts->map(fn ($b) => $b['label'].' '.\App\Support\Privacy\SmallGroups::count($b['count'], $mask))->implode(', ') }}"></div>
            @else
                <div class="exec-empty-state"><p class="small mb-0">Belum ada hasil penilaian pada periode ini.</p></div>
            @endif
        </div></div></div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-lg-7"><div class="card exec-card h-100 mb-0"><div class="card-body">
            <div class="exec-section-header">
                <h5 class="exec-section-title"><span class="exec-icon-box exec-tone-success" aria-hidden="true"><i class="fa fa-sitemap"></i></span><span>Rata-rata skor per unit</span></h5>
                <span class="exec-pill exec-tone-neutral">{{ $unitChart ? count($unitChart['labels']) : 0 }} unit</span>
            </div>
            @if($unitChart)
                <div data-chart="{{ json_encode($unitChart) }}" style="height:{{ max(190, 36 * count($unitChart['labels']) + 28) }}px" role="img" aria-label="Rata-rata skor per unit: {{ collect($unitChart['labels'])->zip($unitChart['values'])->map(fn ($r) => $r[0].' '.$r[1])->implode(', ') }}"></div>
            @else
                <div class="exec-empty-state"><p class="small mb-0">Belum ada hasil skor dengan penempatan unit pada periode ini.</p></div>
            @endif
        </div></div></div>

        <div class="col-lg-5"><div class="card exec-card h-100 mb-0"><div class="card-body">
            <div class="exec-section-header">
                <h5 class="exec-section-title"><span class="exec-icon-box exec-tone-warning" aria-hidden="true"><i class="fa fa-lightbulb"></i></span><span>Prioritas tindak lanjut</span></h5>
            </div>
            @if($insights->isEmpty())
                <div class="exec-empty-state"><i class="fa fa-check-circle d-block mb-2 fs-4 opacity-50" aria-hidden="true"></i><p class="small mb-0">Belum ada hal yang memerlukan perhatian khusus.</p></div>
            @else
                <ul class="exec-insights">
                    @foreach($insights as $insight)
                        <li><span class="exec-insight-icon exec-tone-{{ $insight['type'] }}" aria-hidden="true"><i class="fa {{ $insight['type'] === 'warning' ? 'fa-exclamation' : ($insight['type'] === 'success' ? 'fa-check' : 'fa-info') }}"></i></span>
                            <div><div class="exec-insight-label">{{ $insight['title'] }}</div><div class="exec-insight-text">{{ $insight['text'] }}</div></div>
                        </li>
                    @endforeach
                </ul>
            @endif
            @if($riskCount > 0)
                <a class="btn btn-sm btn-outline-primary mt-3" href="{{ url('performance/pips') }}"><i class="fa fa-arrow-right me-1" aria-hidden="true"></i> Tinjau rencana perbaikan</a>
            @endif
        </div></div></div>
    </div>

    <div class="card exec-card mb-0"><div class="card-body">
        <div class="exec-section-header">
            <h5 class="exec-section-title"><span class="exec-icon-box exec-tone-info" aria-hidden="true"><i class="fa fa-tasks"></i></span><span>Kemajuan siklus penilaian</span></h5>
            <span class="exec-pill exec-tone-neutral">{{ \App\Domain\Performance\Services\PerformancePeriods::STATUS[$period->status] }}</span>
        </div>
        <div class="row g-3">
            @foreach($phaseItems as $item)
                @php($phasePct = $item['total'] > 0 ? min(100, round($item['value'] / $item['total'] * 100)) : 0)
                <div class="col-md-4"><div class="exec-status-tile">
                    <div class="d-flex justify-content-between align-items-start gap-2"><div><div class="small text-muted">{{ $item['label'] }}</div><div class="h4 fw-bold mt-1 mb-0 tabular-nums">{{ $fmt($item['value']) }}</div></div><span class="small text-muted tabular-nums">{{ $fmt($phasePct) }}%</span></div>
                    <div class="exec-mini-progress"><div class="exec-mini-progress-bar" style="width:{{ $phasePct }}%;background:var(--sx-primary)"></div></div>
                    <div class="small text-muted mt-2">{{ $item['note'] }}</div>
                </div></div>
            @endforeach
        </div>
    </div></div>

    <div class="exec-footnote mt-3"><i class="fa fa-info-circle" aria-hidden="true"></i><span>{{ $source }} · skor periode berjalan masih dapat berubah sampai proses kalibrasi dan pengesahan selesai.</span></div>
    @vite('resources/js/charts.js')
@endif
</div>
@endsection
