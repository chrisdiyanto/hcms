@extends('layouts.app')

@section('content')
<div class="page-titles d-flex flex-wrap justify-content-between align-items-center gap-2">
	<h4 class="mb-0"> {{ $pageTitle }} <small>{{ $pageNote }}</small></h4>
	<div>
		@if($canEdit && !$versions->contains('status', 'draft'))
			<form method="POST" action="{{ url('competency/framework/draft') }}" class="d-inline">@csrf
				<button class="btn btn-sm btn-primary" type="submit">{{ $versions->isEmpty() ? 'Mulai kamus kompetensi' : 'Buat draft versi baru' }}</button></form>
		@endif
	</div>
</div>

@include('core.tenants.partials.flash')

@php($statusBadge = ['draft' => ['Draft', 'warning text-dark'], 'published' => ['Berlaku', 'success'], 'retired' => ['Pensiun', 'secondary']])

@if($versions->isNotEmpty())
<ul class="nav nav-pills mb-3">
	@foreach($versions as $v)
		<li class="nav-item"><a class="nav-link {{ $selected && $selected->id === $v->id ? 'active' : '' }}" href="{{ url('competency/framework?version='.$v->id) }}">
			v{{ $v->version }} <span class="badge bg-{{ $statusBadge[$v->status][1] }}">{{ $statusBadge[$v->status][0] }}</span></a></li>
	@endforeach
</ul>
@endif

@if(!$selected)
	<div class="card"><div class="card-body text-muted">Belum ada kamus kompetensi. Mulai dengan membuat draft pertama, isi kompetensinya, lalu terbitkan.</div></div>
@else
	@if($selected->status === 'draft')
	<div class="card border-warning"><div class="card-body">
		<div class="d-flex flex-wrap justify-content-between align-items-start gap-2">
			<div>
				<h6 class="mb-1">Draft belum berlaku</h6>
				<p class="small text-muted mb-0">Perubahan pada draft tidak memengaruhi asesmen atau profil job sampai diterbitkan. Saat terbit, versi yang berlaku dipensiunkan dan profil job dipindahkan berdasarkan kode kompetensi.</p>
			</div>
			<div class="text-nowrap">
				@if($canEdit)<a href="{{ url('competency/framework/'.$selected->id.'/competencies/create') }}" class="btn btn-sm btn-info">Tambah kompetensi</a>@endif
				@if($canApprove)
					<form method="POST" action="{{ url('competency/framework/'.$selected->id.'/publish') }}" class="d-inline"
						onsubmit="return confirm('Terbitkan {{ $selected->name }}? Versi yang berlaku sekarang akan dipensiunkan.')">@csrf
						<button class="btn btn-sm btn-success" type="submit" @disabled($problems !== [])>Terbitkan</button></form>
				@endif
			</div>
		</div>
		@if($problems !== [])
			<div class="alert alert-warning mt-3 mb-0 small">
				<strong>Belum dapat diterbitkan ({{ count($problems) }}):</strong>
				<ul class="mb-0 ps-3">@foreach(array_slice($problems, 0, 15) as $p)<li>{{ $p }}</li>@endforeach
					@if(count($problems) > 15)<li>… dan {{ count($problems) - 15 }} lainnya</li>@endif</ul>
			</div>
		@endif
	</div></div>
	@else
		<p class="small text-muted">Diterbitkan {{ $selected->published_at }}, berlaku mulai {{ $selected->effective_from }}. Versi ini hanya baca.</p>
	@endif

	@forelse($competencies as $category => $items)
		<div class="card"><div class="card-body">
			<h5 class="card-title">{{ $category }} <small class="text-muted">{{ $items->count() }} kompetensi</small></h5>
			<div class="table-responsive"><table class="table table-sm align-middle mb-0">
				<thead><tr><th>Kode</th><th>Nama</th><th>Definisi</th><th class="text-end">Indikator</th><th class="text-end">Dipakai job</th><th>Aktif</th><th></th></tr></thead>
				<tbody>
				@foreach($items as $c)
					<tr class="{{ $c->is_active ? '' : 'text-muted' }}">
						<td><code>{{ $c->code }}</code></td>
						<td>{{ $c->name }}</td>
						<td class="small">{{ \Illuminate\Support\Str::limit($c->definition, 90) }}</td>
						<td class="text-end">{{ $c->indicator_count }}</td>
						<td class="text-end">{{ $c->job_count }}</td>
						<td>{{ $c->is_active ? 'Ya' : 'Tidak' }}</td>
						<td class="text-end text-nowrap">
							<a href="{{ url('competency/competencies/'.$c->id.'/edit') }}" class="btn btn-sm btn-outline-primary">{{ $selected->status === 'draft' && $canEdit ? 'Ubah' : 'Lihat' }}</a>
							@if($selected->status === 'draft' && $canEdit)
								<form method="POST" action="{{ url('competency/competencies/'.$c->id) }}" class="d-inline" onsubmit="return confirm('Hapus kompetensi ini dari draft?')">@csrf @method('DELETE')
									<button class="btn btn-sm btn-outline-danger" type="submit">Hapus</button></form>
							@endif
						</td>
					</tr>
				@endforeach
				</tbody>
			</table></div>
		</div></div>
	@empty
		<div class="card"><div class="card-body text-muted">Belum ada kompetensi pada versi ini.</div></div>
	@endforelse
@endif
@endsection
