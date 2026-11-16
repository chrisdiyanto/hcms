@extends('layouts.app')

@section('content')
<div class="page-titles d-flex justify-content-between align-items-center">
	<h4 class="mb-0"> {{ $pageTitle }} <small>{{ $pageNote }}</small></h4>
	<a href="{{ url('competency/my-assessments') }}" class="btn btn-sm btn-outline-secondary">Kembali</a>
</div>

@include('core.tenants.partials.flash')

@if($readOnly)
	<div class="alert alert-secondary">Asesmen sudah {{ $assessment->status === 'closed' ? 'ditutup' : 'dibatalkan' }}; penilaian tidak dapat diubah.</div>
@elseif($rater->status === 'submitted')
	<div class="alert alert-info">Anda sudah mengirim penilaian ini. Selama asesmen masih berjalan, Anda masih dapat memperbaikinya.</div>
@endif
<p class="small text-muted">Pilih level yang paling sesuai dengan <strong>perilaku yang benar-benar teramati</strong>, dan tuliskan contoh singkat sebagai bukti. Penilaian rekan dan bawahan bersifat anonim.</p>

<form method="POST" action="{{ url('competency/my-assessments/'.$rater->id) }}">
	@csrf @method('PUT')
	@foreach($competencies as $c)
		@php($cid = $c->competency_id)
		@php($compLevels = $levels->get($cid, collect())->keyBy('level'))
		<div class="card" x-data="{ score: '{{ old('scores.'.$cid.'.score', optional($existing->get($cid))->score) }}' }"><div class="card-body">
			<div class="d-flex justify-content-between flex-wrap gap-2">
				<h6 class="mb-1"><code>{{ $c->code }}</code> {{ $c->name }} <span class="badge bg-light text-dark">{{ $c->category }}</span></h6>
				<span class="small text-muted">Level yang diharapkan posisi: {{ $c->required_level }}</span>
			</div>
			<div class="d-flex flex-wrap gap-1 my-2" role="radiogroup" aria-label="Skor {{ $c->code }}">
				@foreach($levelNames as $lvl => [$name])
					<input type="radio" class="btn-check" name="scores[{{ $cid }}][score]" id="s-{{ $cid }}-{{ $lvl }}" value="{{ $lvl }}" x-model="score" @disabled($readOnly) required>
					<label class="btn btn-outline-primary" for="s-{{ $cid }}-{{ $lvl }}">{{ $lvl }} · {{ $compLevels->get($lvl)->title ?? $name }}</label>
				@endforeach
			</div>
			@error('scores.'.$cid.'.score')<div class="text-danger small">{{ $message }}</div>@enderror
			@foreach($compLevels as $lvl => $l)
				<div class="small bg-light rounded p-2 mb-2" x-show="score == '{{ $lvl }}'" x-cloak>
					{{ $l->description }}
					@if($indicators->has($l->id))<ul class="mb-0 mt-1">@foreach($indicators->get($l->id) as $ind)<li>{{ $ind->description }}</li>@endforeach</ul>@endif
				</div>
			@endforeach
			<textarea name="scores[{{ $cid }}][evidence]" rows="2" maxlength="2000" class="form-control form-control-sm" placeholder="Bukti / contoh perilaku (opsional)" @disabled($readOnly)>{{ old('scores.'.$cid.'.evidence', optional($existing->get($cid))->evidence) }}</textarea>
		</div></div>
	@endforeach
	@unless($readOnly)
		<button class="btn btn-primary" type="submit">Kirim penilaian</button>
	@endunless
</form>
@endsection
