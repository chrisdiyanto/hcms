@extends('layouts.login')

@section('content')
<div class="login-form" x-data="{ copied: false }">
	<h5 class="mb-1">Simpan kode pemulihan</h5>
	<p class="text-muted small">
		Verifikasi dua langkah sudah aktif. Simpan kode berikut di tempat aman. Setiap kode hanya bisa dipakai sekali
		bila ponsel Anda hilang. Kode ini <strong>tidak akan ditampilkan lagi</strong>.
	</p>

	<div class="row g-2 mb-3" id="recovery-codes">
		@foreach($codes as $code)
			<div class="col-6"><code class="d-block text-center p-1 border rounded">{{ $code }}</code></div>
		@endforeach
	</div>

	<button type="button" class="btn btn-outline-secondary btn-sm w-100"
		@click="navigator.clipboard.writeText(@js(implode("\n", $codes))).then(() => copied = true)">
		<span x-text="copied ? 'Tersalin' : 'Salin semua kode'">Salin semua kode</span>
	</button>

	<a href="{{ url('dashboard') }}" class="btn btn-primary w-100 mt-3">Saya sudah menyimpannya, lanjutkan</a>
</div>
@endsection
