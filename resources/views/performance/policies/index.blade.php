@extends('layouts.app')

@section('content')
<div class="page-titles d-flex justify-content-between align-items-center flex-wrap gap-2">
	<h4 class="mb-0"> {{ $pageTitle }} <small>{{ $pageNote }}</small></h4>
	<div class="d-flex gap-2">
		@if($versions->isNotEmpty())
			<form method="GET"><select name="version" class="form-select form-select-sm" onchange="this.form.submit()" aria-label="Versi">
				@foreach($versions as $v)<option value="{{ $v->id }}" @selected($policy && $policy->id == $v->id)>v{{ $v->version }} — {{ ['draft' => 'draft', 'active' => 'berlaku', 'retired' => 'pensiun'][$v->status] }}</option>@endforeach
			</select></form>
		@endif
		@if($canDraft)
			<form method="POST" action="{{ url('performance/policies/draft') }}">@csrf<button class="btn btn-sm btn-primary" type="submit">{{ $versions->isEmpty() ? 'Buat kebijakan pertama' : 'Buat draft versi baru' }}</button></form>
		@endif
	</div>
</div>

@include('core.tenants.partials.flash')

@if(!$policy)
	<div class="alert alert-info">Belum ada kebijakan. Buat kebijakan pertama — isinya diisi dari default blueprint (60/30/10, cap 120%, 3–10 KPI berbobot 5–40%, konversi 50/65/75/85/95).</div>
