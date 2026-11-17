@extends('layouts.app')

@section('content')
@php
	$badge = ['draft' => 'bg-secondary', 'submitted' => 'bg-info', 'returned' => 'bg-warning text-dark', 'approved' => 'bg-primary', 'completed' => 'bg-success', 'cancelled' => 'bg-light text-dark'];
	$abadge = ['planned' => 'bg-secondary', 'in_progress' => 'bg-info', 'completed' => 'bg-success', 'deferred' => 'bg-warning text-dark', 'cancelled' => 'bg-light text-dark'];
	$editable = $isOwner && in_array($idp->status, ['draft', 'returned'], true);
	$running = in_array($idp->status, ['approved', 'completed'], true);
	$counted = $activities->where('status', '!=', 'cancelled');
	$done = $counted->where('status', 'completed')->count();
	$today = now()->toDateString();
@endphp
<div class="page-titles d-flex flex-wrap justify-content-between align-items-center gap-2">
	<h4 class="mb-0"> {{ $pageTitle }} <small>{{ $pageNote }}</small> <span class="badge {{ $badge[$idp->status] }}">{{ $statuses[$idp->status] }}</span></h4>
	<a href="{{ url('ess/idp?year='.$idp->period_year) }}" class="btn btn-sm btn-outline-secondary">Kembali</a>
</div>

@include('core.tenants.partials.flash')
@if($errors->any())<div class="alert alert-danger small"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif
@if($idp->status === 'returned' && $idp->return_note)<div class="alert alert-warning"><strong>Dikembalikan atasan:</strong> {{ $idp->return_note }}</div>@endif
@if($idp->status === 'submitted')<div class="alert alert-info small">Menunggu persetujuan atasan langsung (Tugas Persetujuan).</div>@endif

