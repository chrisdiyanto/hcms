@extends('layouts.app')

@section('content')
<div class="page-titles d-flex justify-content-between align-items-center flex-wrap gap-2">
	<h4 class="mb-0"> {{ $pageTitle }} <small>{{ $pageNote }}</small></h4>
	@if($periods->isNotEmpty())
		<form method="GET"><select name="period" class="form-select form-select-sm" onchange="this.form.submit()" aria-label="Periode">
			@foreach($periods as $p)<option value="{{ $p->id }}" @selected($period && $period->id == $p->id)>{{ $p->name }}</option>@endforeach</select></form>
	@endif
</div>

@if(!$period)
	<div class="alert alert-info">Belum ada periode kinerja yang dibuka.</div>
@else
	<div class="row">
		@foreach([
			['Rencana disetujui', $approvedCount, ($planCounts->sum() ?: 0).' rencana dibuat'],
			['Mid-year selesai', $midyear, 'dari '.$approvedCount.' rencana'],
			['Penilaian selesai', $assessmentCounts['completed'] ?? 0, 'dari '.$assessmentCounts->sum().' penilaian'],
			['Rata-rata skor', $average !== null ? number_format($average, 2, ',', '.') : '—', $scored.' pegawai dinilai'],
		] as [$label, $value, $note])
			<div class="col-6 col-lg-3"><div class="card"><div class="card-body">
				<div class="small text-muted">{{ $label }}</div>
				<div class="h3 mb-0">{{ $value }}</div>
				<div class="small text-muted">{{ $note }}</div>
			</div></div></div>
		@endforeach
	</div>

	<div class="row">
		<div class="col-lg-6">
			<div class="card"><div class="card-body">
				<h5 class="card-title">Sebaran rating</h5>
				<div class="small text-muted mb-2">{{ $source }}</div>
				@if($scored)<div data-chart="{{ json_encode($distChart) }}" style="height:230px"></div>
				@else<p class="text-muted small mb-0">Belum ada skor.</p>@endif
			</div></div>
		</div>
		<div class="col-lg-6">
			<div class="card"><div class="card-body">
				<h5 class="card-title">Rata-rata skor per unit</h5>
				@if($unitChart)<div data-chart="{{ json_encode($unitChart) }}" style="height:{{ max(160, 34 * count($unitChart['labels']) + 40) }}px"></div>
				@else<p class="text-muted small mb-0">Belum ada skor.</p>@endif
			</div></div>
		</div>
	</div>
	@vite('resources/js/charts.js')
@endif
@endsection
