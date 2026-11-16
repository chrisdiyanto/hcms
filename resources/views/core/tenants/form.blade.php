@extends('layouts.app')

@section('content')
@php($isNew = ! $tenant->exists)
<div class="page-titles">
	<h4 class="mb-0"> {{ $pageTitle }} <small> {{ $pageNote }} </small> </h4>
</div>

@include('core.tenants.partials.flash')

<form method="POST" action="{{ $isNew ? url('core/tenants') : url('core/tenants/'.$tenant->uuid) }}"
	x-data="{ code: @js(old('code', $tenant->code)), companyCode: @js(old('company_code')), touched: {{ old('company_code') ? 'true' : 'false' }} }">
	@csrf
	@unless($isNew) @method('PUT') @endunless

	<div class="row">
		<div class="{{ $isNew ? 'col-lg-6' : 'col-lg-8' }}">
			<div class="card">
				<div class="card-body">
					<h5 class="card-title mb-3">Data tenant</h5>

					<div class="row g-3">
						<div class="col-md-4">
							<label class="form-label" for="code">Kode <span class="text-danger">*</span></label>
							<input type="text" id="code" name="code" x-model="code" maxlength="30" required
								@input="if (!touched) companyCode = code"
								class="form-control form-control-sm text-uppercase @error('code') is-invalid @enderror">
							<div class="form-text">Huruf, angka, - atau _. Tidak bisa dipakai tenant lain.</div>
						</div>
						<div class="col-md-8">
							<label class="form-label" for="name">Nama <span class="text-danger">*</span></label>
							<input type="text" id="name" name="name" value="{{ old('name', $tenant->name) }}" maxlength="150" required
								class="form-control form-control-sm @error('name') is-invalid @enderror">
						</div>
						<div class="col-12">
							<label class="form-label" for="legal_name">Nama badan hukum</label>
							<input type="text" id="legal_name" name="legal_name" value="{{ old('legal_name', $tenant->legal_name) }}" maxlength="255"
								class="form-control form-control-sm">
						</div>
						<div class="col-md-6">
							<label class="form-label" for="license_type">Jenis lisensi <span class="text-danger">*</span></label>
							<select id="license_type" name="license_type" class="form-select form-select-sm">
								@foreach(['enterprise' => 'Enterprise License', 'saas' => 'SaaS Subscription'] as $value => $label)
									<option value="{{ $value }}" @selected(old('license_type', $tenant->license_type) === $value)>{{ $label }}</option>
								@endforeach
							</select>
						</div>
						<div class="col-md-6">
							<label class="form-label" for="max_employees">Kuota pegawai</label>
							<input type="number" id="max_employees" name="max_employees" min="1" value="{{ old('max_employees', $tenant->max_employees) }}"
								class="form-control form-control-sm" placeholder="Tanpa batas">
						</div>
						<div class="col-md-4">
							<label class="form-label" for="contact_name">Nama kontak</label>
							<input type="text" id="contact_name" name="contact_name" value="{{ old('contact_name', $tenant->contact_name) }}" maxlength="150" class="form-control form-control-sm">
						</div>
						<div class="col-md-4">
							<label class="form-label" for="contact_email">Email kontak</label>
							<input type="email" id="contact_email" name="contact_email" value="{{ old('contact_email', $tenant->contact_email) }}" maxlength="150" class="form-control form-control-sm">
						</div>
						<div class="col-md-4">
							<label class="form-label" for="contact_phone">Telepon kontak</label>
							<input type="text" id="contact_phone" name="contact_phone" value="{{ old('contact_phone', $tenant->contact_phone) }}" maxlength="30" class="form-control form-control-sm">
						</div>
					</div>
				</div>
			</div>
		</div>

		@if($isNew)
		<div class="col-lg-6">
			<div class="card">
				<div class="card-body">
					<h5 class="card-title mb-3">Company pertama</h5>
					<div class="row g-3">
						<div class="col-md-4">
							<label class="form-label" for="company_code">Kode <span class="text-danger">*</span></label>
							<input type="text" id="company_code" name="company_code" x-model="companyCode" @input="touched = true" maxlength="20" required
								class="form-control form-control-sm text-uppercase @error('company_code') is-invalid @enderror">
						</div>
						<div class="col-md-8">
							<label class="form-label" for="company_name">Nama company <span class="text-danger">*</span></label>
							<input type="text" id="company_name" name="company_name" value="{{ old('company_name') }}" maxlength="255" required
								class="form-control form-control-sm @error('company_name') is-invalid @enderror">
						</div>
						<div class="col-12">
							<label class="form-label" for="company_address">Alamat</label>
							<textarea id="company_address" name="company_address" rows="2" class="form-control form-control-sm">{{ old('company_address') }}</textarea>
						</div>
					</div>

					<h5 class="card-title mt-4 mb-3">Tenant Administrator</h5>
					<div class="row g-3">
						<div class="col-md-6">
							<label class="form-label" for="admin_first_name">Nama depan <span class="text-danger">*</span></label>
							<input type="text" id="admin_first_name" name="admin_first_name" value="{{ old('admin_first_name') }}" maxlength="50" required class="form-control form-control-sm @error('admin_first_name') is-invalid @enderror">
						</div>
						<div class="col-md-6">
							<label class="form-label" for="admin_last_name">Nama belakang <span class="text-danger">*</span></label>
							<input type="text" id="admin_last_name" name="admin_last_name" value="{{ old('admin_last_name') }}" maxlength="50" required class="form-control form-control-sm @error('admin_last_name') is-invalid @enderror">
						</div>
						<div class="col-md-6">
							<label class="form-label" for="admin_username">Username <span class="text-danger">*</span></label>
							<input type="text" id="admin_username" name="admin_username" value="{{ old('admin_username') }}" maxlength="100" required autocomplete="off" class="form-control form-control-sm @error('admin_username') is-invalid @enderror">
						</div>
						<div class="col-md-6">
							<label class="form-label" for="admin_email">Email <span class="text-danger">*</span></label>
							<input type="email" id="admin_email" name="admin_email" value="{{ old('admin_email') }}" maxlength="100" required autocomplete="off" class="form-control form-control-sm @error('admin_email') is-invalid @enderror">
						</div>
						<div class="col-md-6">
							<label class="form-label" for="admin_password">Kata sandi <span class="text-danger">*</span></label>
							<input type="password" id="admin_password" name="admin_password" minlength="8" required autocomplete="new-password" class="form-control form-control-sm @error('admin_password') is-invalid @enderror">
						</div>
						<div class="col-md-6">
							<label class="form-label" for="admin_password_confirmation">Ulangi kata sandi <span class="text-danger">*</span></label>
							<input type="password" id="admin_password_confirmation" name="admin_password_confirmation" minlength="8" required autocomplete="new-password" class="form-control form-control-sm">
						</div>
					</div>
					<div class="form-text mt-2">Akun ini masuk ke grup Administrator dan mengelola company serta pengguna di tenant ini.</div>
				</div>
			</div>
		</div>
		@endif
	</div>

	<div class="mt-3">
		<button type="submit" class="btn btn-sm btn-primary">{{ $isNew ? 'Buat tenant' : 'Simpan' }}</button>
		<a href="{{ url('core/tenants') }}" class="btn btn-sm btn-outline-secondary">Batal</a>
	</div>
</form>
@endsection
