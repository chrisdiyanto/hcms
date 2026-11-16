@extends('layouts.login')

@section('content')
<div class="login-form">
	<h5 class="mb-1">Verifikasi dua langkah</h5>
	<p class="text-muted small">Masukkan 6 digit kode dari aplikasi autentikator Anda, atau salah satu kode pemulihan.</p>

	@if($errors->any())
		<div class="alert alert-danger alert-box">{{ $errors->first() }}</div>
	@endif

	<form method="POST" action="{{ url('user/mfa') }}">
		@csrf
		<div class="input-group-login">
			<i class="fa fa-key"></i>
			<input type="text" name="code" inputmode="numeric" autocomplete="one-time-code" autofocus required maxlength="20"
				placeholder="123456 atau ABCD-EFGH" class="form-control">
		</div>
		<button type="submit" class="btn btn-primary w-100 mt-3">Verifikasi</button>
	</form>

	<p class="small text-muted mt-3 mb-1">Sisa kode pemulihan: {{ $remaining }}</p>
	<a href="{{ url('user/logout') }}" class="small">Keluar</a>
</div>
@endsection
