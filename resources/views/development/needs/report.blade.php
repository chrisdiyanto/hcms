@extends('layouts.app')

@section('content')
@php
	$small = fn ($n) => $mask && \App\Support\Privacy\SmallGroups::hidden($n);
	$fmt = fn ($n) => \App\Support\Privacy\SmallGroups::count($n, $mask);
	$rp = fn ($v) => 'Rp '.number_format((float) $v, 0, ',', '.');
	$badge = ['high' => 'bg-danger', 'medium' => 'bg-warning text-dark', 'low' => 'bg-secondary'];
@endphp
<div class="exec-dashboard">
<div class="page-titles d-flex justify-content-between align-items-center flex-wrap gap-2">
	<div><h4 class="mb-0">{{ $pageTitle }} {{ $year }}</h4><small class="text-muted">Kebutuhan pelatihan yang disetujui menurut sumber, prioritas, unit, dan kompetensi</small></div>
	<form method="GET" class="d-flex align-items-center gap-2">
		<label for="year" class="small text-muted mb-0">Tahun</label>
		<input id="year" type="number" name="year" value="{{ $year }}" class="form-control form-control-sm" style="width: 90px" onchange="this.form.submit()">
		<a href="{{ url('development/needs?year='.$year) }}" class="btn btn-sm btn-outline-secondary text-nowrap">Daftar kebutuhan</a>
	</form>
</div>

@include('executive._hero', [
	'kicker' => 'Kebutuhan disetujui (TNA)', 'icon' => 'fa fa-lightbulb',
	'number' => $fmt($approvedTotal),
	'sub' => $fmt($people).' pegawai · perkiraan biaya '.$rp($cost),
	'stats' => [
		['Terbuka', $fmt($openTotal), 'disetujui, belum direncanakan', 'fa fa-folder-open'],
		['Prioritas tinggi', $fmt($highTotal), 'skor K5 ≥ 6 atau dari PIP', 'fa fa-exclamation-circle'],
		['Berulang', $fmt($recurring), 'kompetensi sama disetujui tahun lalu', 'fa fa-redo'],
	],
	'ring' => ['value' => $approvedTotal ? round($plannedTotal / $approvedTotal * 100, 1) : null, 'center' => 'direncanakan', 'title' => 'Tindak lanjut', 'icon' => 'fa fa-tasks',
		'note' => $fmt($plannedTotal).' dari '.$fmt($approvedTotal).' kebutuhan sudah punya intervensi', 'slot' => 2,
		'chip' => $fmt($pendingProposed).' diajukan · '.$fmt($pendingSuggested).' saran menunggu tinjauan'],
])

<div class="row g-3 mb-4">
	<div class="col-lg-4">@include('executive._donut', ['title' => 'Menurut sumber', 'icon' => 'fa fa-sitemap', 'tone' => 'exec-tone-primary',
		'items' => $bySource, 'unit' => 'kebutuhan', 'empty' => 'Belum ada kebutuhan yang disetujui.', 'mask' => $mask])</div>
	<div class="col-lg-4">@include('executive._donut', ['title' => 'Menurut prioritas', 'icon' => 'fa fa-flag', 'tone' => 'exec-tone-danger',
		'items' => $byPriority, 'unit' => 'kebutuhan', 'empty' => 'Belum ada kebutuhan yang disetujui.', 'mask' => $mask])</div>
	<div class="col-lg-4">
		<div class="card exec-card h-100 mb-0"><div class="card-body">
			<div class="exec-section-header"><h5 class="exec-section-title"><span class="exec-icon-box exec-tone-warning" aria-hidden="true"><i class="fa fa-hourglass-half"></i></span><span>Belum masuk TNA</span></h5></div>
			<ul class="exec-insights">
				<li><span class="exec-insight-icon" aria-hidden="true"><i class="fa fa-magic"></i></span><div><div class="exec-insight-label">Saran otomatis</div>
					<div class="exec-insight-text">{{ $fmt($pendingSuggested) }} saran dari gap kompetensi/PIP menunggu tinjauan HR.</div></div></li>
				<li><span class="exec-insight-icon" aria-hidden="true"><i class="fa fa-paper-plane"></i></span><div><div class="exec-insight-label">Pengajuan</div>
					<div class="exec-insight-text">{{ $fmt($pendingProposed) }} usulan pegawai/atasan/organisasi menunggu keputusan.</div></div></li>
				<li><span class="exec-insight-icon" aria-hidden="true"><i class="fa fa-ban"></i></span><div><div class="exec-insight-label">Ditolak</div>
					<div class="exec-insight-text">{{ $fmt($rejected) }} kebutuhan ditolak tahun ini.</div></div></li>
			</ul>
		</div></div>
	</div>
