@extends('layouts.app')

@section('content')
<div class="page-titles"><h4 class="mb-0"> {{ $pageTitle }} <small>{{ $pageNote }}</small></h4></div>

@include('core.tenants.partials.flash')

@if(!$employee)
	<div class="card"><div class="card-body text-muted">Akun Anda belum ditautkan ke data pegawai. Hubungi HR.</div></div>
@else
@php($status = ['pending' => ['Menunggu', 'warning'], 'approved' => ['Disetujui', 'success'], 'rejected' => ['Ditolak', 'danger'], 'cancelled' => ['Dibatalkan', 'secondary']])
@php($hasPending = $requests->contains('status', 'pending'))
<div class="row">
	<div class="col-lg-7">
		<div class="card"><div class="card-body">
			<h5 class="card-title">{{ $employee->full_name }}</h5>
			<p class="text-muted small">Ubah data di bawah lalu kirim. Perubahan berlaku setelah diverifikasi HR. Hanya kolom yang Anda ubah yang dikirim.</p>
			@error('changes')<div class="alert alert-danger py-2">{{ $message }}</div>@enderror

			<form method="POST" action="{{ url('ess/change-requests') }}"
				x-data="{ original: @js(collect($fields)->keys()->mapWithKeys(fn ($f) => [$f => (string) ($employee->{$f} ?? '')])),
					form: @js(collect($fields)->keys()->mapWithKeys(fn ($f) => [$f => (string) old($f, $employee->{$f} ?? '')])),
					get changed() { return Object.keys(this.form).filter(k => this.form[k] !== this.original[k]) } }">
				@csrf
				<fieldset @disabled($hasPending)>
				<div class="row g-3">
					@foreach($fields as $name => $label)
						<div class="col-md-6">
							<label class="form-label">{{ $label }} <span class="badge bg-info" x-show="changed.includes('{{ $name }}')" x-cloak>diubah</span></label>
							@if($name === 'marital_status')
								<select name="{{ $name }}" x-model="form.{{ $name }}" class="form-select form-select-sm"><option value=""></option>
									@foreach($marital as $v => $l)<option value="{{ $v }}">{{ $l }}</option>@endforeach</select>
							@elseif($name === 'ptkp_status')
								<select name="{{ $name }}" x-model="form.{{ $name }}" class="form-select form-select-sm"><option value=""></option>
									@foreach($ptkp as $v)<option value="{{ $v }}">{{ $v }}</option>@endforeach</select>
							@elseif(str_starts_with($name, 'address'))
								<textarea name="{{ $name }}" rows="2" x-model="form.{{ $name }}" class="form-control form-control-sm"></textarea>
							@else
								<input name="{{ $name }}" x-model="form.{{ $name }}" class="form-control form-control-sm @error($name) is-invalid @enderror">
							@endif
						</div>
					@endforeach
					<div class="col-12"><label class="form-label">Alasan perubahan <span class="text-danger">*</span></label>
						<input name="reason" required maxlength="500" value="{{ old('reason') }}" class="form-control form-control-sm" placeholder="mis. Pindah alamat, menikah"></div>
				</div>
				<button type="submit" class="btn btn-sm btn-primary mt-3" :disabled="changed.length === 0"
					x-text="changed.length ? `Kirim ${changed.length} perubahan` : 'Belum ada perubahan'"></button>
				</fieldset>
				@if($hasPending)<p class="small text-warning mt-2 mb-0">Masih ada pengajuan yang menunggu verifikasi HR.</p>@endif
			</form>
		</div></div>
	</div>
	<div class="col-lg-5">
		<div class="card"><div class="card-body">
			<h5 class="card-title">Riwayat pengajuan</h5>
			<ul class="list-unstyled mb-0">
				@forelse($requests as $r)
					<li class="mb-2 small">
						<span class="badge bg-{{ $status[$r->status][1] }}">{{ $status[$r->status][0] }}</span> {{ $r->created_at }}
						<div>{{ collect(json_decode($r->changes, true))->keys()->map(fn ($k) => $fields[$k] ?? $k)->implode(', ') }} — {{ $r->reason }}</div>
						@if($r->status === 'pending' && $r->workflow_instance_id)
							<form method="POST" action="{{ url('workflow/requests/'.$r->workflow_instance_id.'/cancel') }}" class="d-inline">@csrf
								<button class="btn btn-link btn-sm text-danger p-0" type="submit">Batalkan</button></form>
						@endif
					</li>
				@empty
					<li class="text-muted">Belum ada pengajuan.</li>
				@endforelse
			</ul>
		</div></div>
	</div>
</div>
@endif
@endsection
