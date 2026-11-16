@extends('layouts.app')

@section('content')
@php($e = $employee)
<div class="page-titles">
	<h4 class="mb-0"> {{ $pageTitle }} <small> {{ $pageNote }} </small> </h4>
</div>

@include('core.tenants.partials.flash')

<form method="POST" action="{{ $e ? url('employees/'.$e->id) : url('employees') }}"
	x-data="{ status: @js(old('employment_status', $e->employment_status ?? 'probation')), editIdentity: {{ $e ? (old('update_identity') ? 'true' : 'false') : 'true' }} }">
	@csrf
	@if($e) @method('PUT') @endif

	<div class="row">
		<div class="col-lg-8">
			<div class="card"><div class="card-body">
				<h5 class="card-title">Data pribadi</h5>
				<div class="row g-3">
					<div class="col-md-4"><label class="form-label" for="f-employee_number">NIP <span class="text-danger">*</span></label>
						<input id="f-employee_number" name="employee_number" value="{{ old('employee_number', $e->employee_number ?? '') }}" required maxlength="30" class="form-control form-control-sm text-uppercase @error('employee_number') is-invalid @enderror"></div>
					<div class="col-md-8"><label class="form-label" for="f-full_name">Nama lengkap <span class="text-danger">*</span></label>
						<input id="f-full_name" name="full_name" value="{{ old('full_name', $e->full_name ?? '') }}" required maxlength="150" class="form-control form-control-sm @error('full_name') is-invalid @enderror"></div>
					<div class="col-md-4"><label class="form-label" for="f-birth_place">Tempat lahir</label>
						<input id="f-birth_place" name="birth_place" value="{{ old('birth_place', $e->birth_place ?? '') }}" maxlength="100" class="form-control form-control-sm"></div>
					<div class="col-md-4"><label class="form-label" for="f-birth_date">Tanggal lahir</label>
						<input type="date" id="f-birth_date" name="birth_date" value="{{ old('birth_date', $e->birth_date ?? '') }}" class="form-control form-control-sm @error('birth_date') is-invalid @enderror"></div>
					<div class="col-md-4"><label class="form-label" for="f-gender">Jenis kelamin</label>
						<select id="f-gender" name="gender" class="form-select form-select-sm"><option value=""></option>
							@foreach(['M' => 'Laki-laki', 'F' => 'Perempuan'] as $v => $l)<option value="{{ $v }}" @selected(old('gender', $e->gender ?? '') === $v)>{{ $l }}</option>@endforeach</select></div>
					<div class="col-md-4"><label class="form-label" for="f-marital_status">Status perkawinan</label>
						<select id="f-marital_status" name="marital_status" class="form-select form-select-sm"><option value=""></option>
							@foreach($labels['marital'] as $v => $l)<option value="{{ $v }}" @selected(old('marital_status', $e->marital_status ?? '') === $v)>{{ $l }}</option>@endforeach</select></div>
					<div class="col-md-4"><label class="form-label" for="f-ptkp_status">Status PTKP</label>
						<select id="f-ptkp_status" name="ptkp_status" class="form-select form-select-sm"><option value=""></option>
							@foreach($labels['ptkp'] as $v)<option value="{{ $v }}" @selected(old('ptkp_status', $e->ptkp_status ?? '') === $v)>{{ $v }}</option>@endforeach</select></div>
					<div class="col-md-4"><label class="form-label" for="f-phone">Telepon</label>
						<input id="f-phone" name="phone" value="{{ old('phone', $e->phone ?? '') }}" maxlength="30" class="form-control form-control-sm"></div>
					<div class="col-md-6"><label class="form-label" for="f-email">Email</label>
						<input type="email" id="f-email" name="email" value="{{ old('email', $e->email ?? '') }}" maxlength="150" class="form-control form-control-sm @error('email') is-invalid @enderror"></div>
					<div class="col-md-6"><label class="form-label" for="f-user_id">Akun login</label>
						<select id="f-user_id" name="user_id" class="form-select form-select-sm select2 @error('user_id') is-invalid @enderror"><option value="">Belum ditautkan</option>
							@foreach($users as $v => $l)<option value="{{ $v }}" @selected((string) old('user_id', $e->user_id ?? '') === (string) $v)>{{ $l }}</option>@endforeach</select></div>
					<div class="col-md-6"><label class="form-label" for="f-address_ktp">Alamat KTP</label>
						<textarea id="f-address_ktp" name="address_ktp" rows="2" class="form-control form-control-sm">{{ old('address_ktp', $e->address_ktp ?? '') }}</textarea></div>
					<div class="col-md-6"><label class="form-label" for="f-address_domicile">Alamat domisili</label>
						<textarea id="f-address_domicile" name="address_domicile" rows="2" class="form-control form-control-sm">{{ old('address_domicile', $e->address_domicile ?? '') }}</textarea></div>
				</div>
			</div></div>
		</div>

		<div class="col-lg-4">
			<div class="card"><div class="card-body">
				<h5 class="card-title">Kepegawaian</h5>
				<div class="mb-3"><label class="form-label" for="f-company_id">Company <span class="text-danger">*</span></label>
					<select id="f-company_id" name="company_id" required class="form-select form-select-sm @error('company_id') is-invalid @enderror"><option value=""></option>
						@foreach($companies as $v => $l)<option value="{{ $v }}" @selected((string) old('company_id', $e->company_id ?? '') === (string) $v)>{{ $l }}</option>@endforeach</select></div>
				<div class="mb-3"><label class="form-label" for="f-employment_status">Status kerja <span class="text-danger">*</span></label>
					<select id="f-employment_status" name="employment_status" x-model="status" required class="form-select form-select-sm">
						@foreach($labels['employment'] as $v => $l)<option value="{{ $v }}">{{ $l }}</option>@endforeach</select></div>
				<div class="mb-3"><label class="form-label" for="f-join_date">Tanggal bergabung <span class="text-danger">*</span></label>
					<input type="date" id="f-join_date" name="join_date" value="{{ old('join_date', $e->join_date ?? '') }}" required class="form-control form-control-sm @error('join_date') is-invalid @enderror"></div>
				<div class="mb-3" x-show="status === 'probation'" x-cloak><label class="form-label" for="f-probation_end_date">Akhir masa percobaan <span class="text-danger">*</span></label>
					<input type="date" id="f-probation_end_date" name="probation_end_date" value="{{ old('probation_end_date', $e->probation_end_date ?? '') }}" :disabled="status !== 'probation'" class="form-control form-control-sm @error('probation_end_date') is-invalid @enderror"></div>
				<div class="mb-3" x-show="status === 'pkwt'" x-cloak><label class="form-label" for="f-contract_end_date">Akhir kontrak (PKWT) <span class="text-danger">*</span></label>
					<input type="date" id="f-contract_end_date" name="contract_end_date" value="{{ old('contract_end_date', $e->contract_end_date ?? '') }}" :disabled="status !== 'pkwt'" class="form-control form-control-sm @error('contract_end_date') is-invalid @enderror"></div>
			</div></div>

			@if($canSensitive)
			<div class="card border-warning"><div class="card-body">
				<h5 class="card-title">Identitas negara <span class="badge bg-warning text-dark">Data sensitif</span></h5>
				<p class="small text-muted">Disimpan terenkripsi. Hanya pengguna dengan izin Data Sensitif yang dapat melihat atau mengubahnya.</p>
				@if($e)
					<div class="form-check mb-2">
						<input class="form-check-input" type="checkbox" value="1" id="update_identity" name="update_identity" x-model="editIdentity">
						<label class="form-check-label" for="update_identity">Ubah identitas {{ $hasIdentity ? '(nilai lama diganti seluruhnya)' : '' }}</label>
					</div>
				@endif
				<div x-show="editIdentity" x-cloak>
					@foreach(\App\Domain\Employee\Services\EmployeeIdentity::FIELDS as $name => $label)
						<div class="mb-2"><label class="form-label small" for="f-{{ $name }}">{{ $label }}</label>
							<input id="f-{{ $name }}" name="{{ $name }}" value="{{ old($name) }}" autocomplete="off" :disabled="!editIdentity" class="form-control form-control-sm @error($name) is-invalid @enderror">
							@error($name)<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
					@endforeach
				</div>
			</div></div>
			@endif
		</div>
	</div>

	<div class="mt-3">
		<button type="submit" class="btn btn-sm btn-primary">Simpan</button>
		<a href="{{ $e ? url('employees/'.$e->id) : url('employees') }}" class="btn btn-sm btn-outline-secondary">Batal</a>
	</div>
</form>
@endsection
