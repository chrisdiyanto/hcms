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
					<form method="GET" action="{{ url($baseUrl) }}" class="input-group input-group-sm">
						<input type="search" name="q" value="{{ $search }}" class="form-control" placeholder="Cari">
						<button class="btn btn-outline-secondary" type="submit">Cari</button>
					</form>
				</div>
				<div class="col-md-7 text-end">
					@if(!empty($access['is_add']))
						<a href="{{ url($baseUrl.'/create') }}" class="btn btn-sm btn-info">Tambah</a>
					@endif
				</div>
			</div>
		</div>

		<div class="table-responsive">
			<table class="table table-striped table-hover align-middle mb-0">
				<thead>
					<tr>
						@foreach($columns as $label)<th>{{ $label }}</th>@endforeach
						<th class="text-end">Aksi</th>
					</tr>
				</thead>
				<tbody>
				@forelse($rows as $row)
					<tr>
						@foreach($columns as $key => $label)
							<td>{!! $formatter($row, $key) !!}</td>
						@endforeach
						<td class="text-end text-nowrap">
							@if(!empty($access['is_edit']))
								<a href="{{ url($baseUrl.'/'.$row->id.'/edit') }}" class="btn btn-sm btn-outline-primary">Ubah</a>
							@endif
							@if(!empty($access['is_remove']))
								<form method="POST" action="{{ url($baseUrl.'/'.$row->id) }}" class="d-inline" onsubmit="return confirm('Hapus data ini?')">
									@csrf @method('DELETE')
									<button class="btn btn-sm btn-outline-danger" type="submit">Hapus</button>
								</form>
							@endif
						</td>
					</tr>
				@empty
					<tr><td colspan="{{ count($columns) + 1 }}" class="text-center text-muted py-4">Belum ada data.</td></tr>
				@endforelse
				</tbody>
			</table>
		</div>

		<div class="mt-3">{{ $rows->links('pagination::bootstrap-5') }}</div>
	</div>
</div>
@endsection
