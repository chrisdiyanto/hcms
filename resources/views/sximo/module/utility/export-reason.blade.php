@extends(request()->ajax() ? 'layouts.fragment' : 'layouts.app')

@section('content')
<div class="card" style="max-width: 560px; margin: 20px auto;">
	<div class="card-body">
		<h5 class="card-title">Alasan ekspor data</h5>
		<p class="small text-muted">Setiap ekspor dicatat beserta alasannya dan berkas diberi watermark. Ekspor lebih dari {{ $threshold }} baris
			hanya untuk pengguna dengan izin Approve pada modul ini.</p>
		@if($error)<div class="alert alert-danger py-2">{{ $error }}</div>@endif
		<form method="GET" action="{{ $action }}">
			@foreach($query as $name => $value)
				@if(is_scalar($value))<input type="hidden" name="{{ $name }}" value="{{ $value }}">@endif
			@endforeach
			<textarea name="export_reason" required minlength="{{ $min }}" maxlength="500" rows="3" class="form-control form-control-sm mb-2"
				placeholder="mis. Laporan headcount bulanan untuk direksi">{{ old('export_reason') }}</textarea>
			<button type="submit" class="btn btn-sm btn-primary">Lanjutkan ekspor</button>
			<a href="{{ url()->previous() }}" class="btn btn-sm btn-outline-secondary">Batal</a>
		</form>
	</div>
</div>
@endsection
