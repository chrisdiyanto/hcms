@extends('layouts.app')

@section('content')
<div class="page-titles d-flex justify-content-between align-items-center">
	<h4 class="mb-0"> {{ $pageTitle }} <small> {{ $pageNote }} </small> </h4>
	<a href="{{ url('core/users') }}" class="btn btn-sm btn-outline-secondary">Kembali ke Users</a>
</div>

@include('core.tenants.partials.flash')

<div class="row">
	<div class="col-lg-7">
		<div class="card">
			<div class="card-body">
				<h5 class="card-title">Cakupan efektif</h5>
				<p class="text-muted small mb-3">Unit yang datanya dapat dilihat pengguna ini, per peran yang dimilikinya. Unit penugasan selalu ikut, beserta bawahannya.</p>
				@forelse($effective as $role => $scope)
					<div class="mb-2">
						<strong>{{ $role }}</strong>:
						@if($scope->mode === 'all')
							<span class="badge bg-success">Seluruh tenant</span>
						@elseif($scope->mode === 'own')
							<span class="badge bg-secondary">Hanya data sendiri</span>
						@else
							<span class="badge bg-info">{{ count($scope->unitIds) }} unit</span>
						@endif
					</div>
				@empty
					<p class="text-muted">Pengguna belum punya peran.</p>
				@endforelse

				<h6 class="mt-4">Unit penugasan</h6>
				<ul class="mb-0">
					@forelse($assigned as $a)
						<li>{{ $a->unit_name }} <span class="text-muted">({{ $a->unit_code }})</span></li>
					@empty
						<li class="text-muted">Tidak ada</li>
					@endforelse
				</ul>

				<h6 class="mt-4">Cakupan tambahan</h6>
				<div class="table-responsive">
					<table class="table table-sm align-middle mb-0">
						<thead><tr><th>Unit</th><th>Bawahan</th><th>Berlaku</th><th>Alasan</th><th></th></tr></thead>
						<tbody>
						@forelse($grants as $g)
							<tr>
								<td>{{ $g->unit_name }} <span class="text-muted small">{{ $g->unit_code }}</span></td>
								<td>{{ $g->include_children ? 'Ya' : 'Tidak' }}</td>
								<td class="small">{{ $g->valid_from ?? '…' }} – {{ $g->valid_to ?? '…' }}</td>
								<td class="small">{{ $g->reason }}</td>
								<td class="text-end">
									@unless($isSelf)
									<form method="POST" action="{{ url('core/users/'.$target->id.'/scopes/'.$g->id) }}" onsubmit="return confirm('Cabut cakupan unit ini?')">
										@csrf @method('DELETE')
										<button class="btn btn-sm btn-outline-danger" type="submit">Cabut</button>
									</form>
									@endunless
								</td>
							</tr>
						@empty
							<tr><td colspan="5" class="text-muted">Belum ada cakupan tambahan.</td></tr>
						@endforelse
						</tbody>
					</table>
				</div>
			</div>
		</div>
	</div>

	<div class="col-lg-5">
		<div class="card">
			<div class="card-body">
				<h5 class="card-title">Tambah cakupan</h5>
				@if($isSelf)
					<div class="alert alert-warning mb-0">Anda tidak dapat mengubah cakupan data akun sendiri. Minta administrator lain melakukannya.</div>
				@else
				<form method="POST" action="{{ url('core/users/'.$target->id.'/scopes') }}" x-data="{ limited: {{ old('valid_from') || old('valid_to') ? 'true' : 'false' }} }">
					@csrf
					<div class="mb-3">
						<label class="form-label" for="unit_id">Unit <span class="text-danger">*</span></label>
						<select id="unit_id" name="unit_id" class="form-select form-select-sm select2" required>
							<option value=""></option>
							@foreach($units as $u)
								<option value="{{ $u->id_unit }}" @selected(old('unit_id') == $u->id_unit)>{{ $u->unit_name }} ({{ $u->unit_code }})</option>
							@endforeach
						</select>
					</div>
					<div class="form-check mb-3">
						<input class="form-check-input" type="checkbox" value="1" id="include_children" name="include_children" @checked(old('include_children', '1'))>
						<label class="form-check-label" for="include_children">Termasuk seluruh unit bawahan</label>
					</div>
					<div class="form-check mb-2">
						<input class="form-check-input" type="checkbox" id="limited" x-model="limited">
						<label class="form-check-label" for="limited">Batasi masa berlaku</label>
					</div>
					<div class="row g-2 mb-3" x-show="limited" x-cloak>
						<div class="col"><label class="form-label small" for="valid_from">Dari</label><input type="date" id="valid_from" name="valid_from" value="{{ old('valid_from') }}" class="form-control form-control-sm" :disabled="!limited"></div>
						<div class="col"><label class="form-label small" for="valid_to">Sampai</label><input type="date" id="valid_to" name="valid_to" value="{{ old('valid_to') }}" class="form-control form-control-sm" :disabled="!limited"></div>
					</div>
					<div class="mb-3">
						<label class="form-label" for="reason">Alasan <span class="text-danger">*</span></label>
						<input type="text" id="reason" name="reason" value="{{ old('reason') }}" maxlength="255" required class="form-control form-control-sm" placeholder="mis. HRBP untuk Divisi Kredit">
					</div>
					<button class="btn btn-sm btn-primary" type="submit">Simpan</button>
				</form>
				@endif
			</div>
		</div>

		<div class="card">
			<div class="card-body">
				<h5 class="card-title">Verifikasi dua langkah</h5>
				<p class="mb-2">
					@if($mfaEnabledAt)
						<span class="badge bg-success">Aktif</span> <span class="small text-muted">sejak {{ $mfaEnabledAt }}</span>
					@else
						<span class="badge bg-secondary">Belum aktif</span>
					@endif
					@if($mfaRequired)<span class="badge bg-warning text-dark">Wajib untuk perannya</span>@endif
				</p>
				@if($mfaEnabledAt && ! $isSelf)
					<form method="POST" action="{{ url('core/users/'.$target->id.'/mfa-reset') }}"
						onsubmit="return confirm('Reset MFA pengguna ini? Ia harus mendaftar ulang saat login berikutnya.')">
						@csrf
						<button class="btn btn-sm btn-outline-danger" type="submit">Reset MFA</button>
					</form>
				@endif
			</div>
		</div>

		<div class="card">
			<div class="card-body">
				<h5 class="card-title">Riwayat login</h5>
				<table class="table table-sm mb-0">
					<tbody>
					@forelse($logins as $l)
						<tr>
							<td class="small text-nowrap">{{ $l->created_at }}</td>
							<td>
								@php($label = ['login' => ['Masuk', 'success'], 'logout' => ['Keluar', 'secondary'], 'failed' => ['Gagal', 'danger'], 'blocked' => ['Diblokir', 'warning']][$l->event] ?? [$l->event, 'secondary'])
								<span class="badge bg-{{ $label[1] }}">{{ $label[0] }}</span>
							</td>
							<td class="small text-muted">{{ $l->ip_address }}</td>
						</tr>
					@empty
						<tr><td class="text-muted">Belum ada catatan.</td></tr>
					@endforelse
					</tbody>
				</table>
			</div>
		</div>
	</div>
</div>
@endsection
