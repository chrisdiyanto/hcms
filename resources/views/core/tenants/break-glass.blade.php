@extends('layouts.app')

@section('content')
<div class="page-titles">
	<h4 class="mb-0"> {{ $pageTitle }} <small> {{ $pageNote }} </small> </h4>
</div>

@include('core.tenants.partials.flash')

<div class="row">
	<div class="col-lg-7">
		<div class="card border-danger">
			<div class="card-body">
				<div class="alert alert-danger">
					<strong>Akses darurat ke data pegawai {{ $tenant->name }} ({{ $tenant->code }}).</strong>
					Gunakan hanya untuk insiden atau permintaan dukungan resmi dari tenant. Tenant Administrator
					akan langsung menerima notifikasi berisi alasan Anda, dan setiap halaman yang Anda buka dicatat.
				</div>

				<form method="POST" action="{{ url('core/tenants/'.$tenant->uuid.'/break-glass') }}" x-data="{ reason: @js(old('reason', '')) }">
					@csrf
					<div class="mb-3">
						<label class="form-label" for="reason">Alasan <span class="text-danger">*</span></label>
						<textarea id="reason" name="reason" rows="3" required minlength="20" maxlength="1000" x-model="reason"
							class="form-control form-control-sm @error('reason') is-invalid @enderror"
							placeholder="mis. Tiket dukungan #1234: Tenant Admin tidak dapat membuka data pegawai"></textarea>
						<div class="form-text" x-text="reason.length < 20 ? `Minimal 20 karakter (${reason.length}/20)` : `${reason.length}/1000`"></div>
					</div>
					<div class="mb-3">
						<label class="form-label" for="minutes">Durasi</label>
						<select id="minutes" name="minutes" class="form-select form-select-sm">
							@foreach($durations as $m)
								<option value="{{ $m }}" @selected(old('minutes', $durations[0]) == $m)>{{ $m }} menit</option>
							@endforeach
						</select>
					</div>
					<div class="form-check mb-3">
						<input class="form-check-input" type="checkbox" value="1" id="confirm" name="confirm" required>
						<label class="form-check-label" for="confirm">Saya memahami akses ini dicatat dan diberitahukan ke tenant.</label>
					</div>
					<button class="btn btn-sm btn-danger" type="submit" :disabled="reason.length < 20">Mulai break-glass</button>
					<a href="{{ url('core/tenants') }}" class="btn btn-sm btn-outline-secondary">Batal</a>
				</form>
			</div>
		</div>
	</div>
</div>
@endsection
