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
    }
    .db-header h2 {
        font-size: 24px;
        font-weight: 600;
        color: #1e293b;
        margin: 0;
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
        margin-top:0;
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
    
    /* FontAwesome override fix since some icons might differ */
    .fa-folder-open, .fa-users, .fa-stopwatch, .fa-file-medical, .fa-file-pdf, .fa-exclamation-triangle, .fa-calendar-times, .fa-desktop, .fa-mobile-alt {
        display: inline-block;
    }

</style>
<div class="page-titles">
  <h2> {{ $pageTitle }} <small> {{ $pageNote }} </small></h2>
</div>

<div class="db-container">
    <div class="db-header">
        <h2>{{ $pageTitle ?? 'Dashboard' }} <small style="font-size:14px;color:#64748b;font-weight:normal;">{{ $pageNote ?? '' }}</small></h2>
        <button class="db-filter-btn">
            <div>
                <span class="db-filter-title">Filter Periode</span>
                1 Mar 2026 - 7 Apr 2026
            </div>
            <i class="fas fa-chevron-down"></i>
        </button>
    </div>

    <!-- Top Metrics -->
    <div class="row">
        <div class="col-lg-3 col-md-6 mb-3">
            <div class="db-card metric-card" style="height: 100%; margin-bottom: 0;">
                <div class="metric-icon bg-icon-blue">
                    <i class="fas fa-folder-open"></i>
                </div>
                <div class="metric-info">
                    <h6>Total Dokumen</h6>
                    <h3>12,450</h3>
                    <div class="metric-trend trend-up"><i class="fas fa-arrow-up"></i> 5% <span style="color:#94a3b8;">from last month</span></div>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6 mb-3">
            <div class="db-card metric-card" style="height: 100%; margin-bottom: 0;">
                <div class="metric-icon bg-icon-green">
                    <i class="fas fa-users"></i>
                </div>
                <div class="metric-info">
                    <h6>Pengunjung Hari Ini</h6>
                    <h3>458</h3>
                    <div class="metric-trend trend-up"><i class="fas fa-arrow-up"></i> 12% <span style="color:#94a3b8;">from yesterday</span></div>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6 mb-3">
            <div class="db-card metric-card" style="height: 100%; margin-bottom: 0;">
                <div class="metric-icon bg-icon-dark">
                    <i class="fas fa-stopwatch"></i>
                </div>
                <div class="metric-info">
                    <h6>Rata-rata Durasi Baca</h6>
                    <h3>12 Menit</h3>
                    <div class="metric-trend trend-down"><i class="fas fa-arrow-down"></i> 2% <span style="color:#94a3b8;">weekly trend</span></div>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6 mb-3">
            <div class="db-card metric-card" style="height: 100%; margin-bottom: 0;">
                <div class="metric-icon" style="background-color: #22c55e;">
                    <i class="fas fa-file-medical"></i>
                </div>
                <div class="metric-info">
                    <h6>Dokumen Baru (Bulan Ini)</h6>
                    <h3>+128</h3>
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
                <h4 class="db-card-title">Distribusi Akses Berdasarkan Unit</h4>
                <div id="donutChart" style="height: 300px;"></div>
            </div>
        </div>
    </div>

    <!-- Bottom Section -->
    <div class="row pt-2">
        <div class="col-lg-5 mb-3">
            <div class="db-card" style="height: 100%; margin-bottom: 0;">
                <h4 class="db-card-title">Dokumen Terpopuler</h4>
                <ul class="doc-list">
                    <li>
                        <i class="fas fa-file-pdf doc-icon blue"></i>
                        <span class="doc-title">1. SOP Work From Anywhere 2026.pdf</span>
                        <span class="doc-views">(986)</span>
                    </li>
                    <li>
                        <i class="fas fa-file-pdf doc-icon orange"></i>
                        <span class="doc-title">2. Kalender Libur Nasional 2026.pdf</span>
                        <span class="doc-views">(745)</span>
                    </li>
                    <li>
                        <i class="fas fa-file-pdf doc-icon green"></i>
                        <span class="doc-title">3. Panduan Teknis IT.pdf</span>
                        <span class="doc-views">(620)</span>
                    </li>
                </ul>
            </div>
        </div>
        <div class="col-lg-4 mb-3">
            <div class="db-card" style="height: 100%; margin-bottom: 0;">
                <h4 class="db-card-title">Perilaku Kunjungan</h4>
                <div class="d-flex justify-content-center" style="gap:40px; align-items:flex-end;">
                    <div style="text-align: center;">
                        <div id="chart-pie-1" style="width: 110px; height: 110px; margin:0 auto;"></div>
                        <div style="font-size: 13px; font-weight:500; margin-top:10px;">2-10 Menit<br/>(45%)</div>
                    </div>
                    <div style="text-align: center;">
                        <div id="chart-pie-2" style="width: 110px; height: 110px; margin:0 auto;"></div>
                        <div style="font-size: 13px; font-weight:500; margin-top:10px;">> 10 Menit<br/>(15%)</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-3 mb-3">
            <div class="db-card" style="padding: 15px; margin-bottom: 15px;">
                <h4 class="db-card-title" style="text-align: center; font-size:14px; margin-bottom: 10px;">Metode Akses:</h4>
                <div class="access-method">
                    <div class="access-item">
                        <i class="fas fa-desktop"></i> Desktop (75%)
                    </div>
                    <div class="access-item">
                        <i class="fas fa-mobile-alt"></i> Mobile (25%)
                    </div>
                </div>
            </div>
            
            <div class="db-card" style="padding: 15px; margin-bottom: 0;">
                <h4 class="db-card-title" style="font-size: 14px;">Peringatan Keamanan & Sistem</h4>
                <div class="alert-box alert-warning">
                    <i class="fas fa-exclamation-triangle"></i> 5x Gagal Akses IP Luar Kantor
                </div>
                <div class="alert-box alert-danger">
                    <i class="fas fa-calendar-times"></i> 12 Dokumen Kedaluwarsa (30 hari)
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

    const dates = ['10 Mar', '16 Mar', '26 Mar', '28 Jan', '21 Jan', '18 Jan', '16 Jun', '26 Jan', '20 Jan', '27 Jun', '28 Jan', '30 Jan', '31 Jun', '1 Nov', '1 Apr', '2 Apr', '3 Apr', '4 Apr', '5 Apr', '6 Apr', '7 Apr', '7 Apr']; 

    // Main Trend Chart (Area Spline -> smooth Area)
    var optionsTrend = {
        series: [{
            name: 'Pengunjung',
            data: [200, 350, 500, 300, 550, 320, 520, 380, 350, 250, 480, 500, 470, 520, 800, 500, 280, 450, 280, 400, 300, 600]
        }, {
            name: 'Akses Dokumen',
            data: [100, 250, 250, 200, 350, 250, 400, 320, 350, 200, 480, 350, 400, 500, 810, 400, 250, 380, 250, 400, 350, 450]
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
            categories: dates,
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
            points: [{
                x: '1 Apr',
                y: 810,
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
                    text: 'Puncak Tertinggi: 1 April - Rilis Kebijakan SDM'
                }
            }]
        },
        legend: { show: false }
    };
    var chartTrend = new ApexCharts(document.querySelector("#trendChart"), optionsTrend);
    chartTrend.render();

    // Donut Chart - Distribusi Akses Berdasarkan Unit
    var optionsDonut = {
        series: [35, 25, 20, 15, 5],
        labels: ['SDM (35%)', 'Finance (25%)', 'Operations (20%)', 'Legal (15%)', 'Lainnya (5%)'],
        chart: {
            type: 'donut',
            height: 300,
            parentHeightOffset: 0
        },
        colors: isDark ? ['#388bfd', '#58a6ff', '#34d399', '#a7f3d0', '#f97316'] : ['#163b65', '#1d748f', '#279a78', '#7bb04a', '#ed8d35'],
        dataLabels: { enabled: false },
        legend: {
            position: 'right',
            offsetY: 20, // Center it vertically
            itemMargin: { vertical: 8 },
            fontSize: '13px',
            fontWeight: 500,
            labels: { colors: isDark ? '#c9d1d9' : '#334155' }
        },
        plotOptions: {
            pie: { donut: { size: '65%' } }
        },
        stroke: { show: true, colors: [isDark ? '#0b0e11' : 'transparent'], width: 2 } // Cleaner look
    };
    var chartDonut = new ApexCharts(document.querySelector("#donutChart"), optionsDonut);
    chartDonut.render();

    // Small pie chart 1
    var optionsPie1 = {
        series: [45, 55],
        chart: { type: 'donut', width: 110, height: 110, sparkline: { enabled: true } },
        colors: isDark ? ['#388bfd', '#21262d'] : ['#2a5b7c', '#e2e8f0'],
        tooltip: { enabled: false },
        plotOptions: { pie: { donut: { size: '75%' } } },
        stroke: { width: 0 }
    };
    var chartPie1 = new ApexCharts(document.querySelector("#chart-pie-1"), optionsPie1);
    chartPie1.render();

    // Small pie chart 2
    var optionsPie2 = {
        series: [15, 85],
        chart: { type: 'donut', width: 110, height: 110, sparkline: { enabled: true } },
        colors: isDark ? ['#58a6ff', '#21262d'] : ['#1d748f', '#e2e8f0'],
        tooltip: { enabled: false },
        plotOptions: { pie: { donut: { size: '75%' } } },
        stroke: { width: 0 }
    };
    var chartPie2 = new ApexCharts(document.querySelector("#chart-pie-2"), optionsPie2);
    chartPie2.render();
}

$(function () {
    if (typeof ApexCharts === 'undefined') {
        loadCachedScript('https://cdn.jsdelivr.net/npm/apexcharts', function() {
            // ApexCharts loaded
            initDashboardCharts();
        });
    } else {
        initDashboardCharts();
    }
});
</script>
@stop