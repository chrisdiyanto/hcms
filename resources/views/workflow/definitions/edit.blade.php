@extends('layouts.app')

@section('content')
<div class="page-titles"><h4 class="mb-0"> {{ $pageTitle }} <small>{{ $pageNote }}</small></h4></div>

@include('core.tenants.partials.flash')

<form method="POST" action="{{ url('workflow/definitions/'.$code) }}"
	x-data="{ seq: 0, steps: [], types: @js($types), roles: @js($roles), positions: @js($positions),
		{{-- _uid: kunci baris yang stabil saat langkah dipindah (tidak ikut terkirim) --}}
		init() { this.steps = @js(array_values($steps)).map(s => ({ ...s, _uid: ++this.seq })) },
		add() { this.steps.push({ _uid: ++this.seq, name: '', approver: { type: 'supervisor', levels: 1 }, sla_hours: 48 }) },
		remove(i) { this.steps.splice(i, 1) },
		move(i, d) { const j = i + d; if (j < 0 || j >= this.steps.length) return; const s = this.steps.splice(i, 1)[0]; this.steps.splice(j, 0, s) } }">
	@csrf @method('PUT')
	<div class="card"><div class="card-body">
		<p class="small text-muted">Langkah dijalankan berurutan. Approver "Atasan" ditentukan dari posisi pengaju; posisi kosong otomatis naik ke atasan berikutnya,
			dan bila tidak ada sama sekali tugas jatuh ke HR Administrator. Menyimpan membuat versi baru; pengajuan yang sedang berjalan tidak berubah.</p>

		<template x-for="(step, i) in steps" :key="step._uid">
			<div class="row g-2 align-items-end border-bottom pb-2 mb-2">
				<div class="col-md-1 fw-bold" x-text="`Langkah ${i + 1}`"></div>
				<div class="col-md-2"><label class="form-label small">Nama langkah</label>
					<input class="form-control form-control-sm" required maxlength="150" :name="`steps[${i}][name]`" x-model="step.name"></div>
				<div class="col-md-2"><label class="form-label small">Approver</label>
					<select class="form-select form-select-sm" :name="`steps[${i}][approver][type]`" x-model="step.approver.type">
						<template x-for="(label, key) in types" :key="key"><option :value="key" x-text="label" :selected="step.approver.type === key"></option></template>
					</select></div>
				<div class="col-md-3">
					<template x-if="step.approver.type === 'supervisor'">
						<div><label class="form-label small">Tingkat ke atas</label>
							<input type="number" min="1" max="5" class="form-control form-control-sm" :name="`steps[${i}][approver][levels]`" x-model="step.approver.levels"></div>
					</template>
					<template x-if="step.approver.type === 'role'">
						<div><label class="form-label small">Peran</label>
							<select class="form-select form-select-sm" :name="`steps[${i}][approver][role]`" x-model="step.approver.role">
								<template x-for="(label, key) in roles" :key="key"><option :value="key" x-text="label" :selected="step.approver.role === key"></option></template>
							</select></div>
					</template>
					<template x-if="step.approver.type === 'position'">
						<div><label class="form-label small">Posisi</label>
							<select class="form-select form-select-sm" :name="`steps[${i}][approver][position_id]`" x-model="step.approver.position_id">
								<template x-for="(label, key) in positions" :key="key"><option :value="key" x-text="label" :selected="String(step.approver.position_id) === String(key)"></option></template>
							</select></div>
					</template>
				</div>
				<div class="col-md-2"><label class="form-label small">SLA (jam)</label>
					<input type="number" min="1" max="720" class="form-control form-control-sm" :name="`steps[${i}][sla_hours]`" x-model="step.sla_hours"></div>
				<div class="col-md-2 text-end text-nowrap">
					<button type="button" class="btn btn-sm btn-light" @click="move(i, -1)" :disabled="i === 0" title="Naikkan langkah">↑</button>
					<button type="button" class="btn btn-sm btn-light" @click="move(i, 1)" :disabled="i === steps.length - 1" title="Turunkan langkah">↓</button>
					<button type="button" class="btn btn-sm btn-outline-danger" @click="remove(i)" :disabled="steps.length === 1" title="Hapus langkah">×</button>
				</div>
			</div>
		</template>
		<button type="button" class="btn btn-sm btn-outline-primary" @click="add()" :disabled="steps.length >= 6">Tambah langkah</button>
	</div></div>

	<button type="submit" class="btn btn-sm btn-primary mt-2">Simpan versi baru</button>
	<a href="{{ url('workflow/definitions') }}" class="btn btn-sm btn-outline-secondary mt-2">Batal</a>
</form>
@endsection
