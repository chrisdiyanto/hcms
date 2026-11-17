@extends('layouts.app')

@section('content')
@php
	$hbadge = ['now' => 'bg-success', '1_2' => 'bg-warning text-dark', '3_plus' => 'bg-danger'];
	$active = $candidates->where('status', 'active');
@endphp
<div class="page-titles d-flex flex-wrap justify-content-between align-items-center gap-2">
	<h4 class="mb-0"> {{ $pageTitle }} <small>{{ $pageNote }}</small>
		@if($plan)<span class="badge {{ $plan->status === 'approved' ? 'bg-success' : 'bg-warning text-dark' }}">{{ $plan->status === 'approved' ? 'Disahkan' : 'Draft — perlu disahkan' }}</span>@endif</h4>
	<div class="d-flex gap-2">
		@if($canApprove)<form method="POST" action="{{ url('talent/succession/'.$position->id.'/approve') }}" onsubmit="return confirm('Sahkan rencana suksesi ini?')">@csrf <button class="btn btn-sm btn-success"><i class="fa fa-check"></i> Sahkan</button></form>@endif
		<a href="{{ url('talent/succession') }}" class="btn btn-sm btn-outline-secondary">Kembali</a>
	</div>
</div>

@include('core.tenants.partials.flash')
@if($errors->any())<div class="alert alert-danger small"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif

<div class="card"><div class="card-body small d-flex flex-wrap gap-4">
	<div><span class="text-muted">Unit</span><div>{{ $position->unit_name }}</div></div>
	<div><span class="text-muted">Dampak</span><div>{{ \App\Domain\Talent\Succession::impactLabel($position->critical_impact) }}</div></div>
	<div><span class="text-muted">Alasan kritis</span><div>{{ $position->critical_reason ?? '—' }}</div></div>
	<div><span class="text-muted">Pemangku</span><div>{{ $position->incumbents ? implode(', ', $position->incumbents) : 'Kosong' }}</div></div>
	<div><span class="text-muted">Risiko kekosongan ≤ 12 bulan</span><div>@forelse($position->risks as $r)<span class="badge bg-danger">{{ $risks[$r] }}</span> @empty Tidak ada @endforelse</div></div>
	@if($plan && $plan->approved_at)<div><span class="text-muted">Disahkan / tinjau</span><div>{{ \Carbon\Carbon::parse($plan->approved_at)->translatedFormat('d M Y') }} · {{ $plan->next_review_date }}</div></div>@endif
</div></div>
<p class="small text-muted">Pemilihan suksesor adalah rencana, bukan keputusan penempatan. Setiap perubahan kandidat mengembalikan rencana ke draft dan perlu disahkan oleh pemegang izin Approve selain pengubah terakhir. Akses halaman ini tercatat di audit.</p>

<div class="card"><div class="card-body">
	<h5 class="card-title">Kandidat suksesor</h5>
	<div class="table-responsive"><table class="table table-sm align-middle mb-0">
		<thead><tr><th>Pegawai</th><th>Peran</th><th>Kesiapan</th><th>Sumber</th><th>Alasan & tindakan</th>@if($canEdit)<th></th>@endif</tr></thead>
		<tbody>
		@forelse($active as $c)
			<tr>
				<td>{{ $c->full_name }}<div class="small text-muted">{{ $c->current_position ?? '—' }}</div>
					@if(($primaryCounts[$c->employee_id] ?? 0) > 1)<span class="badge bg-warning text-dark" title="Suksesor utama di lebih dari satu posisi kritis yang disahkan">kandidat ganda</span>@endif</td>
				<td>{{ $roles[$c->role] }}</td>
				<td><span class="badge {{ $hbadge[$c->readiness] }}">{{ $horizons[$c->readiness] }}</span></td>
				<td class="small">{{ $sources[$c->source] }}</td>
				<td class="small">{{ $c->rationale }}@if($c->development_action)<div class="text-muted">Tindakan: {{ $c->development_action }}</div>@endif</td>
				@if($canEdit)
					<td><details><summary class="small text-primary">Ubah</summary>
						<form method="POST" action="{{ url('talent/succession/candidates/'.$c->id) }}" class="mt-1" style="min-width: 230px">@csrf @method('PUT')
							<div class="d-flex gap-1 mb-1"><select name="role" class="form-select form-select-sm" aria-label="Peran">@foreach($roles as $k => $l)<option value="{{ $k }}" @selected($c->role === $k)>{{ $l }}</option>@endforeach</select>
								<select name="readiness" class="form-select form-select-sm" aria-label="Kesiapan">@foreach($horizons as $k => $l)<option value="{{ $k }}" @selected($c->readiness === $k)>{{ $l }}</option>@endforeach</select></div>
							<input name="development_action" class="form-control form-control-sm mb-1" value="{{ $c->development_action }}" maxlength="500" placeholder="Tindakan pengembangan" aria-label="Tindakan pengembangan">
							<input name="reason" class="form-control form-control-sm mb-1" maxlength="500" placeholder="Alasan perubahan / pengeluaran (wajib)" aria-label="Alasan" required>
							<div class="d-flex gap-1"><button name="action" value="update" class="btn btn-sm btn-outline-primary">Simpan</button>
								<button name="action" value="remove" class="btn btn-sm btn-outline-danger" onclick="return confirm('Keluarkan kandidat?')">Keluarkan</button></div>
						</form></details></td>
				@endif
			</tr>
		@empty
			<tr><td colspan="6" class="text-muted small">Belum ada kandidat suksesor.</td></tr>
		@endforelse
		</tbody>
	</table></div>
	@if($candidates->where('status', 'removed')->isNotEmpty())
		<p class="small text-muted mt-2 mb-0">Pernah dikeluarkan: @foreach($candidates->where('status', 'removed') as $c){{ $c->full_name }} ({{ $c->removal_reason }})@if(!$loop->last), @endif @endforeach</p>
	@endif
