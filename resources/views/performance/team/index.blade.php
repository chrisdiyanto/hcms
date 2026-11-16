@extends('layouts.app')

@section('content')
<div class="page-titles"><h4 class="mb-0"> {{ $pageTitle }} <small>{{ $pageNote }}</small></h4></div>

@include('core.tenants.partials.flash')

@forelse($periods as $period)
	<div class="card"><div class="card-body">
		<div class="d-flex justify-content-between flex-wrap gap-2">
			<h5 class="card-title mb-1">{{ $period->name }}</h5>
			<span class="small text-muted">tahap {{ mb_strtolower($periodStatuses[$period->status]) }}</span>
		</div>
		<div class="table-responsive"><table class="table table-sm align-middle mb-0 mt-2">
			<thead><tr><th>Pegawai</th><th>Posisi</th><th>Rencana</th><th class="text-end">Proyeksi What</th><th>Realisasi</th><th>Mid-year</th><th></th></tr></thead>
			<tbody>
			@forelse($period->plans as $p)
				<tr>
					<td>{{ $p->full_name }}<div class="small text-muted">{{ $p->employee_number }}</div></td>
					<td class="small">{{ $p->position_title }}</td>
					<td><span class="badge {{ ['approved' => 'bg-success', 'submitted' => 'bg-info', 'returned' => 'bg-warning text-dark', 'draft' => 'bg-light text-dark'][$p->status] }}">{{ $statuses[$p->status] }}</span></td>
					<td class="text-end">{{ $p->progress && $p->progress['what'] !== null ? number_format($p->progress['what'], 1, ',', '.').'%' : '—' }}</td>
					<td class="small">{{ $p->progress ? $p->progress['updated'].'/'.$p->progress['total'].' KPI' : '—' }}</td>
					<td class="small">
						@if(!$p->midyear)<span class="badge bg-warning text-dark">belum</span>
						@elseif($p->midyear->status === 'open')<span class="badge bg-info">perlu tanggapan Anda</span>
						@else<span class="badge bg-success">selesai</span>@endif
						@if($p->open_checkins && (!$p->midyear || $p->midyear->status !== 'open'))<div class="text-muted">{{ $p->open_checkins }} check-in terbuka</div>@endif
					</td>
					<td class="text-end">
						@if($p->status === 'approved' && $period->status !== 'planning')
							<a href="{{ url('performance/plans/'.$p->id.'/monitoring') }}" class="btn btn-sm btn-outline-primary">Monitoring</a>
						@elseif($p->status === 'submitted')
							<a href="{{ url('workflow/tasks') }}" class="btn btn-sm btn-primary">Tinjau</a>
						@endif
					</td>
				</tr>
			@empty
				<tr><td colspan="7" class="text-muted">Tidak ada bawahan langsung dengan rencana di periode ini.</td></tr>
			@endforelse
			</tbody>
		</table></div>
	</div></div>
@empty
	<div class="alert alert-info">Belum ada periode kinerja yang berjalan.</div>
@endforelse
@endsection
