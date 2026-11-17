@extends('layouts.app')

@section('content')
@php
    // K9: pengguna tanpa hak membuka data pegawai individual melihat kelompok 1–4 orang sebagai "< 5"
    $mask = app(\App\Support\Privacy\SmallGroups::class)->masks('employees');
    $small = fn ($n) => $mask && \App\Support\Privacy\SmallGroups::hidden($n);
    $fmt = fn ($value, $decimals = 0) => $value === null ? '—' : ($decimals === 0 && $small($value) ? \App\Support\Privacy\SmallGroups::LABEL : number_format((float) $value, $decimals, ',', '.'));
    $pct = fn ($part, $total) => $small($part) || $small($total) ? null : ($total > 0 ? round($part / $total * 100, 1) : 0);
    $chart = fn (array $values) => \App\Support\Privacy\SmallGroups::chartValues($values, $mask);
    $num = fn ($value) => number_format((float) $value, 0, ',', '.'); // jumlah unit/lokasi (bukan orang)
    $monthLabel = \Carbon\Carbon::createFromFormat('Y-m', $month)->translatedFormat('F Y');
    $endLabel = \Carbon\Carbon::parse($end)->translatedFormat('d F Y');
@endphp

<div class="exec-dashboard">
@include('executive._header', [
    'active' => $report, 'showMonth' => $report !== 'development',
    'subtitle' => $report === 'development' ? $description : $description.' · '.$monthLabel,
])

