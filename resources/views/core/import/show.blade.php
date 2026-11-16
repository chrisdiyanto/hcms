@extends('layouts.app')

@section('content')
<div class="page-titles d-flex justify-content-between align-items-center">
	<h4 class="mb-0"> {{ $pageTitle }} <small>{{ $title }} · {{ $pageNote }}</small></h4>
	<a href="{{ url('core/import') }}" class="btn btn-sm btn-outline-secondary">Kembali</a>
</div>

@include('core.tenants.partials.flash')

<div class="row">
	<div class="col-lg-4">
		<div class="card"><div class="card-body">
			<p class="mb-2">Status: @include('core.import.partials.status', ['status' => $batch->status])</p>
			<h6>Rekonsiliasi</h6>
			<table class="table table-sm mb-3">
				<tr><td>Baris di berkas</td><td class="text-end">{{ $reconciliation['rows_in_file'] ?? 0 }}</td></tr>
				<tr><td>Data baru</td><td class="text-end">{{ $reconciliation['created'] ?? 0 }}</td></tr>
				<tr><td>Data diperbarui</td><td class="text-end">{{ $reconciliation['updated'] ?? 0 }}</td></tr>
				<tr><td>Baris error</td><td class="text-end {{ $batch->error_rows ? 'text-danger fw-bold' : '' }}">{{ $batch->error_rows }}</td></tr>
				<tr><td>Jumlah di sistem sebelum</td><td class="text-end">{{ $reconciliation['db_before'] ?? '–' }}</td></tr>
				<tr><td>Jumlah di sistem sesudah{{ ($reconciliation['committed'] ?? false) ? '' : ' (simulasi)' }}</td><td class="text-end">{{ $reconciliation['db_after'] ?? '–' }}</td></tr>
			</table>
			@if(isset($reconciliation['rows_in_file']) && ($reconciliation['created'] + $reconciliation['updated']) === $reconciliation['rows_in_file'] && ($reconciliation['db_after'] - $reconciliation['db_before']) === $reconciliation['created'])
				<p class="small text-success">Jumlah cocok: setiap baris berkas menjadi tepat satu data baru atau diperbarui.</p>
			@endif

			@if($canCommit)
				<form method="POST" action="{{ url('core/import/'.$batch->uuid.'/commit') }}" onsubmit="return confirm('Simpan {{ $reconciliation['rows_in_file'] ?? 0 }} baris ke sistem?')">
					@csrf
					<button type="submit" class="btn btn-sm btn-success w-100">Commit impor</button>
				</form>
			@elseif($batch->status === 'committed')
				<p class="small mb-0">Disimpan pada {{ $batch->committed_at }}.</p>
			@endif
		</div></div>
	</div>

	<div class="col-lg-8">
		<div class="card"><div class="card-body">
			<h5 class="card-title">Error per baris</h5>
			@if($rowErrors === [])
				<p class="text-muted mb-0">Tidak ada error.</p>
			@else
				<p class="small text-muted">Perbaiki baris berikut di berkas lalu unggah ulang. Nomor baris mengikuti berkas (baris 1 = header).</p>
				<table class="table table-sm mb-0">
					<thead><tr><th style="width: 90px">Baris</th><th>Pesan</th></tr></thead>
					@foreach($rowErrors as $line => $messages)
						<tr><td>{{ $line ?: 'Berkas' }}</td><td><ul class="mb-0 ps-3 small">@foreach($messages as $m)<li>{{ $m }}</li>@endforeach</ul></td></tr>
					@endforeach
				</table>
			@endif
		</div></div>
	</div>
</div>
@endsection
