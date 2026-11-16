@extends('layouts.app')

@section('content')
<div class="page-titles"><h4 class="mb-0"> {{ $pageTitle }} <small>{{ $pageNote }}</small></h4></div>

@include('core.tenants.partials.flash')

<div class="row">
	<div class="col-lg-7">
		<div class="card"><div class="card-body">
			<h5 class="card-title">Penilaian untuk Anda isi</h5>
			<table class="table table-sm align-middle mb-0">
				<thead><tr><th>Pegawai dinilai</th><th>Sebagai</th><th>Batas waktu</th><th></th></tr></thead>
				<tbody>
				@forelse($tasks as $t)
					<tr class="{{ $t->status === 'submitted' ? 'text-muted' : '' }}">
						<td>{{ $t->full_name }}<div class="small text-muted">{{ $t->title }}</div></td>
						<td class="small">{{ $raterTypes[$t->rater_type] }}</td>
						<td class="small">{{ $t->due_date ?: '—' }}@if($t->status === 'pending' && $t->due_date && $t->due_date < now()->toDateString()) <span class="badge bg-danger">lewat</span>@endif</td>
						<td class="text-end">
							@if($t->status === 'pending' && $t->assessment_status === 'open')
								<a href="{{ url('competency/my-assessments/'.$t->id) }}" class="btn btn-sm btn-primary">Nilai</a>
							@else
								<a href="{{ url('competency/my-assessments/'.$t->id) }}" class="btn btn-sm btn-link">terkirim {{ \Illuminate\Support\Str::of($t->submitted_at)->before(' ') }}</a>
							@endif
						</td>
					</tr>
				@empty
					<tr><td colspan="4" class="text-muted">Tidak ada penilaian yang perlu diisi.</td></tr>
				@endforelse
				</tbody>
			</table>
		</div></div>
	</div>
	<div class="col-lg-5">
		<div class="card"><div class="card-body">
			<h5 class="card-title">Profil kompetensi saya</h5>
			@if(!$employee)
				<p class="text-muted small mb-0">Akun Anda belum ditautkan ke data pegawai.</p>
			@elseif(!$fit)
				<p class="text-muted small mb-0">Anda belum memiliki penugasan posisi aktif.</p>
			@else
				<p class="small mb-2">Posisi: <strong>{{ $position->title }}</strong></p>
				@if($scores->isEmpty())
					<p class="text-muted small mb-0">Belum ada hasil asesmen yang berlaku.</p>
				@else
					<div class="d-flex align-items-baseline gap-2 mb-2">
						<span class="h3 mb-0">{{ number_format($fit['jfi'], 1, ',', '.') }}%</span>
						<span class="small">Job Fit Index · {{ $bands[$fit['band']] }}</span>
					</div>
					<table class="table table-sm small mb-0">
						<thead><tr><th>Kompetensi</th><th class="text-center">Wajib</th><th class="text-center">Aktual</th></tr></thead>
						<tbody>
						@foreach($fit['items'] as $i)
							<tr><td><code>{{ $i['code'] }}</code> {{ $i['name'] }}</td><td class="text-center">{{ $i['required'] }}</td>
								<td class="text-center {{ $i['actual'] === null ? 'text-muted' : ($i['gap'] < 0 ? 'text-danger' : 'text-success') }}">{{ $i['actual'] === null ? 'belum dinilai' : number_format($i['actual'], 2, ',', '.') }}</td></tr>
						@endforeach
						</tbody>
					</table>
					<div class="small text-muted mt-1">Hasil berlaku 24 bulan sejak asesmen ditutup.</div>
				@endif
			@endif
		</div></div>
	</div>
</div>
@endsection
