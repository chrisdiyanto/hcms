@extends('layouts.app')

@section('content')
@php($c = $competency)
<div class="page-titles d-flex justify-content-between align-items-center">
	<h4 class="mb-0"> {{ $pageTitle }} <small>{{ $pageNote }}</small></h4>
	<a href="{{ url('competency/framework?version='.$version->id) }}" class="btn btn-sm btn-outline-secondary">Kembali</a>
</div>

@include('core.tenants.partials.flash')

<form method="POST" action="{{ $c ? url('competency/competencies/'.$c->id) : url('competency/framework/'.$version->id.'/competencies') }}">
	@csrf
	@if($c) @method('PUT') @endif
	<fieldset @disabled($readOnly)>
	<div class="card"><div class="card-body">
		<div class="row g-3">
			<div class="col-md-3"><label class="form-label" for="code">Kode <span class="text-danger">*</span></label>
				<input id="code" name="code" value="{{ old('code', $c->code ?? '') }}" required maxlength="40" class="form-control form-control-sm text-uppercase @error('code') is-invalid @enderror"></div>
			<div class="col-md-5"><label class="form-label" for="name">Nama <span class="text-danger">*</span></label>
				<input id="name" name="name" value="{{ old('name', $c->name ?? '') }}" required maxlength="150" class="form-control form-control-sm @error('name') is-invalid @enderror"></div>
			<div class="col-md-4"><label class="form-label" for="category_id">Kelompok <span class="text-danger">*</span></label>
				<select id="category_id" name="category_id" required class="form-select form-select-sm">
					@foreach($categories as $id => $name)<option value="{{ $id }}" @selected((string) old('category_id', $c->category_id ?? '') === (string) $id)>{{ $name }}</option>@endforeach
				</select></div>
			<div class="col-12"><label class="form-label" for="definition">Definisi</label>
				<textarea id="definition" name="definition" rows="2" class="form-control form-control-sm">{{ old('definition', $c->definition ?? '') }}</textarea></div>
			<div class="col-12"><div class="form-check">
				<input type="hidden" name="is_active" value="0">
				<input class="form-check-input" type="checkbox" value="1" id="is_active" name="is_active" @checked(old('is_active', $c->is_active ?? true))>
				<label class="form-check-label" for="is_active">Aktif (kompetensi nonaktif tidak bisa dipakai di profil job)</label>
			</div></div>
		</div>
	</div></div>

	<div class="card"><div class="card-body">
		<h5 class="card-title">Level & indikator perilaku</h5>
		<p class="small text-muted">Tulis 2–4 indikator perilaku yang dapat diamati per level, satu indikator per baris. Kelengkapan diperiksa saat kamus diterbitkan.</p>
		@foreach($levels as $level => $l)
			<div class="row g-2 border-bottom pb-3 mb-3" x-data="{ text: @js(old('levels.'.$level.'.indicators', $l['indicators'])), get count() { return this.text.split(/\n/).filter(s => s.trim() !== '').length } }">
				<div class="col-md-1"><span class="badge bg-primary fs-6">{{ $level }}</span></div>
				<div class="col-md-3">
					<label class="form-label small" for="lv{{ $level }}t">Judul level</label>
					<input id="lv{{ $level }}t" name="levels[{{ $level }}][title]" value="{{ old('levels.'.$level.'.title', $l['title']) }}" required maxlength="60" class="form-control form-control-sm">
					<label class="form-label small mt-2" for="lv{{ $level }}d">Deskripsi</label>
					<textarea id="lv{{ $level }}d" name="levels[{{ $level }}][description]" rows="2" class="form-control form-control-sm">{{ old('levels.'.$level.'.description', $l['description']) }}</textarea>
				</div>
				<div class="col-md-8">
					<label class="form-label small" for="lv{{ $level }}i">Indikator perilaku
						<span class="badge" :class="count >= 2 && count <= 4 ? 'bg-success' : 'bg-warning text-dark'" x-text="`${count} indikator`"></span></label>
					<textarea id="lv{{ $level }}i" name="levels[{{ $level }}][indicators]" rows="4" x-model="text" class="form-control form-control-sm"
						placeholder="mis. Mengidentifikasi risiko utama pada proposal kredit"></textarea>
				</div>
			</div>
		@endforeach
	</div></div>
	</fieldset>

	@unless($readOnly)
		<button type="submit" class="btn btn-sm btn-primary">Simpan</button>
		<a href="{{ url('competency/framework?version='.$version->id) }}" class="btn btn-sm btn-outline-secondary">Batal</a>
	@endunless
</form>
@endsection
