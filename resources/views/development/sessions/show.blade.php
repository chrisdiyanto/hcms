@extends('layouts.app')

@section('content')
@php
	$badge = ['registered' => 'bg-secondary', 'attended' => 'bg-info', 'completed' => 'bg-success', 'no_show' => 'bg-danger', 'cancelled' => 'bg-light text-dark'];
	$sbadge = ['planned' => 'bg-secondary', 'ongoing' => 'bg-info', 'completed' => 'bg-success', 'cancelled' => 'bg-light text-dark'];
	$active = $participants->where('status', '!=', 'cancelled');
@endphp
<div class="page-titles d-flex flex-wrap justify-content-between align-items-center gap-2">
	<h4 class="mb-0"> {{ $pageTitle }} <small>{{ $pageNote }}</small> <span class="badge {{ $sbadge[$session->status] }}">{{ $sessionStatuses[$session->status] }}</span></h4>
	<a href="{{ url('development/sessions?year='.substr($session->start_date, 0, 4)) }}" class="btn btn-sm btn-outline-secondary">Kembali</a>
</div>

@include('core.tenants.partials.flash')
@if($errors->any())<div class="alert alert-danger small"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif

<div class="row">
	<div class="col-lg-4">
		<div class="card"><div class="card-body">
			<dl class="row small mb-0">
				<dt class="col-5">Intervensi</dt><dd class="col-7">{{ $session->intervention_name }} ({{ $types[$session->intervention_type] ?? '' }})</dd>
				<dt class="col-5">Tanggal</dt><dd class="col-7">{{ $session->start_date }} s.d. {{ $session->end_date }}</dd>
				<dt class="col-5">Moda / lokasi</dt><dd class="col-7">{{ $modes[$session->mode] }}@if($session->location) · {{ $session->location }}@endif</dd>
				<dt class="col-5">Pengajar</dt><dd class="col-7">{{ $session->trainer ?? '—' }}</dd>
				<dt class="col-5">Peserta</dt><dd class="col-7">{{ $active->count() }}@if($session->capacity) / {{ $session->capacity }}@endif · lulus {{ $active->where('status', 'completed')->count() }}</dd>
				<dt class="col-5">Pre/post-test</dt><dd class="col-7">{{ $session->has_test ? 'Ya' : 'Tidak' }}</dd>
				@if($session->cost_total !== null)<dt class="col-5">Biaya realisasi</dt><dd class="col-7">Rp {{ number_format($session->cost_total, 0, ',', '.') }}</dd>@endif
			</dl>
		</div></div>
		@if($canEdit)
			<form method="POST" action="{{ url('development/sessions/'.$session->id.'/enroll') }}" class="card"><div class="card-body">
				@csrf
				<h5 class="card-title">Daftarkan peserta</h5>
				@if($candidates->isNotEmpty())
					<p class="small text-muted mb-1">Kandidat dari IDP yang disetujui dan kebutuhan TNA untuk kompetensi intervensi ini:</p>
					<div class="border rounded p-2 mb-2" style="max-height: 220px; overflow-y: auto">
						@foreach($candidates as $c)
							<div class="form-check small"><input class="form-check-input" type="checkbox" name="candidates[]" id="cand{{ $loop->index }}"
								value="{{ $c->employee_id }}:{{ $c->idp_activity_id }}:{{ $c->training_need_id }}">
								<label class="form-check-label" for="cand{{ $loop->index }}">{{ $c->full_name }} <span class="badge bg-light text-dark">{{ $c->source }}</span>
									<span class="text-muted">{{ \Illuminate\Support\Str::limit($c->detail, 40) }}</span></label></div>
						@endforeach
					</div>
				@endif
				<label class="form-label small" for="employee_ids">Pegawai lain</label>
				<select id="employee_ids" name="employee_ids[]" class="form-select form-select-sm" multiple size="5">
					@foreach($employees as $e)<option value="{{ $e->id }}">{{ $e->full_name }} ({{ $e->employee_number }})</option>@endforeach
				</select>
				<button class="btn btn-sm btn-primary mt-2"><i class="fa fa-user-plus"></i> Daftarkan</button>
			</div></form>
			<form method="POST" action="{{ url('development/sessions/'.$session->id.'/complete') }}" class="card"
				onsubmit="return confirm('Tutup kelas? Pastikan kehadiran semua peserta sudah dicatat.')"><div class="card-body">
				@csrf
				<h5 class="card-title">Tutup kelas</h5>
				<label class="form-label small" for="cost_total">Biaya realisasi kelas (Rp)</label>
				<input id="cost_total" type="number" min="0" step="1000" name="cost_total" class="form-control form-control-sm" value="{{ $session->cost_total }}">
				<button class="btn btn-sm btn-success mt-2"><i class="fa fa-check"></i> Tutup kelas</button>
			</div></form>
		@endif
	</div>
	<div class="col-lg-8">
		<div class="card"><div class="card-body">
			<h5 class="card-title">Peserta & kehadiran</h5>
			<p class="small text-muted">Status <strong>Lulus</strong> otomatis menyelesaikan aktivitas IDP terkait (dengan bukti kelulusan) dan menutup kebutuhan TNA-nya. Survei reaksi diisi peserta sendiri di Layanan Mandiri › Pelatihan Saya.</p>
			@if($canEdit)
				@foreach($participants as $p)
					<form id="pf{{ $p->id }}" method="POST" action="{{ url('development/sessions/participants/'.$p->id) }}">@csrf @method('PUT')</form>
				@endforeach
			@endif
			<div class="table-responsive"><table class="table table-sm align-middle mb-0">
				<thead><tr><th>Peserta</th><th>Status</th><th class="text-end">Hadir %</th>@if($session->has_test)<th class="text-end">Pre</th><th class="text-end">Post</th>@endif<th>Sertifikat</th><th class="text-end">Reaksi</th>@if($canEdit)<th></th>@endif</tr></thead>
				<tbody>
				@forelse($participants as $p)
					<tr>
						<td>{{ $p->full_name }}<div class="small text-muted">{{ $p->employee_number }}@if($p->idp_activity_id) · IDP @endif @if($p->training_need_id) · TNA @endif</div></td>
						@if($canEdit)
							<td><select form="pf{{ $p->id }}" name="status" class="form-select form-select-sm" aria-label="Status {{ $p->full_name }}">@foreach($participantStatuses as $k => $l)<option value="{{ $k }}" @selected($p->status === $k)>{{ $l }}</option>@endforeach</select></td>
							<td><input form="pf{{ $p->id }}" type="number" name="attendance_pct" min="0" max="100" class="form-control form-control-sm text-end" style="width: 70px" value="{{ $p->attendance_pct }}" aria-label="Kehadiran"></td>
							@if($session->has_test)
								<td><input form="pf{{ $p->id }}" type="number" name="pre_score" min="0" max="100" step="0.01" class="form-control form-control-sm text-end" style="width: 70px" value="{{ $p->pre_score }}" aria-label="Pre-test"></td>
								<td><input form="pf{{ $p->id }}" type="number" name="post_score" min="0" max="100" step="0.01" class="form-control form-control-sm text-end" style="width: 70px" value="{{ $p->post_score }}" aria-label="Post-test"></td>
							@endif
							<td><input form="pf{{ $p->id }}" name="certificate_no" class="form-control form-control-sm" style="width: 120px" value="{{ $p->certificate_no }}" maxlength="100" aria-label="Nomor sertifikat"></td>
						@else
							<td><span class="badge {{ $badge[$p->status] }}">{{ $participantStatuses[$p->status] }}</span></td>
							<td class="text-end">{{ $p->attendance_pct !== null ? $p->attendance_pct.'%' : '—' }}</td>
							@if($session->has_test)<td class="text-end">{{ $p->pre_score ?? '—' }}</td><td class="text-end">{{ $p->post_score ?? '—' }}</td>@endif
							<td class="small">{{ $p->certificate_no ?? '—' }}</td>
						@endif
						<td class="text-end small" title="{{ $p->reaction_comment }}">{{ $p->reaction_score !== null ? number_format($p->reaction_score, 1, ',', '.').' / 5' : '—' }}</td>
						@if($canEdit)<td><button form="pf{{ $p->id }}" class="btn btn-sm btn-outline-primary">Simpan</button></td>@endif
					</tr>
				@empty
					<tr><td colspan="8" class="text-muted small">Belum ada peserta.</td></tr>
				@endforelse
				</tbody>
			</table></div>
		</div></div>
	</div>
</div>
@endsection
