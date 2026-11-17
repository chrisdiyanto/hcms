@extends('layouts.app')

@section('content')
<div class="page-titles d-flex justify-content-between align-items-center">
	<h4 class="mb-0"> {{ $pageTitle }} <small>{{ $pageNote }}</small></h4>
	<a href="{{ url('development/needs?year='.($row->period_year ?? $year).'&status='.($row->status ?? 'proposed')) }}" class="btn btn-sm btn-outline-secondary">Kembali</a>
</div>

@include('core.tenants.partials.flash')
@if($errors->any())<div class="alert alert-danger small"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif

@if($row)
	{{-- ===== Rincian tindak lanjut ===== --}}
	<div class="row">
		<div class="col-lg-5">
			<div class="card"><div class="card-body">
				<h5 class="card-title">@if($row->competency_code)<code>{{ $row->competency_code }}</code> @endif{{ $row->topic }}</h5>
				<dl class="row small mb-0">
					<dt class="col-5">Pegawai</dt><dd class="col-7">{{ $row->full_name }} ({{ $row->employee_number }})</dd>
					<dt class="col-5">Tahun / status</dt><dd class="col-7">{{ $row->period_year }} · {{ $statuses[$row->status] }}</dd>
					<dt class="col-5">Sumber</dt><dd class="col-7">{{ $sources[$row->source] }}</dd>
					@if($row->gap !== null)<dt class="col-5">Level / wajib / gap</dt><dd class="col-7">{{ number_format($row->current_level, 2, ',', '.') }} / {{ $row->target_level }} / {{ number_format($row->gap, 2, ',', '.') }} (kritikalitas {{ $row->criticality }})</dd>@endif
					<dt class="col-5">Skor prioritas (K5)</dt><dd class="col-7">{{ $row->priority_score !== null ? number_format($row->priority_score, 2, ',', '.') : 'ditetapkan manual' }}</dd>
					<dt class="col-5">Alasan</dt><dd class="col-7">{{ $row->reason }}</dd>
					@if($row->evidence)<dt class="col-5">Bukti</dt><dd class="col-7">{{ $row->evidence }}</dd>@endif
				</dl>
			</div></div>
		</div>
		<div class="col-lg-7">
			<form method="POST" action="{{ url('development/needs/'.$row->id) }}" class="card"><div class="card-body">
				@csrf @method('PUT')
				<h5 class="card-title">Tindak lanjut</h5>
				<div class="row g-3">
					<div class="col-md-4"><label class="form-label" for="priority">Prioritas</label>
						<select id="priority" name="priority" class="form-select" @disabled($row->priority_score !== null || $row->source === 'performance')>
							@foreach($priorities as $k => $l)<option value="{{ $k }}" @selected(old('priority', $row->priority) === $k)>{{ $l }}</option>@endforeach
						</select>
						@if($row->priority_score !== null || $row->source === 'performance')<input type="hidden" name="priority" value="{{ $row->priority }}"><div class="form-text">Dihitung otomatis (K5).</div>@endif</div>
					<div class="col-md-8"><label class="form-label" for="strategic_objective_id">Terkait sasaran strategis</label>
						<select id="strategic_objective_id" name="strategic_objective_id" class="form-select"><option value="">— Tidak —</option>
							@foreach($objectives as $o)<option value="{{ $o->id }}" @selected(old('strategic_objective_id', $row->strategic_objective_id) == $o->id)>{{ $o->code }} — {{ $o->title }}</option>@endforeach</select>
						<div class="form-text">Faktor strategis ×1,5 pada skor prioritas.</div></div>
					<div class="col-md-8"><label class="form-label" for="suggested_intervention">Intervensi yang disarankan</label>
						<input id="suggested_intervention" name="suggested_intervention" class="form-control" maxlength="255" value="{{ old('suggested_intervention', $row->suggested_intervention) }}" placeholder="mis. pelatihan, coaching, mentoring, penugasan, sertifikasi"></div>
					<div class="col-md-4"><label class="form-label" for="estimated_cost">Perkiraan biaya (Rp)</label>
						<input id="estimated_cost" name="estimated_cost" type="number" min="0" step="1000" class="form-control" value="{{ old('estimated_cost', $row->estimated_cost) }}"></div>
					<div class="col-md-4"><label class="form-label" for="due_date">Tenggat</label>
						<input id="due_date" name="due_date" type="date" class="form-control" value="{{ old('due_date', $row->due_date) }}"></div>
					<div class="col-md-8"><label class="form-label" for="owner_user_id">Penanggung jawab</label>
						<select id="owner_user_id" name="owner_user_id" class="form-select"><option value="">—</option>
							@foreach($owners as $u)<option value="{{ $u->id }}" @selected(old('owner_user_id', $row->owner_user_id) == $u->id)>{{ trim($u->first_name.' '.$u->last_name) }}</option>@endforeach</select></div>
					<div class="col-12"><label class="form-label" for="impact_if_unaddressed">Dampak bila tidak ditangani</label>
						<textarea id="impact_if_unaddressed" name="impact_if_unaddressed" class="form-control" rows="2" maxlength="2000">{{ old('impact_if_unaddressed', $row->impact_if_unaddressed) }}</textarea></div>
				</div>
				<button class="btn btn-primary mt-3"><i class="fa fa-save"></i> Simpan</button>
			</div></form>
		</div>
	</div>
