@extends('layouts.app')

@section('content')
<div class="page-titles d-flex justify-content-between align-items-center flex-wrap gap-2">
	<h4 class="mb-0"> {{ $pageTitle }} <small>{{ $pageNote }}</small></h4>
	<div class="d-flex gap-2">
		@if($canEdit)
			<form method="POST" action="{{ url('performance/calibration/sessions/'.$session->id.'/close') }}" onsubmit="return confirm('Tutup sesi kalibrasi? Penempatan band tidak bisa diubah lagi di sesi ini.')">
				@csrf<button class="btn btn-sm btn-outline-success" type="submit">Tutup sesi</button></form>
		@endif
		<a href="{{ url('performance/periods/'.$period->id.'/calibration') }}" class="btn btn-sm btn-outline-secondary">Kembali</a>
	</div>
</div>

@include('core.tenants.partials.flash')

<div x-data="{ dist: @js($distribution), get total() { return Object.values(this.dist).reduce((s, d) => s + d.count, 0) } }" id="calibration-board"
	data-move-url="{{ url('performance/calibration/sessions/'.$session->id.'/move') }}" data-can-edit="{{ $canEdit ? 1 : 0 }}">
	<div class="card"><div class="card-body py-2">
		<div class="d-flex flex-wrap gap-3 small align-items-center">
			<strong>Distribusi (<span x-text="total"></span> pegawai):</strong>
			<template x-for="(d, code) in dist" :key="code">
				<span><span x-text="d.label"></span>: <strong x-text="d.count"></strong> (<span x-text="d.pct.toLocaleString('id-ID')"></span>%)</span>
			</template>
		</div>
		@if($canEdit)<div class="small text-muted mt-1">Seret kartu pegawai ke band lain. Setiap pemindahan wajib beralasan dan tercatat.</div>@endif
	</div></div>

	<div class="row g-2 flex-nowrap overflow-auto pb-2">
		@foreach($bands as $band)
			<div class="col" style="min-width: 210px">
				<div class="card h-100"><div class="card-body p-2">
					<h6 class="mb-1">{{ $band['label'] }}</h6>
					<div class="small text-muted mb-2">skor ≥ {{ $band['min'] + 0 }} · <span x-text="dist['{{ $band['code'] }}'].count"></span> orang</div>
					<ul class="list-unstyled calib-column mb-0" data-band="{{ $band['code'] }}" style="min-height: 120px">
						@foreach($members->get($band['code'], collect()) as $m)
							<li class="border rounded p-2 mb-1 bg-body {{ $canEdit ? 'calib-card' : '' }}" data-id="{{ $m->id }}" style="{{ $canEdit ? 'cursor: grab' : '' }}">
								<div class="small fw-bold">{{ $m->full_name }}</div>
								<div class="small text-muted">{{ $m->position_title }}{{ $m->unit_name ? ' · '.$m->unit_name : '' }}</div>
								<div class="small">Skor <span class="calib-score">{{ number_format($m->score, 2, ',', '.') }}</span>@if($m->calibrated_band)<span class="badge bg-warning text-dark ms-1">dikalibrasi</span>@endif</div>
							</li>
						@endforeach
					</ul>
				</div></div>
			</div>
		@endforeach
	</div>
</div>

<div class="card"><div class="card-body">
	<h5 class="card-title">Jejak kalibrasi</h5>
	<table class="table table-sm small mb-0">
		<thead><tr><th>Waktu</th><th>Pegawai</th><th>Perubahan</th><th>Alasan</th><th>Oleh</th></tr></thead>
		<tbody>
		@forelse($adjustments as $a)
			<tr><td>{{ $a->created_at }}</td><td>{{ $a->full_name }}</td>
				<td>{{ $bandLabels[$a->from_band] ?? $a->from_band }} ({{ number_format($a->from_score, 2, ',', '.') }}) → {{ $bandLabels[$a->to_band] ?? $a->to_band }} ({{ number_format($a->to_score, 2, ',', '.') }})</td>
				<td>{{ $a->reason }}</td><td>{{ trim($a->first_name.' '.$a->last_name) }}</td></tr>
		@empty
			<tr><td colspan="5" class="text-muted">Belum ada penyesuaian.</td></tr>
		@endforelse
		</tbody>
	</table>
</div></div>

@if($canEdit)
<script>
	// jQuery UI sortable (sudah dimuat bundel sximo.min.js). Rumus & distribusi dihitung server.
	jQuery(function ($) {
		const board = document.getElementById('calibration-board');
		$('.calib-column').sortable({
			connectWith: '.calib-column', items: '.calib-card', placeholder: 'border border-primary rounded mb-1 p-3',
			receive: function (event, ui) {
				const reason = window.prompt('Alasan memindahkan ' + ui.item.find('.fw-bold').text() + ' ke band ini:');
				if (!reason || reason.trim().length < 10) {
					alert('Alasan wajib diisi (minimal 10 karakter). Pemindahan dibatalkan.');
					$(ui.sender).sortable('cancel');
					return;
				}
				fetch(board.dataset.moveUrl, {
					method: 'POST',
					headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
					body: JSON.stringify({ assessment_id: ui.item.data('id'), to_band: $(this).data('band'), reason: reason.trim() }),
				}).then(r => r.json().then(body => ({ ok: r.ok, body }))).then(({ ok, body }) => {
					if (!ok) { throw new Error(Object.values(body.errors || {}).flat()[0] || body.message || 'Gagal menyimpan'); }
					Alpine.$data(board).dist = body.distribution;
					ui.item.find('.calib-score').text(body.score.toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 }));
					if (!ui.item.find('.badge').length) { ui.item.find('.calib-score').after('<span class="badge bg-warning text-dark ms-1">dikalibrasi</span>'); }
				}).catch(e => { alert(e.message); $(ui.sender).sortable('cancel'); });
			},
		}).disableSelection();
	});
</script>
@endif
@endsection
