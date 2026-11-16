@extends('layouts.app')

@section('content')
<div class="page-titles"><h4 class="mb-0"> {{ $pageTitle }} <small>{{ $pageNote }}</small></h4></div>

@include('core.tenants.partials.flash')

@if(!$employee)
	<div class="alert alert-info">Akun Anda belum ditautkan ke data pegawai aktif.</div>
@elseif($periods->isEmpty())
	<div class="alert alert-info">Belum ada periode kinerja yang berjalan.</div>
@else
	@foreach($periods as $period)
		<div class="card"><div class="card-body">
			<div class="d-flex justify-content-between flex-wrap gap-2">
				<h5 class="card-title mb-1">{{ $period->name }}</h5>
				<span class="small text-muted">{{ $period->start_date }} – {{ $period->end_date }} · tahap {{ mb_strtolower($periodStatuses[$period->status]) }}@if($period->planning_deadline) · batas rencana {{ $period->planning_deadline }}@endif</span>
			</div>
			<table class="table table-sm align-middle mb-0 mt-2">
				<thead><tr><th>Penugasan</th><th>Cakupan</th><th class="text-center">KPI</th><th class="text-center">Total bobot</th><th>Status</th><th></th></tr></thead>
				<tbody>
				@forelse($period->plans as $p)
					<tr>
						<td>{{ $p->position_title }} <code class="small">{{ $p->position_code }}</code></td>
						<td class="small">{{ $p->start_date }} – {{ $p->end_date }}</td>
						<td class="text-center">{{ $p->kpi_count }}</td>
						<td class="text-center {{ abs($p->weight_total - 100) < 0.01 ? 'text-success' : 'text-danger' }}">{{ $p->weight_total + 0 }}%</td>
						<td><span class="badge {{ ['approved' => 'bg-success', 'submitted' => 'bg-info', 'returned' => 'bg-warning text-dark', 'draft' => 'bg-light text-dark'][$p->status] }}">{{ $statuses[$p->status] }}</span></td>
						<td class="text-end"><a href="{{ url('performance/my-plans/'.$p->id) }}" class="btn btn-sm {{ in_array($p->status, ['draft', 'returned']) && $period->status === 'planning' ? 'btn-primary' : 'btn-outline-primary' }}">
							{{ in_array($p->status, ['draft', 'returned']) && $period->status === 'planning' ? 'Susun KPI' : 'Lihat' }}</a></td>
					</tr>
				@empty
					<tr><td colspan="6" class="text-muted">Tidak ada penugasan primer pada periode ini.</td></tr>
				@endforelse
				</tbody>
			</table>
		</div></div>
	@endforeach
@endif
@endsection
