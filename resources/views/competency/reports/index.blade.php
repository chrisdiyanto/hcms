@extends('layouts.app')

@section('content')
<div class="page-titles d-flex justify-content-between align-items-center flex-wrap gap-2">
	<h4 class="mb-0"> {{ $pageTitle }} <small>{{ $pageNote }}</small></h4>
	<form method="GET" class="d-flex gap-2">
		<select name="unit" class="form-select form-select-sm" onchange="this.form.submit()" aria-label="Filter unit">
			<option value="">Semua unit</option>
			@foreach($units as $v => $l)<option value="{{ $v }}" @selected($unitId == $v)>{{ $l }}</option>@endforeach
		</select>
		@if($band)<input type="hidden" name="band" value="{{ $band }}">@endif
	</form>
</div>

<div class="row">
	<div class="col-lg-4">
		<div class="card"><div class="card-body">
			<h5 class="card-title">Kesiapan terhadap posisi saat ini</h5>
			@if($bandCounts->sum() > 0)
				<div data-chart="{{ json_encode($barChart) }}" style="height:160px"></div>
				<div class="small mt-2">
					@foreach($bands as $k => $l)
						<a href="{{ url('competency/reports?'.http_build_query(array_filter(['unit' => $unitId, 'band' => $band === $k ? null : $k]))) }}"
							class="badge text-decoration-none {{ $band === $k ? 'bg-primary' : 'bg-light text-dark' }}">{{ $l }}: {{ $bandCounts[$k] }}</a>
					@endforeach
				</div>
			@else
				<p class="text-muted small mb-0">Belum ada hasil asesmen yang ditutup.</p>
			@endif
		</div></div>

		<div class="card"><div class="card-body">
			<h5 class="card-title">Gap terbesar</h5>
			<table class="table table-sm small mb-0">
				<thead><tr><th>Kompetensi</th><th class="text-center" title="pegawai di bawah level wajib / dinilai">Kurang</th><th class="text-center">Rata gap</th></tr></thead>
				<tbody>
				@forelse($topGaps as $g)
					<tr><td><code>{{ $g->code }}</code> {{ $g->name }}@if($g->critical) <span class="badge bg-danger" title="pegawai dengan gap pada kompetensi kritis">{{ $g->critical }} kritis</span>@endif</td>
						<td class="text-center">{{ $g->below }}/{{ $g->employees }}</td>
						<td class="text-center {{ $g->avg_gap < 0 ? 'text-danger' : 'text-success' }}">{{ number_format($g->avg_gap, 2, ',', '.') }}</td></tr>
				@empty
					<tr><td colspan="3" class="text-muted">Belum ada data.</td></tr>
				@endforelse
				</tbody>
			</table>
		</div></div>
	</div>

	<div class="col-lg-8">
		<div class="card"><div class="card-body">
			<h5 class="card-title">Heatmap gap unit × kompetensi</h5>
			<p class="small text-muted">Rata-rata (aktual − wajib) per unit; negatif = di bawah standar posisi. Arahkan kursor untuk jumlah pegawai.</p>
			@if($heatmap)
				@if($heatmap['truncated'])<p class="small text-warning mb-1">Ditampilkan {{ count($heatmap['rows']) }} unit dengan rata-rata gap terburuk; pilih unit di filter untuk rincian.</p>@endif
				<div data-chart="{{ json_encode($heatmap) }}" style="height:{{ $heatmap['height'] }}px"></div>
			@else
				<p class="text-muted small mb-0">Belum ada data gap.</p>
			@endif
		</div></div>

		<div class="card"><div class="card-body">
			<h5 class="card-title">Pegawai{{ $band ? ' — '.$bands[$band] : '' }}</h5>
			<div class="table-responsive"><table class="table table-sm align-middle mb-0">
				<thead><tr><th>Pegawai</th><th>Posisi</th><th>Unit</th><th class="text-end">JFI</th><th>Kesiapan</th><th></th></tr></thead>
				<tbody>
				@forelse($fits as $f)
					<tr>
						<td>{{ $f->full_name }}<div class="small text-muted">{{ $f->employee_number }}</div></td>
						<td class="small">{{ $f->position_title }}</td>
						<td class="small">{{ $f->unit_name }}</td>
						<td class="text-end">{{ number_format($f->jfi, 1, ',', '.') }}%@unless($f->complete)<span class="text-muted" title="belum lengkap">*</span>@endunless</td>
						<td><span class="badge {{ ['ready_now' => 'bg-success', 'ready_1_2' => 'bg-warning text-dark', 'ready_later' => 'bg-danger'][$f->band] ?? 'bg-secondary' }}">{{ $bands[$f->band] ?? $f->band }}</span></td>
						<td class="text-end"><a href="{{ url('competency/reports/employees/'.$f->employee_id) }}" class="btn btn-sm btn-outline-primary">Profil</a></td>
					</tr>
				@empty
					<tr><td colspan="6" class="text-muted">Tidak ada data.</td></tr>
				@endforelse
				</tbody>
			</table></div>
			<div class="mt-2">{{ $fits->links('pagination::bootstrap-5') }}</div>
			<div class="small text-muted mt-1">* belum lengkap: ada kompetensi wajib yang belum dinilai (dihitung 0).</div>
		</div></div>
	</div>
</div>
@vite('resources/js/charts.js')
@endsection
