@extends('layouts.app')

@section('content')
@php
	$badge = ['now' => 'bg-success', '1_2' => 'bg-warning text-dark', '3_plus' => 'bg-danger'];
	$query = fn (array $extra) => url('competency/readiness?'.http_build_query(array_filter(array_merge(['position' => $target->id ?? null, 'unit' => $unitId, 'horizon' => $horizon], $extra), fn ($v) => $v !== null && $v !== '')));
@endphp
<div class="page-titles d-flex justify-content-between align-items-center flex-wrap gap-2">
	<h4 class="mb-0"> {{ $pageTitle }} <small>{{ $pageNote }}</small></h4>
	<form method="GET" class="d-flex flex-wrap gap-2">
		<select name="position" class="form-select form-select-sm" style="max-width: 340px" onchange="this.form.submit()" aria-label="Posisi sasaran">
			<option value="">— Pilih posisi sasaran —</option>
			@foreach($positions as $p)
				<option value="{{ $p->id }}" @selected($target && $target->id === $p->id)>{{ $p->is_critical ? '★ ' : '' }}{{ $p->code }} — {{ $p->title }}</option>
			@endforeach
		</select>
		<select name="unit" class="form-select form-select-sm" style="max-width: 220px" onchange="this.form.submit()" aria-label="Unit kandidat">
			<option value="">Kandidat semua unit</option>
			@foreach($units as $v => $l)<option value="{{ $v }}" @selected($unitId == $v)>{{ $l }}</option>@endforeach
		</select>
	</form>
</div>

<div class="alert alert-info small"><i class="fa fa-info-circle" aria-hidden="true"></i>
	Kesiapan adalah bahan pertimbangan berdasarkan kompetensi yang masih berlaku, <strong>bukan</strong> rekomendasi atau keputusan penempatan.
	Horizon: siap sekarang = JFI ≥ 90% tanpa gap kritis; siap 1–2 tahun = JFI 75–89% atau JFI ≥ 90% dengan 1 gap kritis; selain itu siap ≥ 3 tahun.
	Kandidat yang skor berlakunya mencakup &lt; 80% bobot profil tidak diberi horizon. Akses daftar ini tercatat di audit.</div>

@if(!$target)
	<div class="card"><div class="card-body text-muted">Pilih posisi sasaran untuk melihat kandidat internal. Posisi kritis ditandai ★.</div></div>
@else
<div class="row">
	<div class="col-lg-4">
		<div class="card"><div class="card-body">
			<h5 class="card-title">{{ $target->title }} <code class="small">{{ $target->code }}</code></h5>
			@if($target->is_critical)<p class="mb-2"><span class="badge bg-danger">Posisi kritis</span> <span class="small text-muted">dampak {{ $impacts[$target->critical_impact] ?? 'belum diisi' }}</span></p>@endif
			@if($profile->isEmpty())
				<p class="text-muted small mb-0">Posisi ini belum memiliki profil kompetensi. Lengkapi profil job/posisi di menu Profil Kompetensi Job.</p>
			@else
				<table class="table table-sm small mb-0">
					<thead><tr><th>Kompetensi wajib</th><th class="text-center">Level</th><th class="text-center">Bobot</th></tr></thead>
					<tbody>
					@foreach($profile as $c)
						<tr><td><code>{{ $c->code }}</code> {{ $c->name }}</td><td class="text-center">{{ $c->required_level }}</td>
							<td class="text-center">{{ $c->criticality }}@if($c->criticality == 3) <span class="badge bg-danger">kritis</span>@endif</td></tr>
					@endforeach
					</tbody>
				</table>
			@endif
		</div></div>
		<div class="card"><div class="card-body">
			<h5 class="card-title">Ringkasan kandidat</h5>
			<div class="d-flex flex-wrap gap-2">
				<a href="{{ $query(['horizon' => null, 'page' => null]) }}" class="badge text-decoration-none {{ $horizon ? 'bg-light text-dark' : 'bg-primary' }}">Semua: {{ $total }}</a>
				@foreach($horizons as $k => $l)
					<a href="{{ $query(['horizon' => $k, 'page' => null]) }}" class="badge text-decoration-none {{ $horizon === $k ? 'bg-primary' : $badge[$k] }}">{{ $l }}: {{ $counts[$k] }}</a>
				@endforeach
				@if($counts['none'])<span class="badge bg-secondary">Data belum cukup: {{ $counts['none'] }}</span>@endif
			</div>
		</div></div>
	</div>
	<div class="col-lg-8">
		<div class="card"><div class="card-body">
			<h5 class="card-title">Kandidat{{ $horizon ? ' — '.$horizons[$horizon] : '' }}</h5>
			<div class="table-responsive"><table class="table table-sm align-middle mb-0">
				<thead><tr><th>Pegawai</th><th>Posisi saat ini</th><th class="text-end">JFI</th><th>Horizon</th><th class="text-center">Terpenuhi</th><th>Gap kritis</th><th>Asesmen</th><th></th></tr></thead>
				<tbody>
				@forelse($candidates as $c)
					<tr>
						<td>{{ $c->full_name }}<div class="small text-muted">{{ $c->employee_number }}</div></td>
						<td class="small">{{ $c->position_title ?? '—' }}@if($c->incumbent) <span class="badge bg-info">pemangku</span>@endif<div class="text-muted">{{ $c->unit_name }}</div></td>
						<td class="text-end">{{ \App\Domain\Competency\Services\JobFitCalculator::format($c->jfi) }}
							@if($c->jfi !== null && !$c->complete)<span class="text-muted" title="ada kompetensi tanpa skor berlaku (cakupan {{ round($c->coverage) }}%)">*</span>@endif</td>
						<td>@if($c->horizon)<span class="badge {{ $badge[$c->horizon] }}">{{ $horizons[$c->horizon] }}</span>@else<span class="badge bg-secondary" title="cakupan {{ round($c->coverage) }}% bobot">Data belum cukup</span>@endif</td>
						<td class="text-center small">{{ $c->met }}/{{ $c->required_count }}</td>
						<td class="small">@forelse($c->critical_gaps as $code)<span class="badge bg-danger">{{ $code }}</span> @empty — @endforelse</td>
						<td class="small">{{ $c->last_assessed ?? '—' }}@if($c->expired)<div><span class="badge bg-secondary" title="{{ implode(', ', $c->expired) }}">{{ count($c->expired) }} kedaluwarsa</span></div>@endif</td>
						<td class="text-end"><a href="{{ url('competency/reports/employees/'.$c->employee_id) }}" class="btn btn-sm btn-outline-primary">Profil</a></td>
					</tr>
				@empty
					<tr><td colspan="8" class="text-muted small">{{ $profile->isEmpty() ? 'Profil kompetensi posisi belum tersedia.' : 'Belum ada kandidat dalam cakupan data Anda yang memiliki skor kompetensi berlaku untuk posisi ini.' }}</td></tr>
				@endforelse
				</tbody>
			</table></div>
			<div class="mt-2">{{ $candidates->links() }}</div>
		</div></div>
	</div>
</div>
@endif
@endsection
