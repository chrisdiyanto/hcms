@extends('layouts.app')

@section('content')
<div class="page-titles d-flex flex-wrap justify-content-between align-items-center gap-2">
	<h4 class="mb-0"> {{ $pageTitle }} <small>{{ $pageNote }}</small></h4>
	@if($canEdit && !$versions->contains('status', 'draft'))
		<form method="POST" action="{{ url('talent/potential-instruments/draft') }}">@csrf
			<button class="btn btn-sm btn-primary" type="submit">{{ $versions->isEmpty() ? 'Mulai instrumen potensi' : 'Buat draft versi baru' }}</button></form>
	@endif
</div>

@include('core.tenants.partials.flash')
@if($errors->any())
	<div class="alert alert-danger"><ul class="mb-0 small">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
@endif

@php($badge = ['draft' => 'warning text-dark', 'published' => 'success', 'retired' => 'secondary'])
@php($fmt = fn ($v) => number_format((float) $v, 2, ',', '.'))

@if($versions->isNotEmpty())
<ul class="nav nav-pills mb-3">
	@foreach($versions as $v)
		<li class="nav-item"><a class="nav-link {{ $selected && $selected->id === $v->id ? 'active' : '' }}" href="{{ url('talent/potential-instruments?version='.$v->id) }}">
			v{{ $v->version }} <span class="badge bg-{{ $badge[$v->status] }}">{{ $statuses[$v->status] }}</span></a></li>
	@endforeach
</ul>
@endif

@if(!$selected)
	<div class="card"><div class="card-body">
		<p class="mb-1">Belum ada instrumen asesmen potensi.</p>
		<p class="text-muted small mb-0">Instrumen potensi menjadi sumbu kedua Nine Box dan terpisah dari rating kinerja maupun asesmen kompetensi. Draft pertama diisi tiga kriteria awal (kemampuan & kelincahan belajar, aspirasi, keterikatan) yang dapat Anda ubah sebelum diterbitkan.</p>
	</div></div>
@elseif($selected->status === 'draft' && $canEdit)
	{{-- ===== Editor draft ===== --}}
	<div class="card border-warning"><div class="card-body">
		<div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-2">
			<div>
				<h6 class="mb-1">Draft belum berlaku</h6>
				<p class="small text-muted mb-0">Perubahan draft tidak memengaruhi apa pun sampai diterbitkan. Terbit memensiunkan versi yang berlaku.</p>
			</div>
			<div class="text-nowrap">
				@if($canApprove)
					<form method="POST" action="{{ url('talent/potential-instruments/'.$selected->id.'/publish') }}" class="d-inline"
						onsubmit="return confirm('Terbitkan instrumen ini? Versi yang berlaku akan dipensiunkan.')">@csrf
						<button class="btn btn-sm btn-success" @disabled($problems !== [])>Terbitkan</button></form>
				@endif
				<form method="POST" action="{{ url('talent/potential-instruments/'.$selected->id) }}" class="d-inline" onsubmit="return confirm('Buang draft ini?')">@csrf @method('DELETE')
					<button class="btn btn-sm btn-outline-danger">Buang draft</button></form>
			</div>
		</div>
		@if($problems)
			<div class="alert alert-warning small mb-0"><strong>Belum dapat diterbitkan:</strong><ul class="mb-0">@foreach($problems as $p)<li>{{ $p }}</li>@endforeach</ul></div>
		@endif
	</div></div>

	<form method="POST" action="{{ url('talent/potential-instruments/'.$selected->id) }}"
		x-data="{ criteria: @js(old('criteria', $criteria->map(fn ($c) => ['code' => $c->code, 'name' => $c->name, 'description' => $c->description, 'indicators' => $c->indicators, 'weight' => (int) $c->weight])->values())),
			get total() { return this.criteria.reduce((s, c) => s + (parseInt(c.weight) || 0), 0) },
			add() { this.criteria.push({ code: '', name: '', description: '', indicators: '', weight: 0 }) } }">
		@csrf @method('PUT')
		<div class="card"><div class="card-body">
			<h5 class="card-title">Pengaturan</h5>
			<div class="row g-3">
				<div class="col-md-6"><label class="form-label" for="name">Nama instrumen</label>
					<input id="name" name="name" class="form-control" required maxlength="150" value="{{ old('name', $selected->name) }}"></div>
				<div class="col-md-2"><label class="form-label" for="scale_max">Skala 1 s.d.</label>
					<input id="scale_max" name="scale_max" type="number" min="3" max="10" class="form-control" required value="{{ old('scale_max', $selected->scale_max) }}"></div>
				<div class="col-md-2"><label class="form-label" for="threshold_medium">Ambang sedang ≥</label>
					<input id="threshold_medium" name="threshold_medium" type="number" step="0.01" min="1" class="form-control" required value="{{ old('threshold_medium', $selected->threshold_medium) }}"></div>
				<div class="col-md-2"><label class="form-label" for="threshold_high">Ambang tinggi ≥</label>
					<input id="threshold_high" name="threshold_high" type="number" step="0.01" min="1" class="form-control" required value="{{ old('threshold_high', $selected->threshold_high) }}"></div>
				<div class="col-md-3"><label class="form-label" for="validity_months">Masa berlaku hasil (bulan)</label>
					<input id="validity_months" name="validity_months" type="number" min="6" max="60" class="form-control" required value="{{ old('validity_months', $selected->validity_months) }}"></div>
				<div class="col-md-9"><label class="form-label" for="notes">Catatan</label>
					<input id="notes" name="notes" class="form-control" maxlength="2000" value="{{ old('notes', $selected->notes) }}" placeholder="mis. dasar pemilihan kriteria, rujukan model"></div>
			</div>
			<p class="small text-muted mt-2 mb-0">Skor potensi = rata-rata tertimbang skor kriteria. Di bawah ambang sedang = potensi rendah.</p>
		</div></div>

		<div class="card"><div class="card-body">
			<div class="d-flex justify-content-between align-items-center mb-2">
				<h5 class="card-title mb-0">Kriteria</h5>
				<span class="badge" :class="total === 100 ? 'bg-success' : 'bg-warning text-dark'">Total bobot <span x-text="total"></span>%</span>
			</div>
			<template x-for="(c, i) in criteria" :key="i">
				<div class="border rounded p-3 mb-3">
					<div class="row g-2">
						<div class="col-md-2"><label class="form-label small" :for="'code'+i">Kode</label>
							<input class="form-control form-control-sm text-uppercase" :id="'code'+i" :name="'criteria['+i+'][code]'" x-model="c.code" maxlength="30" required></div>
						<div class="col-md-6"><label class="form-label small" :for="'cname'+i">Nama kriteria</label>
							<input class="form-control form-control-sm" :id="'cname'+i" :name="'criteria['+i+'][name]'" x-model="c.name" maxlength="150" required></div>
						<div class="col-md-2"><label class="form-label small" :for="'weight'+i">Bobot (%)</label>
							<input class="form-control form-control-sm" type="number" min="1" max="100" :id="'weight'+i" :name="'criteria['+i+'][weight]'" x-model="c.weight" required></div>
						<div class="col-md-2 d-flex align-items-end"><button type="button" class="btn btn-sm btn-outline-danger w-100" @click="criteria.splice(i, 1)" :disabled="criteria.length <= 1">Hapus</button></div>
						<div class="col-md-6"><label class="form-label small" :for="'desc'+i">Deskripsi</label>
							<textarea class="form-control form-control-sm" rows="3" :id="'desc'+i" :name="'criteria['+i+'][description]'" x-model="c.description" maxlength="1000" required></textarea></div>
						<div class="col-md-6"><label class="form-label small" :for="'ind'+i">Indikator perilaku (satu per baris, minimal 2)</label>
							<textarea class="form-control form-control-sm" rows="3" :id="'ind'+i" :name="'criteria['+i+'][indicators]'" x-model="c.indicators" maxlength="2000" required></textarea></div>
					</div>
				</div>
			</template>
			<button type="button" class="btn btn-sm btn-outline-primary" @click="add()" :disabled="criteria.length >= {{ \App\Domain\Talent\PotentialInstruments::MAX_CRITERIA }}"><i class="fa fa-plus"></i> Tambah kriteria</button>
		</div></div>
		<button class="btn btn-primary"><i class="fa fa-save"></i> Simpan draft</button>
	</form>
