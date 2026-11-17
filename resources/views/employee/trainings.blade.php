@extends('layouts.app')

@section('content')
@php($badge = ['registered' => 'bg-secondary', 'attended' => 'bg-info', 'completed' => 'bg-success', 'no_show' => 'bg-danger'])
<div class="page-titles"><h4 class="mb-0"> {{ $pageTitle }} <small>{{ $pageNote }}</small></h4></div>

@include('core.tenants.partials.flash')
@if($errors->any())<div class="alert alert-danger small"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif

@if(!$me)
	<div class="card"><div class="card-body text-muted">Akun Anda belum ditautkan ke data pegawai. Hubungi HR.</div></div>
@else
	@forelse($rows as $r)
		<div class="card"><div class="card-body">
			<div class="d-flex flex-wrap justify-content-between gap-2">
				<div><strong>{{ $r->title }}</strong> <span class="badge {{ $badge[$r->status] ?? 'bg-secondary' }}">{{ $statuses[$r->status] }}</span>
					<div class="small text-muted">{{ $r->code }} · {{ $r->start_date }}@if($r->end_date !== $r->start_date) s.d. {{ $r->end_date }}@endif · {{ $modes[$r->mode] }}@if($r->location) · {{ $r->location }}@endif @if($r->trainer) · {{ $r->trainer }}@endif</div>
					@if($r->certificate_no)<div class="small">Sertifikat: {{ $r->certificate_no }}</div>@endif</div>
				@if($r->reaction_at)<div class="small text-muted">Penilaian Anda: {{ number_format($r->reaction_score, 0) }}/5</div>@endif
			</div>
			@if(!$r->reaction_at && in_array($r->status, ['attended', 'completed'], true))
				<form method="POST" action="{{ url('ess/trainings/'.$r->id.'/reaction') }}" class="row g-2 align-items-end mt-2">
					@csrf
					<div class="col-md-4"><label class="form-label small" for="rs{{ $r->id }}">Seberapa bermanfaat pelatihan ini? (1–5)</label>
						<select id="rs{{ $r->id }}" name="reaction_score" class="form-select form-select-sm" required>
							<option value="">—</option>@foreach([5 => 'Sangat bermanfaat', 4 => 'Bermanfaat', 3 => 'Cukup', 2 => 'Kurang', 1 => 'Tidak bermanfaat'] as $v => $l)<option value="{{ $v }}">{{ $v }} — {{ $l }}</option>@endforeach
						</select></div>
					<div class="col-md-6"><label class="form-label small" for="rc{{ $r->id }}">Masukan (opsional)</label>
						<input id="rc{{ $r->id }}" name="reaction_comment" class="form-control form-control-sm" maxlength="1000"></div>
					<div class="col-md-2"><button class="btn btn-sm btn-primary w-100">Kirim</button></div>
				</form>
			@endif
		</div></div>
	@empty
		<div class="card"><div class="card-body text-muted">Belum ada pelatihan yang Anda ikuti.</div></div>
	@endforelse
@endif
@endsection
