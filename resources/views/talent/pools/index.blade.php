@extends('layouts.app')

@section('content')
<div class="page-titles d-flex flex-wrap justify-content-between align-items-center gap-2">
	<h4 class="mb-0"> {{ $pageTitle }}</h4>
	<a href="{{ url('talent/pools/report') }}" class="btn btn-sm btn-outline-secondary"><i class="fa fa-chart-pie"></i> Laporan talent pool</a>
</div>

@include('core.tenants.partials.flash')
@if($errors->any())<div class="alert alert-danger small"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif

<div class="row">
	<div class="col-lg-7">
		<div class="card"><div class="card-body">
			<p class="small text-muted">Keanggotaan pool adalah bahan perencanaan pengembangan dan suksesi, <strong>bukan janji promosi</strong>.</p>
			<div class="table-responsive"><table class="table table-sm align-middle mb-0">
				<thead><tr><th>Pool</th><th>Sasaran</th><th class="text-end">Aktif</th><th class="text-end">Nominasi</th><th></th></tr></thead>
				<tbody>
				@forelse($pools as $p)
					<tr class="{{ $p->is_active ? '' : 'text-muted' }}"><td><code>{{ $p->code }}</code> {{ $p->name }}<div class="small text-muted">{{ \Illuminate\Support\Str::limit($p->purpose, 90) }}</div></td>
						<td class="small">{{ $p->position_title ?? $p->family_name }}</td>
						<td class="text-end">{{ $p->active_count }}</td><td class="text-end">@if($p->nominated_count)<span class="badge bg-info">{{ $p->nominated_count }}</span>@else 0 @endif</td>
						<td class="text-end"><a href="{{ url('talent/pools/'.$p->id) }}" class="btn btn-sm btn-outline-primary">Buka</a></td></tr>
				@empty
					<tr><td colspan="5" class="text-muted small">Belum ada talent pool.</td></tr>
				@endforelse
				</tbody>
			</table></div>
		</div></div>
	</div>
	@if($canAdd)
		<div class="col-lg-5">
			<form method="POST" action="{{ url('talent/pools') }}" class="card"><div class="card-body">
				@csrf
				<h5 class="card-title">Pool baru</h5>
				<div class="row g-2">
					<div class="col-4"><label class="form-label" for="code">Kode</label><input id="code" name="code" class="form-control" required maxlength="30" value="{{ old('code') }}"></div>
					<div class="col-8"><label class="form-label" for="name">Nama</label><input id="name" name="name" class="form-control" required maxlength="150" value="{{ old('name') }}" placeholder="mis. Calon Kepala Cabang"></div>
					<div class="col-12"><label class="form-label" for="purpose">Tujuan</label><textarea id="purpose" name="purpose" class="form-control" rows="2" required maxlength="2000">{{ old('purpose') }}</textarea></div>
					<div class="col-6"><label class="form-label" for="target_job_family_id">Rumpun sasaran</label>
						<select id="target_job_family_id" name="target_job_family_id" class="form-select"><option value="">—</option>@foreach($families as $k => $l)<option value="{{ $k }}" @selected(old('target_job_family_id') == $k)>{{ $l }}</option>@endforeach</select></div>
					<div class="col-6"><label class="form-label" for="target_position_id">Posisi sasaran</label>
						<select id="target_position_id" name="target_position_id" class="form-select"><option value="">—</option>@foreach($positions as $p)<option value="{{ $p->id }}" @selected(old('target_position_id') == $p->id)>{{ $p->is_critical ? '★ ' : '' }}{{ $p->code }} — {{ $p->title }}</option>@endforeach</select>
						<div class="form-text">Bila diisi, kesiapan anggota dihitung terhadap posisi ini.</div></div>
					<div class="col-12"><label class="form-label" for="entry_criteria">Kriteria masuk</label><textarea id="entry_criteria" name="entry_criteria" class="form-control" rows="2" required maxlength="2000" placeholder="mis. kotak 6/8/9 pada talent review terakhir, masa kerja ≥ 2 tahun">{{ old('entry_criteria') }}</textarea></div>
					<div class="col-12"><label class="form-label" for="exit_criteria">Kriteria keluar</label><textarea id="exit_criteria" name="exit_criteria" class="form-control" rows="2" required maxlength="2000" placeholder="mis. turun ke kotak 1–4, ditempatkan ke posisi sasaran, keluar perusahaan">{{ old('exit_criteria') }}</textarea></div>
					<div class="col-8"><label class="form-label" for="owner_user_id">Pemilik pool</label>
						<select id="owner_user_id" name="owner_user_id" class="form-select" required>@foreach($owners as $u)<option value="{{ $u->id }}" @selected(old('owner_user_id') == $u->id)>{{ trim($u->first_name.' '.$u->last_name) }}</option>@endforeach</select></div>
					<div class="col-4"><label class="form-label" for="review_cycle_months">Tinjau tiap (bulan)</label><input id="review_cycle_months" type="number" min="3" max="24" name="review_cycle_months" class="form-control" required value="{{ old('review_cycle_months', 12) }}"></div>
				</div>
				<button class="btn btn-primary mt-3"><i class="fa fa-plus"></i> Buat pool</button>
			</div></form>
		</div>
	@endif
</div>
@endsection
