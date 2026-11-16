@extends('layouts.app')

@section('content')
<div class="page-titles"><h4 class="mb-0"> {{ $pageTitle }} <small>{{ $pageNote }}</small></h4></div>

@include('core.tenants.partials.flash')

<div class="row">
	<div class="{{ $canAdd ? 'col-lg-8' : 'col-12' }}">
		@forelse($pips as $p)
			<div class="card"><div class="card-body" x-data="{ status: 'completed' }">
				<div class="d-flex justify-content-between flex-wrap gap-2">
					<div><strong>{{ $p->full_name }}</strong> · {{ $p->start_date }} – {{ $p->end_date }}
						<div class="small text-muted">Penanggung jawab: {{ trim($p->first_name.' '.$p->last_name) }}</div></div>
					<span class="badge {{ in_array($p->status, ['active', 'extended']) ? 'bg-info' : 'bg-secondary' }}">{{ $statuses[$p->status] }}</span>
				</div>
				<div class="small mt-2"><span class="text-muted">Area perbaikan:</span> {!! nl2br(e($p->objectives)) !!}</div>
				@if($p->support)<div class="small"><span class="text-muted">Dukungan:</span> {!! nl2br(e($p->support)) !!}</div>@endif
				@if($p->outcome_note)<div class="small"><span class="text-muted">Hasil:</span> {{ $p->outcome_note }}</div>@endif
				@if(in_array($p->status, ['active', 'extended']))
					<details class="mt-2"><summary class="small">Perbarui status</summary>
						<form method="POST" action="{{ url('performance/pips/'.$p->id.'/close') }}" class="mt-2 row g-2">
							@csrf
							<div class="col-md-4"><select name="status" x-model="status" class="form-select form-select-sm" aria-label="Status PIP">
								@foreach(['completed', 'extended', 'unsuccessful', 'cancelled'] as $s)<option value="{{ $s }}">{{ $statuses[$s] }}</option>@endforeach</select></div>
							<div class="col-md-4" x-show="status === 'extended'" x-cloak><input type="date" name="end_date" class="form-control form-control-sm" aria-label="Tanggal selesai baru"></div>
							<div class="col-12"><textarea name="outcome_note" required minlength="10" rows="2" class="form-control form-control-sm" placeholder="Catatan hasil / alasan" aria-label="Catatan hasil"></textarea></div>
							<div class="col-12"><button class="btn btn-sm btn-primary" type="submit">Simpan</button></div>
						</form>
					</details>
				@endif
			</div></div>
		@empty
			<div class="alert alert-info">Belum ada PIP.</div>
		@endforelse
	</div>
	@if($canAdd)
		<div class="col-lg-4">
			<div class="card"><div class="card-body">
				<h5 class="card-title">PIP baru</h5>
				@if($candidates->isEmpty())
					<p class="small text-muted mb-0">Tidak ada pegawai dengan rating rendah tanpa PIP aktif.</p>
				@else
					<form method="POST" action="{{ url('performance/pips') }}">
						@csrf
						<div class="mb-2"><label class="form-label" for="p-rating">Pegawai</label>
							<select id="p-rating" name="rating_id" required class="form-select form-select-sm">
								@foreach($candidates as $c)<option value="{{ $c->id }}">{{ $c->full_name }} — {{ $c->band_label }} ({{ number_format($c->final_score, 2, ',', '.') }}, {{ $c->period_name }})</option>@endforeach</select></div>
						<div class="row g-2 mb-2">
							<div class="col"><label class="form-label" for="p-start">Mulai</label><input id="p-start" type="date" name="start_date" required value="{{ now()->toDateString() }}" class="form-control form-control-sm"></div>
							<div class="col"><label class="form-label" for="p-end">Selesai</label><input id="p-end" type="date" name="end_date" required value="{{ now()->addDays(90)->toDateString() }}" class="form-control form-control-sm"></div>
						</div>
						<div class="mb-2"><label class="form-label" for="p-obj">Area perbaikan & ukuran keberhasilan</label>
							<textarea id="p-obj" name="objectives" required minlength="20" rows="4" class="form-control form-control-sm"></textarea></div>
						<div class="mb-3"><label class="form-label" for="p-sup">Dukungan perusahaan</label>
							<textarea id="p-sup" name="support" rows="2" class="form-control form-control-sm" placeholder="mis. coaching mingguan, pelatihan analisis kredit"></textarea></div>
						<button class="btn btn-sm btn-primary" type="submit">Buat PIP</button>
					</form>
				@endif
			</div></div>
		</div>
	@endif
</div>
@endsection