@else
	{{-- ===== Tampilan baca ===== --}}
	<div class="card"><div class="card-body">
		<div class="d-flex flex-wrap gap-4">
			<div><div class="small text-muted">Status</div><span class="badge bg-{{ $badge[$selected->status] }}">{{ $statuses[$selected->status] }}</span>
				@if($selected->published_at)<span class="small text-muted"> sejak {{ \Carbon\Carbon::parse($selected->published_at)->translatedFormat('d M Y') }}</span>@endif</div>
			<div><div class="small text-muted">Skala</div>1–{{ $selected->scale_max }}</div>
			<div><div class="small text-muted">Tingkat potensi</div>
				{{ $levels['low'] }} &lt; {{ $fmt($selected->threshold_medium) }} ≤ {{ $levels['medium'] }} &lt; {{ $fmt($selected->threshold_high) }} ≤ {{ $levels['high'] }}</div>
			<div><div class="small text-muted">Masa berlaku hasil</div>{{ $selected->validity_months }} bulan</div>
		</div>
		@if($selected->notes)<p class="small text-muted mt-2 mb-0">{{ $selected->notes }}</p>@endif
		@if($selected->status === 'draft')<p class="small text-muted mt-2 mb-0">Draft ini hanya dapat diubah oleh pengguna dengan izin ubah.</p>@endif
	</div></div>
	<div class="card"><div class="card-body">
		<h5 class="card-title">Kriteria</h5>
		<div class="table-responsive"><table class="table table-sm mb-0">
			<thead><tr><th>Kriteria</th><th class="text-end">Bobot</th><th>Indikator perilaku</th></tr></thead>
			<tbody>
			@foreach($criteria as $c)
				<tr><td><code>{{ $c->code }}</code> <strong>{{ $c->name }}</strong><div class="small text-muted">{{ $c->description }}</div></td>
					<td class="text-end">{{ $c->weight }}%</td>
					<td><ul class="small mb-0 ps-3">@foreach(\App\Domain\Talent\PotentialInstruments::indicatorLines($c->indicators) as $line)<li>{{ $line }}</li>@endforeach</ul></td></tr>
			@endforeach
			</tbody>
		</table></div>
	</div></div>
@endif
@endsection