{{-- ===================================================== TURNOVER ===================================================== --}}
@if($report === 'turnover')
    @php
        $trendLabels = array_column($trend, 'label');
        $rates = array_map(fn ($t) => $small($t['terminations']) ? null : $t['rate'], $trend);
        $rateShown = $rate !== null && ! $small($terms) ? $rate : null;
        $avgRate = count($rates) ? array_sum($rates) / count($rates) : 0;
        $peak = collect($trend)->sortByDesc('rate')->first();
        $mainReason = $rows->first();
        $reasonItems = $rows->map(fn ($r) => ['label' => $r['label'], 'value' => $r['value']]);
        $yearTerms = array_sum(array_column($trend, 'terminations'));
        $insights = array_values(array_filter([
            $rateShown !== null ? ['fa fa-balance-scale', 'Dibanding rata-rata', 'Turnover '.$monthLabel.' '.$fmt($rate, 1).'% '.($rate > $avgRate ? 'di atas' : ($rate < $avgRate ? 'di bawah' : 'sama dengan')).' rata-rata bulanan 12 bulan ('.$fmt($avgRate, 2).'%).'] : null,
            $peak && $peak['terminations'] > 0 && ! $small($peak['terminations']) ? ['fa fa-chart-line', 'Bulan tertinggi', $peak['label'].': '.$fmt($peak['terminations']).' pegawai keluar ('.$fmt($peak['rate'], 2).'%).'] : null,
            $mainReason ? ['fa fa-comment-alt', 'Alasan utama', $mainReason['label'].' — '.$fmt($mainReason['value']).' dari '.$fmt($terms).' terminasi ('.$fmt($pct($mainReason['value'], $terms), 1).'%).'] : null,
            ['fa fa-calendar-alt', '12 bulan terakhir', $fmt($yearTerms).' terminasi diproses, '.$fmt(array_sum(array_column($trend, 'hires'))).' pegawai bergabung.'],
        ]));
    @endphp

    @include('executive._hero', [
        'kicker' => 'Tingkat turnover', 'icon' => 'fa fa-sync-alt',
        'number' => $rateShown !== null ? $fmt($rateShown, 1).'%' : '—',
        'sub' => $fmt($terms).' pegawai keluar ÷ rata-rata '.$fmt(($opening + $closing) / 2, 1).' pegawai aktif',
        'stats' => [
            ['Awal periode', $fmt($opening), 'aktif per '.\Carbon\Carbon::parse($start)->subDay()->format('d/m/Y'), 'fa fa-user-circle'],
            ['Keluar', $fmt($terms), 'terminasi diproses', 'fa fa-user-times'],
            ['Akhir periode', $fmt($closing), 'aktif per '.\Carbon\Carbon::parse($end)->format('d/m/Y'), 'fa fa-users'],
        ],
        'ring' => ['value' => $small($opening) ? null : $retention, 'center' => 'bertahan', 'title' => 'Retensi pegawai awal', 'icon' => 'fa fa-shield-alt',
            'note' => 'dari '.$fmt($opening).' pegawai yang aktif sebelum '.$monthLabel, 'slot' => 2],
    ])

    <div class="row g-3 mb-4">
        <div class="col-lg-7">
            <div class="card exec-card h-100 mb-0"><div class="card-body">
                <div class="exec-section-header">
                    <h5 class="exec-section-title"><span class="exec-icon-box exec-tone-danger" aria-hidden="true"><i class="fa fa-chart-line"></i></span><span>Turnover bulanan</span></h5>
                    <span class="exec-pill exec-tone-neutral">12 bulan · rata-rata {{ $fmt($avgRate, 2) }}%</span>
                </div>
                <div data-chart="{{ json_encode(['type' => 'area', 'name' => 'Turnover', 'suffix' => '%', 'zero' => true, 'labels' => $trendLabels, 'values' => $rates]) }}" style="height: 240px" role="img"
                    aria-label="Turnover bulanan: {{ collect($trend)->map(fn ($t, $i) => $t['label'].' '.($rates[$i] === null ? '—' : $fmt($t['rate'], 2).'%'))->implode(', ') }}"></div>
            </div></div>
        </div>
        <div class="col-lg-5">
            <div class="card exec-card h-100 mb-0"><div class="card-body">
                <div class="exec-section-header">
                    <h5 class="exec-section-title"><span class="exec-icon-box exec-tone-success" aria-hidden="true"><i class="fa fa-exchange-alt"></i></span><span>Arus pegawai</span></h5>
                    <span class="exec-pill exec-tone-neutral">bergabung vs keluar</span>
                </div>
                <div data-chart="{{ json_encode(['type' => 'flows', 'labels' => $trendLabels, 'series' => [
                        ['name' => 'Bergabung', 'values' => $chart(array_column($trend, 'hires')), 'slot' => 2],
                        ['name' => 'Keluar', 'values' => $chart(array_column($trend, 'terminations')), 'slot' => 1]]]) }}" style="height: 240px" role="img"
                    aria-label="Pegawai bergabung dan keluar per bulan: {{ collect($trend)->map(fn ($t) => $t['label'].' +'.$fmt($t['hires']).'/−'.$fmt($t['terminations']))->implode(', ') }}"></div>
            </div></div>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-lg-6">
            @include('executive._donut', ['title' => 'Alasan keluar — '.$monthLabel, 'icon' => 'fa fa-sign-out-alt', 'tone' => 'exec-tone-danger',
                'items' => $reasonItems, 'unit' => 'terminasi', 'mask' => $mask, 'empty' => 'Tidak ada terminasi yang diproses selama periode ini.'])
        </div>
        <div class="col-lg-6">@include('executive._insights', ['insights' => $insights])</div>
    </div>

