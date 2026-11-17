{{-- Judul, pemilih bulan, dan navigasi dashboard SDM. Parameter: $pageTitle, $subtitle, $active, $month, $showMonth --}}
<div class="page-titles d-flex justify-content-between align-items-center flex-wrap gap-2">
    <div><h4 class="mb-0">{{ $pageTitle }}</h4><small class="text-muted">{{ $subtitle }}</small></div>
    @if($showMonth ?? true)
        <form method="GET" class="d-flex align-items-center gap-2">
            <label for="report-month" class="small text-muted mb-0">Periode</label>
            <input id="report-month" class="form-control form-control-sm" type="month" name="month" value="{{ $month }}" onchange="this.form.submit()" aria-label="Pilih bulan laporan">
        </form>
    @endif
</div>

<nav class="nav nav-pills flex-wrap gap-2 mb-4" aria-label="Dashboard SDM">
    @foreach(['dashboard' => 'Ringkasan', 'turnover' => 'Turnover & retensi', 'promotions' => 'Mobilitas', 'deib' => 'DEIB', 'workforce' => 'Demografi', 'development' => 'Pengembangan'] as $key => $label)
        <a class="nav-link {{ $active === $key ? 'active' : '' }}"
            href="{{ url($key === 'dashboard' ? 'executive/dashboard' : 'executive/analytics/'.$key) }}{{ $key !== 'development' ? '?month='.$month : '' }}"
            @if($active === $key) aria-current="page" @endif>{{ $label }}</a>
    @endforeach
    <a class="nav-link {{ $active === 'performance' ? 'active' : '' }}" href="{{ url('executive/analytic/performance') }}" @if($active === 'performance') aria-current="page" @endif>Kinerja</a>
</nav>
