@extends('layouts.app')

@section('content')
<div class="page-titles d-flex justify-content-between align-items-center flex-wrap gap-2">
	<h4 class="mb-0"> {{ $pageTitle }} <small>{{ $pageNote }}</small></h4>
	<div class="d-flex gap-2">
		@if($ratings->isNotEmpty())<a href="{{ url('performance/periods/'.$period->id.'/ratings/export') }}" class="btn btn-sm btn-outline-primary"><i class="fa fa-download"></i> Ekspor CSV</a>@endif
		<a href="{{ url('performance/periods/'.$period->id) }}" class="btn btn-sm btn-outline-secondary">Kembali ke periode</a>
	</div>
</div>

@include('core.tenants.partials.flash')

<div class="card"><div class="card-body">
	<div class="table-responsive"><table class="table table-sm align-middle mb-0">
		<thead><tr><th>Pegawai</th><th class="text-end">Skor</th><th>Rating</th><th>Status</th><th class="text-center">Revisi</th><th>Diumumkan</th><th>Batas keberatan</th><th>Snapshot</th></tr></thead>
		<tbody>
		@forelse($ratings as $r)
			<tr>
				<td>{{ $r->full_name }} <span class="small text-muted">{{ $r->employee_number }}</span></td>
				<td class="text-end">{{ $r->rated ? number_format($r->final_score, 2, ',', '.') : '—' }}@if($r->raw_score > $r->final_score)<div class="small text-muted">mentah {{ number_format($r->raw_score, 2, ',', '.') }}</div>@endif</td>
				<td><span class="badge {{ $r->rated ? 'bg-primary' : 'bg-secondary' }}">{{ $r->band_label }}</span></td>
				<td><span class="badge {{ $r->status === 'final' ? 'bg-success' : 'bg-warning text-dark' }}">{{ $r->status === 'final' ? 'final' : 'sementara' }}</span></td>
				<td class="text-center">{{ $r->revision }}@if($r->reason)<div class="small text-muted" title="{{ $r->reason }}">koreksi</div>@endif</td>
				<td class="small">{{ $r->published_at }}</td>
				<td class="small">{{ $r->appeal_deadline }}</td>
				<td class="small"><code title="SHA-256">{{ \Illuminate\Support\Str::limit($snapshots[$r->id] ?? '—', 12, '…') }}</code></td>
			</tr>
		@empty
			<tr><td colspan="8" class="text-muted">Belum ada rating yang diumumkan.</td></tr>
		@endforelse
		</tbody>
	</table></div>
	<div class="mt-2">{{ $ratings->links('pagination::bootstrap-5') }}</div>
	@if($period->status === 'appeal')<div class="alert alert-warning small mt-2 mb-1">Rating masih sementara. Keberatan dapat diajukan s.d. {{ $period->appeal_closes_on }}; pengesahan final (dengan snapshot) setelah tanggal tersebut dan semua keberatan diputus.</div>@endif
	<div class="small text-muted mt-2">Snapshot (dibuat saat pengesahan final) menyimpan kebijakan terkunci, KPI (termasuk versi lama), realisasi, penilaian, dan kalibrasi; tidak dapat diubah atau dihapus (dijaga trigger database).</div>
</div></div>
@endsection
