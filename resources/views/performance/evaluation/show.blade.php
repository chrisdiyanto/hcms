@extends('layouts.app')

@php
	$fmt = fn ($v, $d = 2) => $v === null ? '—' : rtrim(rtrim(number_format((float) $v, $d, ',', '.'), '0'), ',');
	$editing = $canSelf ? 'self' : ($canManager ? 'manager' : null);
	$mine = $editing === 'self' ? $self : $manager;
	$get = fn ($rows, $key, $field = 'score') => optional($rows->get($key))->{$field};
@endphp

@section('content')
<div class="page-titles d-flex justify-content-between align-items-center flex-wrap gap-2">
	<h4 class="mb-0"> {{ $pageTitle }} <small>{{ $pageNote }}</small></h4>
	<a href="{{ url('performance/assessments') }}" class="btn btn-sm btn-outline-secondary">Kembali</a>
</div>

@include('core.tenants.partials.flash')

<div class="d-flex flex-wrap gap-2 mb-3 small">
	@foreach($statuses as $k => $l)
		<span class="badge {{ $assessment->status === $k ? 'bg-primary' : (array_search($k, array_keys($statuses)) < array_search($assessment->status, array_keys($statuses)) ? 'bg-success' : 'bg-light text-dark') }}">{{ $loop->iteration }}. {{ $l }}</span>
	@endforeach
</div>
@if($assessment->status === 'manager' && $assessment->review_note && $role !== 'owner')
	<div class="alert alert-warning"><strong>Dikembalikan atasan dari atasan:</strong> {{ $assessment->review_note }}</div>
@endif

