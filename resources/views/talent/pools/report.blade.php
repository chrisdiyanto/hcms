@extends('layouts.app')

@section('content')
@php
	$cnt = fn ($n) => \App\Support\Privacy\SmallGroups::count($n, $mask);
	$small = fn ($n) => $mask && \App\Support\Privacy\SmallGroups::hidden($n);
	$pct = fn ($part, $total) => $total > 0 && !$small($part) && !$small($total) ? number_format($part / $total * 100, 0, ',', '.').'%' : '—';
@endphp
<div class="exec-dashboard">
<div class="page-titles d-flex justify-content-between align-items-center flex-wrap gap-2">
	<div><h4 class="mb-0">{{ $pageTitle }} {{ $year }}</h4><small class="text-muted">Komposisi, kesiapan, dan pergerakan anggota talent pool aktif — tanpa data individu</small></div>
	<form method="GET" class="d-flex align-items-center gap-2"><label for="year" class="small text-muted mb-0">Tahun</label>
		<input id="year" type="number" name="year" value="{{ $year }}" class="form-control form-control-sm" style="width: 90px" onchange="this.form.submit()"></form>
</div>

@include('executive._hero', [
	'kicker' => 'Pegawai di talent pool', 'icon' => 'fa fa-object-group',
	'number' => $cnt($people),
	'sub' => $cnt($memberships).' keanggotaan aktif di '.$rows->count().' pool · '.$cnt($nominated).' nominasi menunggu',
	'stats' => [
		['Siap sekarang', $cnt($readyNow), 'pegawai dengan kesiapan "siap sekarang"', 'fa fa-check-circle'],
		['Masuk / keluar', $cnt($joined).' / '.$cnt($exited), 'selama '.$year, 'fa fa-exchange-alt'],
		['Tinjau lewat jadwal', $cnt($overdue), 'keanggotaan aktif', 'fa fa-exclamation-circle'],
	],
	'ring' => ['value' => $memberships ? round($withIdp / $memberships * 100, 1) : null, 'center' => 'ber-IDP', 'title' => 'Cakupan IDP', 'icon' => 'fa fa-map-signs', 'slot' => 0,
		'note' => 'keanggotaan aktif dengan IDP '.$year.' berjalan/selesai', 'chip' => 'Aspirasi selaras: '.$pct($aligned, $memberships)],
])

<div class="card exec-card"><div class="card-body">
	<div class="exec-section-header"><h5 class="exec-section-title"><span class="exec-icon-box exec-tone-primary" aria-hidden="true"><i class="fa fa-object-group"></i></span><span>Per pool</span></h5></div>
	@if($rows->isEmpty())
		<div class="exec-empty-state"><p class="small mb-0">Belum ada talent pool aktif.</p></div>
	@else
		<div class="table-responsive"><table class="table exec-table mb-0">
			<thead><tr><th>Pool</th><th class="text-end">Aktif</th><th class="text-end">Nominasi</th>
				@foreach($horizons as $l)<th class="text-end">{{ $l }}</th>@endforeach
				<th class="text-end">Aspirasi selaras</th><th class="text-end">Ber-IDP</th><th class="text-end">Perempuan</th><th class="text-end">Masuk / keluar</th><th class="text-end">Tinjau lewat</th></tr></thead>
			<tbody>@foreach($rows as $r)
				<tr><td><code>{{ $r->code }}</code> {{ $r->name }}</td><td class="text-end tabular-nums">{{ $cnt($r->active) }}</td><td class="text-end tabular-nums">{{ $cnt($r->nominated) }}</td>
					<td class="text-end tabular-nums">{{ $cnt($r->now) }}</td><td class="text-end tabular-nums">{{ $cnt($r->soon) }}</td><td class="text-end tabular-nums">{{ $cnt($r->later) }}</td>
					<td class="text-end">{{ $pct($r->aligned, $r->active) }}</td><td class="text-end">{{ $pct($r->with_idp, $r->active) }}</td><td class="text-end">{{ $pct($r->female, $r->active) }}</td>
					<td class="text-end tabular-nums">{{ $cnt($r->joined) }} / {{ $cnt($r->exited) }}</td>
					<td class="text-end">@if($r->overdue && !$small($r->overdue))<span class="badge bg-danger">{{ $r->overdue }}</span>@else {{ $cnt($r->overdue) }} @endif</td></tr>
			@endforeach</tbody>
		</table></div>
	@endif
	<div class="exec-footnote"><i class="fa fa-info-circle" aria-hidden="true"></i><span>Kesiapan ditetapkan saat persetujuan/tinjau ulang (horizon K4). Aspirasi selaras = rumpun aspirasi karier terkini sama dengan rumpun sasaran pool. Keanggotaan bukan jaminan promosi.</span></div>
</div></div>
@if($mask)<p class="small text-muted mt-2 mb-0"><i class="fa fa-user-secret" aria-hidden="true"></i> Kelompok berisi 1–4 orang ditampilkan "&lt; 5" untuk melindungi privasi pegawai.</p>@endif
</div>
@endsection
