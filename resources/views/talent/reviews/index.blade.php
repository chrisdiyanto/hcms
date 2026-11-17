@extends('layouts.app')

@section('content')
@php($badge = ['draft' => 'bg-secondary', 'calibration' => 'bg-info', 'approved' => 'bg-success'])
<div class="page-titles"><h4 class="mb-0"> {{ $pageTitle }} <small>{{ $pageNote }}</small></h4></div>

@include('core.tenants.partials.flash')
@if($errors->any())<div class="alert alert-danger small"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif

<div class="row">
	<div class="col-lg-7">
		<div class="card"><div class="card-body">
			<h5 class="card-title">Siklus review</h5>
			<div class="table-responsive"><table class="table table-sm align-middle mb-0">
				<thead><tr><th>Review</th><th>Periode kinerja</th><th>Cakupan</th><th class="text-end">Lengkap / peserta</th><th>Status</th><th></th></tr></thead>
				<tbody>
				@forelse($reviews as $r)
					<tr><td>{{ $r->name }}<div class="small text-muted">{{ $r->review_date }}</div></td><td class="small">{{ $r->period_name }}</td>
						<td class="small">{{ $r->unit_name ?? 'Seluruh cakupan' }}</td><td class="text-end">{{ $r->complete }} / {{ $r->total }}</td>
						<td><span class="badge {{ $badge[$r->status] }}">{{ $statuses[$r->status] }}</span></td>
						<td class="text-end"><a href="{{ url('talent/reviews/'.$r->id) }}" class="btn btn-sm btn-outline-primary">Buka</a></td></tr>
				@empty
					<tr><td colspan="6" class="text-muted small">Belum ada talent review.</td></tr>
				@endforelse
				</tbody>
			</table></div>
		</div></div>
	</div>
	@if($canAdd)
		<div class="col-lg-5">
			<form method="POST" action="{{ url('talent/reviews') }}" class="card"><div class="card-body">
				@csrf
				<h5 class="card-title">Review baru</h5>
				<p class="small text-muted">Peserta = pegawai aktif dalam cakupan data Anda (atau unit terpilih beserta turunannya). Sumbu kinerja memakai rating final periode terpilih; sumbu potensi memakai asesmen potensi yang berlaku pada tanggal review.</p>
				<div class="mb-2"><label class="form-label" for="name">Nama</label><input id="name" name="name" class="form-control" required maxlength="150" value="{{ old('name') }}" placeholder="mis. Talent Review 2026 — Divisi Risiko"></div>
				<div class="row g-2 mb-2">
					<div class="col-6"><label class="form-label" for="review_date">Tanggal review</label><input id="review_date" type="date" name="review_date" class="form-control" required value="{{ old('review_date', now()->toDateString()) }}"></div>
					<div class="col-6"><label class="form-label" for="performance_period_id">Periode kinerja</label>
						<select id="performance_period_id" name="performance_period_id" class="form-select" required>@foreach($periods as $p)<option value="{{ $p->id }}" @selected(old('performance_period_id') == $p->id)>{{ $p->name }}</option>@endforeach</select></div>
				</div>
				@if($periods->isEmpty())<div class="small text-danger mb-2">Belum ada periode kinerja yang ditutup (rating final).</div>@endif
				<div class="mb-2"><label class="form-label" for="unit_id">Unit (opsional)</label>
					<select id="unit_id" name="unit_id" class="form-select"><option value="">Seluruh cakupan data saya</option>@foreach($units as $k => $l)<option value="{{ $k }}" @selected(old('unit_id') == $k)>{{ $l }}</option>@endforeach</select></div>
				<div class="mb-2"><label class="form-label" for="panel_user_ids">Panel (minimal 2)</label>
					<select id="panel_user_ids" name="panel_user_ids[]" class="form-select" multiple size="5" required>
						@foreach($panelists as $u)<option value="{{ $u->id }}" @selected(in_array($u->id, (array) old('panel_user_ids', [])))>{{ trim($u->first_name.' '.$u->last_name) }}</option>@endforeach</select></div>
				<div class="mb-2"><label class="form-label" for="notes">Catatan</label><textarea id="notes" name="notes" class="form-control" rows="2" maxlength="2000">{{ old('notes') }}</textarea></div>
				<button class="btn btn-primary" @disabled($periods->isEmpty())><i class="fa fa-plus"></i> Buat & isi peserta</button>
			</div></form>
		</div>
	@endif
</div>
@endsection
