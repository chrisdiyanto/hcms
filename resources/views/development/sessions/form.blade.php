@extends('layouts.app')

@section('content')
<div class="page-titles d-flex justify-content-between align-items-center">
	<h4 class="mb-0"> {{ $pageTitle }}</h4>
	<a href="{{ url('development/sessions') }}" class="btn btn-sm btn-outline-secondary">Kembali</a>
</div>
@if($errors->any())<div class="alert alert-danger small"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif

@if($interventions->isEmpty())
	<div class="card"><div class="card-body text-muted">Belum ada intervensi aktif di katalog. Tambahkan di <a href="{{ url('development/interventions') }}">Katalog Intervensi</a> lebih dulu.</div></div>
@else
<form method="POST" action="{{ url('development/sessions') }}" class="card"><div class="card-body">
	@csrf
	<div class="row g-3">
		<div class="col-md-6"><label class="form-label" for="intervention_id">Intervensi dari katalog</label>
			<select id="intervention_id" name="intervention_id" class="form-select" required>
				@foreach($interventions as $i)<option value="{{ $i->id }}" @selected(old('intervention_id') == $i->id)>{{ $i->code }} — {{ $i->name }}</option>@endforeach</select></div>
		<div class="col-md-2"><label class="form-label" for="code">Kode kelas</label>
			<input id="code" name="code" class="form-control" required maxlength="40" value="{{ old('code') }}" placeholder="mis. RISK-2026-01"></div>
		<div class="col-md-4"><label class="form-label" for="title">Judul (opsional)</label>
			<input id="title" name="title" class="form-control" maxlength="200" value="{{ old('title') }}" placeholder="default: nama intervensi"></div>
		<div class="col-md-3"><label class="form-label" for="start_date">Mulai</label><input id="start_date" type="date" name="start_date" class="form-control" required value="{{ old('start_date') }}"></div>
		<div class="col-md-3"><label class="form-label" for="end_date">Selesai</label><input id="end_date" type="date" name="end_date" class="form-control" required value="{{ old('end_date') }}"></div>
		<div class="col-md-3"><label class="form-label" for="mode">Moda</label>
			<select id="mode" name="mode" class="form-select">@foreach($modes as $k => $l)<option value="{{ $k }}" @selected(old('mode') === $k)>{{ $l }}</option>@endforeach</select></div>
		<div class="col-md-3"><label class="form-label" for="capacity">Kapasitas</label><input id="capacity" type="number" min="1" name="capacity" class="form-control" value="{{ old('capacity') }}" placeholder="default: katalog"></div>
		<div class="col-md-6"><label class="form-label" for="location">Lokasi / tautan</label><input id="location" name="location" class="form-control" maxlength="200" value="{{ old('location') }}"></div>
		<div class="col-md-6"><label class="form-label" for="trainer">Pengajar / fasilitator</label><input id="trainer" name="trainer" class="form-control" maxlength="150" value="{{ old('trainer') }}"></div>
		<div class="col-12"><div class="form-check"><input type="hidden" name="has_test" value="0"><input class="form-check-input" type="checkbox" id="has_test" name="has_test" value="1" @checked(old('has_test'))>
			<label class="form-check-label" for="has_test">Gunakan pre-test & post-test (evaluasi tingkat 2)</label></div></div>
		<div class="col-12"><label class="form-label" for="notes">Catatan</label><textarea id="notes" name="notes" class="form-control" rows="2" maxlength="2000">{{ old('notes') }}</textarea></div>
	</div>
	<button class="btn btn-primary mt-3"><i class="fa fa-save"></i> Simpan kelas</button>
</div></form>
@endif
@endsection
