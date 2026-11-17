@extends('layouts.app')

@section('content')
@php($lbadge = ['high' => 'bg-success', 'medium' => 'bg-warning text-dark', 'low' => 'bg-secondary'])
<div class="page-titles d-flex flex-wrap justify-content-between align-items-center gap-2">
	<h4 class="mb-0"> {{ $pageTitle }} <small>{{ $pageNote }}</small></h4>
	@if($canAdd && $instrument)<a href="{{ url('talent/potential-assessments/create') }}" class="btn btn-sm btn-primary"><i class="fa fa-plus"></i> Asesmen baru</a>@endif
</div>

@include('core.tenants.partials.flash')
@if($errors->any())<div class="alert alert-danger small"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif
<div class="alert alert-warning small"><i class="fa fa-user-secret"></i> Hasil potensi bersifat rahasia dan terpisah dari rating kinerja maupun kompetensi. Setiap akses ke halaman ini tercatat di audit.</div>
@unless($instrument)<div class="alert alert-info small">Belum ada instrumen potensi yang berlaku. Terbitkan di <a href="{{ url('talent/potential-instruments') }}">Instrumen Potensi</a>.</div>@endunless

<form method="GET" class="card"><div class="card-body d-flex flex-wrap gap-2">
	<select name="level" class="form-select form-select-sm" style="max-width: 180px" aria-label="Tingkat"><option value="">Semua tingkat</option>@foreach($levels as $k => $l)<option value="{{ $k }}" @selected(request('level') === $k)>{{ $l }}</option>@endforeach</select>
	<select name="state" class="form-select form-select-sm" style="max-width: 200px" aria-label="Keadaan"><option value="">Berlaku & kedaluwarsa</option><option value="expired" @selected(request('state') === 'expired')>Kedaluwarsa saja</option><option value="all" @selected(request('state') === 'all')>Termasuk dibatalkan</option></select>
	<input name="q" value="{{ request('q') }}" class="form-control form-control-sm" style="max-width: 220px" placeholder="Cari pegawai" aria-label="Cari pegawai">
	<button class="btn btn-sm btn-outline-primary">Terapkan</button>
</div></form>

<div class="card"><div class="card-body">
	<div class="table-responsive"><table class="table table-sm align-middle mb-0">
		<thead><tr><th>Pegawai</th><th class="text-end">Skor</th><th>Tingkat</th><th>Keyakinan</th><th>Asesor</th><th>Tanggal / berlaku</th><th></th></tr></thead>
		<tbody>
		@forelse($rows as $r)
			<tr class="{{ $r->status === 'cancelled' ? 'text-muted' : '' }}">
				<td>{{ $r->full_name }}<div class="small text-muted">{{ $r->employee_number }} · instrumen v{{ $r->instrument_version }}</div></td>
				<td class="text-end">{{ number_format($r->score, 2, ',', '.') }}</td>
				<td><span class="badge {{ $lbadge[$r->level] }}">{{ $levels[$r->level] }}</span>@unless($r->complete) <span class="text-muted small">*</span>@endunless</td>
				<td class="small">{{ $levels[$r->confidence] }}</td>
				<td class="small">{{ trim($r->assessor_first.' '.$r->assessor_last) }}</td>
				<td class="small">{{ $r->assessed_at }}<div>s.d. {{ $r->valid_until }}@if($r->valid_until < $today) <span class="badge bg-secondary">kedaluwarsa</span>@endif @if($r->status === 'cancelled') <span class="badge bg-light text-dark">dibatalkan</span>@endif</div></td>
				<td class="text-end">@if($canRemove && $r->status === 'final')
					<details><summary class="small text-danger">Batalkan</summary>
						<form method="POST" action="{{ url('talent/potential-assessments/'.$r->id.'/cancel') }}" class="d-flex gap-1 mt-1">@csrf
							<input name="reason" class="form-control form-control-sm" required maxlength="500" placeholder="Alasan" aria-label="Alasan pembatalan"><button class="btn btn-sm btn-outline-danger">OK</button></form></details>@endif</td>
			</tr>
		@empty
			<tr><td colspan="7" class="text-muted small">Belum ada asesmen potensi.</td></tr>
		@endforelse
		</tbody>
	</table></div>
	<div class="mt-2">{{ $rows->links() }}</div>
</div></div>
@endsection
