@extends('layouts.app')

@section('content')
<div class="page-titles d-flex justify-content-between align-items-center">
	<h4 class="mb-0"> {{ $pageTitle }} <small>{{ $pageNote }}</small></h4>
	@if(!empty($access['is_add']))<a href="{{ url('competency/assessments/create') }}" class="btn btn-sm btn-primary"><i class="fa fa-plus"></i> Buka asesmen</a>@endif
</div>

@include('core.tenants.partials.flash')

<ul class="nav nav-tabs mb-3">
	@foreach($statuses + ['all' => 'Semua'] as $key => $label)
		<li class="nav-item"><a class="nav-link {{ $status === $key ? 'active' : '' }}" href="{{ url('competency/assessments?status='.$key) }}">{{ $label }}</a></li>
	@endforeach
</ul>

<div class="card"><div class="card-body">
	<div class="table-responsive"><table class="table table-sm align-middle mb-0">
		<thead><tr><th>Pegawai</th><th>Posisi</th><th>Asesmen</th><th>Batas waktu</th><th class="text-center">Penilai</th><th>Status</th><th class="text-end">JFI</th><th></th></tr></thead>
		<tbody>
		@forelse($rows as $r)
			@php($c = $counts->get($r->id))
			<tr>
				<td>{{ $r->full_name }}<div class="small text-muted">{{ $r->employee_number }}</div></td>
				<td class="small">{{ $r->position_title }}</td>
				<td class="small">{{ $r->title }}</td>
				<td class="small">{{ $r->due_date ?: '—' }}@if($r->status === 'open' && $r->due_date && $r->due_date < now()->toDateString()) <span class="badge bg-danger">lewat</span>@endif</td>
				<td class="text-center small">{{ (int) ($c->done ?? 0) }}/{{ (int) ($c->total ?? 0) }}</td>
				<td><span class="badge {{ ['open' => 'bg-info', 'closed' => 'bg-success', 'cancelled' => 'bg-secondary'][$r->status] }}">{{ $statuses[$r->status] }}</span></td>
				<td class="text-end small">@if($r->band !== null){{ \App\Domain\Competency\Services\JobFitCalculator::format($r->jfi) }}<div class="text-muted">{{ $bands[$r->band] ?? $r->band }}</div>@else — @endif</td>
				<td class="text-end"><a href="{{ url('competency/assessments/'.$r->id) }}" class="btn btn-sm btn-outline-primary">Detail</a></td>
			</tr>
		@empty
			<tr><td colspan="8" class="text-muted">Tidak ada asesmen.</td></tr>
		@endforelse
		</tbody>
	</table></div>
	<div class="mt-2">{{ $rows->links() }}</div>
</div></div>
@endsection
