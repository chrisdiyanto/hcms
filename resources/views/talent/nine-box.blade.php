@extends('layouts.app')

@section('content')
@php
	$cnt = fn ($n) => \App\Support\Privacy\SmallGroups::count($n, $mask);
	$small = fn ($n) => $mask && \App\Support\Privacy\SmallGroups::hidden($n);
	$pct = fn ($part, $total) => $total > 0 && !$small($part) && !$small($total) ? number_format($part / $total * 100, 1, ',', '.').'%' : '—';
	$tone = [9 => '#1baf7a', 8 => '#56c596', 6 => '#56c596', 7 => '#eda100', 5 => '#7fb6ef', 3 => '#7fb6ef', 4 => '#f0b85a', 2 => '#c9c8c2', 1 => '#e87b7b'];
@endphp
<div class="exec-dashboard">
<div class="page-titles d-flex justify-content-between align-items-center flex-wrap gap-2">
	<div><h4 class="mb-0">{{ $pageTitle }}</h4><small class="text-muted">Sebaran talenta dari talent review yang disahkan — tanpa data individu</small></div>
	@if($reviews->isNotEmpty())
		<form method="GET"><select name="review" class="form-select form-select-sm" onchange="this.form.submit()" aria-label="Talent review">
			@foreach($reviews as $r)<option value="{{ $r->id }}" @selected($review && $review->id === $r->id)>{{ $r->name }} ({{ $r->review_date }})</option>@endforeach</select></form>
	@endif
</div>

@if(!$review)
	<div class="exec-empty-state"><p class="small mb-0">Belum ada talent review yang disahkan.</p></div>
