@extends('layouts.app')

@section('content')
<div class="page-titles d-flex justify-content-between align-items-center">
	<h4 class="mb-0"> {{ $pageTitle }} <small>{{ $pageNote }}</small></h4>
	<a href="{{ url('competency/job-profiles/'.$position->job_id) }}" class="btn btn-sm btn-outline-secondary">Profil job</a>
</div>

@include('core.tenants.partials.flash')

<div class="row">
	<div class="col-lg-7">
		<form method="POST" action="{{ url('competency/positions/'.$position->id) }}"
			x-data="{ items: @js(array_values($rows)), options: @js($competencies), crit: @js($criticality),
				add() { this.items.push({ competency_id: '', required_level: '', criticality: '', is_excluded: false, reason: '' }) },
				remove(i) { this.items.splice(i, 1) } }">
			@csrf @method('PUT')
			<div class="card"><div class="card-body">
				<h5 class="card-title">Override</h5>
				<p class="small text-muted">Kosongkan level/bobot untuk mengikuti job. Centang "Kecualikan" bila kompetensi job tidak berlaku di posisi ini. Kompetensi yang tidak ada di profil job menjadi tambahan khusus posisi. Alasan wajib diisi.</p>
				<template x-for="(row, i) in items" :key="i">
					<div class="row g-2 align-items-end border-bottom pb-2 mb-2">
						<div class="col-md-5"><label class="form-label small">Kompetensi</label>
							<select class="form-select form-select-sm" required :name="`items[${i}][competency_id]`" x-model="row.competency_id" @disabled(!$canEdit)>
								<option value="">Pilih</option>
								<template x-for="(label, id) in options" :key="id"><option :value="id" x-text="label" :selected="String(row.competency_id) === String(id)"></option></template>
							</select></div>
						<div class="col-md-2"><label class="form-label small">Level</label>
							<select class="form-select form-select-sm" :name="`items[${i}][required_level]`" x-model="row.required_level" :disabled="row.is_excluded || {{ $canEdit ? 'false' : 'true' }}">
								<option value="">ikut job</option>
								<template x-for="l in [1,2,3,4,5]" :key="l"><option :value="l" x-text="l" :selected="Number(row.required_level) === l"></option></template>
							</select></div>
						<div class="col-md-3"><label class="form-label small">Bobot</label>
							<select class="form-select form-select-sm" :name="`items[${i}][criticality]`" x-model="row.criticality" :disabled="row.is_excluded || {{ $canEdit ? 'false' : 'true' }}">
								<option value="">ikut job</option>
								<template x-for="(label, w) in crit" :key="w"><option :value="w" x-text="label" :selected="String(row.criticality) === String(w)"></option></template>
							</select></div>
						<div class="col-md-2"><div class="form-check">
							<input type="hidden" :name="`items[${i}][is_excluded]`" value="0">
							<input class="form-check-input" type="checkbox" value="1" :id="`ex${i}`" :name="`items[${i}][is_excluded]`" x-model="row.is_excluded" @disabled(!$canEdit)>
							<label class="form-check-label small" :for="`ex${i}`">Kecualikan</label></div></div>
						<div class="col-md-10"><input class="form-control form-control-sm" required maxlength="255" placeholder="Alasan override" :name="`items[${i}][reason]`" x-model="row.reason" @disabled(!$canEdit)></div>
						<div class="col-md-2 text-end">@if($canEdit)<button type="button" class="btn btn-sm btn-outline-danger" @click="remove(i)">×</button>@endif</div>
					</div>
				</template>
				<p class="text-muted small" x-show="items.length === 0">Tidak ada override: posisi ini mengikuti profil job sepenuhnya.</p>
				@if($canEdit)<button type="button" class="btn btn-sm btn-outline-primary" @click="add()">Tambah override</button>@endif
			</div></div>
			@if($canEdit)<button type="submit" class="btn btn-sm btn-primary">Simpan override</button>@endif
		</form>
	</div>

	<div class="col-lg-5">
		<div class="card"><div class="card-body">
			<h5 class="card-title">Profil efektif posisi</h5>
			<table class="table table-sm mb-0">
				<thead><tr><th>Kompetensi</th><th class="text-end">Level</th><th>Bobot</th><th></th></tr></thead>
				@forelse($effective as $e)
					<tr><td><code>{{ $e->code }}</code> {{ $e->name }}</td><td class="text-end">{{ $e->required_level }}</td><td>{{ $criticality[$e->criticality] }}</td>
						<td>@if($e->source !== 'job')<span class="badge bg-info">{{ $e->source === 'override' ? 'override' : 'tambahan' }}</span>@endif</td></tr>
				@empty
					<tr><td colspan="4" class="text-muted">Belum ada kompetensi.</td></tr>
				@endforelse
			</table>
		</div></div>
	</div>
</div>
@endsection
