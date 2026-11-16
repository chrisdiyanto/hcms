@extends('layouts.app')

@section('content')
<div class="page-titles"><h4 class="mb-0"> {{ $pageTitle }} <small>{{ $pageNote }}</small></h4></div>

@include('core.tenants.partials.flash')

<div class="row">
	<div class="col-lg-8">
		<div class="card"><div class="card-body">
			<table class="table table-sm align-middle mb-0">
				<thead><tr><th>Kode</th><th>Periode</th><th>Rentang</th><th>Kebijakan</th><th>Status</th><th></th></tr></thead>
				<tbody>
				@forelse($periods as $p)
					@php($snap = $p->policy_snapshot ? json_decode($p->policy_snapshot, true) : null)
					<tr>
						<td><code>{{ $p->code }}</code></td>
						<td>{{ $p->name }}</td>
						<td class="small">{{ $p->start_date }} – {{ $p->end_date }}</td>
						<td class="small">{{ $snap ? 'v'.$snap['_version'].' (terkunci)' : '—' }}</td>
						<td><span class="badge {{ $p->status === 'draft' ? 'bg-secondary' : ($p->status === 'closed' ? 'bg-dark' : 'bg-info') }}">{{ $statuses[$p->status] }}</span></td>
						<td class="text-end"><a href="{{ url('performance/periods/'.$p->id) }}" class="btn btn-sm btn-outline-primary">Detail</a></td>
					</tr>
				@empty
					<tr><td colspan="6" class="text-muted">Belum ada periode.</td></tr>
				@endforelse
				</tbody>
			</table>
		</div></div>
	</div>
	@if($canAdd)
		<div class="col-lg-4">
			<div class="card"><div class="card-body">
				<h5 class="card-title">Periode baru</h5>
				<form method="POST" action="{{ url('performance/periods') }}">
					@csrf
					<div class="mb-2"><label class="form-label" for="f-code">Kode</label>
						<input id="f-code" name="code" required maxlength="30" value="{{ old('code', 'KIN-'.now()->year) }}" class="form-control form-control-sm"></div>
					<div class="mb-2"><label class="form-label" for="f-name">Nama</label>
						<input id="f-name" name="name" required maxlength="150" value="{{ old('name', 'Penilaian Kinerja '.now()->year) }}" class="form-control form-control-sm"></div>
					<div class="row g-2 mb-2">
						<div class="col"><label class="form-label" for="f-start">Mulai</label><input id="f-start" type="date" name="start_date" required value="{{ old('start_date', now()->startOfYear()->toDateString()) }}" class="form-control form-control-sm"></div>
						<div class="col"><label class="form-label" for="f-end">Selesai</label><input id="f-end" type="date" name="end_date" required value="{{ old('end_date', now()->endOfYear()->toDateString()) }}" class="form-control form-control-sm"></div>
					</div>
					<div class="mb-3"><label class="form-label" for="f-deadline">Batas penyusunan rencana</label>
						<input id="f-deadline" type="date" name="planning_deadline" value="{{ old('planning_deadline') }}" class="form-control form-control-sm"></div>
					<button class="btn btn-sm btn-primary" type="submit">Buat periode</button>
				</form>
			</div></div>
		</div>
	@endif
</div>
@endsection
