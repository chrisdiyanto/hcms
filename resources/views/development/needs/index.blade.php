@extends('layouts.app')

@section('content')
@php
	$badge = ['high' => 'bg-danger', 'medium' => 'bg-warning text-dark', 'low' => 'bg-secondary'];
	$link = fn (array $extra) => url('development/needs?'.http_build_query(array_filter(array_merge(request()->only(['year', 'status', 'source', 'priority', 'unit', 'q']), $extra, ['page' => null]), fn ($v) => $v !== null && $v !== '')));
@endphp
<div class="page-titles d-flex flex-wrap justify-content-between align-items-center gap-2">
	<h4 class="mb-0"> {{ $pageTitle }} <small>{{ $pageNote }}</small></h4>
	<div class="d-flex flex-wrap gap-2">
		<a href="{{ url('development/needs/report?year='.$year) }}" class="btn btn-sm btn-outline-secondary"><i class="fa fa-chart-area"></i> Laporan TNA</a>
		@if($canEdit)
			<form method="POST" action="{{ url('development/needs/refresh') }}">@csrf <input type="hidden" name="year" value="{{ $year }}">
				<button class="btn btn-sm btn-outline-primary" title="Ambil ulang saran dari gap kompetensi ≤ −1 dan PIP berjalan"><i class="fa fa-sync-alt"></i> Perbarui saran</button></form>
			<a href="{{ url('development/needs/create?year='.$year) }}" class="btn btn-sm btn-primary"><i class="fa fa-plus"></i> Kebutuhan organisasi</a>
		@endif
	</div>
</div>

@include('core.tenants.partials.flash')
@if($errors->any())<div class="alert alert-danger small"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif

<ul class="nav nav-pills flex-wrap gap-1 mb-3">
	@foreach($statuses as $key => $label)
		<li class="nav-item"><a class="nav-link {{ $status === $key ? 'active' : '' }}" href="{{ $link(['status' => $key]) }}">{{ $label }} <span class="badge bg-light text-dark">{{ $counts[$key] ?? 0 }}</span></a></li>
	@endforeach
</ul>
@if($status === 'suggested')
	<div class="alert alert-info small mb-3"><i class="fa fa-info-circle"></i> Saran otomatis dibuat dari gap kompetensi ≤ −1 pada posisi saat ini dan dari PIP yang berjalan. Saran <strong>belum</strong> dihitung sebagai kebutuhan TNA sampai disetujui. Diperbarui otomatis setiap Minggu.</div>
@endif

<form method="GET" class="card"><div class="card-body d-flex flex-wrap gap-2 align-items-end">
	<input type="hidden" name="status" value="{{ $status }}">
	<div><label class="form-label small mb-0" for="f-year">Tahun</label><input id="f-year" type="number" name="year" value="{{ $year }}" class="form-control form-control-sm" style="width: 90px"></div>
	<div><label class="form-label small mb-0" for="f-source">Sumber</label><select id="f-source" name="source" class="form-select form-select-sm"><option value="">Semua</option>@foreach($sources as $k => $l)<option value="{{ $k }}" @selected(request('source') === $k)>{{ $l }}</option>@endforeach</select></div>
	<div><label class="form-label small mb-0" for="f-priority">Prioritas</label><select id="f-priority" name="priority" class="form-select form-select-sm"><option value="">Semua</option>@foreach($priorities as $k => $l)<option value="{{ $k }}" @selected(request('priority') === $k)>{{ $l }}</option>@endforeach</select></div>
	<div><label class="form-label small mb-0" for="f-unit">Unit</label><select id="f-unit" name="unit" class="form-select form-select-sm" style="max-width: 220px"><option value="">Semua unit</option>@foreach($units as $k => $l)<option value="{{ $k }}" @selected(request('unit') == $k)>{{ $l }}</option>@endforeach</select></div>
	<div><label class="form-label small mb-0" for="f-q">Cari</label><input id="f-q" name="q" value="{{ request('q') }}" class="form-control form-control-sm" placeholder="Pegawai atau topik"></div>
	<button class="btn btn-sm btn-outline-primary">Terapkan</button>
</div></form>

<form method="POST" action="{{ url('development/needs/review') }}" x-data="{ all: false }">
	@csrf
	<div class="card"><div class="card-body">
		<div class="table-responsive"><table class="table table-sm align-middle mb-0">
			<thead><tr>
				@if($canApprove && in_array($status, ['suggested', 'proposed'], true))<th style="width: 28px"><input type="checkbox" class="form-check-input" x-model="all" @change="$root.querySelectorAll('[data-pick]').forEach(c => c.checked = all)" aria-label="Pilih semua"></th>@endif
				<th>Pegawai</th><th>Kebutuhan</th><th>Sumber</th><th>Prioritas</th><th>Tindak lanjut</th><th></th>
			</tr></thead>
			<tbody>
			@forelse($rows as $r)
				<tr>
					@if($canApprove && in_array($status, ['suggested', 'proposed'], true))<td><input type="checkbox" class="form-check-input" name="ids[]" value="{{ $r->id }}" data-pick aria-label="Pilih {{ $r->full_name }}"></td>@endif
					<td>{{ $r->full_name }}<div class="small text-muted">{{ $r->employee_number }} · {{ $r->unit_name ?? '—' }}</div></td>
					<td>@if($r->competency_code)<code>{{ $r->competency_code }}</code> @endif{{ $r->topic }}
						<div class="small text-muted">{{ \Illuminate\Support\Str::limit($r->reason, 140) }}</div>
						@if($r->review_note)<div class="small"><span class="text-muted">Catatan tinjauan:</span> {{ $r->review_note }}</div>@endif</td>
					<td class="small">{{ $sources[$r->source] }}</td>
					<td><span class="badge {{ $badge[$r->priority] }}">{{ $priorities[$r->priority] }}</span>
						@if($r->priority_score !== null)<div class="small text-muted">skor {{ number_format($r->priority_score, 2, ',', '.') }}</div>@endif</td>
					<td class="small">{{ $r->suggested_intervention ?? '—' }}@if($r->due_date)<div class="text-muted">tenggat {{ $r->due_date }}</div>@endif</td>
					<td class="text-end">@if($canEdit && !in_array($r->status, ['rejected', 'closed'], true))<a href="{{ url('development/needs/'.$r->id.'/edit') }}" class="btn btn-sm btn-outline-primary">Rincian</a>@endif</td>
				</tr>
			@empty
				<tr><td colspan="7" class="text-muted small">Tidak ada kebutuhan dengan status ini.</td></tr>
			@endforelse
			</tbody>
		</table></div>
		<div class="mt-2">{{ $rows->links() }}</div>
	</div></div>
	@if($canApprove && in_array($status, ['suggested', 'proposed'], true) && $rows->isNotEmpty())
		<div class="card"><div class="card-body d-flex flex-wrap gap-2 align-items-end">
			<div class="flex-grow-1"><label class="form-label small mb-0" for="review_note">Catatan tinjauan (wajib bila menolak)</label>
				<input id="review_note" name="review_note" class="form-control form-control-sm" maxlength="500"></div>
			<button name="decision" value="approved" class="btn btn-sm btn-success"><i class="fa fa-check"></i> Setujui terpilih</button>
			<button name="decision" value="rejected" class="btn btn-sm btn-outline-danger"><i class="fa fa-times"></i> Tolak terpilih</button>
		</div></div>
	@endif
</form>
@endsection