{{-- ===================================================== MOBILITAS ===================================================== --}}
@elseif($report === 'promotions')
    @php
        [$moveCount, $people, $unitCount] = [$metrics[0][1], $metrics[1][1], $metrics[2][1]];
        $maxTransition = max(1, (int) $transitions->max('value'));
    @endphp

    @include('executive._hero', [
        'kicker' => 'Kenaikan grade '.$monthLabel, 'icon' => 'fa fa-level-up-alt',
        'number' => $fmt($moveCount),
        'sub' => $fmt($yearTotal).' kenaikan grade terindikasi dalam 12 bulan terakhir',
        'stats' => [
            ['Pegawai terdampak', $fmt($people), 'naik grade bulan ini', 'fa fa-user'],
            ['Unit tujuan', $num($unitCount), 'menerima pegawai naik grade', 'fa fa-sitemap'],
            ['Rata-rata bulanan', $fmt($yearTotal / 12, 1), 'selama 12 bulan', 'fa fa-calendar-alt'],
        ],
    ])

    <div class="row g-3 mb-4">
        <div class="col-lg-7">
            <div class="card exec-card h-100 mb-0"><div class="card-body">
                <div class="exec-section-header">
                    <h5 class="exec-section-title"><span class="exec-icon-box exec-tone-primary" aria-hidden="true"><i class="fa fa-chart-bar"></i></span><span>Kenaikan grade per bulan</span></h5>
                    <span class="exec-pill exec-tone-neutral">12 bulan</span>
                </div>
                @if($yearTotal === 0)
                    <div class="exec-empty-state"><p class="small mb-0">Belum ada kenaikan grade terindikasi dalam 12 bulan terakhir.</p></div>
                @else
                <div data-chart="{{ json_encode(['type' => 'columns', 'name' => 'Kenaikan grade', 'slot' => 0, 'barMaxWidth' => 28, 'labels' => array_column($trend, 'label'), 'values' => $chart(array_column($trend, 'value'))]) }}"
                    style="height: 240px" role="img" aria-label="Kenaikan grade per bulan: {{ collect($trend)->map(fn ($t) => $t['label'].' '.$fmt($t['value']))->implode(', ') }}"></div>
                @endif
            </div></div>
        </div>
        <div class="col-lg-5">
            <div class="card exec-card h-100 mb-0"><div class="card-body">
                <div class="exec-section-header">
                    <h5 class="exec-section-title"><span class="exec-icon-box exec-tone-success" aria-hidden="true"><i class="fa fa-random"></i></span><span>Jalur kenaikan grade</span></h5>
                    <span class="exec-pill exec-tone-neutral">{{ $monthLabel }}</span>
                </div>
                @if($transitions->isEmpty())
                    <div class="exec-empty-state"><p class="small mb-0">Tidak ditemukan perpindahan penempatan primer dengan kenaikan grade pada periode ini.</p></div>
                @else
                    <ul class="exec-paths">
                        @foreach($transitions->take(8) as $path)
                            <li>
                                <div class="exec-path-grades">
                                    <span class="exec-pill exec-tone-neutral">{{ $path['from'] }}</span>
                                    <i class="fa fa-long-arrow-alt-right" aria-label="ke"></i>
                                    <span class="exec-pill exec-tone-primary">{{ $path['to'] }}</span>
                                </div>
                                <div class="exec-path-bar" aria-hidden="true"><span style="width: {{ $small($path['value']) ? 0 : round($path['value'] / $maxTransition * 100) }}%"></span></div>
                                <strong class="tabular-nums">{{ $fmt($path['value']) }}</strong>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div></div>
        </div>
    </div>

    @include('executive._ranking', ['title' => 'Unit tujuan kenaikan grade', 'icon' => 'fa fa-sitemap', 'tone' => 'exec-tone-primary',
        'rows' => $rows, 'name' => 'Kenaikan grade', 'slot' => 0, 'column' => 'Unit tujuan', 'mask' => $mask,
        'empty' => 'Tidak ada unit tujuan pada periode ini.', 'footnote' => 'Unit dari posisi baru setelah perpindahan penempatan primer.'])

