@extends('layouts.app')

@section('content')
<div class="page-titles"><h4 class="mb-0"> {{ $pageTitle }} <small>{{ $pageNote }}</small></h4></div>

@include('core.tenants.partials.flash')

@unless($hasFramework)
	<div class="alert alert-warning">Belum ada kamus kompetensi yang berlaku. Terbitkan kamus di menu <a href="{{ url('competency/framework') }}">Kamus Kompetensi</a> lebih dulu.</div>
@endunless

@forelse($jobs as $family => $items)
	<div class="card"><div class="card-body">
		<h5 class="card-title">{{ $family ?: 'Tanpa job family' }}</h5>
		<div class="table-responsive"><table class="table table-sm align-middle mb-0">
			<thead><tr><th>Kode</th><th>Job</th><th class="text-end">Kompetensi</th><th class="text-end">Kritis</th><th class="text-end">Total bobot</th><th class="text-end">Posisi</th><th></th></tr></thead>
			<tbody>
			@foreach($items as $job)
				<tr>
					<td><code>{{ $job->code }}</code></td>
					<td>{{ $job->title }}</td>
					<td class="text-end">@if($job->competency_count){{ $job->competency_count }}@else<span class="badge bg-warning text-dark">belum ada</span>@endif</td>
					<td class="text-end">{{ $job->critical_count }}</td>
					<td class="text-end">{{ $job->total_weight }}</td>
					<td class="text-end">{{ $job->position_count }}</td>
					<td class="text-end"><a href="{{ url('competency/job-profiles/'.$job->id) }}" class="btn btn-sm btn-outline-primary">{{ !empty($access['is_edit']) ? 'Atur profil' : 'Lihat' }}</a></td>
				</tr>
			@endforeach
			</tbody>
		</table></div>
	</div></div>
@empty
	<div class="card"><div class="card-body text-muted">Belum ada job. Buat job di menu Organisasi › Job.</div></div>
@endforelse
@endsection
