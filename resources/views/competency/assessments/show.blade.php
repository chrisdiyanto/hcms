@extends('layouts.app')

@section('content')
<div class="page-titles d-flex justify-content-between align-items-center">
	<h4 class="mb-0"> {{ $pageTitle }} <small>{{ $pageNote }}</small></h4>
	<a href="{{ url('competency/assessments') }}" class="btn btn-sm btn-outline-secondary">Kembali</a>
</div>

@include('core.tenants.partials.flash')

<div class="row">
	<div class="col-lg-4">
		<div class="card"><div class="card-body">
			<h5 class="card-title">{{ $employee->full_name }}</h5>
			<dl class="row small mb-0">
				<dt class="col-5">NIP</dt><dd class="col-7">{{ $employee->employee_number }}</dd>
				<dt class="col-5">Posisi dinilai</dt><dd class="col-7">{{ $position->title }} <code>{{ $position->code }}</code></dd>
				<dt class="col-5">Batas waktu</dt><dd class="col-7">{{ $assessment->due_date ?: '—' }}</dd>
				@if($assessment->status === 'closed')
					<dt class="col-5">Ditutup</dt><dd class="col-7">{{ $assessment->closed_at }}</dd>
					<dt class="col-5">Berlaku s.d.</dt><dd class="col-7">{{ $assessment->valid_until }}</dd>
				@endif
			</dl>
			@if($fit)
				<hr>
				<div class="text-center">
					<div class="display-6 fw-bold">{{ \App\Domain\Competency\Services\JobFitCalculator::format($fit->jfi) }}</div>
					<div>Job Fit Index — <span class="badge {{ ['ready_now' => 'bg-success', 'ready_1_2' => 'bg-warning text-dark', 'ready_later' => 'bg-danger', 'insufficient' => 'bg-secondary'][$fit->band] ?? 'bg-secondary' }}">{{ $bands[$fit->band] ?? $fit->band }}</span></div>
					@if($fit->jfi === null)<div class="small text-muted mt-1">Skor berlaku baru mencakup {{ number_format((float) $fit->coverage, 0, ',', '.') }}% bobot profil (minimal {{ (int) \App\Domain\Competency\Services\JobFitCalculator::MIN_COVERAGE }}%); JFI belum dihitung.</div>
					@elseif(!$fit->complete)<div class="small text-muted mt-1">Belum lengkap: kompetensi tanpa skor berlaku dihitung 0 (cakupan {{ number_format((float) $fit->coverage, 0, ',', '.') }}% bobot).</div>@endif
				</div>
			@endif
		</div></div>

		<div class="card"><div class="card-body">
			<h5 class="card-title">Penilai</h5>
			<ul class="list-unstyled small mb-0">
				@foreach($raters as $r)
					<li class="d-flex justify-content-between border-bottom py-1">
						<span>{{ trim($r->first_name.' '.$r->last_name) }} <span class="text-muted">· {{ $raterTypes[$r->rater_type] }}</span></span>
						<span class="badge {{ $r->status === 'submitted' ? 'bg-success' : 'bg-light text-dark' }}">{{ $r->status === 'submitted' ? 'terkirim' : 'menunggu' }}</span>
					</li>
				@endforeach
			</ul>
			<div class="small text-muted mt-2">Bobot: {{ collect($weights)->filter(fn ($w) => $w > 0)->map(fn ($w, $g) => ($groupLabels[$g] ?? $g).' '.($w + 0).'%')->implode(', ') }}</div>
		</div></div>

		@if($canEdit)
			<div class="card"><div class="card-body">
				<h5 class="card-title">Tindakan</h5>
				<form method="POST" action="{{ url('competency/assessments/'.$assessment->id.'/close') }}" class="mb-3"
					onsubmit="return confirm('Tutup asesmen? Skor akan dihitung dan penilai yang belum mengirim tidak lagi dapat menilai.')">
					@csrf
					<button class="btn btn-sm btn-success w-100" type="submit">Tutup &amp; hitung hasil</button>
				</form>
				<form method="POST" action="{{ url('competency/assessments/'.$assessment->id.'/cancel') }}">
					@csrf
					<div class="input-group input-group-sm">
						<input name="reason" required maxlength="255" class="form-control" placeholder="Alasan pembatalan">
						<button class="btn btn-outline-danger" type="submit">Batalkan</button>
					</div>
				</form>
			</div></div>
		@endif
	</div>

	<div class="col-lg-8">
		<div class="card"><div class="card-body">
			<h5 class="card-title">{{ $assessment->status === 'closed' ? 'Hasil per kompetensi' : 'Rekap sementara' }}</h5>
			<p class="small text-muted">Rata-rata per kelompok penilai; nilai individual rekan &amp; bawahan tidak ditampilkan. Kelompok rekan/bawahan dengan kurang dari 3 penilai tidak dihitung.</p>
			<div class="table-responsive"><table class="table table-sm align-middle mb-0">
				<thead><tr><th>Kompetensi</th><th class="text-center">Wajib</th><th class="text-center">Bobot</th>
					@foreach(['manager', 'peer', 'subordinate', 'self'] as $g)<th class="text-center">{{ $groupLabels[$g] }}</th>@endforeach
					<th class="text-center">AC</th><th class="text-center">Skor</th></tr></thead>
				<tbody>
				@foreach($competencies as $c)
					@php($a = $aggregate[$c->competency_id] ?? null)
					<tr>
						<td><code>{{ $c->code }}</code> {{ $c->name }}</td>
						<td class="text-center">{{ $c->required_level }}</td>
						<td class="text-center">{{ $c->criticality }}</td>
						@foreach(['manager', 'peer', 'subordinate', 'self'] as $g)
							@php($grp = $a['groups'][$g] ?? null)
							<td class="text-center small {{ $grp && !$grp['counted'] ? 'text-muted text-decoration-line-through' : '' }}"
								@if($grp) title="{{ $grp['raters'] }} penilai{{ $grp['counted'] ? '' : ' — tidak dihitung' }}" @endif>
								{{ $grp ? number_format($grp['mean'], 1, ',', '.') : '—' }}</td>
						@endforeach
						<td class="text-center small">{{ optional($centerScores->get($c->competency_id))->score ?? '—' }}</td>
						<td class="text-center fw-bold">
							@if($a && $a['score'] !== null)
								<span class="{{ $a['score'] < $c->required_level ? 'text-danger' : 'text-success' }}">{{ number_format($a['score'], 2, ',', '.') }}</span>
							@else — @endif</td>
					</tr>
				@endforeach
				</tbody>
			</table></div>
		</div></div>

		@if($assessment->status === 'closed')
			<div class="card"><div class="card-body">
				<h5 class="card-title">Gap terhadap posisi</h5>
				<table class="table table-sm mb-0">
					<thead><tr><th>Kompetensi</th><th class="text-center">Wajib</th><th class="text-center">Aktual</th><th class="text-center">Gap</th></tr></thead>
					<tbody>
					@foreach($gaps as $g)
						<tr><td><code>{{ $g->competency_code }}</code> {{ $g->name }} @if($g->criticality == 3)<span class="badge bg-danger">kritis</span>@endif</td>
							<td class="text-center">{{ $g->required_level }}</td>
							<td class="text-center">{{ $g->actual !== null ? number_format($g->actual, 2, ',', '.') : '—' }}</td>
							<td class="text-center fw-bold {{ $g->gap === null ? 'text-muted' : ($g->gap < 0 ? 'text-danger' : 'text-success') }}">@if($g->gap === null)<span class="fw-normal small">belum dinilai</span>@else{{ ($g->gap > 0 ? '+' : '').number_format($g->gap, 2, ',', '.') }}@if(\App\Domain\Competency\Services\JobFitCalculator::isCriticalGap((int) $g->criticality, (float) $g->gap)) <span class="badge bg-danger">gap kritis</span>@endif @endif</td></tr>
					@endforeach
					</tbody>
				</table>
				@if($idp->isNotEmpty())
					<h6 class="mt-3">Draf rencana pengembangan (IDP)</h6>
					<ul class="small mb-0">@foreach($idp as $i)<li><code>{{ $i->competency_code }}</code> {{ $i->name }}: level {{ $i->current_level }} → {{ $i->target_level }} <span class="badge bg-light text-dark">{{ $i->status }}</span></li>@endforeach</ul>
				@endif
			</div></div>
		@endif

		@if($canEdit)
			<div class="card"><div class="card-body">
				<h5 class="card-title">Hasil assessment center</h5>
				<p class="small text-muted">Bila diisi, skor assessment center menggantikan skor gabungan penilai untuk kompetensi tersebut.</p>
				<form method="POST" action="{{ url('competency/assessments/'.$assessment->id.'/assessment-center') }}">
					@csrf
					<table class="table table-sm align-middle">
						<thead><tr><th>Kompetensi</th><th style="width:110px">Skor</th><th>Catatan / bukti</th></tr></thead>
						<tbody>
						@foreach($competencies as $c)
							@php($cs = $centerScores->get($c->competency_id))
							<tr><td class="small"><code>{{ $c->code }}</code> {{ $c->name }}</td>
								<td><select name="scores[{{ $c->competency_id }}][score]" required class="form-select form-select-sm @error('scores.'.$c->competency_id.'.score') is-invalid @enderror">
									<option value=""></option>@for($i = 1; $i <= 5; $i++)<option value="{{ $i }}" @selected(old('scores.'.$c->competency_id.'.score', $cs->score ?? null) == $i)>{{ $i }}</option>@endfor</select></td>
								<td><input name="scores[{{ $c->competency_id }}][evidence]" maxlength="2000" value="{{ old('scores.'.$c->competency_id.'.evidence', $cs->evidence ?? '') }}" class="form-control form-control-sm"></td></tr>
						@endforeach
						</tbody>
					</table>
					<button class="btn btn-sm btn-primary" type="submit">Simpan hasil assessment center</button>
				</form>
			</div></div>
		@endif
	</div>
</div>
@endsection
