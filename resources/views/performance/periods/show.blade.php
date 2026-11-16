@extends('layouts.app')

@section('content')
<div class="page-titles d-flex justify-content-between align-items-center flex-wrap gap-2">
	<h4 class="mb-0"> {{ $pageTitle }} <small>{{ $pageNote }}</small></h4>
	<div class="d-flex gap-2">
		@if($canApprove && $period->status === 'draft')
			<form method="POST" action="{{ url('performance/periods/'.$period->id.'/open') }}" onsubmit="return confirm('Buka periode? Kebijakan yang berlaku sekarang akan dikunci untuk periode ini.')">
				@csrf<button class="btn btn-sm btn-success" type="submit">Buka periode &amp; kunci kebijakan</button></form>
		@elseif($canApprove && $period->status === 'planning')
			<form method="POST" action="{{ url('performance/periods/'.$period->id.'/advance') }}" onsubmit="return confirm('Akhiri tahap perencanaan dan mulai monitoring?')">
				@csrf<button class="btn btn-sm btn-outline-primary" type="submit">Mulai tahap monitoring</button></form>
		@endif
		<a href="{{ url('performance/periods') }}" class="btn btn-sm btn-outline-secondary">Kembali</a>
	</div>
</div>

@include('core.tenants.partials.flash')

