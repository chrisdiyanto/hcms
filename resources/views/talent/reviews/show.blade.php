@extends('layouts.app')

@section('content')
@php
	$sbadge = ['draft' => 'bg-secondary', 'calibration' => 'bg-info', 'approved' => 'bg-success'];
	$lbadge = ['high' => 'bg-success', 'medium' => 'bg-warning text-dark', 'low' => 'bg-secondary'];
	$byBox = $entries->whereNotNull('final_box')->groupBy('final_box');
	$tone = [9 => '#1baf7a', 8 => '#56c596', 6 => '#56c596', 7 => '#eda100', 5 => '#7fb6ef', 3 => '#7fb6ef', 4 => '#f0b85a', 2 => '#c9c8c2', 1 => '#e87b7b'];
	$incomplete = $entries->where('data_status', '!=', 'complete');
@endphp
<div class="page-titles d-flex flex-wrap justify-content-between align-items-center gap-2">
	<h4 class="mb-0"> {{ $pageTitle }} <span class="badge {{ $sbadge[$review->status] }}">{{ $pageNote }}</span></h4>
	<div class="d-flex gap-2">
		@if($review->status !== 'approved' && $canDecide)
			<form method="POST" action="{{ url('talent/reviews/'.$review->id.'/populate') }}">@csrf <button class="btn btn-sm btn-outline-primary" title="Ambil ulang rating & potensi terbaru; keputusan panel dipertahankan"><i class="fa fa-sync-alt"></i> Perbarui data</button></form>
		@endif
		@if($canApprove)
			<form method="POST" action="{{ url('talent/reviews/'.$review->id.'/approve') }}" onsubmit="return confirm('Sahkan review? Setelah disahkan tidak dapat diubah.')">@csrf <button class="btn btn-sm btn-success"><i class="fa fa-check"></i> Sahkan</button></form>
		@endif
		<a href="{{ url('talent/reviews') }}" class="btn btn-sm btn-outline-secondary">Kembali</a>
	</div>
</div>

@include('core.tenants.partials.flash')
@if($errors->any())<div class="alert alert-danger small"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif

<div class="card"><div class="card-body small d-flex flex-wrap gap-4">
	<div><span class="text-muted">Tanggal</span><div>{{ $review->review_date }}</div></div>
	<div><span class="text-muted">Periode kinerja</span><div>{{ $review->period_name }}</div></div>
	<div><span class="text-muted">Cakupan</span><div>{{ $review->unit_name ?? 'Seluruh cakupan pembuat' }}</div></div>
	<div><span class="text-muted">Panel</span><div>{{ $panel->map(fn ($u) => trim($u->first_name.' '.$u->last_name))->implode(', ') }}</div></div>
	<div><span class="text-muted">Data lengkap</span><div>{{ $entries->count() - $incomplete->count() }} / {{ $entries->count() }}</div></div>
	@if($review->approved_at)<div><span class="text-muted">Disahkan</span><div>{{ \Carbon\Carbon::parse($review->approved_at)->translatedFormat('d M Y') }}</div></div>@endif
</div></div>

{{-- Nine Box: baris = potensi (tinggi di atas), kolom = kinerja (rendah di kiri) --}}
<div class="card"><div class="card-body">
	<h5 class="card-title">Nine Box</h5>
	<div class="d-flex gap-2">
		<div class="d-flex flex-column justify-content-around small text-muted text-end" style="width: 70px">
			<div>Potensi tinggi</div><div>Potensi sedang</div><div>Potensi rendah</div></div>
		<div class="flex-grow-1">
			<div class="d-grid gap-2" style="grid-template-columns: repeat(3, minmax(0, 1fr))">
				@foreach([7, 8, 9, 4, 5, 6, 1, 2, 3] as $box)
					@php($people = $byBox->get($box, collect()))
					<div class="rounded p-2" style="min-height: 110px; background: {{ $tone[$box] }}22; border: 1px solid {{ $tone[$box] }}">
						<div class="d-flex justify-content-between"><strong class="small">{{ $box }}. {{ $boxLabels[$box] }}</strong><span class="badge bg-dark">{{ $people->count() }}</span></div>
						@if($sensitive)
							<div class="small mt-1">@foreach($people->take(8) as $p)<a href="#entry-{{ $p->id }}" class="d-block text-truncate">{{ $p->full_name }}</a>@endforeach
								@if($people->count() > 8)<span class="text-muted">+{{ $people->count() - 8 }} lainnya</span>@endif</div>
						@endif
					</div>
				@endforeach
			</div>
			<div class="d-grid small text-muted text-center mt-1" style="grid-template-columns: repeat(3, minmax(0, 1fr))"><div>Kinerja rendah</div><div>Kinerja sedang</div><div>Kinerja tinggi</div></div>
		</div>
	</div>
	@unless($sensitive)<p class="small text-muted mt-2 mb-0">Nama pegawai hanya tampil bagi pemegang izin data sensitif.</p>@endunless
</div></div>