{{-- ===================================================== DEIB ===================================================== --}}
@elseif($report === 'deib')
    @php
        $g = $genderRows->keyBy('key');
        $recorded = $g['F']['headcount'] + $g['M']['headcount'];
        $total = (int) $genderRows->sum('headcount');
        $femaleShare = $recorded > 0 ? $pct($g['F']['headcount'], $recorded) : null;
        $sum = fn ($key) => (int) $genderRows->sum($key);
        $share = fn ($gender, $key) => $pct($g[$gender][$key], $g['F'][$key] + $g['M'][$key]);
        $series = $genderRows->filter(fn ($r) => $r['key'] !== 'unknown' || $r['headcount'] + $r['hires'] + $r['terminations'] > 0)
            ->map(fn ($r) => ['name' => $r['label'], 'neutral' => $r['key'] === 'unknown', 'values' => $chart([$r['headcount'], $r['hires'], $r['terminations']])])->values();
        $insights = array_values(array_filter([
            $femaleShare !== null ? ['fa fa-venus-mars', 'Komposisi saat ini', 'Perempuan '.$fmt($femaleShare, 1).'% dan laki-laki '.$fmt(100 - $femaleShare, 1).'% dari pegawai yang gendernya tercatat.'] : null,
            $g['F']['hires'] + $g['M']['hires'] > 0 ? ['fa fa-user-plus', 'Rekrutmen', 'Perempuan '.$fmt($share('F', 'hires'), 1).'% dari pegawai baru ('.(($d = round($share('F', 'hires') - (float) $femaleShare, 1)) >= 0 ? '+' : '').$fmt($d, 1).' poin dibanding komposisi saat ini).'] : null,
            $g['F']['terminations'] + $g['M']['terminations'] > 0 ? ['fa fa-user-times', 'Keluar', 'Perempuan '.$fmt($share('F', 'terminations'), 1).'% dari pegawai yang keluar bulan ini.'] : null,
            $total > 0 ? ['fa fa-clipboard-check', 'Kelengkapan data', $g['unknown']['headcount'] > 0 ? $fmt($g['unknown']['headcount']).' pegawai aktif ('.$fmt($pct($g['unknown']['headcount'], $total), 1).'%) belum mengisi gender.' : 'Semua pegawai aktif sudah memiliki data gender.'] : null,
        ]));
    @endphp

    @include('executive._hero', [
        'kicker' => 'Perempuan di tenaga kerja', 'icon' => 'fa fa-venus',
        'number' => $femaleShare !== null ? $fmt($femaleShare, 1).'%' : '—',
        'sub' => $fmt($g['F']['headcount']).' perempuan · '.$fmt($g['M']['headcount']).' laki-laki'.($g['unknown']['headcount'] ? ' · '.$fmt($g['unknown']['headcount']).' belum diisi' : '').' per '.$endLabel,
        'stats' => [
            ['Pegawai aktif', $fmt($total), 'akhir periode', 'fa fa-users'],
            ['Pegawai baru', $fmt($sum('hires')), 'selama '.$monthLabel, 'fa fa-user-plus'],
            ['Terminasi', $fmt($sum('terminations')), 'diproses selama '.$monthLabel, 'fa fa-user-times'],
        ],
    ])

    <div class="row g-3 mb-4">
        <div class="col-lg-8">
            <div class="card exec-card h-100 mb-0"><div class="card-body">
                <div class="exec-section-header">
                    <h5 class="exec-section-title"><span class="exec-icon-box exec-tone-primary" aria-hidden="true"><i class="fa fa-align-left"></i></span><span>Representasi gender per tahap</span></h5>
                    <span class="exec-pill exec-tone-neutral">porsi 100%</span>
                </div>
                @if($total + $sum('hires') + $sum('terminations') === 0)
                    <div class="exec-empty-state"><p class="small mb-0">Belum ada data gender pada periode ini.</p></div>
                @else
                    <div data-chart="{{ json_encode(['type' => 'stack100', 'stages' => ['Pegawai aktif', 'Pegawai baru', 'Terminasi'], 'series' => $series->all()]) }}" style="height: 200px" role="img"
                        aria-label="Representasi gender: {{ $genderRows->map(fn ($r) => $r['label'].' aktif '.$fmt($r['headcount']).', baru '.$fmt($r['hires']).', keluar '.$fmt($r['terminations']))->implode('; ') }}"></div>
                @endif
                <div class="table-responsive mt-3"><table class="table exec-table mb-0">
                    <thead><tr><th>Gender</th><th class="text-end">Pegawai aktif</th><th class="text-end">Pegawai baru</th><th class="text-end">Terminasi</th></tr></thead>
                    <tbody>
                    @foreach($genderRows as $i => $row)
                        <tr>
                            <td><span class="exec-swatch d-inline-block me-2 align-middle" style="background: var({{ $row['key'] === 'unknown' ? '--viz-neutral' : '--viz-'.($i + 1) }})"></span>{{ $row['label'] }}</td>
                            @foreach(['headcount', 'hires', 'terminations'] as $key)
                                <td class="text-end tabular-nums">{{ $fmt($row[$key]) }} <span class="text-muted small">({{ $fmt($pct($row[$key], $sum($key)), 1) }}%)</span></td>
                            @endforeach
                        </tr>
                    @endforeach
                    </tbody>
                </table></div>
            </div></div>
        </div>
        <div class="col-lg-4">@include('executive._insights', ['insights' => $insights])</div>
    </div>