<div class="row">
	<div class="col-lg-4">
		<div class="card"><div class="card-body">
			<h5 class="card-title">Periode</h5>
			<dl class="row small mb-0">
				<dt class="col-5">Kode</dt><dd class="col-7"><code>{{ $period->code }}</code></dd>
				<dt class="col-5">Rentang</dt><dd class="col-7">{{ $period->start_date }} – {{ $period->end_date }}</dd>
				<dt class="col-5">Batas rencana</dt><dd class="col-7">{{ $period->planning_deadline ?: '—' }}</dd>
				<dt class="col-5">Tahap</dt><dd class="col-7">{{ $statuses[$period->status] }}</dd>
			</dl>
			@if($period->policy)
				<hr>
				<h6>Kebijakan terkunci: v{{ $period->policy['_version'] }}</h6>
				<ul class="small mb-0">
					<li>Bobot: @foreach($dimensions as $k => $l){{ $l }} {{ $period->policy['weights'][$k] + 0 }}%@if(!$loop->last), @endif @endforeach</li>
					<li>KPI {{ $period->policy['kpi']['min_count'] }}–{{ $period->policy['kpi']['max_count'] }}, bobot {{ $period->policy['kpi']['min_weight'] }}–{{ $period->policy['kpi']['max_weight'] }}%, cap {{ $period->policy['cap'] + 0 }}%</li>
					<li>Band: {{ collect($period->policy['bands'])->map(fn ($b) => $b['label'].' ≥ '.($b['min'] + 0))->implode('; ') }}</li>
				</ul>
			@else
				<p class="small text-muted mt-2 mb-0">Kebijakan dikunci saat periode dibuka.</p>
			@endif
		</div></div>

		<div class="card"><div class="card-body">
			<h5 class="card-title">Status rencana kinerja</h5>
			<ul class="list-unstyled small mb-0">
				@foreach($planStatuses + ['none' => 'Belum dibuat'] as $k => $l)
					<li class="d-flex justify-content-between"><span>{{ $l }}</span><strong>{{ $planCounts[$k] ?? 0 }}</strong></li>
				@endforeach
			</ul>
		</div></div>
	</div>

	<div class="col-lg-8">
		<div class="card" id="sasaran"><div class="card-body">
			<h5 class="card-title">Sasaran strategis (cascading)</h5>
			<table class="table table-sm align-middle">
				<thead><tr><th>Sasaran</th><th>Level</th><th>Unit</th><th>Ukuran</th><th class="text-end">Kontribusi</th>@if($canEdit)<th></th>@endif</tr></thead>
				<tbody>
				@forelse($tree as $node)
					@php($o = $node['row'])
					<tr>
						<td style="padding-left: {{ 0.5 + $node['depth'] * 1.25 }}rem">@if($node['depth'])<span class="text-muted">↳</span> @endif<code>{{ $o->code }}</code> {{ $o->title }}</td>
						<td class="small">{{ $levels[$o->level] }}</td>
						<td class="small">{{ $o->unit_name ?: '—' }}</td>
						<td class="small">{{ $o->measure ?: '—' }}</td>
						<td class="text-end small">{{ $o->contribution_weight !== null ? ($o->contribution_weight + 0).'%' : '—' }}</td>
						@if($canEdit)
							<td class="text-end text-nowrap">
								<form method="POST" action="{{ url('performance/objectives/'.$o->id) }}" class="d-inline" onsubmit="return confirm('Hapus sasaran {{ $o->code }}?')">
									@csrf @method('DELETE')<button class="btn btn-sm btn-link text-danger p-0" type="submit">Hapus</button></form>
							</td>
						@endif
					</tr>
				@empty
					<tr><td colspan="6" class="text-muted">Belum ada sasaran strategis.</td></tr>
				@endforelse
				</tbody>
			</table>

			@if($canEdit)
				<h6 class="mt-3">Tambah sasaran</h6>
				<form method="POST" action="{{ url('performance/periods/'.$period->id.'/objectives') }}" class="row g-2" x-data="{ level: '{{ old('level', 'corporate') }}' }">
					@csrf
					<div class="col-md-2"><label class="form-label small" for="o-code">Kode</label><input id="o-code" name="code" required maxlength="30" value="{{ old('code') }}" class="form-control form-control-sm"></div>
					<div class="col-md-5"><label class="form-label small" for="o-title">Sasaran</label><input id="o-title" name="title" required maxlength="255" value="{{ old('title') }}" class="form-control form-control-sm"></div>
					<div class="col-md-2"><label class="form-label small" for="o-level">Level</label>
						<select id="o-level" name="level" x-model="level" class="form-select form-select-sm">@foreach($levels as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach</select></div>
					<div class="col-md-3" x-show="level !== 'corporate'" x-cloak><label class="form-label small" for="o-parent">Induk</label>
						<select id="o-parent" name="parent_id" class="form-select form-select-sm"><option value="">—</option>
							@foreach($objectives as $p)<option value="{{ $p->id }}" @selected(old('parent_id') == $p->id)>{{ $p->code }} — {{ \Illuminate\Support\Str::limit($p->title, 40) }}</option>@endforeach</select></div>
					<div class="col-md-4"><label class="form-label small" for="o-unit">Unit pemilik</label>
						<select id="o-unit" name="unit_id" class="form-select form-select-sm select2"><option value="">—</option>
							@foreach($units as $k => $l)<option value="{{ $k }}" @selected(old('unit_id') == $k)>{{ $l }}</option>@endforeach</select></div>
					<div class="col-md-5"><label class="form-label small" for="o-measure">Ukuran / target ringkas</label><input id="o-measure" name="measure" maxlength="255" value="{{ old('measure') }}" class="form-control form-control-sm" placeholder="mis. NPL ≤ 3%"></div>
					<div class="col-md-3" x-show="level !== 'corporate'" x-cloak><label class="form-label small" for="o-cw">Bobot kontribusi ke induk (%)</label><input id="o-cw" type="number" step="0.01" min="0" max="100" name="contribution_weight" value="{{ old('contribution_weight') }}" class="form-control form-control-sm"></div>
					<div class="col-12"><button class="btn btn-sm btn-primary" type="submit">Tambah sasaran</button></div>
				</form>
			@endif
		</div></div>

		<div class="card"><div class="card-body">
			<h5 class="card-title">Rencana kinerja pegawai</h5>
			<div class="table-responsive"><table class="table table-sm align-middle mb-0">
				<thead><tr><th>Pegawai</th><th>Posisi</th><th>Status</th><th>Diajukan</th></tr></thead>
				<tbody>
				@forelse($plans as $p)
					<tr><td>{{ $p->full_name }} <span class="small text-muted">{{ $p->employee_number }}</span></td>
						<td class="small">{{ $p->position_title ?: '—' }}</td>
						<td><span class="badge {{ ['approved' => 'bg-success', 'submitted' => 'bg-info', 'returned' => 'bg-warning text-dark', 'draft' => 'bg-light text-dark'][$p->status] ?? 'bg-secondary' }}">{{ $planStatuses[$p->status] ?? 'Belum dibuat' }}</span></td>
						<td class="small">{{ $p->submitted_at ?: '—' }}</td></tr>
				@empty
					<tr><td colspan="4" class="text-muted">Tidak ada pegawai dalam cakupan data Anda.</td></tr>
				@endforelse
				</tbody>
			</table></div>
		</div></div>
	</div>
</div>
@endsection
