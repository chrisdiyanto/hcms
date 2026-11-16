@extends('layouts.app')

@section('content')
<div class="page-titles d-flex justify-content-between align-items-center flex-wrap gap-2">
	<h4 class="mb-0"> {{ $pageTitle }} <small>{{ $pageNote }}</small></h4>
	<a href="{{ url('performance/periods/'.$period->id) }}" class="btn btn-sm btn-outline-secondary">Kembali ke periode</a>
</div>

@include('core.tenants.partials.flash')

<div class="row">
	<div class="col-lg-7">
		<div class="card"><div class="card-body">
			<h5 class="card-title">Sesi kalibrasi</h5>
			<table class="table table-sm align-middle mb-0">
				<thead><tr><th>Sesi</th><th>Cakupan</th><th>Status</th><th></th></tr></thead>
				<tbody>
				@forelse($sessions as $s)
					<tr><td>{{ $s->name }}</td><td class="small">{{ $s->unit_name ?: 'Semua unit dalam cakupan data' }}</td>
						<td><span class="badge {{ $s->status === 'open' ? 'bg-info' : 'bg-secondary' }}">{{ $s->status === 'open' ? 'terbuka' : 'ditutup' }}</span></td>
						<td class="text-end"><a href="{{ url('performance/calibration/sessions/'.$s->id) }}" class="btn btn-sm btn-outline-primary">Buka papan</a></td></tr>
				@empty
					<tr><td colspan="4" class="text-muted">Belum ada sesi.</td></tr>
				@endforelse
				</tbody>
			</table>
		</div></div>
	</div>
	@if($canEdit)
		<div class="col-lg-5">
			<div class="card"><div class="card-body">
				<h5 class="card-title">Sesi baru</h5>
				<form method="POST" action="{{ url('performance/periods/'.$period->id.'/calibration') }}">
					@csrf
					<div class="mb-2"><label class="form-label" for="c-name">Nama sesi</label>
						<input id="c-name" name="name" required maxlength="150" value="{{ old('name', 'Kalibrasi '.$period->code) }}" class="form-control form-control-sm"></div>
					<div class="mb-2"><label class="form-label" for="c-unit">Unit (beserta turunannya)</label>
						<select id="c-unit" name="unit_id" class="form-select form-select-sm select2"><option value="">Semua unit</option>
							@foreach($units as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach</select></div>
					<div class="mb-3"><label class="form-label" for="c-notes">Catatan / peserta rapat</label>
						<textarea id="c-notes" name="notes" rows="2" class="form-control form-control-sm"></textarea></div>
					<button class="btn btn-sm btn-primary" type="submit">Buat sesi</button>
				</form>
			</div></div>
		</div>
	@endif
</div>
@endsection
