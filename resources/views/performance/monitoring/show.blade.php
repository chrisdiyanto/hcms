@extends('layouts.app')

@php
	$fmt = fn ($v, $d = 2) => rtrim(rtrim(number_format((float) $v, $d, ',', '.'), '0'), ',');
	$bar = fn ($a) => $a === null ? 'bg-secondary' : ($a >= 100 ? 'bg-success' : ($a >= 80 ? 'bg-info' : ($a >= 60 ? 'bg-warning' : 'bg-danger')));
@endphp

@section('content')
<div class="page-titles d-flex justify-content-between align-items-center flex-wrap gap-2">
	<h4 class="mb-0"> {{ $pageTitle }} <small>{{ $pageNote }}</small></h4>
	<div class="d-flex gap-2">
		@if($role === 'owner' && $period->status === 'monitoring' && $plan->status === 'approved' && !$pendingChange)
			<a href="{{ url('performance/plans/'.$plan->id.'/change-request') }}" class="btn btn-sm btn-outline-primary">Ajukan perubahan KPI</a>
		@endif
		<a href="{{ url($role === 'manager' ? 'performance/team' : ($role === 'owner' ? 'performance/my-plans' : 'performance/periods/'.$period->id)) }}" class="btn btn-sm btn-outline-secondary">Kembali</a>
	</div>
</div>

@include('core.tenants.partials.flash')

@if($pendingChange)
	<div class="alert alert-info">Perubahan KPI sedang menunggu persetujuan atasan (diajukan {{ $pendingChange->created_at }}). Realisasi tetap dapat dicatat pada KPI yang berlaku.</div>
@endif

