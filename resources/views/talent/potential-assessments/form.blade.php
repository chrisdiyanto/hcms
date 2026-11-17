@extends('layouts.app')

@section('content')
<div class="page-titles d-flex justify-content-between align-items-center">
	<h4 class="mb-0"> {{ $pageTitle }} <small>{{ $pageNote }}</small></h4>
	<a href="{{ url('talent/potential-assessments') }}" class="btn btn-sm btn-outline-secondary">Kembali</a>
</div>
@if($errors->any())<div class="alert alert-danger small"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif

@if(!$instrument)
	<div class="card"><div class="card-body text-muted">Belum ada instrumen potensi yang berlaku.</div></div>
@else
<form method="POST" action="{{ url('talent/potential-assessments') }}"
	x-data="{ scores: @js($criteria->mapWithKeys(fn ($c) => [$c->code => old('scores.'.$c->code, '')])), weights: @js($criteria->pluck('weight', 'code')),
		get total() { let s = 0, w = 0; for (const k in this.weights) { const v = parseFloat(this.scores[k]); if (!isNaN(v)) { s += v * this.weights[k]; w += this.weights[k] } } return w ? (s / w) : null },
		get level() { const t = this.total; if (t === null) return '—'; return t >= {{ (float) $instrument->threshold_high }} ? 'Tinggi' : (t >= {{ (float) $instrument->threshold_medium }} ? 'Sedang' : 'Rendah') } }">
	@csrf
	<div class="card"><div class="card-body row g-3">
		<div class="col-md-6"><label class="form-label" for="employee_id">Pegawai</label>
			<select id="employee_id" name="employee_id" class="form-select" required><option value="">— Pilih —</option>
				@foreach($employees as $e)<option value="{{ $e->id }}" @selected(old('employee_id') == $e->id)>{{ $e->full_name }} ({{ $e->employee_number }})</option>@endforeach</select></div>
		<div class="col-md-3"><label class="form-label" for="confidence">Tingkat keyakinan asesor</label>
			<select id="confidence" name="confidence" class="form-select">@foreach($levels as $k => $l)<option value="{{ $k }}" @selected(old('confidence', 'medium') === $k)>{{ $l }}</option>@endforeach</select></div>
		<div class="col-md-3"><div class="form-label">Hasil sementara</div><div class="h5 mb-0"><span x-text="total === null ? '—' : total.toFixed(2).replace('.', ',')"></span> · <span x-text="level"></span></div>
			<div class="small text-muted">Sedang ≥ {{ number_format($instrument->threshold_medium, 2, ',', '.') }}, tinggi ≥ {{ number_format($instrument->threshold_high, 2, ',', '.') }}</div></div>
	</div></div>
	@foreach($criteria as $c)
		<div class="card"><div class="card-body">
			<div class="d-flex flex-wrap justify-content-between gap-2">
				<div><strong><code>{{ $c->code }}</code> {{ $c->name }}</strong> <span class="badge bg-light text-dark">bobot {{ $c->weight }}%</span>
					<div class="small text-muted">{{ $c->description }}</div>
					<ul class="small mb-0 mt-1">@foreach(\App\Domain\Talent\PotentialInstruments::indicatorLines($c->indicators) as $line)<li>{{ $line }}</li>@endforeach</ul></div>
				<div style="min-width: 160px"><label class="form-label small" for="s{{ $c->code }}">Skor 1–{{ $instrument->scale_max }}</label>
					<input id="s{{ $c->code }}" type="number" step="0.5" min="1" max="{{ $instrument->scale_max }}" name="scores[{{ $c->code }}]" class="form-control" x-model="scores['{{ $c->code }}']" required></div>
			</div>
		</div></div>
	@endforeach
	<div class="card"><div class="card-body">
		<label class="form-label" for="evidence">Bukti perilaku & sumber penilaian</label>
		<textarea id="evidence" name="evidence" class="form-control" rows="4" required minlength="20" maxlength="4000" placeholder="Contoh perilaku yang diamati, sumber (wawancara, assessment center, observasi atasan), tanggal">{{ old('evidence') }}</textarea>
		<button class="btn btn-primary mt-3"><i class="fa fa-save"></i> Simpan asesmen</button>
	</div></div>
</form>
@endif
@endsection
