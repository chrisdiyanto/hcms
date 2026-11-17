@extends('layouts.app')

@section('content')
@php($badge = ['planned' => 'bg-secondary', 'ongoing' => 'bg-info', 'completed' => 'bg-success', 'cancelled' => 'bg-light text-dark'])
<div class="page-titles d-flex flex-wrap justify-content-between align-items-center gap-2">
	<h4 class="mb-0"> {{ $pageTitle }} <small>{{ $pageNote }}</small></h4>
	<div class="d-flex gap-2">
		<form method="GET" class="d-flex gap-2">
			<input type="number" name="year" value="{{ $year }}" class="form-control form-control-sm" style="width: 90px" aria-label="Tahun">
			<select name="status" class="form-select form-select-sm" onchange="this.form.submit()" aria-label="Status"><option value="">Semua status</option>
				@foreach($statuses as $k => $l)<option value="{{ $k }}" @selected(request('status') === $k)>{{ $l }}</option>@endforeach</select>
		</form>
		<a href="{{ url('development/effectiveness?year='.$year) }}" class="btn btn-sm btn-outline-secondary text-nowrap"><i class="fa fa-chart-line"></i> Efektivitas</a>
		@if($canAdd)<a href="{{ url('development/sessions/create') }}" class="btn btn-sm btn-primary text-nowrap"><i class="fa fa-plus"></i> Kelas baru</a>@endif
	</div>
</div>

@include('core.tenants.partials.flash')

<div class="card"><div class="card-body">
	<div class="table-responsive"><table class="table table-sm align-middle mb-0">
		<thead><tr><th>Kelas</th><th>Jenis</th><th>Tanggal</th><th class="text-end">Peserta</th><th class="text-end">Lulus</th><th>Status</th><th></th></tr></thead>
		<tbody>
		@forelse($sessions as $s)
			<tr><td><code>{{ $s->code }}</code> {{ $s->title }}<div class="small text-muted">{{ $s->intervention_name }}@if($s->trainer) · {{ $s->trainer }}@endif</div></td>
				<td class="small">{{ $types[$s->type] ?? $s->type }}</td>
				<td class="small">{{ $s->start_date }}@if($s->end_date !== $s->start_date) s.d. {{ $s->end_date }}@endif</td>
				<td class="text-end">{{ $s->enrolled }}@if($s->capacity)<span class="text-muted">/{{ $s->capacity }}</span>@endif</td>
				<td class="text-end">{{ $s->passed }}</td>
				<td><span class="badge {{ $badge[$s->status] }}">{{ $statuses[$s->status] }}</span></td>
				<td class="text-end"><a href="{{ url('development/sessions/'.$s->id) }}" class="btn btn-sm btn-outline-primary">Buka</a></td></tr>
		@empty
			<tr><td colspan="7" class="text-muted small">Belum ada kelas pada tahun ini.</td></tr>
		@endforelse
		</tbody>
	</table></div>
	<div class="mt-2">{{ $sessions->links() }}</div>
</div></div>
@endsection
