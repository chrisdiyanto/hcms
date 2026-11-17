@extends('layouts.app')

@section('content')
@php
	$fmt = fn ($n) => \App\Support\Privacy\SmallGroups::count($n, $mask);
	$rp = fn ($v) => 'Rp '.number_format((float) $v, 0, ',', '.');
	$badge = ['draft' => 'bg-secondary', 'submitted' => 'bg-info', 'returned' => 'bg-warning text-dark', 'approved' => 'bg-primary', 'completed' => 'bg-success'];
	$progress = $activityTotal ? round($activityDone / $activityTotal * 100, 1) : null;
@endphp
<div class="exec-dashboard">
<div class="page-titles d-flex justify-content-between align-items-center flex-wrap gap-2">
	<div><h4 class="mb-0">{{ $pageTitle }} {{ $year }}</h4><small class="text-muted">Rencana pengembangan individu: cakupan, kemajuan, dan gap setelah aktivitas selesai</small></div>
	<form method="GET" class="d-flex align-items-center gap-2"><label for="year" class="small text-muted mb-0">Tahun</label>
		<input id="year" type="number" name="year" value="{{ $year }}" class="form-control form-control-sm" style="width: 90px" onchange="this.form.submit()"></form>
</div>

@include('executive._hero', [
	'kicker' => 'Pegawai dengan IDP', 'icon' => 'fa fa-map-signs',
	'number' => $fmt($idpTotal),
	'sub' => 'dari '.$fmt($headcount).' pegawai aktif dalam cakupan ('.($headcount ? number_format($idpTotal / $headcount * 100, 1, ',', '.') : '0').'%)',
	'stats' => [
		['Berjalan', $fmt($statusCounts['approved'] ?? 0), 'disetujui atasan', 'fa fa-play'],
		['Menunggu / dikembalikan', $fmt(($statusCounts['submitted'] ?? 0) + ($statusCounts['returned'] ?? 0)), 'perlu tindakan', 'fa fa-hourglass-half'],
		['Lewat tenggat', $fmt($late), 'aktivitas belum selesai', 'fa fa-exclamation-circle'],
	],
	'ring' => ['value' => $progress, 'center' => 'selesai', 'title' => 'Kemajuan aktivitas', 'icon' => 'fa fa-tasks',
		'note' => $fmt($activityDone).' dari '.$fmt($activityTotal).' aktivitas IDP berjalan/selesai (tanpa yang dibatalkan)', 'slot' => 2,
		'chip' => 'Biaya '.$rp($actualCost).' dari rencana '.$rp($plannedCost)],
])

<div class="row g-3 mb-4">
	<div class="col-lg-5">@include('executive._donut', ['title' => 'Status IDP', 'icon' => 'fa fa-flag', 'tone' => 'exec-tone-primary', 'mask' => $mask,
		'items' => collect($statuses)->except('cancelled')->map(fn ($l, $k) => ['label' => $l, 'value' => (int) ($statusCounts[$k] ?? 0)])->filter(fn ($i) => $i['value'] > 0)->values(),
		'unit' => 'IDP', 'empty' => 'Belum ada IDP tahun ini.'])</div>
	<div class="col-lg-7">
		<div class="card exec-card h-100 mb-0"><div class="card-body">
			<div class="exec-section-header"><h5 class="exec-section-title"><span class="exec-icon-box exec-tone-success" aria-hidden="true"><i class="fa fa-check-circle"></i></span><span>Gap setelah aktivitas selesai</span></h5></div>
			<ul class="exec-insights">
				<li><span class="exec-insight-icon" aria-hidden="true"><i class="fa fa-check"></i></span><div><div class="exec-insight-label">Tertutup</div>
					<div class="exec-insight-text">{{ $fmt($gapClosed) }} aktivitas selesai dengan gap kompetensi sasaran kini ≥ 0.</div></div></li>
				<li><span class="exec-insight-icon" aria-hidden="true"><i class="fa fa-exclamation-triangle"></i></span><div><div class="exec-insight-label">Belum tertutup</div>
					<div class="exec-insight-text">{{ $fmt($gapOpen) }} aktivitas selesai tetapi gap masih negatif — pertimbangkan intervensi lanjutan atau asesmen ulang.</div></div></li>
				<li><span class="exec-insight-icon" aria-hidden="true"><i class="fa fa-question"></i></span><div><div class="exec-insight-label">Belum diukur</div>
					<div class="exec-insight-text">{{ $fmt($gapUnknown) }} aktivitas selesai tanpa data gap untuk kompetensinya (belum diases pada posisi saat ini).</div></div></li>
			</ul>
			<div class="exec-footnote"><i class="fa fa-info-circle" aria-hidden="true"></i><span>Gap dibaca dari hasil asesmen terkini; penutupan gap bukan bukti sebab-akibat dari satu aktivitas.</span></div>
		</div></div>
	</div>
</div>

<div class="card exec-card"><div class="card-body">
	<form method="GET" class="d-flex flex-wrap gap-2 mb-3">
		<input type="hidden" name="year" value="{{ $year }}">
		<select name="status" class="form-select form-select-sm" style="max-width: 200px" aria-label="Status"><option value="">Semua status</option>@foreach($statuses as $k => $l)@continue($k === 'cancelled')<option value="{{ $k }}" @selected($status === $k)>{{ $l }}</option>@endforeach</select>
		<select name="unit" class="form-select form-select-sm" style="max-width: 220px" aria-label="Unit"><option value="">Semua unit</option>@foreach($units as $k => $l)<option value="{{ $k }}" @selected(request('unit') == $k)>{{ $l }}</option>@endforeach</select>
		<input name="q" value="{{ request('q') }}" class="form-control form-control-sm" style="max-width: 220px" placeholder="Cari pegawai" aria-label="Cari pegawai">
		<button class="btn btn-sm btn-outline-primary">Terapkan</button>
	</form>
	<div class="table-responsive"><table class="table exec-table mb-0">
		<thead><tr><th>Pegawai</th><th>Unit</th><th>Status</th><th class="text-end">Kemajuan</th><th class="text-end">Lewat tenggat</th><th></th></tr></thead>
		<tbody>
		@forelse($rows as $r)
			<tr><td>{{ $r->full_name }}<div class="small text-muted">{{ $r->employee_number }}</div></td><td class="small">{{ $r->unit_name ?? '—' }}</td>
				<td><span class="badge {{ $badge[$r->status] ?? 'bg-secondary' }}">{{ $statuses[$r->status] }}</span></td>
				<td class="text-end tabular-nums">{{ $r->done }}/{{ $r->total }}</td>
				<td class="text-end">@if($r->late)<span class="badge bg-danger">{{ $r->late }}</span>@else — @endif</td>
				<td class="text-end"><a href="{{ url('ess/idp/'.$r->id) }}" class="btn btn-sm btn-outline-primary">Buka</a></td></tr>
		@empty
			<tr><td colspan="6" class="text-muted small">Belum ada IDP.</td></tr>
		@endforelse
		</tbody>
	</table></div>
	<div class="mt-2">{{ $rows->links() }}</div>
</div></div>
@if($mask)<p class="small text-muted mt-2 mb-0"><i class="fa fa-user-secret" aria-hidden="true"></i> Kelompok berisi 1–4 orang ditampilkan "&lt; 5" untuk melindungi privasi pegawai.</p>@endif
</div>
@vite('resources/js/charts.js')
@endsection
