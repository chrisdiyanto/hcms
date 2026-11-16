@extends('layouts.app')

@section('content')
<div class="page-titles"><h4 class="mb-0"> {{ $pageTitle }} <small>{{ $pageNote }}</small></h4></div>

@include('core.tenants.partials.flash')

<div class="row">
	<div class="col-lg-5">
		<div class="card"><div class="card-body">
			<h5 class="card-title">Urutan impor</h5>
			<p class="small text-muted">Impor data rujukan lebih dulu. Setiap berkas divalidasi kering terhadap data sebenarnya; tidak ada yang tersimpan sampai Anda menekan Commit, dan commit menyimpan semua baris atau tidak sama sekali.</p>
			<ol class="mb-0">
				@foreach($types as $type => $title)
					<li class="mb-1">{{ $title }} — <a href="{{ url('core/import/template/'.$type) }}">unduh template</a></li>
				@endforeach
			</ol>
		</div></div>

		@if($canAdd)
		<div class="card"><div class="card-body">
			<h5 class="card-title">Unggah berkas</h5>
			<form method="POST" action="{{ url('core/import') }}" enctype="multipart/form-data">
				@csrf
				<div class="mb-2"><label class="form-label">Jenis data</label>
					<select name="type" required class="form-select form-select-sm">
						@foreach($types as $type => $title)<option value="{{ $type }}" @selected(old('type') === $type)>{{ $title }}</option>@endforeach
					</select></div>
				<div class="mb-2"><label class="form-label">Berkas CSV</label>
					<input type="file" name="file" accept=".csv,text/csv" required class="form-control form-control-sm">
					<div class="form-text">UTF-8, pemisah koma atau titik koma. Maksimal {{ number_format($maxRows) }} baris per berkas; lebih dari itu gunakan <code>php artisan hcms:import</code>.</div></div>
				<button type="submit" class="btn btn-sm btn-primary">Validasi</button>
			</form>
		</div></div>
		@endif
	</div>

	<div class="col-lg-7">
		<div class="card"><div class="card-body">
			<h5 class="card-title">Riwayat impor</h5>
			<table class="table table-sm align-middle mb-0">
				<thead><tr><th>Waktu</th><th>Jenis</th><th>Berkas</th><th>Status</th><th class="text-end">Baris</th><th></th></tr></thead>
				<tbody>
				@forelse($batches as $b)
					<tr>
						<td class="small">{{ $b->created_at }}<div class="text-muted">{{ $b->username }}</div></td>
						<td>{{ $types[$b->type] ?? $b->type }}</td>
						<td class="small">{{ $b->file_name }}</td>
						<td>@include('core.import.partials.status', ['status' => $b->status])</td>
						<td class="text-end small">{{ $b->total_rows }}@if($b->error_rows) <span class="text-danger">({{ $b->error_rows }} error)</span>@endif</td>
						<td class="text-end"><a href="{{ url('core/import/'.$b->uuid) }}" class="btn btn-sm btn-outline-primary">Lihat</a></td>
					</tr>
				@empty
					<tr><td colspan="6" class="text-muted">Belum ada impor.</td></tr>
				@endforelse
				</tbody>
			</table>
		</div></div>
	</div>
</div>
@endsection