<div class="row">
	<div class="col-lg-8">
		<div class="card"><div class="card-body d-flex flex-wrap gap-4 align-items-center">
			<div>
				<div class="small text-muted">Proyeksi skor Kinerja (What)</div>
				<div class="h3 mb-0">{{ $progress['what'] !== null ? $fmt($progress['what']).'%' : '—' }}</div>
			</div>
			<div class="small">
				<div>{{ $progress['updated'] }} dari {{ $progress['total'] }} KPI sudah ada realisasi</div>
				<div class="text-muted">KPI tanpa realisasi dihitung 0. Skor akhir (dengan Perilaku & Kontribusi) dihitung pada tahap penilaian.</div>
			</div>
			<div class="small ms-auto text-muted">Versi KPI {{ $plan->kpi_version }}</div>
		</div></div>

		@foreach($progress['items'] as $o)
			<div class="card" id="kpi-{{ $o->id }}" x-data="{ open: false }"><div class="card-body">
				<div class="d-flex justify-content-between gap-2 flex-wrap">
					<div>
						<h6 class="mb-1">{{ $o->title }}</h6>
						<div class="small text-muted">{{ $types[$o->kpi_type] }} · bobot {{ $fmt($o->weight) }}% ·
							@switch($o->kpi_type)
								@case('higher') @case('lower') target {{ $fmt($o->target, 4) }} {{ $o->unit }} @break
								@case('range') rentang {{ $fmt($o->range_min, 4) }}–{{ $fmt($o->range_max, 4) }} {{ $o->unit }} @break
								@case('milestone') {{ count($o->milestones) }} milestone @break
								@default rubrik 1–5
							@endswitch
							· cap {{ $fmt($o->cap ?: $period->policy['cap']) }}%</div>
					</div>
					<div class="text-end">
						<div class="fw-bold">{{ $o->achievement !== null ? $fmt($o->achievement).'%' : 'belum ada realisasi' }}</div>
						@if($o->latest)<div class="small text-muted">per {{ $o->latest->as_of }}</div>@endif
					</div>
				</div>
				<div class="progress my-2" style="height:6px" role="progressbar" aria-label="Capaian {{ $o->title }}" aria-valuenow="{{ (int) ($o->achievement ?? 0) }}" aria-valuemin="0" aria-valuemax="{{ (int) ($o->cap ?: $period->policy['cap']) }}">
					<div class="progress-bar {{ $bar($o->achievement) }}" style="width: {{ min(100, (float) ($o->achievement ?? 0)) }}%"></div>
				</div>

				@if($canRecord)
					<button type="button" class="btn btn-sm btn-outline-primary" @click="open = !open" x-text="open ? 'Tutup' : 'Catat realisasi'"></button>
					<form method="POST" action="{{ url('performance/kpis/'.$o->id.'/updates') }}" enctype="multipart/form-data" class="mt-2 border rounded p-2" x-show="open" x-cloak>
						@csrf
						<div class="row g-2">
							<div class="col-md-3"><label class="form-label small" for="d-{{ $o->id }}">Per tanggal</label>
								<input id="d-{{ $o->id }}" type="date" name="as_of" required value="{{ min(now()->toDateString(), $plan->end_date) }}" class="form-control form-control-sm"></div>
							@if(in_array($o->kpi_type, ['higher', 'lower', 'range']))
								<div class="col-md-3"><label class="form-label small" for="r-{{ $o->id }}">Realisasi ({{ $o->unit ?: 'angka' }})</label>
									<input id="r-{{ $o->id }}" type="number" step="any" name="realization" required value="{{ $o->latest->realization ?? '' }}" class="form-control form-control-sm"></div>
							@elseif($o->kpi_type === 'qualitative')
								<div class="col-md-9"><label class="form-label small" for="q-{{ $o->id }}">Skor rubrik</label>
									<select id="q-{{ $o->id }}" name="score" required class="form-select form-select-sm">
										@foreach($o->rubric as $i => $desc)<option value="{{ $i + 1 }}" @selected(($o->latest->score ?? null) == $i + 1)>{{ $i + 1 }} — {{ $desc }}</option>@endforeach
									</select></div>
							@else
								<div class="col-12">
									@foreach($o->milestones as $i => $m)
										@php($state = $o->latest->milestones[$i] ?? [])
										<div class="d-flex flex-wrap gap-2 align-items-center small mb-1">
											<div class="form-check mb-0">
												<input type="hidden" name="milestones[{{ $i }}][done]" value="0">
												<input class="form-check-input" type="checkbox" id="m-{{ $o->id }}-{{ $i }}" name="milestones[{{ $i }}][done]" value="1" @checked(!empty($state['done']))>
												<label class="form-check-label" for="m-{{ $o->id }}-{{ $i }}">{{ $m['name'] }} ({{ $fmt($m['weight']) }}%{{ !empty($m['due_date']) ? ', tenggat '.$m['due_date'] : '' }})</label>
											</div>
											<input type="date" name="milestones[{{ $i }}][done_date]" value="{{ $state['done_date'] ?? '' }}" class="form-control form-control-sm" style="max-width:160px" aria-label="Tanggal selesai {{ $m['name'] }}">
										</div>
									@endforeach
								</div>
							@endif
							<div class="col-12"><label class="form-label small" for="n-{{ $o->id }}">Catatan</label>
								<textarea id="n-{{ $o->id }}" name="note" rows="2" maxlength="2000" class="form-control form-control-sm"></textarea></div>
							<div class="col-12"><label class="form-label small" for="e-{{ $o->id }}">Bukti ({{ $evidenceRule }})</label>
								<input id="e-{{ $o->id }}" type="file" name="evidences[]" multiple class="form-control form-control-sm"></div>
						</div>
						<button class="btn btn-sm btn-primary mt-2" type="submit">Simpan realisasi</button>
					</form>
				@endif

				@if($o->updates->isNotEmpty())
					<details class="mt-2 small">
						<summary>Riwayat realisasi ({{ $o->updates->count() }})</summary>
						<table class="table table-sm mt-1 mb-0">
							<thead><tr><th>Tanggal</th><th>Realisasi</th><th class="text-end">Capaian</th><th>Catatan & bukti</th></tr></thead>
							<tbody>
							@foreach($o->updates as $u)
								<tr><td>{{ $u->as_of }}</td>
									<td>@if($o->kpi_type === 'milestone'){{ collect($u->milestones)->where('done', true)->count() }}/{{ count($o->milestones) }} selesai
										@elseif($o->kpi_type === 'qualitative')skor {{ $u->score }}@else{{ $fmt($u->realization, 4) }} {{ $o->unit }}@endif</td>
									<td class="text-end">{{ $fmt($u->achievement) }}%</td>
									<td>{{ $u->note }}
										@foreach($u->evidences as $e)<div><a href="{{ url('performance/evidences/'.$e->id) }}"><i class="fa fa-paperclip"></i> {{ $e->original_name }}</a></div>@endforeach</td></tr>
							@endforeach
							</tbody>
						</table>
					</details>
				@endif
			</div></div>
		@endforeach
	</div>

	<div class="col-lg-4">
		<div class="card" id="checkin"><div class="card-body">
			<h5 class="card-title">Check-in & mid-year review</h5>
			@unless($hasMidyear)<div class="alert alert-warning small py-1">Mid-year review wajib sebelum tahap penilaian.</div>@endunless
			@forelse($checkins as $c)
				<div class="border rounded p-2 mb-2 small">
					<div class="d-flex justify-content-between"><strong>{{ $checkinTypes[$c->type] }}</strong><span>{{ $c->checkin_date }}</span></div>
					@if($c->employee_notes)<div class="mt-1"><span class="text-muted">Pegawai:</span> {{ $c->employee_notes }}</div>@endif
					@if($c->status === 'completed')
						<div class="mt-1"><span class="text-muted">Atasan:</span> {{ $c->manager_notes }}</div>
						<span class="badge bg-success">selesai {{ \Illuminate\Support\Str::before($c->completed_at, ' ') }}</span>
					@elseif($role === 'manager')
						<form method="POST" action="{{ url('performance/checkins/'.$c->id.'/complete') }}" class="mt-1">
							@csrf
							<textarea name="manager_notes" required rows="2" maxlength="3000" class="form-control form-control-sm mb-1" placeholder="Catatan & arahan atasan" aria-label="Catatan atasan"></textarea>
							<button class="btn btn-sm btn-success" type="submit">Selesaikan</button>
						</form>
					@else
						<span class="badge bg-light text-dark">menunggu tanggapan atasan</span>
					@endif
				</div>
			@empty
				<p class="small text-muted">Belum ada check-in.</p>
			@endforelse

			@if($canCheckin)
				<form method="POST" action="{{ url('performance/plans/'.$plan->id.'/checkins') }}" class="mt-2">
					@csrf
					<div class="row g-2">
						<div class="col-7"><select name="type" class="form-select form-select-sm" aria-label="Jenis check-in">
							@foreach($checkinTypes as $k => $l)@if($k !== 'midyear' || !$hasMidyear)<option value="{{ $k }}" @selected(!$hasMidyear && $k === 'midyear')>{{ $l }}</option>@endif @endforeach</select></div>
						<div class="col-5"><input type="date" name="checkin_date" required value="{{ now()->toDateString() }}" class="form-control form-control-sm" aria-label="Tanggal check-in"></div>
						<div class="col-12"><textarea name="employee_notes" rows="2" maxlength="3000" @required($role === 'owner') class="form-control form-control-sm"
							placeholder="{{ $role === 'owner' ? 'Progres, kendala, dukungan yang dibutuhkan' : 'Catatan pembuka (opsional)' }}" aria-label="Catatan"></textarea></div>
					</div>
					<button class="btn btn-sm btn-outline-primary mt-2" type="submit">Buat check-in</button>
				</form>
			@endif
		</div></div>

		@if($changeHistory->isNotEmpty())
			<div class="card"><div class="card-body">
				<h5 class="card-title">Riwayat perubahan KPI</h5>
				<ul class="small mb-0 ps-3">
					@foreach($changeHistory as $c)
						<li>{{ \Illuminate\Support\Str::before($c->created_at, ' ') }} — {{ ['approved' => 'disetujui', 'rejected' => 'ditolak', 'cancelled' => 'dibatalkan'][$c->status] }}@if($c->to_version) (v{{ $c->from_version }} → v{{ $c->to_version }})@endif: {{ \Illuminate\Support\Str::limit($c->reason, 80) }}</li>
					@endforeach
				</ul>
			</div></div>
		@endif
	</div>
</div>
@endsection
