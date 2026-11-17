@extends('layouts.app')

@section('content')
<div class="page-titles">
	<h4 class="mb-0"> {{ $pageTitle }} <small>{{ $pageNote }}</small></h4>
</div>

@include('core.tenants.partials.flash')

@if(!$employee)
	<div class="card"><div class="card-body text-muted">Akun Anda belum ditautkan ke data pegawai. Hubungi HR.</div></div>
@else
<div class="row">
	<div class="col-lg-7">
		<div class="card"><div class="card-body">
			<h5 class="card-title">{{ $current ? 'Perbarui aspirasi karier' : 'Isi aspirasi karier' }}</h5>
			<p class="text-muted small">Aspirasi Anda dilihat atasan dan HR sebagai bahan pembicaraan karier dan rencana pengembangan. Ini bukan pengajuan mutasi atau promosi, dan setiap pembaruan tersimpan sebagai riwayat.</p>

			<form method="POST" action="{{ url('ess/career') }}"
				x-data="{ family: @js((string) old('target_job_family_id', $current->target_job_family_id ?? '')), job: @js((string) old('target_job_id', $current->target_job_id ?? '')),
					jobs: @js($jobs->map(fn ($j) => ['id' => (string) $j->id, 'title' => $j->title, 'family' => (string) $j->job_family_id])->values()),
					get options() { return this.family ? this.jobs.filter(j => j.family === this.family) : this.jobs } }">
				@csrf
				<div class="row g-3">
					<div class="col-md-6">
						<label for="target_job_family_id" class="form-label">Rumpun jabatan yang diminati</label>
						<select id="target_job_family_id" name="target_job_family_id" class="form-select" x-model="family" @change="if (job && !options.some(o => o.id === job)) job = ''">
							<option value="">— Pilih —</option>
							@foreach($families as $id => $name)<option value="{{ $id }}">{{ $name }}</option>@endforeach
						</select>
						@error('target_job_family_id')<div class="text-danger small">{{ $message }}</div>@enderror
					</div>
					<div class="col-md-6">
						<label for="target_job_id" class="form-label">Jabatan yang diminati</label>
						<select id="target_job_id" name="target_job_id" class="form-select" x-model="job">
							<option value="">— Pilih —</option>
							<template x-for="o in options" :key="o.id"><option :value="o.id" x-text="o.title" :selected="o.id === job"></option></template>
						</select>
						@error('target_job_id')<div class="text-danger small">{{ $message }}</div>@enderror
					</div>
					<div class="col-12">
						<label for="target_role" class="form-label">Atau tuliskan peran yang diinginkan</label>
						<input id="target_role" name="target_role" class="form-control" maxlength="150" value="{{ old('target_role', $current->target_role ?? '') }}" placeholder="mis. Data Scientist, Kepala Cabang">
						@error('target_role')<div class="text-danger small">{{ $message }}</div>@enderror
					</div>
					<div class="col-md-6">
						<label for="horizon" class="form-label">Jangka waktu</label>
						<select id="horizon" name="horizon" class="form-select" required>
							@foreach($horizons as $key => $label)<option value="{{ $key }}" @selected(old('horizon', $current->horizon ?? '3_5') === $key)>{{ $label }}</option>@endforeach
						</select>
					</div>
					<div class="col-md-6">
						<label for="mobility" class="form-label">Kesediaan pindah lokasi</label>
						<select id="mobility" name="mobility" class="form-select" required>
							@foreach($mobility as $key => $label)<option value="{{ $key }}" @selected(old('mobility', $current->mobility ?? 'discuss') === $key)>{{ $label }}</option>@endforeach
						</select>
					</div>
					<div class="col-12">
						<label for="interests" class="form-label">Minat & alasan</label>
						<textarea id="interests" name="interests" class="form-control" rows="3" maxlength="2000" placeholder="Bidang pekerjaan yang Anda minati dan mengapa">{{ old('interests', $current->interests ?? '') }}</textarea>
					</div>
					<div class="col-12">
						<label for="development_needs" class="form-label">Pengembangan yang Anda butuhkan</label>
						<textarea id="development_needs" name="development_needs" class="form-control" rows="3" maxlength="2000" placeholder="Pelatihan, penugasan, atau mentor yang membantu mencapai aspirasi">{{ old('development_needs', $current->development_needs ?? '') }}</textarea>
					</div>
				</div>
				<button class="btn btn-primary mt-3"><i class="fa fa-save"></i> Simpan aspirasi</button>
			</form>
		</div></div>
	</div>
	<div class="col-lg-5">
		<div class="card"><div class="card-body">
			<h5 class="card-title">Riwayat</h5>
			@include('employee._career_history', ['history' => $history])
		</div></div>
	</div>
</div>
@endif
@endsection
