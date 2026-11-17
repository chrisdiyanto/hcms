@extends('layouts.app')

@section('content')
@php
	$sbadge = ['nominated' => 'bg-info', 'active' => 'bg-success', 'rejected' => 'bg-light text-dark', 'exited' => 'bg-secondary'];
	$hbadge = ['now' => 'bg-success', '1_2' => 'bg-warning text-dark', '3_plus' => 'bg-danger'];
	$nominated = $members->where('status', 'nominated');
	$current = $members->whereIn('status', ['active']);
	$past = $members->whereIn('status', ['exited', 'rejected']);
@endphp
<div class="page-titles d-flex flex-wrap justify-content-between align-items-center gap-2">
	<h4 class="mb-0"> {{ $pageTitle }} <small>{{ $pageNote }}</small></h4>
	<a href="{{ url('talent/pools') }}" class="btn btn-sm btn-outline-secondary">Kembali</a>
</div>

@include('core.tenants.partials.flash')
@if($errors->any())<div class="alert alert-danger small"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif

<div class="card"><div class="card-body small">
	<div class="row g-3">
		<div class="col-md-4"><div class="text-muted">Tujuan</div>{{ $pool->purpose }}</div>
		<div class="col-md-2"><div class="text-muted">Sasaran</div>{{ $pool->position_title ?? $pool->family_name }}</div>
		<div class="col-md-3"><div class="text-muted">Kriteria masuk</div>{{ $pool->entry_criteria }}</div>
		<div class="col-md-3"><div class="text-muted">Kriteria keluar</div>{{ $pool->exit_criteria }}</div>
	</div>
	<p class="mb-0 mt-2 text-muted">Keanggotaan bukan janji promosi. Tinjau ulang tiap {{ $pool->review_cycle_months }} bulan. Akses halaman ini tercatat di audit.</p>
</div></div>