@if($sensitive)
	@if($canDecide)
		@foreach($entries as $e)
			<form id="ef{{ $e->id }}" method="POST" action="{{ url('talent/reviews/entries/'.$e->id) }}">@csrf @method('PUT')</form>
		@endforeach
	@endif
	<div class="card"><div class="card-body">
		<h5 class="card-title">Peserta & keputusan panel</h5>
		<p class="small text-muted">Kotak usulan dihitung otomatis (kinerja: Outstanding/Exceeds = tinggi, Meets = sedang, Below/Unsatisfactory = rendah; potensi: hasil instrumen berlaku). Kotak final yang berbeda dari usulan wajib disertai justifikasi. Risiko kehilangan dinilai panel dan bersifat rahasia.</p>
		<div class="table-responsive"><table class="table table-sm align-middle mb-0">
			<thead><tr><th>Pegawai</th><th>Kinerja</th><th>Potensi</th><th>Usulan</th><th>Final</th><th>Justifikasi</th><th>Risiko kehilangan</th><th>Tindak lanjut</th>@if($canDecide)<th></th>@endif</tr></thead>
			<tbody>
			@foreach($entries as $e)
				<tr id="entry-{{ $e->id }}">
					<td>{{ $e->full_name }}<div class="small text-muted">{{ $e->unit_name ?? '—' }}@if($e->previous_box !== null) · sebelumnya kotak {{ $e->previous_box }}@endif</div>
						@if($e->data_status !== 'complete')<span class="badge bg-warning text-dark">{{ $dataStatus[$e->data_status] }}</span>@endif</td>
					<td class="small">@if($e->performance_level)<span class="badge {{ $lbadge[$e->performance_level] }}">{{ $levels[$e->performance_level] }}</span><div class="text-muted">{{ $e->performance_band }}</div>@else — @endif</td>
					<td>@if($e->potential_level)<span class="badge {{ $lbadge[$e->potential_level] }}">{{ $levels[$e->potential_level] }}</span>@else — @endif</td>
					<td class="small">{{ $e->proposed_box ? $e->proposed_box.'. '.$boxLabels[$e->proposed_box] : '—' }}</td>
					@if($canDecide)
						<td><select form="ef{{ $e->id }}" name="final_box" class="form-select form-select-sm" style="width: 170px" aria-label="Kotak final {{ $e->full_name }}" @disabled(!$e->proposed_box)>
							<option value="">—</option>@foreach($boxLabels as $k => $l)<option value="{{ $k }}" @selected($e->final_box == $k)>{{ $k }}. {{ $l }}</option>@endforeach</select></td>
						<td><input form="ef{{ $e->id }}" name="justification" class="form-control form-control-sm" maxlength="1000" value="{{ $e->justification }}" aria-label="Justifikasi"></td>
						<td><select form="ef{{ $e->id }}" name="flight_risk" class="form-select form-select-sm" aria-label="Risiko kehilangan"><option value="">—</option>@foreach($levels as $k => $l)<option value="{{ $k }}" @selected($e->flight_risk === $k)>{{ $l }}</option>@endforeach</select>
							<input form="ef{{ $e->id }}" name="flight_risk_reason" class="form-control form-control-sm mt-1" maxlength="500" value="{{ $e->flight_risk_reason }}" placeholder="Alasan" aria-label="Alasan risiko"></td>
						<td><input form="ef{{ $e->id }}" name="development_action" class="form-control form-control-sm" maxlength="500" value="{{ $e->development_action }}" placeholder="Tindakan pengembangan" aria-label="Tindakan pengembangan">
							<div class="d-flex gap-1 mt-1">
								<select form="ef{{ $e->id }}" name="action_owner_user_id" class="form-select form-select-sm" aria-label="Penanggung jawab"><option value="">Penanggung jawab</option>@foreach($owners as $u)<option value="{{ $u->id }}" @selected($e->action_owner_user_id == $u->id)>{{ trim($u->first_name.' '.$u->last_name) }}</option>@endforeach</select>
								<input form="ef{{ $e->id }}" type="date" name="next_review_date" class="form-control form-control-sm" value="{{ $e->next_review_date }}" aria-label="Tinjau ulang"></div></td>
						<td><button form="ef{{ $e->id }}" class="btn btn-sm btn-outline-primary">Simpan</button></td>
					@else
						<td class="small"><strong>{{ $e->final_box ? $e->final_box.'. '.$boxLabels[$e->final_box] : '—' }}</strong></td>
						<td class="small">{{ $e->justification ?? '—' }}</td>
						<td class="small">{{ $e->flight_risk ? $levels[$e->flight_risk] : '—' }}@if($e->flight_risk_reason)<div class="text-muted">{{ $e->flight_risk_reason }}</div>@endif</td>
						<td class="small">{{ $e->development_action ?? '—' }}@if($e->next_review_date)<div class="text-muted">tinjau {{ $e->next_review_date }}</div>@endif</td>
					@endif
				</tr>
			@endforeach
			</tbody>
		</table></div>
	</div></div>
@endif
@endsection