@else
	@include('executive._hero', [
		'kicker' => 'Pegawai di Nine Box', 'icon' => 'fa fa-th',
		'number' => $cnt($placed->count()),
		'sub' => 'dari '.$cnt($entries->count()).' peserta review · '.$pct($placed->count(), $entries->count()).' data lengkap',
		'stats' => [
			['Talenta kunci', $cnt($groupCounts['key']), 'kotak 6, 8, 9', 'fa fa-star'],
			['Inti', $cnt($groupCounts['core']), 'kotak 2, 3, 5', 'fa fa-users'],
			['Perlu tindak lanjut', $cnt($groupCounts['attention']), 'kotak 1, 4, 7', 'fa fa-exclamation-circle'],
		],
	])

	<div class="row g-3 mb-4">
		<div class="col-lg-7">
			<div class="card exec-card h-100 mb-0"><div class="card-body">
				<div class="exec-section-header"><h5 class="exec-section-title"><span class="exec-icon-box exec-tone-primary" aria-hidden="true"><i class="fa fa-th"></i></span><span>Sebaran kotak</span></h5></div>
				<div class="d-flex gap-2">
					<div class="d-flex flex-column justify-content-around small text-muted text-end" style="width: 64px"><div>Potensi tinggi</div><div>Sedang</div><div>Rendah</div></div>
					<div class="flex-grow-1">
						<div class="d-grid gap-2" style="grid-template-columns: repeat(3, minmax(0, 1fr))">
							@foreach([7, 8, 9, 4, 5, 6, 1, 2, 3] as $box)
								<div class="rounded p-2 text-center" style="background: {{ $tone[$box] }}22; border: 1px solid {{ $tone[$box] }}">
									<div class="small">{{ $box }}. {{ $boxLabels[$box] }}</div>
									<div class="h4 mb-0 tabular-nums">{{ $cnt($boxCounts[$box]) }}</div>
									<div class="small text-muted">{{ $pct($boxCounts[$box], $placed->count()) }}</div>
								</div>
							@endforeach
						</div>
						<div class="d-grid small text-muted text-center mt-1" style="grid-template-columns: repeat(3, minmax(0, 1fr))"><div>Kinerja rendah</div><div>Sedang</div><div>Tinggi</div></div>
					</div>
				</div>
			</div></div>
		</div>
		<div class="col-lg-5">
			<div class="card exec-card h-100 mb-0"><div class="card-body">
				<div class="exec-section-header"><h5 class="exec-section-title"><span class="exec-icon-box exec-tone-warning" aria-hidden="true"><i class="fa fa-lightbulb"></i></span><span>Sorotan</span></h5></div>
				<ul class="exec-insights">
					<li><span class="exec-insight-icon" aria-hidden="true"><i class="fa fa-exchange-alt"></i></span><div><div class="exec-insight-label">Pergerakan</div>
						<div class="exec-insight-text">@if($previous)Dibanding {{ $previous->name }}: {{ $cnt($moves['up']) }} naik, {{ $cnt($moves['same']) }} tetap, {{ $cnt($moves['down']) }} turun.@else Belum ada review sah sebelumnya untuk dibandingkan.@endif</div></div></li>
					<li><span class="exec-insight-icon" aria-hidden="true"><i class="fa fa-venus-mars"></i></span><div><div class="exec-insight-label">Keragaman talenta kunci</div>
						<div class="exec-insight-text">Perempuan {{ $pct($keyFemale, $keyFemale + $keyMale) }} dari talenta kunci, dibanding {{ $pct($allFemale, $allFemale + $allMale) }} dari seluruh pegawai di Nine Box.</div></div></li>
					<li><span class="exec-insight-icon" aria-hidden="true"><i class="fa fa-clipboard-check"></i></span><div><div class="exec-insight-label">Kelengkapan data</div>
						<div class="exec-insight-text">@forelse($incomplete as $status => $n){{ $dataStatus[$status] }}: {{ $cnt($n) }}@if(!$loop->last) · @endif @empty Semua peserta memiliki rating final dan potensi berlaku.@endforelse</div></div></li>
					@if($sensitive)
						<li><span class="exec-insight-icon" aria-hidden="true"><i class="fa fa-user-secret"></i></span><div><div class="exec-insight-label">Risiko kehilangan (rahasia)</div>
							<div class="exec-insight-text">{{ $cnt($keyHighRisk) }} talenta kunci dinilai panel berisiko tinggi keluar.</div></div></li>
					@endif
				</ul>
			</div></div>
		</div>
	</div>

	<div class="card exec-card"><div class="card-body">
		<div class="exec-section-header"><h5 class="exec-section-title"><span class="exec-icon-box exec-tone-success" aria-hidden="true"><i class="fa fa-sitemap"></i></span><span>Per unit</span></h5></div>
		<div class="table-responsive"><table class="table exec-table mb-0">
			<thead><tr><th>Unit</th><th class="text-end">Di Nine Box</th>@foreach($groupLabels as $l)<th class="text-end">{{ $l }}</th>@endforeach</tr></thead>
			<tbody>@foreach($byUnit as $unit => $row)
				<tr><td>{{ $unit }}</td><td class="text-end tabular-nums">{{ $cnt($row['total']) }}</td>
					@foreach(array_keys($groupLabels) as $g)<td class="text-end tabular-nums">{{ $cnt($row[$g]) }}</td>@endforeach</tr>
			@endforeach</tbody>
		</table></div>
		<div class="exec-footnote"><i class="fa fa-info-circle" aria-hidden="true"></i><span>Kinerja: rating final periode review (Outstanding/Exceeds = tinggi, Meets = sedang, Below/Unsatisfactory = rendah). Potensi: instrumen potensi yang berlaku. Penempatan di Nine Box bukan jaminan promosi.</span></div>
	</div></div>
	@if($mask)<p class="small text-muted mt-2 mb-0"><i class="fa fa-user-secret" aria-hidden="true"></i> Kelompok berisi 1–4 orang ditampilkan "&lt; 5" untuk melindungi privasi pegawai.</p>@endif
@endif
</div>
@endsection
