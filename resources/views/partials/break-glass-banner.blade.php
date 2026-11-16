{{-- Tampil selama Super Administrator berada dalam sesi break-glass --}}
<div class="alert alert-danger d-flex flex-wrap align-items-center justify-content-between gap-2 mt-3" role="alert"
	x-data="{ left: {{ max(0, \Illuminate\Support\Carbon::parse($breakGlass->expires_at)->diffInSeconds(now(), true)) }},
		get label() { const m = Math.floor(this.left / 60), s = this.left % 60; return `${m}:${String(s).padStart(2, '0')}` } }"
	x-init="setInterval(() => { if (left > 0) left--; else location.reload() }, 1000)">
	<div>
		<strong>Mode break-glass:</strong> Anda melihat data <strong>{{ $breakGlass->tenant_name }} ({{ $breakGlass->tenant_code }})</strong>.
		Setiap halaman dicatat. Berakhir dalam <strong x-text="label"></strong>.
	</div>
	<form method="POST" action="{{ url('core/break-glass/end') }}" class="m-0">
		@csrf
		<button type="submit" class="btn btn-sm btn-light">Akhiri sekarang</button>
	</form>
</div>
