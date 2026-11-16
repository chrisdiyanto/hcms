@extends('layouts.login')

@section('content')
<div class="login-form" x-data="{ showKey: false }">
	<h5 class="mb-1">Aktifkan verifikasi dua langkah</h5>
	<p class="text-muted small">
		@if($required)
			Peran Anda mengakses data sensitif, jadi verifikasi dua langkah wajib diaktifkan sebelum melanjutkan.
		@else
			Lindungi akun Anda dengan kode dari aplikasi autentikator.
		@endif
	</p>

	@if($errors->any())
		<div class="alert alert-danger alert-box">{{ $errors->first() }}</div>
	@endif

	<ol class="small ps-3">
		<li>Buka Google Authenticator, Microsoft Authenticator, atau aplikasi sejenis.</li>
		<li>Pindai kode QR berikut.</li>
		<li>Masukkan 6 digit kode yang muncul.</li>
	</ol>

	<div class="text-center my-2" style="background:#fff;padding:8px;border-radius:8px;">{!! $qr !!}</div>

	<p class="small text-center mb-2">
		<a href="#" @click.prevent="showKey = !showKey">Tidak bisa memindai? Tampilkan kunci</a>
	</p>
	<p class="small text-center" x-show="showKey" x-cloak><code style="word-break:break-all">{{ trim(chunk_split($secret, 4, ' ')) }}</code></p>

	<form method="POST" action="{{ url('user/mfa/setup') }}">
		@csrf
		<div class="input-group-login">
			<i class="fa fa-key"></i>
			<input type="text" name="code" inputmode="numeric" autocomplete="one-time-code" pattern="\d{6}" maxlength="6" required autofocus
				placeholder="Kode 6 digit" class="form-control">
		</div>
		<button type="submit" class="btn btn-primary w-100 mt-3">Aktifkan</button>
	</form>

	<a href="{{ url('user/logout') }}" class="small d-inline-block mt-3">Keluar</a>
</div>
@endsection