@else
	@if($problems)
		<div class="alert alert-warning"><strong>Belum dapat diterbitkan:</strong><ul class="mb-0 ps-3">@foreach($problems as $p)<li>{{ $p }}</li>@endforeach</ul></div>
	@endif
	<form method="POST" action="{{ url('performance/policies/'.$policy->id) }}"
		x-data="{ w: { what: {{ $config['weights']['what'] }}, how: {{ $config['weights']['how'] }}, contribution: {{ $config['weights']['contribution'] }} },
			get total() { return (+this.w.what || 0) + (+this.w.how || 0) + (+this.w.contribution || 0) } }">
		@csrf @method('PUT')
		<fieldset @disabled(!$canEdit)>
		<div class="row">
			<div class="col-lg-6">
				<div class="card"><div class="card-body">
					<h5 class="card-title">Identitas</h5>
					<div class="mb-2"><label class="form-label" for="f-name">Nama</label>
						<input id="f-name" name="name" required maxlength="150" value="{{ old('name', $policy->name) }}" class="form-control form-control-sm"></div>
					<div class="mb-2"><label class="form-label" for="f-notes">Catatan perubahan</label>
						<textarea id="f-notes" name="notes" rows="2" class="form-control form-control-sm">{{ old('notes', $policy->notes) }}</textarea></div>
					<div class="small text-muted">Status: <strong>{{ ['draft' => 'Draft', 'active' => 'Berlaku', 'retired' => 'Pensiun'][$policy->status] }}</strong>@if($policy->published_at) · terbit {{ $policy->published_at }}@endif</div>
				</div></div>

				<div class="card"><div class="card-body">
					<h5 class="card-title">Bobot skor akhir</h5>
					<div class="row g-2">
						@foreach($dimensions as $key => $label)
							<div class="col-4"><label class="form-label small" for="w-{{ $key }}">{{ $label }} (%)</label>
								<input id="w-{{ $key }}" type="number" step="0.01" min="0" max="100" name="config[weights][{{ $key }}]" x-model="w.{{ $key }}" class="form-control form-control-sm"></div>
						@endforeach
					</div>
					<div class="small mt-1" :class="Math.abs(total - 100) < 0.01 ? 'text-success' : 'text-danger'">Total <span x-text="total.toLocaleString('id-ID')"></span>% (harus 100%)</div>
					<div class="form-text">Kontribusi tambahan = inisiatif, proyek lintas unit, dan tugas di luar KPI; dinilai atasan 1–5 lalu dikonversi seperti How.</div>

					<h6 class="mt-3">Bobot khusus per level jabatan</h6>
					<table class="table table-sm align-middle mb-0">
						<thead><tr><th>Level</th>@foreach($dimensions as $label)<th style="width:90px" class="small">{{ $label }}</th>@endforeach</tr></thead>
						<tbody>
						@foreach(array_pad($config['weight_overrides'], count($config['weight_overrides']) + 1, ['job_level_id' => '', 'what' => '', 'how' => '', 'contribution' => '']) as $i => $o)
							<tr><td><select name="config[weight_overrides][{{ $i }}][job_level_id]" class="form-select form-select-sm" aria-label="Level jabatan"><option value="">—</option>
								@foreach($levels as $id => $name)<option value="{{ $id }}" @selected($o['job_level_id'] == $id)>{{ $name }}</option>@endforeach</select></td>
								@foreach(array_keys($dimensions) as $key)<td><input type="number" step="0.01" name="config[weight_overrides][{{ $i }}][{{ $key }}]" value="{{ $o[$key] }}" class="form-control form-control-sm" aria-label="{{ $dimensions[$key] }}"></td>@endforeach</tr>
						@endforeach
						</tbody>
					</table>
				</div></div>
			</div>

			<div class="col-lg-6">
				<div class="card"><div class="card-body">
					<h5 class="card-title">Aturan KPI</h5>
					<div class="row g-2">
						@foreach(['min_count' => 'Jumlah KPI min.', 'max_count' => 'Jumlah KPI maks.', 'min_weight' => 'Bobot per KPI min. (%)', 'max_weight' => 'Bobot per KPI maks. (%)'] as $key => $label)
							<div class="col-6"><label class="form-label small" for="k-{{ $key }}">{{ $label }}</label>
								<input id="k-{{ $key }}" type="number" min="1" max="100" name="config[kpi][{{ $key }}]" value="{{ $config['kpi'][$key] }}" class="form-control form-control-sm"></div>
						@endforeach
						<div class="col-6"><label class="form-label small" for="k-cap">Cap capaian default (%)</label>
							<input id="k-cap" type="number" step="0.01" name="config[cap]" value="{{ $config['cap'] }}" class="form-control form-control-sm"></div>
						<div class="col-6"><label class="form-label small" for="k-range">Penalti rentang (poin per 1% simpangan)</label>
							<input id="k-range" type="number" step="0.01" name="config[range_penalty]" value="{{ $config['range_penalty'] }}" class="form-control form-control-sm"></div>
						<div class="col-6"><label class="form-label small" for="k-late">Faktor keterlambatan milestone (0–1)</label>
							<input id="k-late" type="number" step="0.01" min="0" max="1" name="config[milestone_late_penalty]" value="{{ $config['milestone_late_penalty'] }}" class="form-control form-control-sm"></div>
					</div>
				</div></div>

				<div class="card"><div class="card-body">
					<h5 class="card-title">Konversi skor perilaku 1–5</h5>
					<div class="row g-2">
						@foreach($config['conversion'] as $score => $value)
							<div class="col"><label class="form-label small" for="c-{{ $score }}">Skor {{ $score }}</label>
								<input id="c-{{ $score }}" type="number" step="0.01" name="config[conversion][{{ $score }}]" value="{{ $value }}" class="form-control form-control-sm"></div>
						@endforeach
					</div>
				</div></div>

				<div class="card"><div class="card-body">
					<h5 class="card-title">Band rating</h5>
					<table class="table table-sm align-middle mb-0">
						<thead><tr><th>Label</th><th style="width:120px">Skor minimum</th></tr></thead>
						<tbody>
						@foreach(array_pad($config['bands'], 7, ['code' => '', 'label' => '', 'min' => '']) as $i => $b)
							<tr><td><input type="hidden" name="config[bands][{{ $i }}][code]" value="{{ $b['code'] }}">
								<input name="config[bands][{{ $i }}][label]" value="{{ $b['label'] }}" maxlength="60" class="form-control form-control-sm" aria-label="Label band"></td>
								<td><input type="number" step="0.01" name="config[bands][{{ $i }}][min]" value="{{ $b['min'] }}" class="form-control form-control-sm" aria-label="Skor minimum"></td></tr>
						@endforeach
						</tbody>
					</table>
					<div class="form-text">Kosongkan label untuk menghapus band. Skor akhir di atas 100 dibatasi 100 untuk rating; nilai mentah tetap disimpan.</div>
				</div></div>

				<div class="card"><div class="card-body">
					<h5 class="card-title">Prorata</h5>
					<div class="row g-2">
						<div class="col-6"><label class="form-label small" for="p-seg">Penugasan &lt; (hari) digabung</label>
							<input id="p-seg" type="number" name="config[prorata][min_segment_days]" value="{{ $config['prorata']['min_segment_days'] }}" class="form-control form-control-sm"></div>
						<div class="col-6"><label class="form-label small" for="p-rated">Minimal hari untuk dinilai</label>
							<input id="p-rated" type="number" name="config[prorata][min_rated_days]" value="{{ $config['prorata']['min_rated_days'] }}" class="form-control form-control-sm"></div>
						<div class="col-6"><label class="form-label small" for="p-appeal">Jendela keberatan (hari)</label>
							<input id="p-appeal" type="number" min="1" max="60" name="config[appeal_days]" value="{{ $config['appeal_days'] }}" class="form-control form-control-sm"></div>
					</div>
				</div></div>
			</div>

			<div class="col-12">
				<div class="card"><div class="card-body">
					<h5 class="card-title">Item perilaku (How) & rubrik Kontribusi tambahan</h5>
					<div class="row">
						<div class="col-lg-7">
							<table class="table table-sm align-middle mb-0">
								<thead><tr><th style="width:90px">Kode</th><th style="width:180px">Perilaku</th><th>Indikator perilaku</th></tr></thead>
								<tbody>
								@foreach(array_pad($config['how_items'], count($config['how_items']) + 2, ['code' => '', 'label' => '', 'description' => '']) as $i => $h)
									<tr><td><input name="config[how_items][{{ $i }}][code]" value="{{ $h['code'] }}" maxlength="20" class="form-control form-control-sm" aria-label="Kode perilaku"></td>
										<td><input name="config[how_items][{{ $i }}][label]" value="{{ $h['label'] }}" maxlength="80" class="form-control form-control-sm" aria-label="Nama perilaku"></td>
										<td><input name="config[how_items][{{ $i }}][description]" value="{{ $h['description'] }}" maxlength="300" class="form-control form-control-sm" aria-label="Indikator perilaku"></td></tr>
								@endforeach
								</tbody>
							</table>
							<div class="form-text">Kosongkan nama untuk menghapus. Dinilai 1–5 oleh pegawai (informatif) dan atasan (dihitung).</div>
						</div>
						<div class="col-lg-5">
							@foreach($config['contribution_rubric'] as $lvl => $desc)
								<div class="input-group input-group-sm mb-1"><span class="input-group-text">{{ $lvl }}</span>
									<input name="config[contribution_rubric][{{ $lvl }}]" value="{{ $desc }}" maxlength="200" class="form-control" aria-label="Rubrik kontribusi level {{ $lvl }}"></div>
							@endforeach
						</div>
					</div>
				</div></div>
			</div>
		</div>
		</fieldset>
		@if($canEdit)<button class="btn btn-primary" type="submit">Simpan draft</button>@endif
	</form>
	@if($policy->status === 'draft' && $canApprove)
		<form method="POST" action="{{ url('performance/policies/'.$policy->id.'/publish') }}" class="mt-2"
			onsubmit="return confirm('Terbitkan kebijakan ini? Kebijakan yang berlaku sekarang akan dipensiunkan; periode yang sudah dibuka tidak terpengaruh.')">
			@csrf<button class="btn btn-success" type="submit" @disabled($problems)>Terbitkan kebijakan</button>
		</form>
	@endif
@endif
@endsection
