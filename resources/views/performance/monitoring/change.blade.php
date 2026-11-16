@extends('layouts.app')

@section('content')
<div class="page-titles d-flex justify-content-between align-items-center flex-wrap gap-2">
	<h4 class="mb-0"> {{ $pageTitle }} <small>{{ $pageNote }}</small></h4>
	<a href="{{ url('performance/plans/'.$plan->id.'/monitoring') }}" class="btn btn-sm btn-outline-secondary">Kembali</a>
</div>

@include('core.tenants.partials.flash')

@if($pending)
	<div class="alert alert-info">Masih ada pengajuan perubahan KPI yang menunggu keputusan atasan.</div>
@else
	<p class="small text-muted">Ubah, tambah, atau hapus KPI. KPI yang dihapus tetap tersimpan beserta realisasinya pada versi KPI lama; KPI yang tipe capaiannya diganti dianggap KPI baru.
		Jika target diubah, capaian realisasi yang sudah dicatat dihitung ulang dengan target baru setelah disetujui.</p>
	<form method="POST" action="{{ url('performance/plans/'.$plan->id.'/change-request') }}" x-data="kpiEditor(@js($items), @js($rules), true)">
		@csrf
		@include('performance.plans._kpi_editor')

		<div class="card mt-2"><div class="card-body">
			<label class="form-label" for="f-reason">Alasan perubahan</label>
			<textarea id="f-reason" name="reason" required minlength="10" maxlength="1000" rows="3" class="form-control form-control-sm @error('reason') is-invalid @enderror"
				placeholder="mis. Target penyaluran disesuaikan dengan revisi RKAP semester 2">{{ old('reason') }}</textarea>
		</div></div>
		<div class="d-flex flex-wrap gap-2 mt-2">
			<button type="button" class="btn btn-sm btn-outline-primary" @click="add()" :disabled="items.length >= rules.max_count">+ Tambah KPI</button>
			<button class="btn btn-sm btn-primary ms-auto" type="submit" :disabled="!countOk || !totalOk">Ajukan perubahan ke atasan</button>
		</div>
	</form>
@endif
@endsection