<form method="POST" action="{{ url('performance/assessments/'.$plan->id.'/'.($editing ?? 'self')) }}">
	@csrf
	<fieldset @disabled(!$editing)>
	<div class="row">
		<div class="col-lg-8">
			<div class="card"><div class="card-body">
				<h5 class="card-title">Kinerja (KPI) — bobot {{ $fmt($weights['what']) }}%</h5>
				<p class="small text-muted">Capaian diambil dari realisasi terakhir di tahap monitoring. KPI tanpa realisasi dihitung 0.</p>
				<div class="table-responsive"><table class="table table-sm align-middle">
					<thead><tr><th>KPI</th><th class="text-end">Bobot</th><th class="text-end">Capaian</th><th>Komentar pegawai</th>@if($showManager)<th>Komentar atasan</th>@endif</tr></thead>
					<tbody>
					@foreach($progress['items'] as $o)
						<tr>
							<td>{{ $o->title }}<div class="small text-muted">{{ $types[$o->kpi_type] }}</div></td>
							<td class="text-end">{{ $fmt($o->weight) }}%</td>
							<td class="text-end fw-bold">{{ $o->achievement !== null ? $fmt($o->achievement).'%' : '0%' }}</td>
							<td>@if($editing === 'self')<textarea name="kpi_comment[{{ $o->id }}]" rows="1" maxlength="1000" class="form-control form-control-sm" aria-label="Komentar KPI">{{ old('kpi_comment.'.$o->id, $get($self, 'kpi:'.$o->id, 'comment')) }}</textarea>
								@else<span class="small">{{ $get($self, 'kpi:'.$o->id, 'comment') }}</span>@endif</td>
							@if($showManager)
								<td>@if($editing === 'manager')<textarea name="kpi_comment[{{ $o->id }}]" rows="1" maxlength="1000" class="form-control form-control-sm" aria-label="Komentar atasan">{{ old('kpi_comment.'.$o->id, $get($manager, 'kpi:'.$o->id, 'comment')) }}</textarea>
									@else<span class="small">{{ $get($manager, 'kpi:'.$o->id, 'comment') }}</span>@endif</td>
							@endif
						</tr>
					@endforeach
					</tbody>
				</table></div>
				<div class="small">Skor Kinerja (What): <strong>{{ $fmt($assessment->what_score ?? $progress['what'] ?? 0) }}</strong></div>
			</div></div>

			<div class="card"><div class="card-body">
				<h5 class="card-title">Perilaku (How) — bobot {{ $fmt($weights['how']) }}%</h5>
				<p class="small text-muted">Skor 1–5 berdasarkan perilaku yang teramati selama periode. Skor pegawai bersifat informatif; yang dihitung adalah skor atasan.</p>
				<table class="table table-sm align-middle">
					<thead><tr><th>Perilaku</th><th class="text-center" style="width:90px">Pegawai</th>@if($showManager)<th class="text-center" style="width:90px">Atasan</th>@endif<th>Komentar / contoh perilaku</th></tr></thead>
					<tbody>
					@foreach($howItems as $h)
						@php($key = 'how:'.$h['code'])
						<tr>
							<td><strong>{{ $h['label'] }}</strong><div class="small text-muted">{{ $h['description'] }}</div></td>
							<td class="text-center">@if($editing === 'self')
								<select name="how[{{ $h['code'] }}]" class="form-select form-select-sm @error('how.'.$h['code']) is-invalid @enderror" aria-label="Skor {{ $h['label'] }}"><option value=""></option>
									@for($i = 1; $i <= 5; $i++)<option value="{{ $i }}" @selected(old('how.'.$h['code'], $get($self, $key)) == $i)>{{ $i }}</option>@endfor</select>
								@else{{ $get($self, $key) ?? '—' }}@endif</td>
							@if($showManager)
								<td class="text-center">@if($editing === 'manager')
									<select name="how[{{ $h['code'] }}]" class="form-select form-select-sm @error('how.'.$h['code']) is-invalid @enderror" aria-label="Skor atasan {{ $h['label'] }}"><option value=""></option>
										@for($i = 1; $i <= 5; $i++)<option value="{{ $i }}" @selected(old('how.'.$h['code'], $get($manager, $key)) == $i)>{{ $i }}</option>@endfor</select>
									@else<strong>{{ $get($manager, $key) ?? '—' }}</strong>@endif</td>
							@endif
							<td>@if($editing)<input name="how_comment[{{ $h['code'] }}]" maxlength="1000" value="{{ old('how_comment.'.$h['code'], $get($mine, $key, 'comment')) }}" class="form-control form-control-sm" aria-label="Komentar {{ $h['label'] }}">
								@else<span class="small">{{ $get($self, $key, 'comment') }}@if($showManager && $get($manager, $key, 'comment'))<br><span class="text-muted">Atasan:</span> {{ $get($manager, $key, 'comment') }}@endif</span>@endif</td>
						</tr>
					@endforeach
					</tbody>
				</table>
			</div></div>

			<div class="card"><div class="card-body">
				<h5 class="card-title">Kontribusi tambahan — bobot {{ $fmt($weights['contribution']) }}%</h5>
				<p class="small text-muted">Inisiatif, proyek lintas unit, dan tugas di luar KPI selama periode.</p>
				<div class="row g-2">
					<div class="col-md-5">
						@foreach($rubric as $lvl => $desc)<div class="small"><strong>{{ $lvl }}</strong> — {{ $desc }}</div>@endforeach
					</div>
					<div class="col-md-7">
						<div class="d-flex gap-3 small mb-1">
							<span>Pegawai: <strong>{{ $get($self, 'contribution:CONTRIB') ?? '—' }}</strong></span>
							@if($showManager)<span>Atasan: <strong>{{ $get($manager, 'contribution:CONTRIB') ?? '—' }}</strong></span>@endif
						</div>
						@if($editing)
							<select name="contribution" class="form-select form-select-sm mb-1 @error('contribution') is-invalid @enderror" aria-label="Skor kontribusi"><option value="">Pilih skor</option>
								@foreach($rubric as $lvl => $desc)<option value="{{ $lvl }}" @selected(old('contribution', $get($mine, 'contribution:CONTRIB')) == $lvl)>{{ $lvl }} — {{ \Illuminate\Support\Str::limit($desc, 60) }}</option>@endforeach</select>
							<textarea name="contribution_comment" rows="2" maxlength="2000" class="form-control form-control-sm" placeholder="Sebutkan inisiatif/proyeknya" aria-label="Komentar kontribusi">{{ old('contribution_comment', $get($mine, 'contribution:CONTRIB', 'comment')) }}</textarea>
						@else
							<div class="small">{{ $get($self, 'contribution:CONTRIB', 'comment') }}</div>
							@if($showManager && $get($manager, 'contribution:CONTRIB', 'comment'))<div class="small"><span class="text-muted">Atasan:</span> {{ $get($manager, 'contribution:CONTRIB', 'comment') }}</div>@endif
						@endif
					</div>
				</div>
			</div></div>
		</div>

		<div class="col-lg-4">
			@if($showManager && $assessment->final_score !== null)
				<div class="card"><div class="card-body text-center">
					<div class="small text-muted">Skor akhir{{ $assessment->calibrated_score !== null ? ' (setelah kalibrasi)' : '' }}</div>
					<div class="display-6 fw-bold">{{ $fmt($effective['score']) }}</div>
					<div><span class="badge bg-primary">{{ $bands[$effective['band']] ?? $effective['band'] }}</span></div>
					<table class="table table-sm small mt-2 mb-0 text-start">
						@foreach($dimensions as $k => $l)
							<tr><td>{{ $l }}</td><td class="text-end">{{ $fmt($assessment->{$k === 'what' ? 'what_score' : ($k === 'how' ? 'how_score' : 'contribution_score')}) }}</td><td class="text-end text-muted">× {{ $fmt($weights[$k]) }}%</td></tr>
						@endforeach
						@if((float) $assessment->final_raw > (float) $assessment->final_score)<tr><td colspan="3" class="text-muted">Nilai mentah {{ $fmt($assessment->final_raw) }} (dibatasi 100 untuk rating)</td></tr>@endif
						@if($assessment->calibrated_score !== null)<tr><td colspan="3" class="text-muted">Sebelum kalibrasi: {{ $fmt($assessment->final_score) }} · {{ $bands[$assessment->band] ?? $assessment->band }}</td></tr>@endif
					</table>
				</div></div>
			@endif

			<div class="card"><div class="card-body">
				<h5 class="card-title">Ringkasan</h5>
				<label class="form-label small" for="s-self">Penilaian diri</label>
				@if($editing === 'self')
					<textarea id="s-self" name="summary" rows="4" maxlength="3000" class="form-control form-control-sm" placeholder="Capaian utama, kendala, rencana pengembangan">{{ old('summary', $assessment->self_summary) }}</textarea>
				@else<p class="small">{{ $assessment->self_summary ?: '—' }}</p>@endif
				@if($showManager)
					<label class="form-label small mt-2" for="s-mgr">Penilaian atasan</label>
					@if($editing === 'manager')
						<textarea id="s-mgr" name="summary" rows="4" maxlength="3000" class="form-control form-control-sm @error('summary') is-invalid @enderror" placeholder="Kekuatan, area pengembangan, rekomendasi">{{ old('summary', $assessment->manager_summary) }}</textarea>
					@else<p class="small">{{ $assessment->manager_summary ?: '—' }}</p>@endif
				@endif
				@if($assessment->status === 'completed' && $assessment->review_note)<p class="small text-muted mb-0">Review: {{ $assessment->review_note }}</p>@endif

				@if($editing)
					<div class="d-flex gap-2 mt-2">
						<button class="btn btn-sm btn-secondary" type="submit" name="action" value="draft">Simpan draft</button>
						<button class="btn btn-sm btn-primary" type="submit" name="action" value="submit"
							onclick="return confirm('{{ $editing === 'self' ? 'Kirim penilaian diri ke atasan?' : 'Kirim penilaian? Skor akhir akan dihitung.' }}')">{{ $editing === 'self' ? 'Kirim ke atasan' : 'Kirim penilaian' }}</button>
					</div>
				@endif
			</div></div>
		</div>
	</div>
	</fieldset>
</form>

@if($canReview)
	<div class="card"><div class="card-body">
		<h5 class="card-title">Review atasan dari atasan</h5>
		<form method="POST" action="{{ url('performance/assessments/'.$plan->id.'/review') }}">
			@csrf
			<textarea name="review_note" rows="2" maxlength="2000" class="form-control form-control-sm mb-2" placeholder="Catatan (wajib bila dikembalikan)" aria-label="Catatan review"></textarea>
			<button class="btn btn-sm btn-success" type="submit" name="decision" value="agree">Setujui penilaian</button>
			<button class="btn btn-sm btn-outline-danger" type="submit" name="decision" value="return">Kembalikan ke atasan</button>
		</form>
	</div></div>
@endif
@endsection
