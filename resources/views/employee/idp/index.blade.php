@extends('layouts.app')

@section('content')
@php($badge = ['draft' => 'bg-secondary', 'submitted' => 'bg-info', 'returned' => 'bg-warning text-dark', 'approved' => 'bg-primary', 'completed' => 'bg-success', 'cancelled' => 'bg-light text-dark'])
<div class="page-titles d-flex flex-wrap justify-content-between align-items-center gap-2">
	<h4 class="mb-0"> {{ $pageTitle }} <small>{{ $pageNote }}</small></h4>
	<form method="GET" class="d-flex align-items-center gap-2"><label class="small text-muted mb-0" for="year">Tahun</label>
		<input id="year" type="number" name="year" value="{{ $year }}" class="form-control form-control-sm" style="width: 90px" onchange="this.form.submit()"></form>
</div>

@include('core.tenants.partials.flash')
@if($errors->any())<div class="alert alert-danger small"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif

<div class="row">
	<div class="col-lg-7">
		<div class="card"><div class="card-body">
			<h5 class="card-title">Rencana pengembangan {{ $year }}</h5>
			@if(!$me)
				<p class="text-muted small mb-0">Akun Anda belum ditautkan ke data pegawai. Hubungi HR.</p>
			@elseif($current)
				<p class="mb-2"><span class="badge {{ $badge[$current->status] }}">{{ $statuses[$current->status] }}</span>
					@if($current->career_goal)<span class="small text-muted ms-2">Sasaran karier: {{ $current->career_goal }}</span>@endif</p>
				<a href="{{ url('ess/idp/'.$current->id) }}" class="btn btn-primary btn-sm">{{ in_array($current->status, ['draft', 'returned'], true) ? 'Lanjutkan menyusun' : 'Buka IDP' }}</a>
			@else
				<p class="small text-muted">Draft IDP otomatis berisi kebutuhan pelatihan Anda yang sudah disetujui HR dan saran pengembangan dari asesmen kompetensi, serta aspirasi karier terkini sebagai sasaran karier. Anda dapat menambah, mengubah, atau menghapus aktivitas sebelum mengajukannya ke atasan.</p>
				<form method="POST" action="{{ url('ess/idp') }}">@csrf <input type="hidden" name="year" value="{{ $year }}">
					<button class="btn btn-primary btn-sm"><i class="fa fa-plus"></i> Buat IDP {{ $year }}</button></form>
			@endif
		</div></div>
		@if($history->count() > 1)
			<div class="card"><div class="card-body">
				<h5 class="card-title">Riwayat IDP</h5>
				<ul class="small mb-0">@foreach($history as $h)<li><a href="{{ url('ess/idp/'.$h->id) }}">IDP {{ $h->period_year }}</a> <span class="badge {{ $badge[$h->status] }}">{{ $statuses[$h->status] }}</span></li>@endforeach</ul>
			</div></div>
		@endif
	</div>
	@if($team->isNotEmpty())
		<div class="col-lg-5">
			<div class="card"><div class="card-body">
				<h5 class="card-title">IDP tim saya {{ $year }}</h5>
				<table class="table table-sm small mb-0"><tbody>
				@foreach($team as $t)
					@php($idp = $teamIdps->firstWhere('employee_id', $t->id))
					<tr><td>{{ $t->full_name }}</td>
						<td class="text-end">@if($idp)<a href="{{ url('ess/idp/'.$idp->id) }}" class="badge text-decoration-none {{ $badge[$idp->status] }}">{{ $statuses[$idp->status] }}</a>@else<span class="text-muted">belum ada</span>@endif</td></tr>
				@endforeach
				</tbody></table>
				<p class="small text-muted mt-2 mb-0">Pengajuan IDP bawahan masuk ke Tugas Persetujuan Anda.</p>
			</div></div>
		</div>
	@endif
</div>
@endsection
