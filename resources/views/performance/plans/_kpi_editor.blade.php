{{-- Editor KPI (Alpine). Dipakai di form rencana & form perubahan KPI; elemen pembungkus wajib x-data="kpiEditor(items, rules, canEdit)". --}}
<script>
	window.kpiEditor = function (items, rules, canEdit) {
		const blank = () => ({ title: '', description: '', kpi_type: 'higher', unit: '', target: null, range_min: null, range_max: null,
			milestones: [{ name: '', weight: null }, { name: '', weight: null }], rubric: ['', '', '', '', ''], weight: null, cap: null,
			strategic_objective_id: '', parent_objective_id: '', contribution_weight: null });
		return {
			items: items.length ? items.map(i => Object.assign(blank(), i, { milestones: (i.milestones && i.milestones.length) ? i.milestones : blank().milestones, rubric: (i.rubric && i.rubric.length === 5) ? i.rubric : blank().rubric })) : [blank()],
			rules, canEdit, open: 0,
			get total() { return this.items.reduce((s, i) => s + (parseFloat(i.weight) || 0), 0) },
			get countOk() { return this.items.length >= rules.min_count && this.items.length <= rules.max_count },
			get totalOk() { return Math.abs(this.total - 100) < 0.01 },
			weightOk(i) { const w = parseFloat(i.weight); return w >= rules.min_weight && w <= rules.max_weight },
			msTotal(i) { return i.milestones.reduce((s, m) => s + (parseFloat(m.weight) || 0), 0) },
			add() { if (this.items.length < rules.max_count) { this.items.push(blank()); this.open = this.items.length - 1 } },
			remove(n) { this.items.splice(n, 1); this.open = Math.max(0, n - 1) },
			fmt(v) { return (Math.round(v * 100) / 100).toLocaleString('id-ID') },
		};
	};
