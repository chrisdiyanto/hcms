@extends('layouts.app')

@section('content')

<style>
    .db-container {
        padding: 20px;
        background-color: #f1f5f9;
        font-family: 'Poppins', sans-serif;
    }
    .db-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 25px;
        flex-wrap: wrap;
        gap: 12px;
    }
    .db-header h2 {
        font-size: 24px;
        font-weight: 600;
        color: #1e293b;
        margin: 0;
    }
    .db-filter-wrapper {
        position: relative;
    }
    .db-filter-btn {
        background-color: #2b455b;
        color: white;
        border: none;
        padding: 8px 16px;
        border-radius: 8px;
        font-size: 13px;
        cursor: pointer;
        display: flex;
        align-items: center;
    }
    .db-filter-btn i {
        margin-left: 8px;
    }
    .db-filter-btn div {
        text-align: left;
    }
    .db-filter-title {
        font-size: 11px;
        opacity: 0.8;
        display: block;
    }
    .db-filter-dropdown {
        display: none;
        position: absolute;
        right: 0;
        top: calc(100% + 6px);
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 10px;
        box-shadow: 0 10px 25px rgba(15, 23, 42, 0.12);
        padding: 14px;
        min-width: 260px;
        z-index: 1000;
    }
    .db-filter-dropdown.show {
        display: block;
    }
    .db-filter-preset {
        display: block;
        width: 100%;
        text-align: left;
        background: none;
        border: none;
        padding: 7px 10px;
        border-radius: 6px;
        font-size: 13px;
        color: #334155;
        cursor: pointer;
        text-decoration: none;
    }
    .db-filter-preset:hover,
    .db-filter-preset.active {
        background-color: #f1f5f9;
        color: #0f172a;
        font-weight: 600;
    }

    .db-card {
        background: #ffffff;
        border-radius: 12px;
        box-shadow: 0 4px 15px rgba(0,0,0,0.03);
        padding: 20px;
        margin-bottom: 20px;
        border: 1px solid #e2e8f0;
    }

    .metric-card {
        display: flex;
        align-items: center;
    }
    .metric-icon {
        width: 60px;
        height: 60px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        margin-right: 15px;
        font-size: 28px;
        color: white;
        flex-shrink: 0;
    }
    .bg-icon-blue { background-color: #4a86c6; }
    .bg-icon-green { background-color: #258c73; }
    .bg-icon-dark { background-color: #3b5c77; }

    .metric-info h6 {
        font-size: 13px;
        color: #64748b;
        margin-bottom: 5px;
        font-weight: 500;
        margin-top: 0;
    }
    .metric-info h3 {
        font-size: 24px;
        font-weight: 700;
        color: #0f172a;
        margin-bottom: 2px;
        margin-top: 5px;
    }
    .metric-trend {
        font-size: 12px;
    }
    .trend-up { color: #10b981; }
    .trend-down { color: #ef4444; }

    .db-card-title {
        font-size: 16px;
        font-weight: 600;
        color: #1e293b;
        margin-bottom: 15px;
        margin-top: 0;
    }

    .doc-list {
        list-style: none;
        padding: 0;
        margin: 0;
    }
    .doc-list li {
        display: flex;
        align-items: center;
        padding: 12px 0;
        border-bottom: 1px solid #f8fafc;
    }
    .doc-list li:last-child {
        border-bottom: none;
    }
    .doc-icon {
        font-size: 20px;
        margin-right: 12px;
    }
    .doc-icon.blue { color: #3b82f6; }
    .doc-icon.orange { color: #f97316; }
    .doc-icon.green { color: #22c55e; }
    .doc-title {
        flex-grow: 1;
        font-size: 14px;
        color: #334155;
        font-weight: 500;
    }
    .doc-views {
        font-size: 12px;
        color: #94a3b8;
    }

    .alert-box {
        background-color: #f8fafc;
        border-radius: 8px;
        padding: 10px 12px;
        margin-bottom: 10px;
        display: flex;
        align-items: center;
        font-size: 13px;
        color: #475569;
    }
    .alert-box i {
        margin-right: 10px;
        font-size: 16px;
    }
    .alert-warning i { color: #eab308; }
    .alert-danger i { color: #ef4444; }

    .access-method {
        display: flex;
        justify-content: center;
        align-items: center;
        gap: 20px;
        margin-bottom: 15px;
    }
    .access-item {
        display: flex;
        align-items: center;
        font-size: 13px;
        color: #334155;
        font-weight: 500;
        flex-direction: column;
    }
    .access-item i {
        font-size: 24px;
        margin-bottom: 8px;
        color: #64748b;
    }

    .db-empty-state {
        height: 260px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #94a3b8;
        font-size: 13px;
        text-align: center;
    }

    .fa-folder-open, .fa-users, .fa-stopwatch, .fa-file-medical, .fa-file-pdf, .fa-exclamation-triangle, .fa-calendar-times, .fa-desktop, .fa-mobile-alt {
        display: inline-block;
    }
</style>

<div class="page-titles">
    <h2> {{ $pageTitle ?? 'Dashboard' }} <small> {{ $pageNote ?? '' }} </small></h2>
</div>

<div class="db-container">
    <div class="db-header">
        <h2>{{ $pageTitle ?? 'Dashboard' }} <small style="font-size:14px;color:#64748b;font-weight:normal;">{{ $pageNote ?? '' }}</small></h2>
        <div class="db-filter-wrapper">
            <button type="button" class="db-filter-btn" id="dbFilterToggle">
                <div>
                    <span class="db-filter-title">Filter Periode</span>
                    {{ $periodLabel ?? '-' }}
                </div>
                <i class="fas fa-chevron-down"></i>
            </button>
            <div class="db-filter-dropdown" id="dbFilterDropdown">
                <div class="mb-2">
                    @foreach(($periodOptions ?? []) as $key => $label)
                        <a href="{{ url('dashboard') }}?period={{ $key }}" class="db-filter-preset {{ ($selectedPeriod ?? '30d') === $key ? 'active' : '' }}">
                            {{ $label }}
                        </a>
                    @endforeach
                </div>
                <hr class="my-2">
                <form method="GET" action="{{ url('dashboard') }}">
                    <div class="mb-2">
                        <label class="form-label small text-muted mb-1">Dari Tanggal</label>
                        <input type="date" name="start_date" class="form-control form-control-sm" value="{{ $startDate ?? '' }}" required>
                    </div>
                    <div class="mb-2">
                        <label class="form-label small text-muted mb-1">Sampai Tanggal</label>
                        <input type="date" name="end_date" class="form-control form-control-sm" value="{{ $endDate ?? '' }}" required>
                    </div>
                    <button type="submit" class="btn btn-sm btn-primary w-100">Terapkan</button>
                </form>
            </div>
        </div>
    </div>

    <!-- Top Metrics -->
    <div class="row">
        <div class="col-lg-3 col-md-6 mb-3">
            <div class="db-card metric-card" style="height: 100%; margin-bottom: 0;">
                <div class="metric-icon bg-icon-blue">
                    <i class="fas fa-folder-open"></i>
                </div>
                <div class="metric-info">
                    <h6>{{ $metrics['total_documents']['label'] ?? 'Total Pegawai Aktif' }}</h6>
                    <h3>{{ $metrics['total_documents']['formatted'] ?? '0' }}</h3>
                    <div class="metric-trend {{ ($metrics['total_documents']['trend_dir'] ?? 'up') === 'up' ? 'trend-up' : 'trend-down' }}">
                        <i class="fas fa-arrow-{{ ($metrics['total_documents']['trend_dir'] ?? 'up') === 'up' ? 'up' : 'down' }}"></i>
                        {{ $metrics['total_documents']['trend_pct'] ?? 0 }}%
                        <span style="color:#94a3b8;">{{ $metrics['total_documents']['trend_note'] ?? 'dari bulan lalu' }}</span>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6 mb-3">
            <div class="db-card metric-card" style="height: 100%; margin-bottom: 0;">
                <div class="metric-icon bg-icon-green">
                    <i class="fas fa-users"></i>
                </div>
                <div class="metric-info">
                    <h6>{{ $metrics['visitors_today']['label'] ?? 'Pengunjung Hari Ini' }}</h6>
                    <h3>{{ $metrics['visitors_today']['formatted'] ?? '0' }}</h3>
                    <div class="metric-trend {{ ($metrics['visitors_today']['trend_dir'] ?? 'up') === 'up' ? 'trend-up' : 'trend-down' }}">
                        <i class="fas fa-arrow-{{ ($metrics['visitors_today']['trend_dir'] ?? 'up') === 'up' ? 'up' : 'down' }}"></i>
                        {{ $metrics['visitors_today']['trend_pct'] ?? 0 }}%
                        <span style="color:#94a3b8;">{{ $metrics['visitors_today']['trend_note'] ?? 'dari kemarin' }}</span>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6 mb-3">
            <div class="db-card metric-card" style="height: 100%; margin-bottom: 0;">
                <div class="metric-icon bg-icon-dark">
                    <i class="fas fa-stopwatch"></i>
                </div>
                <div class="metric-info">
                    <h6>{{ $metrics['avg_duration']['label'] ?? 'Rata-rata Durasi Akses' }}</h6>
                    <h3>{{ $metrics['avg_duration']['formatted'] ?? '0 Menit' }}</h3>
                    <div class="metric-trend {{ ($metrics['avg_duration']['trend_dir'] ?? 'up') === 'up' ? 'trend-up' : 'trend-down' }}">
                        <i class="fas fa-arrow-{{ ($metrics['avg_duration']['trend_dir'] ?? 'up') === 'up' ? 'up' : 'down' }}"></i>
                        {{ $metrics['avg_duration']['trend_pct'] ?? 0 }}%
                        <span style="color:#94a3b8;">{{ $metrics['avg_duration']['trend_note'] ?? 'tren mingguan' }}</span>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6 mb-3">
            <div class="db-card metric-card" style="height: 100%; margin-bottom: 0;">
                <div class="metric-icon" style="background-color: #22c55e;">
                    <i class="fas fa-file-medical"></i>
                </div>
                <div class="metric-info">
                    <h6>{{ $metrics['new_this_month']['label'] ?? 'Pegawai Baru (Bulan Ini)' }}</h6>
                    <h3>{{ $metrics['new_this_month']['formatted'] ?? '+0' }}</h3>
                    <div class="metric-trend" style="visibility:hidden;">-</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Middle Charts -->
    <div class="row pt-2">
        <div class="col-lg-8 mb-3">
            <div class="db-card" style="height: 100%; margin-bottom: 0;">
                <h4 class="db-card-title">Tren Pengunjung Harian</h4>
                <div id="trendChart" style="height: 300px;"></div>
            </div>
        </div>
        <div class="col-lg-4 mb-3">
            <div class="db-card" style="height: 100%; margin-bottom: 0;">
                <h4 class="db-card-title">Distribusi Berdasarkan Unit</h4>
                @if(!empty($unitChart['series']))
                    <div id="donutChart" style="height: 300px;"></div>
                @else
                    <div class="db-empty-state">Belum ada data distribusi unit pada periode ini.</div>
                @endif
            </div>
        </div>
    </div>

    <!-- Bottom Section -->
    <div class="row pt-2">
        <div class="col-lg-5 mb-3">
            <div class="db-card" style="height: 100%; margin-bottom: 0;">
                <h4 class="db-card-title">Dokumen & Modul Terpopuler</h4>
                <ul class="doc-list">
                    @forelse(($popularDocuments ?? []) as $doc)
                        <li>
                            <i class="{{ $doc['icon'] ?? 'fas fa-file-pdf' }} doc-icon {{ $doc['color'] ?? 'blue' }}"></i>
                            <span class="doc-title">{{ $doc['rank'] }}. {{ $doc['title'] }}</span>
                            <span class="doc-views">({{ $doc['formatted_count'] }})</span>
                        </li>
                    @empty
                        <li>
                            <span class="text-muted small">Belum ada data dokumen atau aktivitas pada periode ini.</span>
                        </li>
                    @endforelse
                </ul>
            </div>
        </div>
        <div class="col-lg-4 mb-3">
            <div class="db-card" style="height: 100%; margin-bottom: 0;">
                <h4 class="db-card-title">Perilaku Kunjungan</h4>
                <div class="d-flex justify-content-center" style="gap:40px; align-items:flex-end;">
                    <div style="text-align: center;">
                        <div id="chart-pie-1" style="width: 110px; height: 110px; margin:0 auto;"></div>
                        <div style="font-size: 13px; font-weight:500; margin-top:10px;">
                            {{ $visitBehavior['moderate_label'] ?? '2-10 Menit' }}<br/>({{ $visitBehavior['moderate_pct'] ?? 0 }}%)
                        </div>
                    </div>
                    <div style="text-align: center;">
                        <div id="chart-pie-2" style="width: 110px; height: 110px; margin:0 auto;"></div>
                        <div style="font-size: 13px; font-weight:500; margin-top:10px;">
                            {{ $visitBehavior['long_label'] ?? '> 10 Menit' }}<br/>({{ $visitBehavior['long_pct'] ?? 0 }}%)
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-3 mb-3">
            <div class="db-card" style="padding: 15px; margin-bottom: 15px;">
                <h4 class="db-card-title" style="text-align: center; font-size:14px; margin-bottom: 10px;">Metode Akses:</h4>
                <div class="access-method">
                    <div class="access-item">
                        <i class="fas fa-desktop"></i> Desktop ({{ $accessMethods['desktop_pct'] ?? 0 }}%)
                    </div>
                    <div class="access-item">
                        <i class="fas fa-mobile-alt"></i> Mobile ({{ $accessMethods['mobile_pct'] ?? 0 }}%)
                    </div>
                </div>
            </div>

            <div class="db-card" style="padding: 15px; margin-bottom: 0;">
                <h4 class="db-card-title" style="font-size: 14px;">Peringatan Keamanan & Sistem</h4>
                <div class="alert-box alert-warning">
                    <i class="fas fa-exclamation-triangle"></i> {{ $securityAlerts['failed_logins_text'] ?? '0x Gagal Login / Akses Sistem' }}
                </div>
                <div class="alert-box alert-danger">
                    <i class="fas fa-calendar-times"></i> {{ $securityAlerts['expiring_items_text'] ?? '0 Kontrak / Sertifikasi Jatuh Tempo (30 hari)' }}
                </div>
            </div>
        </div>
    </div>
</div>

<script type="text/javascript">
function loadCachedScript(url, callback) {
    $.ajax({
        type: "GET",
        url: url,
        success: callback,
        dataType: "script",
        cache: true
    });
}

function initDashboardCharts() {
    if (typeof ApexCharts === 'undefined') {
        setTimeout(initDashboardCharts, 100);
        return;
    }

    var isDark = $('body').hasClass('dark-theme');

    var trendData = @json($trendChart ?? ['dates' => [], 'visitors' => [], 'activities' => [], 'peak' => null]);
    var unitData = @json($unitChart ?? ['series' => [], 'labels' => []]);
    var visitData = @json($visitBehavior ?? ['moderate_pct' => 0, 'long_pct' => 0]);

    var pointAnnotations = [];
    if (trendData.peak && trendData.peak.x) {
        pointAnnotations.push({
            x: trendData.peak.x,
            y: trendData.peak.y,
            marker: {
                size: 4,
                fillColor: '#fff',
                strokeColor: isDark ? '#388bfd' : '#2a5b7c',
                radius: 2,
                strokeWidth: 2
            },
            label: {
                borderColor: isDark ? '#30363d' : '#1e293b',
                style: {
                    color: '#fff',
                    background: isDark ? '#161b22' : '#1e293b',
                    fontSize: '11px',
                    padding: { left: 8, right: 8, top: 4, bottom: 4 }
                },
                text: trendData.peak.text
            }
        });
    }

    var optionsTrend = {
        series: [{
            name: 'Pengunjung',
            data: trendData.visitors || []
        }, {
            name: 'Aktivitas Sistem',
            data: trendData.activities || []
        }],
        chart: {
            height: 300,
            type: 'area',
            toolbar: { show: false },
            parentHeightOffset: 0
        },
        colors: isDark ? ['#388bfd', '#34d399'] : ['#2a5b7c', '#469085'],
        dataLabels: { enabled: false },
        stroke: { curve: 'smooth', width: 2 },
        xaxis: {
            categories: trendData.dates || [],
            labels: { style: { fontSize: '10px', colors: '#94a3b8' } },
            axisBorder: { show: false },
            axisTicks: { show: false }
        },
        yaxis: {
            labels: { style: { colors: '#94a3b8' } }
        },
        grid: {
            borderColor: isDark ? '#21262d' : '#f1f5f9',
            strokeDashArray: 0,
            yaxis: { lines: { show: true } }
        },
        fill: {
            type: 'gradient',
            gradient: {
                shadeIntensity: 1,
                opacityFrom: 0.5,
                opacityTo: 0.05,
                stops: [0, 100]
            }
        },
        annotations: {
            points: pointAnnotations
        },
        legend: { show: false }
    };
    var trendEl = document.querySelector("#trendChart");
    if (trendEl) {
        var chartTrend = new ApexCharts(trendEl, optionsTrend);
        chartTrend.render();
    }

    var donutEl = document.querySelector("#donutChart");
    if (donutEl && unitData.series && unitData.series.length > 0) {
        var optionsDonut = {
            series: unitData.series,
            labels: unitData.labels,
            chart: {
                type: 'donut',
                height: 300,
                parentHeightOffset: 0
            },
            colors: isDark ? ['#388bfd', '#58a6ff', '#34d399', '#a7f3d0', '#f97316'] : ['#163b65', '#1d748f', '#279a78', '#7bb04a', '#ed8d35'],
            dataLabels: { enabled: false },
            legend: {
                position: 'right',
                offsetY: 20,
                itemMargin: { vertical: 8 },
                fontSize: '13px',
                fontWeight: 500,
                labels: { colors: isDark ? '#c9d1d9' : '#334155' }
            },
            plotOptions: {
                pie: { donut: { size: '65%' } }
            },
            stroke: { show: true, colors: [isDark ? '#0b0e11' : 'transparent'], width: 2 }
        };
        var chartDonut = new ApexCharts(donutEl, optionsDonut);
        chartDonut.render();
    }

    var modPct = Math.min(100, Math.max(0, parseInt(visitData.moderate_pct || 0, 10)));
    var longPct = Math.min(100, Math.max(0, parseInt(visitData.long_pct || 0, 10)));

    var pie1El = document.querySelector("#chart-pie-1");
    if (pie1El) {
        var optionsPie1 = {
            series: [modPct, Math.max(0, 100 - modPct)],
            chart: { type: 'donut', width: 110, height: 110, sparkline: { enabled: true } },
            colors: isDark ? ['#388bfd', '#21262d'] : ['#2a5b7c', '#e2e8f0'],
            tooltip: { enabled: false },
            plotOptions: { pie: { donut: { size: '75%' } } },
            stroke: { width: 0 }
        };
        var chartPie1 = new ApexCharts(pie1El, optionsPie1);
        chartPie1.render();
    }

    var pie2El = document.querySelector("#chart-pie-2");
    if (pie2El) {
        var optionsPie2 = {
            series: [longPct, Math.max(0, 100 - longPct)],
            chart: { type: 'donut', width: 110, height: 110, sparkline: { enabled: true } },
            colors: isDark ? ['#58a6ff', '#21262d'] : ['#1d748f', '#e2e8f0'],
            tooltip: { enabled: false },
            plotOptions: { pie: { donut: { size: '75%' } } },
            stroke: { width: 0 }
        };
        var chartPie2 = new ApexCharts(pie2El, optionsPie2);
        chartPie2.render();
    }
}

$(function () {
    $('#dbFilterToggle').on('click', function (e) {
        e.stopPropagation();
        $('#dbFilterDropdown').toggleClass('show');
    });
    $(document).on('click', function (e) {
        if (!$(e.target).closest('.db-filter-wrapper').length) {
            $('#dbFilterDropdown').removeClass('show');
        }
    });

    if (typeof ApexCharts === 'undefined') {
        loadCachedScript('https://cdn.jsdelivr.net/npm/apexcharts', function() {
            initDashboardCharts();
        });
    } else {
        initDashboardCharts();
    }
});
</script>
@stop
