@extends('layouts.app')

@section('content')
<div class="page-titles d-flex justify-content-between align-items-center">
	<h4 class="mb-0"> {{ $pageTitle }} <small>{{ $pageNote }}</small></h4>
	<a href="{{ url('competency/assessments') }}" class="btn btn-sm btn-outline-secondary">Kembali</a>
</div>

@include('core.tenants.partials.flash')

@unless($hasFramework)
	<div class="alert alert-warning">Belum ada kamus kompetensi yang berlaku. Terbitkan kamus di menu <a href="{{ url('competency/framework') }}">Kamus Kompetensi</a> lebih dulu.</div>
@endunless

<form method="POST" action="{{ url('competency/assessments') }}">
	@csrf
	<div class="row">
		<div class="col-lg-6">
			<div class="card"><div class="card-body">
				<h5 class="card-title">Pegawai yang dinilai</h5>
				<div class="mb-2"><label class="form-label" for="f-employee">Pegawai</label>
					<select id="f-employee" name="employee_id" required class="form-select form-select-sm select2 @error('employee_id') is-invalid @enderror"><option value=""></option>
						@foreach($employees as $v => $l)<option value="{{ $v }}" @selected(old('employee_id') == $v)>{{ $l }}</option>@endforeach</select>
					<div class="form-text">Kompetensi yang dinilai mengikuti profil posisi primer pegawai saat ini.</div></div>
				<div class="mb-2"><label class="form-label" for="f-title">Judul</label>
					<input id="f-title" name="title" maxlength="150" value="{{ old('title') }}" class="form-control form-control-sm" placeholder="Asesmen kompetensi {{ now()->translatedFormat('F Y') }}"></div>
				<div class="mb-2"><label class="form-label" for="f-due">Batas waktu penilaian</label>
					<input id="f-due" type="date" name="due_date" value="{{ old('due_date', now()->addDays(14)->toDateString()) }}" class="form-control form-control-sm"></div>
			</div></div>
		</div>
		<div class="col-lg-6">
			<div class="card"><div class="card-body">
				<h5 class="card-title">Penilai</h5>
				<div class="form-check mb-1"><input type="hidden" name="include_self" value="0">
					<input class="form-check-input" type="checkbox" id="f-self" name="include_self" value="1" @checked(old('include_self', 1))>
					<label class="form-check-label" for="f-self">Diri sendiri (akun pegawai)</label></div>
				<div class="form-check mb-3"><input type="hidden" name="include_manager" value="0">
					<input class="form-check-input" type="checkbox" id="f-manager" name="include_manager" value="1" @checked(old('include_manager', 1))>
					<label class="form-check-label" for="f-manager">Atasan langsung (otomatis dari struktur posisi; naik bila kosong)</label></div>
				@foreach(['peers' => 'Rekan', 'subordinates' => 'Bawahan', 'hr' => 'HR'] as $key => $label)
					<div class="mb-2"><label class="form-label" for="f-{{ $key }}">{{ $label }}</label>
						<select id="f-{{ $key }}" name="{{ $key }}[]" multiple class="form-select form-select-sm select2">
							@foreach($users as $v => $l)<option value="{{ $v }}" @selected(in_array($v, old($key, [])))>{{ $l }}</option>@endforeach</select></div>
				@endforeach
				<div class="form-text">Skor rekan dan bawahan hanya dihitung bila minimal 3 penilai mengirim (anonimitas). Bobot: atasan 50%, rekan 20%, bawahan 20%, diri sendiri 10%.</div>
			</div></div>
		</div>
	</div>
	<button class="btn btn-primary" type="submit" @disabled(!$hasFramework)>Buka asesmen &amp; kirim notifikasi</button>
</form>
@endsection
