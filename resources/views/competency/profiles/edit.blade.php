@extends('layouts.app')

@section('content')
<div class="page-titles d-flex justify-content-between align-items-center">
	<h4 class="mb-0"> {{ $pageTitle }} <small>{{ $pageNote }}</small></h4>
	<a href="{{ url('competency/job-profiles') }}" class="btn btn-sm btn-outline-secondary">Kembali</a>
</div>

@include('core.tenants.partials.flash')

@unless($version)
	<div class="alert alert-warning">Belum ada kamus kompetensi yang berlaku.</div>
@else
<form method="POST" action="{{ url('competency/job-profiles/'.$job->id) }}"
	x-data="{ items: @js(array_values($rows)), options: @js($competencies), crit: @js($criticality),
		add() { this.items.push({ competency_id: '', required_level: 3, criticality: 2 }) },
		remove(i) { this.items.splice(i, 1) },
		get totalWeight() { return this.items.reduce((s, r) => s + Number(r.criticality || 0), 0) },
		get critical() { return this.items.filter(r => Number(r.criticality) === 3).length },
		duplicate(id) { return id !== '' && this.items.filter(r => String(r.competency_id) === String(id)).length > 1 } }">
	@csrf @method('PUT')
	<div class="card"><div class="card-body">
		<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
			<p class="small text-muted mb-0">Standar ini diwarisi semua posisi pada job {{ $job->code }}. Bobot kritikalitas dipakai menghitung Job Fit Index: kritis = 3, penting = 2, pendukung = 1.</p>
			<div class="small text-nowrap"><span x-text="items.length"></span> kompetensi · <span x-text="critical"></span> kritis · total bobot <strong x-text="totalWeight"></strong></div>
		</div>

		<div class="table-responsive"><table class="table table-sm align-middle mb-2">
			<thead><tr><th>Kompetensi</th><th style="width: 140px">Level wajib</th><th style="width: 160px">Kritikalitas</th><th style="width: 60px"></th></tr></thead>
			<tbody>
				<template x-for="(row, i) in items" :key="i">
					<tr :class="duplicate(row.competency_id) ? 'table-danger' : ''">
						<td><select class="form-select form-select-sm" required :name="`items[${i}][competency_id]`" x-model="row.competency_id" @disabled(!$canEdit)>
							<option value="">Pilih kompetensi</option>
							<template x-for="(label, id) in options" :key="id"><option :value="id" x-text="label" :selected="String(row.competency_id) === String(id)"></option></template>
						</select></td>
						<td><select class="form-select form-select-sm" :name="`items[${i}][required_level]`" x-model="row.required_level" @disabled(!$canEdit)>
							<template x-for="l in [1,2,3,4,5]" :key="l"><option :value="l" x-text="`Level ${l}`" :selected="Number(row.required_level) === l"></option></template>
						</select></td>
						<td><select class="form-select form-select-sm" :name="`items[${i}][criticality]`" x-model="row.criticality" @disabled(!$canEdit)>
							<template x-for="(label, w) in crit" :key="w"><option :value="w" x-text="`${label} (${w})`" :selected="String(row.criticality) === String(w)"></option></template>
						</select></td>
						<td class="text-end">@if($canEdit)<button type="button" class="btn btn-sm btn-outline-danger" @click="remove(i)">×</button>@endif</td>
					</tr>
				</template>
				<tr x-show="items.length === 0"><td colspan="4" class="text-muted">Belum ada kompetensi pada profil ini.</td></tr>
			</tbody>
		</table></div>
		@if($canEdit)<button type="button" class="btn btn-sm btn-outline-primary" @click="add()">Tambah kompetensi</button>@endif
	</div></div>
	@if($canEdit)<button type="submit" class="btn btn-sm btn-primary">Simpan profil</button>@endif
</form>

<div class="card mt-3"><div class="card-body">
	<h5 class="card-title">Posisi yang mewarisi profil ini</h5>
	<table class="table table-sm align-middle mb-0">
		@forelse($positions as $p)
			<tr><td><code>{{ $p->code }}</code> {{ $p->title }} <span class="text-muted small">{{ $p->unit_name }}</span></td>
				<td class="small">@if($p->override_count)<span class="badge bg-info">{{ $p->override_count }} override</span>@else ikut job @endif</td>
				<td class="text-end"><a href="{{ url('competency/positions/'.$p->id) }}" class="btn btn-sm btn-outline-secondary">Override</a></td></tr>
		@empty
			<tr><td class="text-muted">Belum ada posisi untuk job ini.</td></tr>
		@endforelse
	</table>
</div></div>
@endunless
@endsection
