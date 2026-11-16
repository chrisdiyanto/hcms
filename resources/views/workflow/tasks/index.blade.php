@extends('layouts.app')

@section('content')
<div class="page-titles d-flex justify-content-between align-items-center">
	<h4 class="mb-0"> {{ $pageTitle }} </h4>
	<a href="{{ url('workflow/delegations') }}" class="btn btn-sm btn-outline-secondary">Delegasi</a>
</div>

@include('core.tenants.partials.flash')

@php($statusLabel = ['pending' => ['Menunggu', 'warning'], 'approved' => ['Disetujui', 'success'], 'rejected' => ['Ditolak', 'danger'], 'cancelled' => ['Dibatalkan', 'secondary'], 'escalated' => ['Dieskalasi', 'info']])

<div class="card">
	<div class="card-body">
		<h5 class="card-title">Menunggu keputusan Anda <span class="badge bg-warning text-dark">{{ $pending->count() }}</span></h5>
		<div class="table-responsive">
			<table class="table table-hover align-middle mb-0">
				<thead><tr><th>Pengajuan</th><th>Langkah</th><th>Pengaju</th><th>Diajukan</th><th>Batas waktu</th><th></th></tr></thead>
				<tbody>
				@forelse($pending as $t)
					@php($overdue = $t->due_at && \Illuminate\Support\Carbon::parse($t->due_at)->isPast())
					<tr>
						<td>{{ $title($t) }}
							<div class="small text-muted">{{ $workflowNames[$t->subject_type] ?? $t->subject_type }}
								@if($t->delegated_from_user_id) · delegasi @endif
								@if($t->escalation_level) · eskalasi {{ $t->escalation_level }} @endif</div></td>
						<td>{{ $t->step_name }}</td>
						<td>{{ $t->requester_name }}</td>
						<td class="small">{{ $t->submitted_at }}</td>
						<td class="small {{ $overdue ? 'text-danger fw-bold' : '' }}">{{ $t->due_at ?? '–' }}</td>
						<td class="text-end"><a href="{{ url('workflow/tasks/'.$t->id) }}" class="btn btn-sm btn-primary">Tinjau</a></td>
					</tr>
				@empty
					<tr><td colspan="6" class="text-muted">Tidak ada tugas yang menunggu.</td></tr>
				@endforelse
				</tbody>
			</table>
		</div>
	</div>
</div>

<div class="row">
	<div class="col-lg-6">
		<div class="card"><div class="card-body">
			<h5 class="card-title">Pengajuan saya</h5>
			<table class="table table-sm mb-0">
				@forelse($mine as $i)
					<tr>
						<td>{{ $title($i) }}<div class="small text-muted">{{ $i->submitted_at }}</div></td>
						<td><span class="badge bg-{{ $statusLabel[$i->status][1] }}">{{ $statusLabel[$i->status][0] }}</span></td>
						<td class="text-end">@if($i->status === 'pending')
							<form method="POST" action="{{ url('workflow/requests/'.$i->id.'/cancel') }}" onsubmit="return confirm('Batalkan pengajuan ini?')">@csrf
								<button class="btn btn-sm btn-link text-danger p-0" type="submit">Batalkan</button></form>@endif</td>
					</tr>
				@empty
					<tr><td class="text-muted">Belum ada pengajuan.</td></tr>
				@endforelse
			</table>
		</div></div>
	</div>
	<div class="col-lg-6">
		<div class="card"><div class="card-body">
			<h5 class="card-title">Sudah Anda putus</h5>
			<table class="table table-sm mb-0">
				@forelse($done as $t)
					<tr><td>{{ $title($t) }}<div class="small text-muted">{{ $t->step_name }} · {{ $t->acted_at }}</div></td>
						<td><span class="badge bg-{{ $statusLabel[$t->status][1] }}">{{ $statusLabel[$t->status][0] }}</span></td></tr>
				@empty
					<tr><td class="text-muted">Belum ada.</td></tr>
				@endforelse
			</table>
		</div></div>
	</div>
</div>
@endsection
