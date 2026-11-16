{{-- Persyaratan jabatan (job_requirements): baris dinamis dengan Alpine.js --}}
<div class="card" x-data="{ items: @js(array_values($requirements)), types: @js($types),
	add() { this.items.push({ type: 'education', description: '', is_mandatory: true }) },
	remove(i) { this.items.splice(i, 1) },
	move(i, d) { const j = i + d; if (j < 0 || j >= this.items.length) return; [this.items[i], this.items[j]] = [this.items[j], this.items[i]] } }">
	<div class="card-body">
		<div class="d-flex justify-content-between align-items-center mb-2">
			<h5 class="card-title mb-0">Persyaratan jabatan</h5>
			<button type="button" class="btn btn-sm btn-outline-primary" @click="add()">Tambah persyaratan</button>
		</div>
		@error('requirements.*')<div class="text-danger small mb-2">{{ $message }}</div>@enderror

		<template x-if="items.length === 0">
			<p class="text-muted mb-0">Belum ada persyaratan.</p>
		</template>

		<template x-for="(item, i) in items" :key="i">
			<div class="row g-2 align-items-center mb-2">
				<div class="col-md-3">
					<select class="form-select form-select-sm" :name="`requirements[${i}][type]`" x-model="item.type">
						<template x-for="(label, key) in types" :key="key"><option :value="key" x-text="label" :selected="item.type === key"></option></template>
					</select>
				</div>
				<div class="col-md-6">
					<input type="text" class="form-control form-control-sm" maxlength="255" required :name="`requirements[${i}][description]`" x-model="item.description" placeholder="mis. S1 Akuntansi/Manajemen Risiko">
				</div>
				<div class="col-md-1">
					<div class="form-check">
						<input class="form-check-input" type="checkbox" value="1" :id="`req-mandatory-${i}`" :name="`requirements[${i}][is_mandatory]`" x-model="item.is_mandatory">
						<label class="form-check-label small" :for="`req-mandatory-${i}`">Wajib</label>
					</div>
				</div>
				<div class="col-md-2 text-end text-nowrap">
					<button type="button" class="btn btn-sm btn-light" @click="move(i, -1)" title="Naik">↑</button>
					<button type="button" class="btn btn-sm btn-light" @click="move(i, 1)" title="Turun">↓</button>
					<button type="button" class="btn btn-sm btn-outline-danger" @click="remove(i)" title="Hapus">×</button>
				</div>
			</div>
		</template>
	</div>
</div>
