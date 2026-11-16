@extends('layouts.app')

@section('content')
<div class="page-titles"><h4 class="mb-0"> {{ $pageTitle }} <small>{{ $pageNote }}</small></h4></div>

@include('core.tenants.partials.flash')

<div class="card"><div class="card-body">
	<form method="GET" action="{{ url('core/audit') }}" class="row g-2 mb-3">
		<div class="col-md-2"><input type="date" name="from" value="{{ $filters['from'] }}" class="form-control form-control-sm" title="Dari"></div>
		<div class="col-md-2"><input type="date" name="to" value="{{ $filters['to'] }}" class="form-control form-control-sm" title="Sampai"></div>
		<div class="col-md-2"><select name="event" class="form-select form-select-sm"><option value="">Semua peristiwa</option>
			@foreach($events as $v => $l)<option value="{{ $v }}" @selected(($filters['event'] ?? '') === $v)>{{ $l }}</option>@endforeach</select></div>
		<div class="col-md-2"><select name="type" class="form-select form-select-sm"><option value="">Semua objek</option>
			@foreach($types as $t)<option value="{{ $t }}" @selected(($filters['type'] ?? '') === $t)>{{ $t }}</option>@endforeach</select></div>
		<div class="col-md-2"><select name="user" class="form-select form-select-sm"><option value="">Semua pengguna</option>
			@foreach($users as $id => $name)<option value="{{ $id }}" @selected((string) ($filters['user'] ?? '') === (string) $id)>{{ $name }}</option>@endforeach</select></div>
		<div class="col-md-1"><input name="q" value="{{ $filters['q'] ?? '' }}" class="form-control form-control-sm" placeholder="Cari"></div>
		<div class="col-md-1"><button class="btn btn-sm btn-outline-secondary w-100" type="submit">Saring</button></div>
	</form>

	<div class="table-responsive">
		<table class="table table-sm align-middle mb-0">
			<thead><tr><th>Waktu</th><th>Pengguna</th><th>Peristiwa</th><th>Objek</th><th>Keterangan</th><th>IP</th><th></th></tr></thead>
			<tbody>
			@forelse($logs as $log)
				@php($old = json_decode((string) $log->old_values, true) ?: [])
				@php($new = json_decode((string) $log->new_values, true) ?: [])
				<tbody x-data="{ open: false }">
				<tr>
					<td class="small text-nowrap">{{ \Illuminate\Support\Str::limit($log->created_at, 19, '') }}</td>
					<td>{{ $log->username ?? 'sistem' }}@if($log->break_glass_id) <span class="badge bg-danger">break-glass</span>@endif</td>
					<td><span class="badge bg-{{ ['created' => 'success', 'updated' => 'primary', 'deleted' => 'danger', 'sensitive_viewed' => 'warning', 'exported' => 'info', 'security' => 'dark'][$log->event] ?? 'secondary' }}">{{ $events[$log->event] ?? $log->event }}</span></td>
					<td class="small"><code>{{ $log->auditable_type }}</code> #{{ $log->auditable_id }}</td>
					<td class="small">{{ $log->description }}</td>
					<td class="small text-muted">{{ $log->ip_address }}</td>
					<td class="text-end">@if($old || $new)<button type="button" class="btn btn-sm btn-link p-0" @click="open = !open" x-text="open ? 'Tutup' : 'Rincian'">Rincian</button>@endif</td>
				</tr>
				@if($old || $new)
				<tr x-show="open" x-cloak><td colspan="7" class="bg-light">
					<table class="table table-sm mb-0 small">
						<thead><tr><th>Kolom</th><th>Sebelum</th><th>Sesudah</th></tr></thead>
						@foreach(array_unique(array_merge(array_keys($old), array_keys($new))) as $key)
							<tr><td><code>{{ $key }}</code></td>
								<td class="text-muted">{{ is_scalar($old[$key] ?? null) ? ($old[$key] ?? '–') : json_encode($old[$key] ?? null) }}</td>
								<td>{{ is_scalar($new[$key] ?? null) ? ($new[$key] ?? '–') : json_encode($new[$key] ?? null) }}</td></tr>
						@endforeach
					</table>
				</td></tr>
				@endif
				</tbody>
			@empty
				<tr><td colspan="7" class="text-muted">Tidak ada catatan pada rentang ini.</td></tr>
			@endforelse
			</tbody>
		</table>
	</div>
	<div class="mt-3">{{ $logs->links('pagination::bootstrap-5') }}</div>
</div></div>

<div class="card"><div class="card-body">
	<h5 class="card-title">Ekspor data terakhir</h5>
	<table class="table table-sm mb-0">
		<thead><tr><th>#</th><th>Waktu</th><th>Pengguna</th><th>Modul</th><th>Format</th><th class="text-end">Baris</th><th>Alasan</th></tr></thead>
		@forelse($exports as $x)
			<tr><td>{{ $x->id }}</td><td class="small">{{ $x->created_at }}</td><td>{{ $x->username }}</td><td>{{ $x->module }}</td><td>{{ $x->format }}</td>
				<td class="text-end">{{ number_format($x->row_count) }}@if($x->approved_mass_export) <span class="badge bg-warning text-dark">massal</span>@endif</td><td class="small">{{ $x->reason }}</td></tr>
		@empty
			<tr><td colspan="7" class="text-muted">Belum ada ekspor.</td></tr>
		@endforelse
	</table>
</div></div>
@endsection
