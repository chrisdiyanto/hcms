@extends('layouts.app')

@section('content')
<div class="page-titles"><h4 class="mb-0"> {{ $pageTitle }} <small>{{ $pageNote }}</small></h4></div>

@include('core.tenants.partials.flash')

@forelse($ratings as $r)
	@php($appeal = $appeals->get($r->id))
	<div class="card {{ $r->is_current ? '' : 'opacity-75' }}"><div class="card-body">
		<div class="d-flex justify-content-between flex-wrap gap-2">
			<div>
				<h5 class="card-title mb-1">{{ $r->period_name }}</h5>
				<div class="small text-muted">Revisi {{ $r->revision }}{{ $r->is_current ? '' : ' (digantikan)' }} ·
					@if($r->status === 'final')<span class="badge bg-success">final</span> disahkan {{ $r->finalized_at }}@else<span class="badge bg-warning text-dark">sementara</span> diumumkan {{ $r->published_at }}@endif</div>
				@if($r->reason)<div class="small">{{ $r->reason }}</div>@endif
			</div>
			<div class="text-end">
				<div class="h3 mb-0">{{ $r->rated ? number_format($r->final_score, 2, ',', '.') : '—' }}</div>
				<span class="badge bg-primary">{{ $r->band_label }}</span>
			</div>
		</div>
		@if($appeal)
			<div class="alert alert-light border small mt-2 mb-0"><strong>Keberatan:</strong> {{ $appealStatus[$appeal->status] }}
				@if($appeal->decision_note)<div>Keputusan: {{ $appeal->decision_note }}</div>@endif</div>
		@elseif($r->is_current && $r->rated && $r->status === 'provisional' && now()->toDateString() <= $r->appeal_deadline && !$ratings->where('period_id', $r->period_id)->pluck('id')->intersect($appeals->keys())->count())
			<details class="mt-2">
				<summary class="small">Ajukan keberatan (s.d. {{ $r->appeal_deadline }})</summary>
				<form method="POST" action="{{ url('performance/ratings/'.$r->id.'/appeal') }}" class="mt-2">
					@csrf
					<textarea name="reason" required minlength="30" maxlength="3000" rows="3" class="form-control form-control-sm mb-2"
						placeholder="Uraikan alasan dan bukti pendukung (minimal 30 karakter)" aria-label="Alasan keberatan"></textarea>
					<button class="btn btn-sm btn-outline-danger" type="submit" onclick="return confirm('Ajukan keberatan? Keberatan hanya dapat diajukan sekali per periode.')">Ajukan keberatan</button>
				</form>
			</details>
		@endif
	</div></div>
@empty
	<div class="alert alert-info">Belum ada rating kinerja yang disahkan untuk Anda.</div>
@endforelse

@if($pips->isNotEmpty())
	<div class="card"><div class="card-body">
		<h5 class="card-title">Rencana perbaikan kinerja (PIP)</h5>
		@foreach($pips as $p)
			<div class="border rounded p-2 mb-2 small">
				<div class="d-flex justify-content-between"><strong>{{ $p->start_date }} – {{ $p->end_date }}</strong><span class="badge bg-light text-dark">{{ $p->status }}</span></div>
				<div class="mt-1"><span class="text-muted">Area perbaikan:</span> {!! nl2br(e($p->objectives)) !!}</div>
				@if($p->support)<div><span class="text-muted">Dukungan:</span> {!! nl2br(e($p->support)) !!}</div>@endif
				@if($p->outcome_note)<div><span class="text-muted">Hasil:</span> {{ $p->outcome_note }}</div>@endif
			</div>
		@endforeach
	</div></div>
@endif
@endsection
