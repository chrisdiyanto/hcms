@extends('layouts.app')

@section('content')
@php($pbadge = ['draft' => 'bg-warning text-dark', 'approved' => 'bg-success'])
<div class="page-titles d-flex flex-wrap justify-content-between align-items-center gap-2">
	<h4 class="mb-0"> {{ $pageTitle }} <small>{{ $pageNote }}</small></h4>
	<a href="{{ url('talent/succession/report') }}" class="btn btn-sm btn-outline-secondary"><i class="fa fa-shield-alt"></i> Laporan suksesi</a>
</div>

@include('core.tenants.partials.flash')

<div class="row g-3 mb-3">
	@foreach([['Posisi kritis', $metrics['total'], 'aktif dalam cakupan'], ['Cakupan suksesi', $metrics['coverage'] === null ? '—' : number_format($metrics['coverage'], 1, ',', '.').'%', 'punya ≥ 1 suksesor siap sekarang (disahkan)'],
		['Bench strength', $metrics['bench'] === null ? '—' : number_format($metrics['bench'], 2, ',', '.'), 'rata-rata suksesor siap sekarang/1–2 thn'], ['Posisi berisiko', $metrics['at_risk'], 'dampak sangat tinggi tanpa siap sekarang, atau risiko kekosongan ≤ 12 bln']] as [$label, $value, $note])
		<div class="col-6 col-xl-3"><div class="card h-100 mb-0"><div class="card-body"><div class="small text-muted">{{ $label }}</div><div class="h3 mb-0">{{ $value }}</div><div class="small text-muted">{{ $note }}</div></div></div></div>
	@endforeach
</div>

<div class="card"><div class="card-body">
	<p class="small text-muted">Posisi kritis ditandai di Organisasi › Posisi (dengan tingkat dampak & alasan). Usia pensiun untuk risiko kekosongan: {{ $retirementAge }} tahun (pengaturan tenant <code>talent.retirement_age</code>).</p>
	<div class="table-responsive"><table class="table table-sm align-middle mb-0">
		<thead><tr><th>Posisi kritis</th><th>Dampak</th><th>Pemangku</th><th>Risiko kekosongan</th><th class="text-end">Siap sekarang / 1–2 thn</th><th>Rencana</th><th></th></tr></thead>
		<tbody>
		@forelse($positions as $p)
			<tr class="{{ $p->at_risk ? 'table-warning' : '' }}">
				<td><code>{{ $p->code }}</code> {{ $p->title }}<div class="small text-muted">{{ $p->unit_name }}</div></td>
				<td class="small">{{ \App\Domain\Talent\Succession::impactLabel($p->critical_impact) }}</td>
				<td class="small">{{ $p->incumbents ? implode(', ', $p->incumbents) : '—' }}</td>
				<td class="small">@forelse($p->risks as $r)<span class="badge bg-danger">{{ $risks[$r] }}</span> @empty — @endforelse</td>
				<td class="text-end">{{ $p->ready_now }} / {{ $p->ready_soon }}</td>
				<td>@if($p->plan_status)<span class="badge {{ $pbadge[$p->plan_status] }}">{{ $p->plan_status === 'approved' ? 'Disahkan' : 'Draft' }}</span> <span class="small text-muted">{{ $p->candidate_count }} kandidat</span>@else<span class="text-muted small">belum ada</span>@endif</td>
				<td class="text-end"><a href="{{ url('talent/succession/'.$p->id) }}" class="btn btn-sm btn-outline-primary">Kelola</a></td>
			</tr>
		@empty
			<tr><td colspan="7" class="text-muted small">Belum ada posisi kritis dalam cakupan Anda.</td></tr>
		@endforelse
		</tbody>
	</table></div>
</div></div>
@endsection