{{-- Nominasi menunggu keputusan --}}
@if($nominated->isNotEmpty())
	<form method="POST" action="{{ url('talent/pools/'.$pool->id.'/decide') }}" class="card"><div class="card-body">
		@csrf
		<h5 class="card-title">Nominasi menunggu keputusan</h5>
		<table class="table table-sm align-middle mb-2"><tbody>
		@foreach($nominated as $m)
			<tr>@if($canApprove)<td style="width: 28px"><input type="checkbox" class="form-check-input" name="ids[]" value="{{ $m->id }}" aria-label="Pilih {{ $m->full_name }}"></td>@endif
				<td>{{ $m->full_name }}<div class="small text-muted">{{ $m->source === 'talent_review' ? 'dari talent review' : 'manual' }} · {{ \Illuminate\Support\Str::limit($m->nomination_reason, 100) }}</div></td>
				<td class="small">@if($m->computed_horizon)Kesiapan terhitung: <span class="badge {{ $hbadge[$m->computed_horizon] }}">{{ $horizons[$m->computed_horizon] }}</span>@endif
					@if($m->jfi !== null) · JFI {{ number_format($m->jfi, 1, ',', '.') }}%@endif</td></tr>
		@endforeach
		</tbody></table>
		@if($canApprove)
			<div class="d-flex flex-wrap gap-2 align-items-end">
				<div><label class="form-label small mb-0" for="readiness">Kesiapan (wajib bila disetujui)</label>
					<select id="readiness" name="readiness" class="form-select form-select-sm"><option value="">—</option>@foreach($horizons as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach</select></div>
				<div class="flex-grow-1"><label class="form-label small mb-0" for="decision_note">Catatan (wajib bila ditolak)</label><input id="decision_note" name="decision_note" class="form-control form-control-sm" maxlength="1000"></div>
				<button name="decision" value="active" class="btn btn-sm btn-success">Setujui terpilih</button>
				<button name="decision" value="rejected" class="btn btn-sm btn-outline-danger">Tolak terpilih</button>
			</div>
		@endif
	</div></form>
@endif

{{-- Anggota aktif --}}
<div class="card"><div class="card-body">
	<h5 class="card-title">Anggota aktif ({{ $current->count() }})</h5>
	<div class="table-responsive"><table class="table table-sm align-middle mb-0">
		<thead><tr><th>Pegawai</th><th>Kesiapan</th><th>Gap kritis</th><th>Aspirasi</th><th>IDP {{ now()->year }}</th>@if($sensitive)<th>Nine Box / risiko</th>@endif<th>Tinjau</th>@if($canEdit)<th></th>@endif</tr></thead>
		<tbody>
		@forelse($current as $m)
			<tr>
				<td>{{ $m->full_name }}<div class="small text-muted">sejak {{ $m->joined_at }}</div></td>
				<td><span class="badge {{ $hbadge[$m->readiness] ?? 'bg-secondary' }}">{{ $horizons[$m->readiness] ?? '—' }}</span>
					@if($m->computed_horizon && $m->computed_horizon !== $m->readiness)<div class="small text-muted">terhitung: {{ $horizons[$m->computed_horizon] }}</div>@endif</td>
				<td class="small">@forelse($m->critical_gaps as $code)<span class="badge bg-danger">{{ $code }}</span> @empty — @endforelse</td>
				<td class="small">{{ ['aligned' => 'Selaras', 'different' => 'Berbeda', 'none' => 'Belum diisi'][$m->aspiration] }}</td>
				<td class="small">{{ $m->idp_status ? (\App\Domain\Development\Idps::STATUS[$m->idp_status] ?? $m->idp_status) : 'Belum ada' }}@if($m->idp_progress !== null) · {{ $m->idp_progress }}%@endif</td>
				@if($sensitive)<td class="small">{{ $m->latest_box ? $m->latest_box.'. '.$boxLabels[$m->latest_box] : '—' }}@if($m->flight_risk)<div>Risiko keluar: <strong>{{ $levels[$m->flight_risk] }}</strong></div>@endif</td>@endif
				<td class="small">{{ $m->next_review_date }}@if($m->review_overdue) <span class="badge bg-danger">lewat</span>@endif</td>
				@if($canEdit)
					<td><details><summary class="small text-primary">Tinjau / keluarkan</summary>
						<form method="POST" action="{{ url('talent/pools/members/'.$m->id) }}" class="mt-1" style="min-width: 240px">@csrf @method('PUT')
							<select name="readiness" class="form-select form-select-sm mb-1" aria-label="Kesiapan"><option value="">Kesiapan (tinjau ulang)</option>@foreach($horizons as $k => $l)<option value="{{ $k }}" @selected($m->readiness === $k)>{{ $l }}</option>@endforeach</select>
							<input name="note" class="form-control form-control-sm mb-1" maxlength="1000" placeholder="Catatan / alasan keluar" aria-label="Catatan">
							<div class="d-flex gap-1"><button name="action" value="review" class="btn btn-sm btn-outline-primary">Tinjau ulang</button>
								<button name="action" value="exit" class="btn btn-sm btn-outline-danger" onclick="return confirm('Keluarkan dari pool?')">Keluarkan</button></div>
						</form></details></td>
				@endif
			</tr>
		@empty
			<tr><td colspan="8" class="text-muted small">Belum ada anggota aktif.</td></tr>
		@endforelse
		</tbody>
	</table></div>
</div></div>

<div class="row">
	@if($canEdit && $pool->is_active)
		<div class="col-lg-6">
			<form method="POST" action="{{ url('talent/pools/'.$pool->id.'/nominate') }}" class="card"><div class="card-body">
				@csrf
				<h5 class="card-title">Nominasikan</h5>
				@if($candidates->isNotEmpty())
					<p class="small text-muted mb-1">Talenta kunci (kotak 6/8/9) pada talent review sah terakhir:</p>
					<div class="border rounded p-2 mb-2" style="max-height: 200px; overflow-y: auto">
						@foreach($candidates as $c)
							<div class="form-check small"><input class="form-check-input" type="checkbox" name="candidates[]" id="c{{ $loop->index }}" value="{{ $c->employee_id }}:{{ $c->entry_id }}">
								<label class="form-check-label" for="c{{ $loop->index }}">{{ $c->full_name }} <span class="text-muted">— kotak {{ $c->box }} ({{ $c->review_name }})</span></label></div>
						@endforeach
					</div>
				@endif
				<label class="form-label small" for="employee_ids">Nominasi manual</label>
				<select id="employee_ids" name="employee_ids[]" class="form-select form-select-sm" multiple size="4">@foreach($employees as $e)<option value="{{ $e->id }}">{{ $e->full_name }} ({{ $e->employee_number }})</option>@endforeach</select>
				<label class="form-label small mt-2" for="reason">Alasan nominasi</label>
				<textarea id="reason" name="reason" class="form-control form-control-sm" rows="2" required minlength="10" maxlength="2000" placeholder="Kaitkan dengan kriteria masuk pool"></textarea>
				<button class="btn btn-sm btn-primary mt-2"><i class="fa fa-user-plus"></i> Nominasikan</button>
			</div></form>
		</div>
	@endif
	<div class="col-lg-6">
		<div class="card"><div class="card-body">
			<h5 class="card-title">Riwayat keputusan</h5>
			<ul class="small mb-0 list-unstyled">
				@forelse($events as $ev)
					<li class="mb-1"><span class="text-muted">{{ \Carbon\Carbon::parse($ev->created_at)->translatedFormat('d M Y') }}</span> · {{ $ev->full_name }}:
						{{ $ev->from_status ? ($statuses[$ev->from_status] ?? $ev->from_status).' → ' : '' }}<strong>{{ $statuses[$ev->to_status] ?? $ev->to_status }}</strong>
						@if($ev->readiness) ({{ $horizons[$ev->readiness] }})@endif @if($ev->note) — {{ \Illuminate\Support\Str::limit($ev->note, 80) }}@endif
						<span class="text-muted">oleh {{ trim($ev->first_name.' '.$ev->last_name) }}</span></li>
				@empty
					<li class="text-muted">Belum ada riwayat.</li>
				@endforelse
			</ul>
			@if($past->isNotEmpty())<p class="small text-muted mt-2 mb-0">Mantan anggota/ditolak: {{ $past->pluck('full_name')->implode(', ') }}</p>@endif
		</div></div>
	</div>
</div>
@endsection
