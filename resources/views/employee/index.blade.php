@extends('layouts.app')

@section('content')
<div class="page-titles">
	<h4 class="mb-0"> {{ $pageTitle }} </h4>
</div>

@include('core.tenants.partials.flash')

<div class="card">
	<div class="card-body">
		<form method="GET" action="{{ url('employees') }}" class="row g-2 mb-3">
			<div class="col-md-4"><input type="search" name="q" value="{{ $filters['q'] }}" class="form-control form-control-sm" placeholder="Cari nama atau NIP"></div>
			<div class="col-md-3">
				<select name="company" class="form-select form-select-sm">
					<option value="">Semua company</option>
					@foreach($companies as $id => $name)<option value="{{ $id }}" @selected((string) $filters['company'] === (string) $id)>{{ $name }}</option>@endforeach
				</select>
			</div>
			<div class="col-md-2">
				<select name="status" class="form-select form-select-sm">
					@foreach(['active' => 'Aktif', 'terminated' => 'Berhenti', 'all' => 'Semua'] as $v => $l)<option value="{{ $v }}" @selected($filters['status'] === $v)>{{ $l }}</option>@endforeach
				</select>
			</div>
			<div class="col-md-1"><button class="btn btn-sm btn-outline-secondary w-100" type="submit">Cari</button></div>
			<div class="col-md-2 text-end">
				@if(!empty($access['is_csv']) || !empty($access['is_excel']))<a href="{{ url('employees/export').'?'.http_build_query($filters) }}" class="btn btn-sm btn-outline-secondary">Ekspor CSV</a>@endif
				@if(!empty($access['is_add']))<a href="{{ url('employees/create') }}" class="btn btn-sm btn-info">Tambah pegawai</a>@endif
			</div>
		</form>

		<div class="table-responsive">
			<table class="table table-striped table-hover align-middle mb-0">
				<thead><tr><th>NIP</th><th>Nama</th><th>Company</th><th>Posisi saat ini</th><th>Unit</th><th>Status kerja</th><th>Bergabung</th><th></th></tr></thead>
				<tbody>
				@forelse($rows as $e)
					<tr>
						<td><code>{{ $e->employee_number }}</code></td>
						<td><a href="{{ url('employees/'.$e->id) }}">{{ $e->full_name }}</a>
							@if($e->status === 'terminated')<span class="badge bg-secondary ms-1">Berhenti</span>@endif</td>
						<td>{{ $e->corporate_name }}</td>
						<td>{{ $e->position_title ?? '–' }}</td>
						<td>{{ $e->unit_name ?? '–' }}</td>
						<td>{{ \App\Http\Controllers\Employee\EmployeesController::EMPLOYMENT[$e->employment_status] ?? $e->employment_status }}</td>
						<td>{{ $e->join_date }}</td>
						<td class="text-end"><a href="{{ url('employees/'.$e->id) }}" class="btn btn-sm btn-outline-primary">Profil</a></td>
					</tr>
				@empty
					<tr><td colspan="8" class="text-center text-muted py-4">Tidak ada pegawai dalam cakupan Anda.</td></tr>
				@endforelse
				</tbody>
			</table>
		</div>
		<div class="mt-3">{{ $rows->links('pagination::bootstrap-5') }}</div>
	</div>
</div>
@endsection