@else
	{{-- ===== Kebutuhan organisasi baru ===== --}}
	<form method="POST" action="{{ url('development/needs') }}" class="card"><div class="card-body">
		@csrf
		<p class="text-muted small">Kebutuhan prioritas organisasi untuk pegawai terpilih atau seluruh pegawai aktif di satu unit (dalam cakupan data Anda). Setiap pegawai mendapat satu kebutuhan berstatus <em>Diajukan</em> yang perlu disetujui peninjau lain.</p>
		<div class="row g-3">
			<div class="col-md-2"><label class="form-label" for="period_year">Tahun</label>
				<input id="period_year" name="period_year" type="number" class="form-control" required value="{{ old('period_year', $year) }}"></div>
			<div class="col-md-5"><label class="form-label" for="employee_ids">Pegawai</label>
				<select id="employee_ids" name="employee_ids[]" class="form-select" multiple size="6">
					@foreach($employees as $e)<option value="{{ $e->id }}" @selected(in_array($e->id, (array) old('employee_ids', [])))>{{ $e->full_name }} ({{ $e->employee_number }})</option>@endforeach
				</select><div class="form-text">Tahan Ctrl untuk memilih beberapa.</div></div>
			<div class="col-md-5"><label class="form-label" for="unit_id">Atau seluruh pegawai di unit</label>
				<select id="unit_id" name="unit_id" class="form-select"><option value="">—</option>
					@foreach($units as $k => $l)<option value="{{ $k }}" @selected(old('unit_id') == $k)>{{ $l }}</option>@endforeach</select></div>
			<div class="col-md-6"><label class="form-label" for="competency_id">Kompetensi (opsional)</label>
				<select id="competency_id" name="competency_id" class="form-select"><option value="">—</option>
					@foreach($competencies as $c)<option value="{{ $c->id }}" @selected(old('competency_id') == $c->id)>{{ $c->code }} — {{ $c->name }}</option>@endforeach</select></div>
			<div class="col-md-6"><label class="form-label" for="topic">Topik (wajib bila tanpa kompetensi)</label>
				<input id="topic" name="topic" class="form-control" maxlength="200" value="{{ old('topic') }}" placeholder="mis. Anti-fraud, K3, sistem baru"></div>
			<div class="col-12"><label class="form-label" for="reason">Alasan</label>
				<textarea id="reason" name="reason" class="form-control" rows="2" required maxlength="2000">{{ old('reason') }}</textarea></div>
			<div class="col-md-4"><label class="form-label" for="priority">Prioritas (bila tanpa gap)</label>
				<select id="priority" name="priority" class="form-select">@foreach($priorities as $k => $l)<option value="{{ $k }}" @selected(old('priority', 'medium') === $k)>{{ $l }}</option>@endforeach</select></div>
			<div class="col-md-8"><label class="form-label" for="strategic_objective_id">Terkait sasaran strategis</label>
				<select id="strategic_objective_id" name="strategic_objective_id" class="form-select"><option value="">— Tidak —</option>
					@foreach($objectives as $o)<option value="{{ $o->id }}" @selected(old('strategic_objective_id') == $o->id)>{{ $o->code }} — {{ $o->title }}</option>@endforeach</select></div>
			<div class="col-md-6"><label class="form-label" for="suggested_intervention">Intervensi yang disarankan</label>
				<input id="suggested_intervention" name="suggested_intervention" class="form-control" maxlength="255" value="{{ old('suggested_intervention') }}"></div>
			<div class="col-md-3"><label class="form-label" for="estimated_cost">Perkiraan biaya per orang (Rp)</label>
				<input id="estimated_cost" name="estimated_cost" type="number" min="0" step="1000" class="form-control" value="{{ old('estimated_cost') }}"></div>
			<div class="col-md-3"><label class="form-label" for="due_date">Tenggat</label>
				<input id="due_date" name="due_date" type="date" class="form-control" value="{{ old('due_date') }}"></div>
		</div>
		<button class="btn btn-primary mt-3"><i class="fa fa-paper-plane"></i> Ajukan</button>
	</div></form>
@endif
@endsection
