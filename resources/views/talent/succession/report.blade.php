@extends('layouts.app')

@section('content')
@php
	$cnt = fn ($n) => \App\Support\Privacy\SmallGroups::count($n, $mask);
	$atRisk = $positions->where('at_risk', true);
@endphp
<div class="exec-dashboard">
<div class="page-titles"><div><h4 class="mb-0">{{ $pageTitle }}</h4><small class="text-muted">Cakupan suksesi & bench strength posisi kritis — dari rencana yang disahkan, tanpa nama kandidat</small></div></div>

@include('executive._hero', [
	'kicker' => 'Cakupan suksesi', 'icon' => 'fa fa-shield-alt',
	'number' => $metrics['coverage'] === null ? '—' : number_format($metrics['coverage'], 1, ',', '.').'%',
	'sub' => $metrics['covered'].' dari '.$metrics['total'].' posisi kritis punya ≥ 1 suksesor siap sekarang',
	'stats' => [
		['Bench strength', $metrics['bench'] === null ? '—' : number_format($metrics['bench'], 2, ',', '.'), 'suksesor siap sekarang/1–2 thn per posisi', 'fa fa-users'],
		['Posisi berisiko', $metrics['at_risk'], 'perlu tindakan', 'fa fa-exclamation-circle'],
		['Kandidat ganda', $cnt($metrics['duplicates']), 'suksesor utama > 1 posisi', 'fa fa-clone'],
	],
	'ring' => ['value' => $metrics['total'] ? round(($metrics['total'] - $metrics['no_plan'] - $metrics['draft']) / $metrics['total'] * 100, 1) : null, 'center' => 'disahkan',
		'title' => 'Rencana disahkan', 'icon' => 'fa fa-check-circle', 'slot' => 2,
		'note' => $metrics['no_plan'].' posisi belum punya rencana · '.$metrics['draft'].' masih draft'],
])

<div class="card exec-card"><div class="card-body">
	<div class="exec-section-header"><h5 class="exec-section-title"><span class="exec-icon-box exec-tone-danger" aria-hidden="true"><i class="fa fa-exclamation-triangle"></i></span><span>Posisi kritis</span></h5>
		<span class="exec-pill exec-tone-neutral">{{ $atRisk->count() }} berisiko</span></div>
	@if($positions->isEmpty())
		<div class="exec-empty-state"><p class="small mb-0">Belum ada posisi kritis dalam cakupan.</p></div>
	@else
		<div class="table-responsive"><table class="table exec-table mb-0">
			<thead><tr><th>Posisi</th><th>Dampak</th><th>Risiko kekosongan</th><th class="text-end">Siap sekarang</th><th class="text-end">Siap 1–2 thn</th><th>Rencana</th></tr></thead>
			<tbody>@foreach($positions->sortByDesc('at_risk') as $p)
				<tr><td>{{ $p->title }}<div class="small text-muted">{{ $p->unit_name }}</div></td>
					<td class="small">{{ \App\Domain\Talent\Succession::impactLabel($p->critical_impact) }}</td>
					<td class="small">@forelse($p->risks as $r)<span class="badge bg-danger">{{ $risks[$r] }}</span> @empty — @endforelse
						@if($p->at_risk && $p->risks === [])<span class="badge bg-warning text-dark">tanpa suksesor siap sekarang</span>@endif</td>
					<td class="text-end tabular-nums">{{ $cnt($p->ready_now) }}</td><td class="text-end tabular-nums">{{ $cnt($p->ready_soon) }}</td>
					<td class="small">{{ $p->plan_status === 'approved' ? 'Disahkan' : ($p->plan_status === 'draft' ? 'Draft' : 'Belum ada') }}</td></tr>
			@endforeach</tbody>
		</table></div>
	@endif
	<div class="exec-footnote"><i class="fa fa-info-circle" aria-hidden="true"></i><span>Cakupan = posisi kritis dengan ≥ 1 suksesor siap sekarang ÷ posisi kritis. Bench strength = rata-rata suksesor siap sekarang atau 1–2 tahun per posisi kritis. Berisiko = dampak sangat tinggi tanpa suksesor siap sekarang, atau pemangku kosong/pensiun (usia {{ $retirementAge }})/kontrak berakhir ≤ 12 bulan. Hanya rencana yang disahkan yang dihitung.</span></div>
</div></div>
@if($mask)<p class="small text-muted mt-2 mb-0"><i class="fa fa-user-secret" aria-hidden="true"></i> Kelompok berisi 1–4 orang ditampilkan "&lt; 5" untuk melindungi privasi pegawai.</p>@endif
</div>
@endsection
