@extends('layouts.app')

@section('content')
<div class="page-titles"><h4 class="mb-0"> {{ $pageTitle }} <small>{{ $pageNote }}</small></h4></div>

@include('core.tenants.partials.flash')

@foreach(['mine' => 'Penilaian saya', 'asManager' => 'Bawahan yang saya nilai', 'asReviewer' => 'Menunggu review saya (atasan dari atasan)'] as $key => $title)
	@if($key === 'mine' || $$key->isNotEmpty())
		<div class="card"><div class="card-body">
			<h5 class="card-title">{{ $title }}</h5>
			<table class="table table-sm align-middle mb-0">
				<thead><tr><th>Pegawai</th><th>Posisi</th><th>Periode</th><th>Tahap</th><th class="text-end">Skor</th><th></th></tr></thead>
				<tbody>
				@forelse($$key as $a)
					@php($todo = ($key === 'mine' && $a->status === 'self') || ($key === 'asManager' && $a->status === 'manager') || ($key === 'asReviewer' && $a->status === 'review'))
					<tr>
						<td>{{ $a->full_name }} <span class="small text-muted">{{ $a->employee_number }}</span></td>
						<td class="small">{{ $a->position_title }}</td>
						<td class="small">{{ $a->period_name }}</td>
						<td><span class="badge {{ $a->status === 'completed' ? 'bg-success' : ($todo ? 'bg-warning text-dark' : 'bg-light text-dark') }}">{{ $statuses[$a->status] }}</span></td>
						<td class="text-end small">{{ ($key !== 'mine' || $a->status === 'completed') && $a->final_score !== null ? number_format($a->calibrated_score ?? $a->final_score, 2, ',', '.') : '—' }}</td>
						<td class="text-end"><a href="{{ url('performance/assessments/'.$a->plan_id) }}" class="btn btn-sm {{ $todo ? 'btn-primary' : 'btn-outline-primary' }}">{{ $todo ? 'Isi penilaian' : 'Lihat' }}</a></td>
					</tr>
				@empty
					<tr><td colspan="6" class="text-muted">Tidak ada penilaian pada periode yang sedang dinilai.</td></tr>
				@endforelse
				</tbody>
			</table>
		</div></div>
	@endif
@endforeach
@endsection