@if($editable)
	{{-- ===== Penyusunan (pemilik) ===== --}}
	<form method="POST" action="{{ url('ess/idp/'.$idp->id) }}"
		x-data="{ items: @js(old('items', $activities->map(fn ($a) => ['id' => $a->id, 'title' => $a->title, 'goal' => $a->goal, 'activity_type' => $a->activity_type,
				'competency_id' => $a->competency_id, 'target_level' => $a->target_level, 'intervention_id' => $a->intervention_id, 'due_date' => $a->due_date,
				'cost_estimate' => $a->cost_estimate, 'employee_note' => $a->employee_note, 'source' => $a->training_need_id ? 'TNA' : ($a->idp_suggestion_id ? 'Saran asesmen' : null)])->values())),
			catalog: @js($interventions->keyBy('id')),
			add() { this.items.push({ id: null, title: '', goal: '', activity_type: 'training', competency_id: '', target_level: '', intervention_id: '', due_date: '{{ $idp->period_year }}-12-31', cost_estimate: '', employee_note: '', source: null }) },
			pick(item) { const c = this.catalog[item.intervention_id]; if (!c) return; item.title = item.title || c.name; item.activity_type = c.type; if (c.competency_id) item.competency_id = c.competency_id; if (c.cost_per_participant) item.cost_estimate = c.cost_per_participant } }">
		@csrf @method('PUT')
		<div class="card"><div class="card-body">
			<label class="form-label" for="career_goal">Sasaran karier</label>
			<input id="career_goal" name="career_goal" class="form-control" maxlength="1000" value="{{ old('career_goal', $idp->career_goal) }}" placeholder="mis. Kepala Seksi Risiko dalam 3–5 tahun">
			<div class="form-text">Diambil dari Aspirasi Karier Saya; boleh disesuaikan.</div>
		</div></div>
		<template x-for="(item, i) in items" :key="i">
			<div class="card"><div class="card-body">
				<div class="d-flex justify-content-between align-items-center mb-2">
					<strong>Aktivitas <span x-text="i + 1"></span> <span class="badge bg-light text-dark" x-show="item.source" x-text="'dari ' + item.source"></span></strong>
					<button type="button" class="btn btn-sm btn-outline-danger" @click="items.splice(i, 1)" :disabled="items.length <= 1">Hapus</button>
				</div>
				<input type="hidden" :name="'items['+i+'][id]'" :value="item.id">
				<div class="row g-2">
					<div class="col-md-6"><label class="form-label small" :for="'iv'+i">Dari katalog (opsional)</label>
						<select class="form-select form-select-sm" :id="'iv'+i" :name="'items['+i+'][intervention_id]'" x-model="item.intervention_id" @change="pick(item)">
							<option value="">— Tidak dari katalog —</option>
							@foreach($interventions as $iv)<option value="{{ $iv->id }}">{{ $iv->code }} — {{ $iv->name }}</option>@endforeach
						</select></div>
					<div class="col-md-3"><label class="form-label small" :for="'ty'+i">Jenis</label>
						<select class="form-select form-select-sm" :id="'ty'+i" :name="'items['+i+'][activity_type]'" x-model="item.activity_type">
							@foreach($types as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach</select></div>
					<div class="col-md-3"><label class="form-label small" :for="'du'+i">Tenggat</label>
						<input type="date" class="form-control form-control-sm" :id="'du'+i" :name="'items['+i+'][due_date]'" x-model="item.due_date" required></div>
					<div class="col-md-6"><label class="form-label small" :for="'ti'+i">Aktivitas</label>
						<input class="form-control form-control-sm" :id="'ti'+i" :name="'items['+i+'][title]'" x-model="item.title" maxlength="200" required></div>
					<div class="col-md-6"><label class="form-label small" :for="'go'+i">Sasaran yang diukur</label>
						<input class="form-control form-control-sm" :id="'go'+i" :name="'items['+i+'][goal]'" x-model="item.goal" maxlength="255" required placeholder="mis. mencapai level 4 Manajemen Risiko"></div>
					<div class="col-md-5"><label class="form-label small" :for="'co'+i">Kompetensi</label>
						<select class="form-select form-select-sm" :id="'co'+i" :name="'items['+i+'][competency_id]'" x-model="item.competency_id">
							<option value="">—</option>@foreach($competencies as $c)<option value="{{ $c->id }}">{{ $c->code }} — {{ $c->name }}</option>@endforeach</select></div>
					<div class="col-md-2"><label class="form-label small" :for="'tl'+i">Level target</label>
						<input type="number" min="1" max="5" class="form-control form-control-sm" :id="'tl'+i" :name="'items['+i+'][target_level]'" x-model="item.target_level"></div>
					<div class="col-md-2"><label class="form-label small" :for="'ce'+i">Biaya (Rp)</label>
						<input type="number" min="0" step="1000" class="form-control form-control-sm" :id="'ce'+i" :name="'items['+i+'][cost_estimate]'" x-model="item.cost_estimate"></div>
					<div class="col-md-3"><label class="form-label small" :for="'no'+i">Catatan</label>
						<input class="form-control form-control-sm" :id="'no'+i" :name="'items['+i+'][employee_note]'" x-model="item.employee_note" maxlength="1000"></div>
				</div>
			</div></div>
		</template>
		<div class="d-flex flex-wrap gap-2 mb-3">
			<button type="button" class="btn btn-outline-primary" @click="add()" :disabled="items.length >= 20"><i class="fa fa-plus"></i> Tambah aktivitas</button>
			<button name="action" value="save" class="btn btn-secondary"><i class="fa fa-save"></i> Simpan draft</button>
			<button name="action" value="submit" class="btn btn-primary" onclick="return confirm('Ajukan IDP ke atasan langsung?')"><i class="fa fa-paper-plane"></i> Ajukan ke atasan</button>
		</div>
	</form>
@else
	{{-- ===== Tampilan & pelaksanaan ===== --}}
	<div class="card"><div class="card-body">
		<div class="d-flex flex-wrap gap-4">
			<div><div class="small text-muted">Sasaran karier</div>{{ $idp->career_goal ?? '—' }}</div>
			@if($running)<div><div class="small text-muted">Kemajuan</div><strong>{{ $done }}/{{ $counted->count() }}</strong> aktivitas selesai
				@if($counted->count())<div class="progress mt-1" style="height: 6px; width: 180px"><div class="progress-bar bg-success" style="width: {{ round($done / $counted->count() * 100) }}%"></div></div>@endif</div>@endif
			@if($idp->approved_at)<div><div class="small text-muted">Disetujui</div>{{ \Carbon\Carbon::parse($idp->approved_at)->translatedFormat('d M Y') }}</div>@endif
		</div>
	</div></div>
	@foreach($activities as $a)
		@php($late = in_array($a->status, ['planned', 'in_progress', 'deferred'], true) && $a->due_date < $today)
		<div class="card"><div class="card-body">
			<div class="d-flex flex-wrap justify-content-between gap-2">
				<div>
					<strong>{{ $a->title }}</strong> <span class="badge {{ $abadge[$a->status] }}">{{ $activityStatuses[$a->status] }}</span>
					@if($late)<span class="badge bg-danger">lewat tenggat</span>@endif
					<div class="small text-muted">{{ $types[$a->activity_type] }} · {{ $a->goal }} · tenggat {{ $a->due_date }}
						@if($a->competency_code) · <code>{{ $a->competency_code }}</code>@if($a->target_level) → L{{ $a->target_level }}@endif @endif
						@if($a->intervention_name) · katalog: {{ $a->intervention_name }}@endif
						@if($a->training_need_id) · dari TNA: {{ $needs[$a->training_need_id] ?? '' }}@endif</div>
					@if($a->evidence)<div class="small mt-1"><span class="text-muted">Bukti:</span> {{ $a->evidence }}</div>@endif
					@if($a->certification_name)<div class="small"><span class="text-muted">Sertifikasi:</span> {{ $a->certification_name }}@if($a->certification_expiry) (berlaku s.d. {{ $a->certification_expiry }})@endif</div>@endif
					@if($a->employee_note)<div class="small"><span class="text-muted">Catatan pegawai:</span> {{ $a->employee_note }}</div>@endif
					@if($a->manager_note)<div class="small"><span class="text-muted">Catatan atasan:</span> {{ $a->manager_note }}</div>@endif
				</div>
				<div class="small text-end text-muted">@if($a->cost_estimate)Rencana Rp {{ number_format($a->cost_estimate, 0, ',', '.') }}@endif
					@if($a->cost_actual)<div>Realisasi Rp {{ number_format($a->cost_actual, 0, ',', '.') }}</div>@endif
					@if($a->completed_at)<div>Selesai {{ $a->completed_at }}</div>@endif</div>
			</div>
			@if($idp->status === 'approved' && $isOwner)
				<details class="mt-2"><summary class="small text-primary">Perbarui pelaksanaan</summary>
					<form method="POST" action="{{ url('ess/idp/activities/'.$a->id) }}" class="row g-2 mt-1">@csrf @method('PUT')
						<div class="col-md-3"><select name="status" class="form-select form-select-sm" aria-label="Status aktivitas">@foreach($activityStatuses as $k => $l)<option value="{{ $k }}" @selected($a->status === $k)>{{ $l }}</option>@endforeach</select></div>
						<div class="col-md-3"><input type="date" name="completed_at" class="form-control form-control-sm" value="{{ $a->completed_at }}" aria-label="Tanggal selesai"></div>
						<div class="col-md-3"><input type="number" min="0" step="1000" name="cost_actual" class="form-control form-control-sm" value="{{ $a->cost_actual }}" placeholder="Biaya realisasi" aria-label="Biaya realisasi"></div>
						<div class="col-md-3"><input name="certification_name" class="form-control form-control-sm" value="{{ $a->certification_name }}" placeholder="Nama sertifikat" aria-label="Nama sertifikat"></div>
						<div class="col-md-9"><input name="evidence" class="form-control form-control-sm" value="{{ $a->evidence }}" maxlength="2000" placeholder="Bukti (wajib bila selesai): nomor sertifikat, tautan laporan, catatan mentor" aria-label="Bukti"></div>
						<div class="col-md-3"><input type="date" name="certification_expiry" class="form-control form-control-sm" value="{{ $a->certification_expiry }}" aria-label="Sertifikat berlaku sampai"></div>
						<div class="col-md-9"><input name="employee_note" class="form-control form-control-sm" value="{{ $a->employee_note }}" maxlength="1000" placeholder="Catatan" aria-label="Catatan pegawai"></div>
						<div class="col-md-3"><button class="btn btn-sm btn-primary w-100">Simpan</button></div>
					</form>
				</details>
			@elseif($idp->status === 'approved' && $isManager)
				<form method="POST" action="{{ url('ess/idp/activities/'.$a->id) }}" class="d-flex gap-2 mt-2">@csrf @method('PUT')
					<input name="manager_note" class="form-control form-control-sm" value="{{ $a->manager_note }}" maxlength="1000" placeholder="Catatan atasan" aria-label="Catatan atasan" required>
					<button class="btn btn-sm btn-outline-primary text-nowrap">Simpan catatan</button></form>
			@endif
		</div></div>
	@endforeach
@endif
@endsection