{{-- ===================================================== DEMOGRAFI ===================================================== --}}
@elseif($report === 'workforce')
    @php
        [$unitCount, $locationCount] = [$metrics[1][1], $metrics[2][1]];
        $young = (int) ($ages->firstWhere('label', 'Di bawah 30')['value'] ?? 0);
        $agesKnown = (int) $ages->where('label', '!=', 'Belum diisi')->sum('value');
        $genderItems = $gender->map(fn ($r) => $r + ['neutral' => $r['label'] === 'Belum diisi']);
    @endphp

    @include('executive._hero', [
        'kicker' => 'Pegawai aktif', 'icon' => 'fa fa-users',
        'number' => $fmt($headcount),
        'sub' => 'per '.$endLabel.' · tersebar di '.$num($unitCount).' unit dan '.$num($locationCount).' lokasi',
        'stats' => [
            ['Unit terisi', $num($unitCount), 'dengan penempatan primer aktif', 'fa fa-sitemap'],
            ['Lokasi kerja', $num($locationCount), 'berdasarkan lokasi posisi', 'fa fa-map-marker-alt'],
            ['Di bawah 30 tahun', $agesKnown ? $fmt($pct($young, $agesKnown), 1).'%' : '—', 'dari pegawai dengan tanggal lahir', 'fa fa-seedling'],
        ],
    ])

    <div class="row g-3 mb-4">
        <div class="col-lg-7">
            <div class="card exec-card h-100 mb-0"><div class="card-body">
                <div class="exec-section-header">
                    <h5 class="exec-section-title"><span class="exec-icon-box exec-tone-primary" aria-hidden="true"><i class="fa fa-birthday-cake"></i></span><span>Kelompok usia</span></h5>
                </div>
                @if($ages->isEmpty())
                    <div class="exec-empty-state"><p class="small mb-0">Belum ada pegawai aktif pada periode ini.</p></div>
                @else
                    <div data-chart="{{ json_encode(['type' => 'columns', 'name' => 'Pegawai', 'slot' => 0, 'labels' => $ages->pluck('label')->all(), 'values' => $chart($ages->pluck('value')->all()),
                            'neutral' => $ages->keys()->filter(fn ($i) => $ages[$i]['label'] === 'Belum diisi')->values()->all()]) }}" style="height: 240px" role="img"
                        aria-label="Kelompok usia: {{ $ages->map(fn ($a) => $a['label'].' '.$fmt($a['value']))->implode(', ') }}"></div>
                @endif
            </div></div>
        </div>
        <div class="col-lg-5">
            @include('executive._donut', ['title' => 'Gender', 'icon' => 'fa fa-venus-mars', 'tone' => 'exec-tone-primary',
                'items' => $genderItems, 'unit' => 'pegawai', 'empty' => 'Belum ada pegawai aktif pada periode ini.', 'mask' => $mask])
        </div>
    </div>

    <div class="row g-3">
        <div class="col-lg-6">
            @include('executive._ranking', ['title' => 'Pegawai per unit', 'icon' => 'fa fa-sitemap', 'tone' => 'exec-tone-success',
                'rows' => $units, 'name' => 'Pegawai', 'slot' => 0, 'column' => 'Unit organisasi', 'mask' => $mask,
                'empty' => 'Belum ada penempatan pegawai pada periode ini.', 'footnote' => 'Penempatan primer pegawai aktif pada akhir periode.'])
        </div>
        <div class="col-lg-6">
            @include('executive._ranking', ['title' => 'Pegawai per lokasi kerja', 'icon' => 'fa fa-map-marker-alt', 'tone' => 'exec-tone-info',
                'rows' => $locations, 'name' => 'Pegawai', 'slot' => 2, 'column' => 'Lokasi kerja', 'mask' => $mask,
                'empty' => 'Belum ada lokasi kerja pada posisi.', 'footnote' => 'Lokasi berasal dari posisi, bukan alamat rumah pegawai.'])
        </div>
    </div>

