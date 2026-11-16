@extends('layouts.app')

@section('content')
<div class="page-titles">
	<h4 class="mb-0"> {{ $pageTitle }} <small> {{ $pageNote }} </small> </h4>
</div>

@include('core.tenants.partials.flash')

<div class="row">
	<div class="col-lg-7">
		<div class="card"><div class="card-body">
			<p class="mb-2">Diajukan oleh <strong>{{ trim(($requester->first_name ?? '').' '.($requester->last_name ?? '')) }}</strong> pada {{ $instance->submitted_at }}</p>
			@if($delegator)<div class="alert alert-info py-2 small">Tugas ini didelegasikan kepada Anda oleh {{ $delegator }}.</div>@endif

			<table class="table table-sm">
				<thead><tr><th>Data</th><th>Sebelum</th><th>Sesudah</th></tr></thead>
				<tbody>
				@foreach($summary as $line)
					@if(array_key_exists('value', $line))
						<tr><td>{{ $line['label'] }}</td><td colspan="2">{{ $line['value'] }}</td></tr>
					@else
						<tr><td>{{ $line['label'] }}</td><td class="text-muted">{{ $line['old'] ?? '–' }}</td><td class="fw-bold">{{ $line['new'] ?? '(dikosongkan)' }}</td></tr>
					@endif
				@endforeach
				</tbody>
			</table>

			@if($task->status === 'pending' && $instance->status === 'pending')
			<div x-data="{ mode: 'approve' }">
				<div class="btn-group btn-group-sm mb-2">
					<button type="button" class="btn" :class="mode === 'approve' ? 'btn-success' : 'btn-outline-success'" @click="mode = 'approve'">Setujui</button>
					<button type="button" class="btn" :class="mode === 'reject' ? 'btn-danger' : 'btn-outline-danger'" @click="mode = 'reject'">Tolak</button>
				</div>
				<form method="POST" :action="mode === 'approve' ? @js(url('workflow/tasks/'.$task->id.'/approve')) : @js(url('workflow/tasks/'.$task->id.'/reject'))">
					@csrf
					<textarea name="comment" rows="2" maxlength="1000" class="form-control form-control-sm mb-2 @error('comment') is-invalid @enderror"
						:required="mode === 'reject'" :placeholder="mode === 'reject' ? 'Alasan penolakan (wajib)' : 'Catatan (opsional)'">{{ old('comment') }}</textarea>
					<button type="submit" class="btn btn-sm" :class="mode === 'approve' ? 'btn-success' : 'btn-danger'" x-text="mode === 'approve' ? 'Kirim persetujuan' : 'Kirim penolakan'"></button>
					<a href="{{ url('workflow/tasks') }}" class="btn btn-sm btn-outline-secondary">Kembali</a>
				</form>
			</div>
			@else
				<a href="{{ url('workflow/tasks') }}" class="btn btn-sm btn-outline-secondary">Kembali</a>
			@endif
		</div></div>
	</div>

	<div class="col-lg-5">
		<div class="card"><div class="card-body">
			<h6>Jejak persetujuan</h6>
			<ul class="list-unstyled small mb-0">
				@foreach($timeline as $a)
					<li class="mb-2">
						<strong>{{ ['submit' => 'Diajukan', 'approve' => 'Disetujui', 'reject' => 'Ditolak', 'cancel' => 'Dibatalkan', 'escalate' => 'Dieskalasi', 'delegate' => 'Didelegasikan', 'complete' => 'Selesai'][$a->action] }}</strong>
						@if($a->username) oleh {{ $a->username }}@endif
						@if($a->position_title) <span class="text-muted">sebagai {{ $a->position_title }}</span>@endif
						<div class="text-muted">{{ $a->created_at }}@if($a->comment) — {{ $a->comment }}@endif</div>
					</li>
				@endforeach
			</ul>
		</div></div>
	</div>
</div>
@endsection