</div></div>

@if($canEdit)
	<form method="POST" action="{{ url('talent/succession/'.$position->id.'/candidates') }}" class="card"
		x-data="{ pick: '', suggestions: @js($suggestions->keyBy('employee_id')), employee: '', readiness: '1_2', source: 'manual', member: '',
			use(s) { this.employee = String(s.employee_id); this.readiness = s.readiness; this.source = s.source; this.member = s.pool_member_id ?? '' } }"><div class="card-body">
		@csrf
		<h5 class="card-title">Tambah kandidat</h5>
		@if($suggestions->isNotEmpty())
			<p class="small text-muted mb-1">Usulan dari Kesiapan Posisi (siap sekarang / 1–2 tahun) dan talent pool yang menyasar posisi atau rumpunnya:</p>
			<div class="d-flex flex-wrap gap-1 mb-2">
				@foreach($suggestions as $s)
					<button type="button" class="btn btn-sm btn-outline-secondary" @click="use(suggestions[{{ $s->employee_id }}])">{{ $s->full_name }} · {{ $horizons[$s->readiness] }} · {{ $s->detail }}</button>
				@endforeach
			</div>
		@endif
		<input type="hidden" name="talent_pool_member_id" :value="member">
		<div class="row g-2">
			<div class="col-md-4"><label class="form-label small" for="employee_id">Pegawai</label>
				<select id="employee_id" name="employee_id" class="form-select form-select-sm" x-model="employee" required><option value="">— Pilih —</option>
					@foreach($employees as $e)<option value="{{ $e->id }}">{{ $e->full_name }} ({{ $e->employee_number }})</option>@endforeach</select></div>
			<div class="col-md-2"><label class="form-label small" for="role">Peran</label>
				<select id="role" name="role" class="form-select form-select-sm">@foreach($roles as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach</select></div>
			<div class="col-md-3"><label class="form-label small" for="readiness">Kesiapan</label>
				<select id="readiness" name="readiness" class="form-select form-select-sm" x-model="readiness">@foreach($horizons as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach</select></div>
			<div class="col-md-3"><label class="form-label small" for="source">Sumber</label>
				<select id="source" name="source" class="form-select form-select-sm" x-model="source">@foreach($sources as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach</select></div>
			<div class="col-md-7"><label class="form-label small" for="rationale">Alasan</label>
				<input id="rationale" name="rationale" class="form-control form-control-sm" required minlength="10" maxlength="2000" placeholder="Dasar pemilihan: kesiapan, kinerja, potensi, aspirasi"></div>
			<div class="col-md-5"><label class="form-label small" for="development_action">Tindakan pengembangan</label>
				<input id="development_action" name="development_action" class="form-control form-control-sm" maxlength="500" placeholder="mis. rotasi 6 bulan, coaching pemimpin"></div>
		</div>
		<button class="btn btn-sm btn-primary mt-2"><i class="fa fa-user-plus"></i> Tambah kandidat</button>
	</div></form>
@endif
@endsection
