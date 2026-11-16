@extends('layouts.app')

@section('content')
<div class="page-titles d-flex justify-content-between align-items-center">
	<h4 class="mb-0"> {{ $pageTitle }} </h4>
	<a href="{{ url('workflow/tasks') }}" class="btn btn-sm btn-outline-secondary">Tugas persetujuan</a>
</div>

@include('core.tenants.partials.flash')

<div class="row">
	<div class="col-lg-7">
		<div class="card"><div class="card-body">
			<h5 class="card-title">Delegasi yang Anda berikan</h5>
			<table class="table table-sm align-middle mb-0">
				<thead><tr><th>Kepada</th><th>Periode</th><th>Alasan</th><th></th></tr></thead>
				<tbody>
				@forelse($given as $d)
					<tr class="{{ $d->revoked_at || $d->ends_on < now()->toDateString() ? 'text-muted' : '' }}">
						<td>{{ trim($d->first_name.' '.$d->last_name) }}</td>
						<td class="small">{{ $d->starts_on }} – {{ $d->ends_on }}</td>
						<td class="small">{{ $d->reason }}@if($d->revoked_at) <span class="badge bg-secondary">dicabut</span>@endif</td>
						<td class="text-end">@if(!$d->revoked_at && $d->ends_on >= now()->toDateString())
							<form method="POST" action="{{ url('workflow/delegations/'.$d->id.'/revoke') }}">@csrf<button class="btn btn-sm btn-link text-danger p-0" type="submit">Cabut</button></form>@endif</td>
					</tr>
				@empty
					<tr><td colspan="4" class="text-muted">Belum ada delegasi.</td></tr>
				@endforelse
				</tbody>
			</table>

			@if($received->isNotEmpty())
				<h6 class="mt-4">Delegasi yang Anda terima</h6>
				<ul class="small mb-0">@foreach($received as $d)<li>Dari {{ trim($d->first_name.' '.$d->last_name) }}, {{ $d->starts_on }} – {{ $d->ends_on }}</li>@endforeach</ul>
			@endif
		</div></div>
	</div>
	<div class="col-lg-5">
		<div class="card"><div class="card-body">
			<h5 class="card-title">Delegasi baru</h5>
			<p class="small text-muted">Selama periode ini, tugas persetujuan baru untuk Anda dialihkan ke penerima delegasi. Maksimal 90 hari.</p>
			<form method="POST" action="{{ url('workflow/delegations') }}">
				@csrf
				<div class="mb-2"><label class="form-label">Kepada</label>
					<select name="delegate_user_id" required class="form-select form-select-sm select2"><option value=""></option>
						@foreach($users as $v => $l)<option value="{{ $v }}" @selected(old('delegate_user_id') == $v)>{{ $l }}</option>@endforeach</select></div>
				<div class="row g-2 mb-2">
					<div class="col"><label class="form-label">Mulai</label><input type="date" name="starts_on" required value="{{ old('starts_on', now()->toDateString()) }}" class="form-control form-control-sm"></div>
					<div class="col"><label class="form-label">Sampai</label><input type="date" name="ends_on" required value="{{ old('ends_on') }}" class="form-control form-control-sm"></div>
				</div>
				<div class="mb-3"><label class="form-label">Alasan</label><input name="reason" required maxlength="255" value="{{ old('reason') }}" class="form-control form-control-sm" placeholder="mis. Cuti tahunan"></div>
				<button class="btn btn-sm btn-primary" type="submit">Simpan delegasi</button>
			</form>
		</div></div>
	</div>
</div>
@endsection
