@extends('layouts.app')

@section('content')
<div class="page-titles"><h4 class="mb-0"> {{ $pageTitle }} <small>{{ $pageNote }}</small></h4></div>

@include('core.tenants.partials.flash')

@forelse($appeals as $a)
	<div class="card"><div class="card-body" x-data="{ adjust: false }">
		<div class="d-flex justify-content-between flex-wrap gap-2">
			<div><strong>{{ $a->full_name }}</strong> · {{ $a->period_name }}
				<div class="small text-muted">Rating saat diajukan: {{ number_format($a->final_score, 2, ',', '.') }} ({{ $a->band_label }}) · diajukan {{ $a->created_at }}</div></div>
			<span class="badge {{ $a->status === 'submitted' ? 'bg-warning text-dark' : 'bg-secondary' }}">{{ $statuses[$a->status] }}</span>
		</div>
		<p class="small mt-2 mb-1"><span class="text-muted">Alasan:</span> {{ $a->reason }}</p>
		@if($a->decision_note)<p class="small mb-0"><span class="text-muted">Keputusan:</span> {{ $a->decision_note }}@if($a->new_score) — skor baru {{ number_format($a->new_score, 2, ',', '.') }}@endif</p>@endif
		@if($a->can_decide)
			<form method="POST" action="{{ url('performance/appeals/'.$a->id.'/decide') }}" class="mt-2">
				@csrf
				<div class="d-flex flex-wrap gap-3 small mb-1">
					<label><input type="radio" name="decision" value="upheld" checked @change="adjust = false"> Tolak (rating tetap)</label>
					<label><input type="radio" name="decision" value="adjusted" @change="adjust = true"> Kabulkan (ubah skor)</label>
				</div>
				<input type="number" step="0.01" min="0" max="100" name="new_score" x-show="adjust" x-cloak class="form-control form-control-sm mb-1" style="max-width:160px" placeholder="Skor baru" aria-label="Skor baru">
				<textarea name="decision_note" required minlength="10" maxlength="2000" rows="2" class="form-control form-control-sm mb-1" placeholder="Alasan keputusan" aria-label="Alasan keputusan"></textarea>
				<button class="btn btn-sm btn-primary" type="submit">Simpan keputusan</button>
			</form>
		@endif
	</div></div>
@empty
	<div class="alert alert-info">Tidak ada keberatan untuk Anda.</div>
@endforelse
@endsection