{{-- ===================================================== PENGEMBANGAN ===================================================== --}}
@elseif($report === 'development')
    @php
        $gapRows = $rows->map(fn ($r) => ['label' => $r['name'] ? $r['name'] : $r['label'], 'value' => $r['value']]);
    @endphp

    @include('executive._hero', [
        'kicker' => 'Asesmen selesai', 'icon' => 'fa fa-clipboard-check',
        'number' => $fmt($closed),
        'sub' => $fmt($open).' asesmen masih berjalan',
        'stats' => [
            ['Asesmen berjalan', $fmt($open), 'menunggu penyelesaian', 'fa fa-hourglass-half'],
            ['Pegawai berdata', $fmt($skills), 'memiliki hasil kompetensi', 'fa fa-id-card'],
            ['Saran IDP', $fmt($idp), 'draf atau diterima', 'fa fa-graduation-cap'],
        ],
        'ring' => ['value' => $small($closed + $open) ? null : $completion, 'center' => 'selesai', 'title' => 'Penyelesaian asesmen', 'icon' => 'fa fa-tasks',
            'note' => $fmt($closed).' dari '.$fmt($closed + $open).' asesmen', 'slot' => 0],
    ])

    <div class="row g-3">
        <div class="col-lg-7">
            @include('executive._ranking', ['title' => 'Kompetensi dengan kesenjangan terbesar', 'icon' => 'fa fa-exclamation-triangle', 'tone' => 'exec-tone-warning',
                'rows' => $gapRows, 'name' => 'Catatan kesenjangan', 'slot' => 3, 'column' => 'Kompetensi', 'mask' => $mask,
                'empty' => 'Belum ada hasil asesmen kompetensi yang menghasilkan kesenjangan.', 'footnote' => 'Diurutkan dari rata-rata gap terendah (paling kurang); maksimal 10 kompetensi.'])
        </div>
        <div class="col-lg-5">
            <div class="card exec-card h-100 mb-0"><div class="card-body">
                <div class="exec-section-header">
                    <h5 class="exec-section-title"><span class="exec-icon-box exec-tone-danger" aria-hidden="true"><i class="fa fa-sliders-h"></i></span><span>Rata-rata gap</span></h5>
                    <span class="exec-pill exec-tone-neutral">aktual − wajib</span>
                </div>
                @if($rows->isEmpty())
                    <div class="exec-empty-state"><p class="small mb-0">Belum ada kesenjangan kompetensi.</p></div>
                @else
                    <ul class="exec-gaps">
                        @foreach($rows as $row)
                            <li>
                                <div class="min-w-0">
                                    <div class="exec-legend-label">{{ $row['name'] ?? $row['label'] }}</div>
                                    <div class="small text-muted">{{ $row['label'] }} · {{ $fmt($row['value']) }} catatan</div>
                                </div>
                                <span class="exec-pill {{ $row['gap'] <= -1 ? 'exec-tone-danger' : ($row['gap'] < 0 ? 'exec-tone-warning' : 'exec-tone-success') }} tabular-nums">
                                    {{ $small($row['value']) ? '—' : ($row['gap'] > 0 ? '+' : '').$row['detail'] }}</span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div></div>
        </div>
    </div>
@endif

<div class="exec-footnote exec-page-note"><i class="fa fa-info-circle" aria-hidden="true"></i><span>{{ $note }}</span></div>
@if($mask)
    <p class="small text-muted mt-2 mb-0"><i class="fa fa-user-secret" aria-hidden="true"></i> Kelompok berisi 1–4 orang ditampilkan "&lt; 5" untuk melindungi privasi pegawai.</p>
@endif
@if($scoped)
    <p class="small text-muted mt-2 mb-0"><i class="fa fa-filter" aria-hidden="true"></i> Angka dibatasi unit dalam cakupan data Anda; pegawai yang keluar atau pindah tetap dihitung di unit asalnya selama periode.</p>
@endif
</div>

@include('executive._filter-script')
@vite('resources/js/charts.js')
@endsection