</div>

<div class="row g-3 mb-4">
	<div class="col-lg-6">@include('executive._ranking', ['title' => 'Kompetensi / topik terbanyak', 'icon' => 'fa fa-book', 'tone' => 'exec-tone-primary',
		'rows' => $byTopic, 'name' => 'Kebutuhan', 'slot' => 0, 'column' => 'Kompetensi / topik', 'mask' => $mask,
		'empty' => 'Belum ada kebutuhan yang disetujui.', 'footnote' => 'Hanya kebutuhan yang sudah disetujui; 15 teratas.'])</div>
	<div class="col-lg-6">@include('executive._ranking', ['title' => 'Kebutuhan per unit', 'icon' => 'fa fa-sitemap', 'tone' => 'exec-tone-success',
		'rows' => $byUnit, 'name' => 'Kebutuhan', 'slot' => 2, 'column' => 'Unit', 'mask' => $mask,
		'empty' => 'Belum ada kebutuhan yang disetujui.', 'footnote' => 'Unit posisi primer pegawai saat ini.'])</div>
</div>

<div class="card exec-card"><div class="card-body">
	<div class="exec-section-header"><h5 class="exec-section-title"><span class="exec-icon-box exec-tone-danger" aria-hidden="true"><i class="fa fa-folder-open"></i></span><span>Kebutuhan terbuka dengan prioritas tertinggi</span></h5></div>
	@if($topOpen->isEmpty())
		<div class="exec-empty-state"><p class="small mb-0">Tidak ada kebutuhan disetujui yang belum direncanakan.</p></div>
	@else
		<div class="table-responsive"><table class="table exec-table mb-0">
			<thead><tr><th>Pegawai</th><th>Kebutuhan</th><th>Prioritas</th><th>Intervensi</th><th>Tenggat</th></tr></thead>
			<tbody>@foreach($topOpen as $r)
				<tr><td>{{ $r->full_name }}</td><td>@if($r->competency_code)<code>{{ $r->competency_code }}</code> @endif{{ $r->topic }}</td>
					<td><span class="badge {{ $badge[$r->priority] }}">{{ $priorities[$r->priority] }}</span></td>
					<td class="small">{{ $r->suggested_intervention ?? '—' }}</td><td class="small">{{ $r->due_date ?? '—' }}</td></tr>
			@endforeach</tbody>
		</table></div>
	@endif
	<div class="exec-footnote"><i class="fa fa-info-circle" aria-hidden="true"></i><span>Prioritas K5 = kritikalitas × |gap| (maks 4) × 1,5 bila terkait sasaran strategis; tinggi ≥ 6, sedang 3–5,99, rendah &lt; 3; kebutuhan dari PIP selalu tinggi. Definisi lengkap: kamus metrik CBHRM §5.</span></div>
</div></div>
@if($mask)<p class="small text-muted mt-2 mb-0"><i class="fa fa-user-secret" aria-hidden="true"></i> Kelompok berisi 1–4 orang ditampilkan "&lt; 5" untuk melindungi privasi pegawai.</p>@endif
</div>
@include('executive._filter-script')
@vite('resources/js/charts.js')
@endsection
