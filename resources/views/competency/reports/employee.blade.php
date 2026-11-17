@extends('layouts.app')

@section('content')
<div class="page-titles d-flex justify-content-between align-items-center">
	<h4 class="mb-0"> {{ $pageTitle }} <small>{{ $pageNote }}</small></h4>
	<a href="{{ url('competency/reports') }}" class="btn btn-sm btn-outline-secondary">Kembali</a>
</div>

<div class="row">
	<div class="col-lg-5">
		<div class="card"><div class="card-body">
			<h5 class="card-title">Kesesuaian dengan posisi</h5>
			@if(!$fit)
				<p class="text-muted small mb-0">Pegawai belum memiliki penugasan primer aktif.</p>
			@else
				<p class="small mb-1">{{ $position->title }} <code>{{ $position->code }}</code></p>
				<div class="d-flex align-items-baseline gap-2">
					<span class="h2 mb-0">{{ number_format($fit['jfi'], 1, ',', '.') }}%</span>
					<span class="badge {{ ['ready_now' => 'bg-success', 'ready_1_2' => 'bg-warning text-dark', 'ready_later' => 'bg-danger'][$fit['band']] }}">{{ $bands[$fit['band']] }}</span>
				</div>
				@unless($fit['complete'])<div class="small text-muted">Belum lengkap: ada kompetensi wajib tanpa skor yang berlaku.</div>@endunless
				@if($radar)<div data-chart="{{ json_encode($radar) }}" style="height:320px" class="mt-2"></div>@endif
			@endif
		</div></div>
		@if($idp->isNotEmpty())
			<div class="card"><div class="card-body">
				<h5 class="card-title">Draf IDP</h5>
				<ul class="small mb-0">@foreach($idp as $i)<li><code>{{ $i->competency_code }}</code> {{ $i->name }}: level {{ $i->current_level }} → {{ $i->target_level }}</li>@endforeach</ul>
			</div></div>
		@endif
	</div>
	<div class="col-lg-7">
		@if($fit)
			<div class="card"><div class="card-body">
				<h5 class="card-title">Gap per kompetensi</h5>
				<table class="table table-sm mb-0">
					<thead><tr><th>Kompetensi</th><th class="text-center">Bobot</th><th class="text-center">Wajib</th><th class="text-center">Aktual</th><th class="text-center">Gap</th></tr></thead>
					<tbody>
					@foreach($fit['items'] as $i)
						<tr><td><code>{{ $i['code'] }}</code> {{ $i['name'] }}</td><td class="text-center">{{ $i['weight'] }}</td><td class="text-center">{{ $i['required'] }}</td>
							<td class="text-center">{{ $i['actual'] === null ? '—' : number_format($i['actual'], 2, ',', '.') }}</td>
							<td class="text-center fw-bold {{ $i['gap'] < 0 ? 'text-danger' : 'text-success' }}">{{ ($i['gap'] > 0 ? '+' : '').number_format($i['gap'], 2, ',', '.') }}</td></tr>
					@endforeach
					</tbody>
				</table>
			</div></div>
		@endif
		<div class="card"><div class="card-body">
			<h5 class="card-title">Skor kompetensi tersimpan</h5>
			<table class="table table-sm small mb-0">
				<thead><tr><th>Kompetensi</th><th class="text-center">Skor</th><th class="text-center">Perubahan</th><th>Sumber</th><th>Dinilai</th><th>Berlaku s.d.</th></tr></thead>
				<tbody>
				@forelse($scores as $s)
					<tr class="{{ $s->valid_until < now()->toDateString() ? 'text-muted' : '' }}"><td><code>{{ $s->competency_code }}</code> {{ $s->name }}</td>
						<td class="text-center">{{ number_format($s->score, 2, ',', '.') }}</td>
						@php($line = $timeline[$s->competency_code] ?? null)
						<td class="text-center" title="{{ $line ? count($line['points']).' asesmen' : '' }}">@if($line && $line['previous'] !== null)@php($delta = $line['latest'] - $line['previous'])<span class="{{ $delta > 0 ? 'text-success' : ($delta < 0 ? 'text-danger' : 'text-muted') }}">{{ ($delta > 0 ? '+' : '').number_format($delta, 2, ',', '.') }}</span>@else<span class="text-muted">—</span>@endif</td>
						<td>{{ $s->source === 'assessment_center' ? 'Assessment center' : 'Multi-rater' }}</td>
						<td>{{ $s->assessed_at }}</td><td>{{ $s->valid_until }}@if($s->valid_until < now()->toDateString()) <span class="badge bg-secondary">kedaluwarsa</span>@endif</td></tr>
				@empty
					<tr><td colspan="6" class="text-muted">Belum ada.</td></tr>
				@endforelse
				</tbody>
			</table>
		</div></div>
		<div class="card"><div class="card-body">
			<h5 class="card-title">Riwayat Job Fit Index</h5>
			@if($fitTimeline->isEmpty())
				<p class="text-muted small mb-0">Belum ada perhitungan JFI yang tersimpan.</p>
			@else
				@if($fitTimeline->count() >= 2)
					<div data-chart="{{ json_encode(['type' => 'area', 'name' => 'JFI', 'suffix' => '%', 'labels' => $fitTimeline->map(fn ($f) => \Carbon\Carbon::parse($f->computed_at)->format('d/m/y'))->all(), 'values' => $fitTimeline->map(fn ($f) => (float) $f->jfi)->all()]) }}" style="height:200px" role="img" aria-label="Riwayat JFI: {{ $fitTimeline->map(fn ($f) => \Carbon\Carbon::parse($f->computed_at)->format('d/m/Y').' '.$f->jfi.'%')->implode(', ') }}"></div>
				@endif
				<table class="table table-sm small mb-0 mt-2">
					<thead><tr><th>Tanggal</th><th>Posisi</th><th class="text-end">JFI</th><th>Band</th><th>Sumber</th></tr></thead>
					<tbody>
					@foreach($fitTimeline->reverse() as $f)
						<tr><td>{{ \Carbon\Carbon::parse($f->computed_at)->format('d/m/Y') }}</td><td>{{ $f->position_title }}</td>
							<td class="text-end">{{ number_format($f->jfi, 1, ',', '.') }}%</td><td>{{ $bands[$f->band] ?? $f->band }}@unless($f->complete) <span class="badge bg-secondary">tidak lengkap</span>@endunless</td>
							<td>{{ $f->assessment_title ?? $triggers[$f->trigger] }}</td></tr>
					@endforeach
					</tbody>
				</table>
			@endif
		</div></div>
		<div class="card"><div class="card-body">
			<h5 class="card-title">Riwayat asesmen</h5>
			<ul class="small mb-0">
				@forelse($history as $h)<li>{{ $h->title }} — {{ $h->position_title }} <span class="badge bg-light text-dark">{{ $statuses[$h->status] }}</span> {{ $h->closed_at }}</li>
				@empty<li class="text-muted">Belum ada.</li>@endforelse
			</ul>
		</div></div>
	</div>
</div>
@vite('resources/js/charts.js')
@endsection