</script>
	<div class="card"><div class="card-body py-2 d-flex flex-wrap gap-3 align-items-center small">
		<span :class="countOk ? 'text-success' : 'text-danger'"><strong x-text="items.length"></strong> KPI (aturan {{ $rules['min_count'] }}–{{ $rules['max_count'] }})</span>
		<span :class="totalOk ? 'text-success' : 'text-danger'">Total bobot <strong x-text="fmt(total) + '%'"></strong> (harus 100%)</span>
		<span class="text-muted">Bobot per KPI {{ $rules['min_weight'] }}–{{ $rules['max_weight'] }}%</span>
	</div></div>

	<template x-for="(item, n) in items" :key="n">
		<div class="card mb-2"><div class="card-body">
			<div class="d-flex justify-content-between align-items-center gap-2" role="button" @click="open = open === n ? -1 : n">
				<h6 class="mb-0"><span class="text-muted" x-text="(n + 1) + '.'"></span> <span x-text="item.title || 'KPI baru'"></span></h6>
				<span class="small" :class="weightOk(item) ? 'text-muted' : 'text-danger'" x-text="(item.weight ? fmt(item.weight) : '—') + '%'"></span>
			</div>
			<fieldset x-show="open === n" :disabled="!canEdit" class="mt-2">
				<template x-if="item.id"><input type="hidden" :name="`items[${n}][id]`" :value="item.id"></template>
				<div class="row g-2">
					<div class="col-md-8"><label class="form-label small">Nama KPI</label>
						<input :name="`items[${n}][title]`" x-model="item.title" maxlength="255" class="form-control form-control-sm"></div>
					<div class="col-md-4"><label class="form-label small">Tipe capaian</label>
						<select :name="`items[${n}][kpi_type]`" x-model="item.kpi_type" class="form-select form-select-sm">
							@foreach($types as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach</select></div>
					<div class="col-12"><label class="form-label small">Deskripsi / cara ukur</label>
						<textarea :name="`items[${n}][description]`" x-model="item.description" rows="2" class="form-control form-control-sm"></textarea></div>

					<template x-if="item.kpi_type === 'higher' || item.kpi_type === 'lower'">
						<div class="col-md-4"><label class="form-label small">Target</label>
							<input type="number" step="any" min="0" :name="`items[${n}][target]`" x-model="item.target" class="form-control form-control-sm"></div>
					</template>
					<template x-if="item.kpi_type === 'range'">
						<div class="col-md-4"><label class="form-label small">Rentang target</label>
							<div class="input-group input-group-sm"><input type="number" step="any" :name="`items[${n}][range_min]`" x-model="item.range_min" class="form-control" aria-label="Batas bawah">
								<span class="input-group-text">–</span><input type="number" step="any" :name="`items[${n}][range_max]`" x-model="item.range_max" class="form-control" aria-label="Batas atas"></div></div>
					</template>
					<template x-if="['higher', 'lower', 'range'].includes(item.kpi_type)">
						<div class="col-md-2"><label class="form-label small">Satuan</label>
							<input :name="`items[${n}][unit]`" x-model="item.unit" maxlength="30" class="form-control form-control-sm" placeholder="%, Rp, unit"></div>
					</template>
					<div class="col-md-3"><label class="form-label small">Bobot (%)</label>
						<input type="number" step="0.01" :name="`items[${n}][weight]`" x-model="item.weight" class="form-control form-control-sm" :class="item.weight && !weightOk(item) ? 'is-invalid' : ''"></div>
					<div class="col-md-3"><label class="form-label small">Cap khusus (%)</label>
						<input type="number" step="0.01" :name="`items[${n}][cap]`" x-model="item.cap" class="form-control form-control-sm" placeholder="{{ $period->policy['cap'] + 0 }} (kebijakan)"></div>

					<template x-if="item.kpi_type === 'milestone'">
						<div class="col-12"><label class="form-label small">Milestone (total bobot <span :class="Math.abs(msTotal(item) - 100) < 0.01 ? 'text-success' : 'text-danger'" x-text="fmt(msTotal(item)) + '%'"></span>)</label>
							<template x-for="(m, k) in item.milestones" :key="k">
								<div class="input-group input-group-sm mb-1">
									<input :name="`items[${n}][milestones][${k}][name]`" x-model="m.name" class="form-control" placeholder="Nama milestone" aria-label="Nama milestone">
									<input type="date" :name="`items[${n}][milestones][${k}][due_date]`" x-model="m.due_date" class="form-control" style="max-width:160px" aria-label="Tenggat">
									<input type="number" step="0.01" :name="`items[${n}][milestones][${k}][weight]`" x-model="m.weight" class="form-control" style="max-width:100px" placeholder="Bobot %" aria-label="Bobot milestone">
									<button type="button" class="btn btn-outline-secondary" x-show="canEdit && item.milestones.length > 2" @click="item.milestones.splice(k, 1)" aria-label="Hapus milestone">×</button>
								</div>
							</template>
							<button type="button" class="btn btn-sm btn-link p-0" x-show="canEdit" @click="item.milestones.push({ name: '', weight: null })">+ milestone</button>
						</div>
					</template>
					<template x-if="item.kpi_type === 'qualitative'">
						<div class="col-12"><label class="form-label small">Rubrik per level (wajib 1–5)</label>
							<template x-for="(r, k) in item.rubric" :key="k">
								<div class="input-group input-group-sm mb-1"><span class="input-group-text" x-text="k + 1"></span>
									<input :name="`items[${n}][rubric][${k}]`" x-model="item.rubric[k]" class="form-control" :aria-label="'Rubrik level ' + (k + 1)"></div>
							</template>
						</div>
					</template>

					<div class="col-md-6"><label class="form-label small">Mendukung sasaran strategis</label>
						<select :name="`items[${n}][strategic_objective_id]`" x-model="item.strategic_objective_id" class="form-select form-select-sm"><option value="">—</option>
							@foreach($strategic as $k => $l)<option value="{{ $k }}">{{ \Illuminate\Support\Str::limit($l, 70) }}</option>@endforeach</select></div>
					<div class="col-md-4"><label class="form-label small">Cascade dari KPI atasan</label>
						<select :name="`items[${n}][parent_objective_id]`" x-model="item.parent_objective_id" class="form-select form-select-sm"><option value="">—</option>
							@foreach($parents as $k => $l)<option value="{{ $k }}">{{ \Illuminate\Support\Str::limit($l, 60) }}</option>@endforeach</select>
						@if(!$parents)<div class="form-text">Muncul setelah rencana atasan disetujui.</div>@endif</div>
					<div class="col-md-2" x-show="item.parent_objective_id"><label class="form-label small">Kontribusi (%)</label>
						<input type="number" step="0.01" :name="`items[${n}][contribution_weight]`" x-model="item.contribution_weight" class="form-control form-control-sm"></div>
				</div>
				<button type="button" class="btn btn-sm btn-link text-danger p-0 mt-2" x-show="canEdit" @click="remove(n)">Hapus KPI ini</button>
			</fieldset>
		</div></div>
	</template>
