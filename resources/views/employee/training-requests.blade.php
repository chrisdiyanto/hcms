@extends('layouts.app')

@section('content')
@php($badge = ['proposed' => 'bg-info', 'approved' => 'bg-success', 'rejected' => 'bg-danger', 'planned' => 'bg-primary', 'closed' => 'bg-secondary'])
<div class="page-titles"><h4 class="mb-0"> {{ $pageTitle }} <small>{{ $pageNote }}</small></h4></div>

@include('core.tenants.partials.flash')

@if(!$me && $team->isEmpty())
	<div class="card"><div class="card-body text-muted">Akun Anda belum ditautkan ke data pegawai. Hubungi HR.</div></div>
@else
<div class="row">
	<div class="col-lg-5">
		<form method="POST" action="{{ url('ess/training-requests') }}" class="card"><div class="card-body">
			@csrf
			<h5 class="card-title">Ajukan kebutuhan pelatihan</h5>
			<p class="small text-muted">Usulan ditinjau HR sebelum masuk rencana pelatihan. Usulan bukan jaminan pelatihan akan diadakan.</p>
			<div class="mb-2"><label class="form-label" for="employee_id">Untuk</label>
				<select id="employee_id" name="employee_id" class="form-select" required>
					@if($me)<option value="{{ $me->id }}">Saya sendiri</option>@endif
					@foreach($team as $t)<option value="{{ $t->id }}" @selected(old('employee_id') == $t->id)>{{ $t->full_name }} (bawahan)</option>@endforeach
				</select>@error('employee_id')<div class="text-danger small">{{ $message }}</div>@enderror</div>
			<div class="row g-2">
				<div class="col-4"><label class="form-label" for="period_year">Tahun</label>
					<select id="period_year" name="period_year" class="form-select">@foreach([$year, $year + 1] as $y)<option value="{{ $y }}" @selected(old('period_year') == $y)>{{ $y }}</option>@endforeach</select></div>
				<div class="col-8"><label class="form-label" for="priority">Urgensi</label>
					<select id="priority" name="priority" class="form-select">@foreach($priorities as $k => $l)<option value="{{ $k }}" @selected(old('priority', 'medium') === $k)>{{ $l }}</option>@endforeach</select></div>
			</div>
			<div class="my-2"><label class="form-label" for="competency_id">Kompetensi (opsional)</label>
				<select id="competency_id" name="competency_id" class="form-select"><option value="">—</option>
					@foreach($competencies as $c)<option value="{{ $c->id }}" @selected(old('competency_id') == $c->id)>{{ $c->code }} — {{ $c->name }}</option>@endforeach</select></div>
			<div class="mb-2"><label class="form-label" for="topic">Topik</label>
				<input id="topic" name="topic" class="form-control" maxlength="200" value="{{ old('topic') }}" placeholder="wajib bila tanpa kompetensi">
				@error('topic')<div class="text-danger small">{{ $message }}</div>@enderror</div>
			<div class="mb-2"><label class="form-label" for="reason">Alasan & manfaat bagi pekerjaan</label>
				<textarea id="reason" name="reason" class="form-control" rows="3" required maxlength="2000">{{ old('reason') }}</textarea>
				@error('reason')<div class="text-danger small">{{ $message }}</div>@enderror</div>
			<div class="mb-2"><label class="form-label" for="suggested_intervention">Bentuk yang diusulkan</label>
				<input id="suggested_intervention" name="suggested_intervention" class="form-control" maxlength="255" value="{{ old('suggested_intervention') }}" placeholder="mis. kursus daring, sertifikasi, mentoring"></div>
			<button class="btn btn-primary"><i class="fa fa-paper-plane"></i> Kirim usulan</button>
		</div></form>
	</div>
	<div class="col-lg-7">
		<div class="card"><div class="card-body">
			<h5 class="card-title">Usulan & kebutuhan</h5>
			<div class="table-responsive"><table class="table table-sm align-middle mb-0">
				<thead><tr><th>Pegawai</th><th>Kebutuhan</th><th>Tahun</th><th>Status</th></tr></thead>
				<tbody>
				@forelse($rows as $r)
					<tr><td class="small">{{ $r->full_name }}</td>
						<td>{{ $r->topic }}<div class="small text-muted">{{ \Illuminate\Support\Str::limit($r->reason, 100) }}</div>
							@if($r->review_note)<div class="small"><span class="text-muted">Catatan HR:</span> {{ $r->review_note }}</div>@endif</td>
						<td class="small">{{ $r->period_year }}</td>
						<td><span class="badge {{ $badge[$r->status] ?? 'bg-secondary' }}">{{ $statuses[$r->status] }}</span></td></tr>
				@empty
					<tr><td colspan="4" class="text-muted small">Belum ada usulan.</td></tr>
				@endforelse
				</tbody>
			</table></div>
		</div></div>
	</div>
</div>
@endif
@endsection
