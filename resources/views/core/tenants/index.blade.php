@extends('layouts.app')

@section('content')
<div class="page-titles">
	<h4 class="mb-0"> {{ $pageTitle }} <small> {{ $pageNote }} </small> </h4>
</div>

@include('core.tenants.partials.flash')

<div class="card">
	<div class="card-body">
		<div class="toolbar-nav">
			<div class="row g-2">
				<div class="col-md-5">
					<form method="GET" action="{{ url('core/tenants') }}" class="input-group input-group-sm">
						<input type="search" name="q" value="{{ $search }}" class="form-control" placeholder="Cari kode atau nama tenant">
						<button class="btn btn-outline-secondary" type="submit">Cari</button>
					</form>
				</div>
				<div class="col-md-7 text-end">
					<a href="{{ url('core/tenants/create') }}" class="btn btn-sm btn-info">Tenant Baru</a>
				</div>
			</div>
		</div>

		<div class="table-responsive">
			<table class="table table-striped table-hover align-middle mb-0">
				<thead>
					<tr>
						<th>Kode</th>
						<th>Nama</th>
						<th>Lisensi</th>
						<th class="text-end">Company</th>
						<th class="text-end">Pengguna</th>
						<th>Status</th>
						<th>Aktif sejak</th>
						<th class="text-end">Aksi</th>
					</tr>
				</thead>
				<tbody>
				@forelse($tenants as $tenant)
					<tr>
						<td><code>{{ $tenant->code }}</code></td>
						<td>
							{{ $tenant->name }}
							@if($tenant->legal_name)<div class="small text-muted">{{ $tenant->legal_name }}</div>@endif
						</td>
						<td class="text-capitalize">{{ $tenant->license_type }}</td>
						<td class="text-end">{{ $companies[$tenant->id] ?? 0 }}</td>
						<td class="text-end">
							{{ $users[$tenant->id] ?? 0 }}@if($tenant->max_employees) <span class="text-muted">/ {{ number_format($tenant->max_employees) }}</span>@endif
						</td>
						<td>
							@php($badge = ['active' => 'success', 'suspended' => 'warning', 'terminated' => 'secondary'][$tenant->status] ?? 'secondary')
							<span class="badge bg-{{ $badge }}">{{ ['active' => 'Aktif', 'suspended' => 'Ditangguhkan', 'terminated' => 'Berakhir'][$tenant->status] ?? $tenant->status }}</span>
						</td>
						<td>{{ optional($tenant->activated_at)->format('Y-m-d') ?? '-' }}</td>
						<td class="text-end text-nowrap">
							<a href="{{ url('core/tenants/'.$tenant->uuid.'/edit') }}" class="btn btn-sm btn-outline-primary">Ubah</a>
							@if((int) auth()->user()->tenant_id !== $tenant->id)
								<a href="{{ url('core/tenants/'.$tenant->uuid.'/break-glass') }}" class="btn btn-sm btn-outline-danger" title="Akses darurat ke data tenant">Break-glass</a>
							@endif
							@if($tenant->status === 'active')
								<form method="POST" action="{{ url('core/tenants/'.$tenant->uuid.'/status') }}" class="d-inline"
									onsubmit="return confirm('Tangguhkan tenant {{ $tenant->code }}? Semua penggunanya akan ter-logout.')">
									@csrf
									<input type="hidden" name="status" value="suspended">
									<button class="btn btn-sm btn-outline-warning" type="submit">Tangguhkan</button>
								</form>
							@else
								<form method="POST" action="{{ url('core/tenants/'.$tenant->uuid.'/status') }}" class="d-inline">
									@csrf
									<input type="hidden" name="status" value="active">
									<button class="btn btn-sm btn-outline-success" type="submit">Aktifkan</button>
								</form>
							@endif
						</td>
					</tr>
				@empty
					<tr><td colspan="8" class="text-center text-muted py-4">Belum ada tenant.</td></tr>
				@endforelse
				</tbody>
			</table>
		</div>

		<div class="mt-3">{{ $tenants->links('pagination::bootstrap-5') }}</div>
	</div>
</div>
@endsection
