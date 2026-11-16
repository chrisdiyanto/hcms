@extends('layouts.app')

@section('content')
@php($e = $employee)
@php($canEdit = !empty($access['is_edit']))
@php($canApprove = !empty($access['is_approve']))
<div class="page-titles d-flex flex-wrap justify-content-between align-items-center gap-2">
	<h4 class="mb-0"> {{ $e->full_name }} <small> {{ $e->employee_number }} · {{ $company }} </small>
		@if($e->status === 'terminated')<span class="badge bg-secondary">Berhenti {{ $e->terminated_at }}</span>@endif</h4>
	<div>
		<a href="{{ url('employees') }}" class="btn btn-sm btn-outline-secondary">Daftar pegawai</a>
		@if($canEdit)<a href="{{ url('employees/'.$e->id.'/edit') }}" class="btn btn-sm btn-primary">Ubah data</a>@endif
	</div>
</div>

@include('core.tenants.partials.flash')

{{-- Setelah error validasi, kembali ke tab form yang dikirim (field _tab) --}}
<div x-data="{ tab: (location.hash || '#' + @js(old('_tab', 'summary'))).slice(1) }" @hashchange.window="tab = (location.hash || '#summary').slice(1)">
	<ul class="nav nav-tabs mb-3">
		@foreach(['summary' => 'Ringkasan', 'assignments' => 'Penugasan', 'records' => 'Data riwayat', 'history' => 'Riwayat status', 'termination' => 'Terminasi'] as $key => $label)
			<li class="nav-item"><a class="nav-link" :class="{ active: tab === '{{ $key }}' || ('{{ $key }}' === 'records' && @js(array_keys($recordTypes)).includes(tab)) }" href="#{{ $key }}">{{ $label }}</a></li>
		@endforeach
	</ul>

	{{-- Ringkasan --}}
	<div x-show="tab === 'summary'">
		<div class="row">
			<div class="col-lg-7"><div class="card"><div class="card-body">
				<dl class="row mb-0">
					<dt class="col-sm-4">Tempat, tanggal lahir</dt><dd class="col-sm-8">{{ $e->birth_place ?? '–' }}, {{ $e->birth_date ?? '–' }}</dd>
					<dt class="col-sm-4">Jenis kelamin</dt><dd class="col-sm-8">{{ ['M' => 'Laki-laki', 'F' => 'Perempuan'][$e->gender] ?? '–' }}</dd>
					<dt class="col-sm-4">Status perkawinan / PTKP</dt><dd class="col-sm-8">{{ $labels['marital'][$e->marital_status] ?? '–' }} / {{ $e->ptkp_status ?? '–' }}</dd>
					<dt class="col-sm-4">Kontak</dt><dd class="col-sm-8">{{ $e->email ?? '–' }} · {{ $e->phone ?? '–' }}</dd>
					<dt class="col-sm-4">Alamat domisili</dt><dd class="col-sm-8">{{ $e->address_domicile ?? '–' }}</dd>
					<dt class="col-sm-4">Status kerja</dt><dd class="col-sm-8">{{ $labels['employment'][$e->employment_status] ?? $e->employment_status }}
						@if($e->employment_status === 'pkwt') · kontrak s.d. {{ $e->contract_end_date }}@endif
						@if($e->employment_status === 'probation') · percobaan s.d. {{ $e->probation_end_date }}@endif</dd>
					<dt class="col-sm-4">Bergabung / masa kerja</dt><dd class="col-sm-8">{{ $e->join_date }} · {{ $tenure }}</dd>
					<dt class="col-sm-4">Posisi aktif</dt><dd class="col-sm-8">
						@forelse($activeAssignments as $a)
							<div>{{ $a->position_title }} <span class="text-muted small">({{ $assignmentTypes[$a->type] }}, {{ $a->unit_name }})</span></div>
						@empty – @endforelse</dd>
				</dl>
			</div></div></div>

			@if($identity !== null)
			<div class="col-lg-5"><div class="card border-warning"><div class="card-body"
				x-data="{ values: @js($identity), revealed: false, loading: false,
					async reveal() { this.loading = true;
						const r = await fetch(@js(url('employees/'.$e->id.'/identity')), { method: 'POST', headers: { 'X-CSRF-TOKEN': @js(csrf_token()), 'Accept': 'application/json' } });
						if (r.ok) { this.values = await r.json(); this.revealed = true } this.loading = false } }">
				<div class="d-flex justify-content-between align-items-center mb-2">
					<h6 class="mb-0">Identitas negara <span class="badge bg-warning text-dark">Sensitif</span></h6>
					<button type="button" class="btn btn-sm btn-outline-warning" x-show="!revealed" @click="reveal()" :disabled="loading">Tampilkan</button>
				</div>
				<dl class="row mb-0 small">
					@foreach(\App\Domain\Employee\Services\EmployeeIdentity::FIELDS as $name => $label)
						<dt class="col-6">{{ $label }}</dt><dd class="col-6 font-monospace" x-text="values['{{ $name }}'] ?? '–'"></dd>
					@endforeach
				</dl>
				<p class="small text-muted mt-2 mb-0" x-show="revealed" x-cloak>Pembukaan data ini tercatat di log.</p>
			</div></div></div>
			@endif
		</div>
	</div>

	{{-- Penugasan --}}
	<div x-show="tab === 'assignments'" x-cloak>
		<div class="card"><div class="card-body">
			<table class="table table-sm align-middle">
				<thead><tr><th>Posisi</th><th>Unit</th><th>Jenis</th><th>Mulai</th><th>Selesai</th><th>SK</th><th></th></tr></thead>
				<tbody>
				@forelse($assignments as $a)
					<tr>
						<td><code>{{ $a->position_code }}</code> {{ $a->position_title }}</td>
						<td>{{ $a->unit_name }}</td>
						<td>{{ $assignmentTypes[$a->type] }}</td>
						<td>{{ $a->effective_from }}</td>
						<td>{{ $a->effective_to ?? 'sekarang' }}</td>
						<td class="small">{{ $a->decree_number }}</td>
						<td class="text-end">
							@if($canEdit && !$a->effective_to)
								<form method="POST" action="{{ url('employees/'.$e->id.'/assignments/'.$a->id.'/end') }}" class="d-inline-flex gap-1" x-data="{ open: false }">
									@csrf
									<button type="button" class="btn btn-sm btn-outline-secondary" x-show="!open" @click="open = true">Akhiri</button>
									<input type="date" name="effective_to" x-show="open" x-cloak required value="{{ now()->toDateString() }}" class="form-control form-control-sm">
									<button type="submit" class="btn btn-sm btn-secondary" x-show="open" x-cloak>OK</button>
								</form>
							@endif
						</td>
					</tr>
				@empty
					<tr><td colspan="7" class="text-muted">Belum ada penugasan.</td></tr>
				@endforelse
				</tbody>
			</table>

			@if($canEdit && $e->status === 'active')
			<h6 class="mt-3">Tambah penugasan</h6>
			<p class="small text-muted">Penugasan primer baru otomatis mengakhiri penugasan primer sebelumnya sehari sebelum tanggal mulai (mutasi/promosi).</p>
			<form method="POST" action="{{ url('employees/'.$e->id.'/assignments') }}" class="row g-2">
				<input type="hidden" name="_tab" value="assignments">
				@csrf
				<div class="col-md-4"><select name="position_id" required class="form-select form-select-sm select2"><option value="">Pilih posisi</option>
					@foreach($positions as $v => $l)<option value="{{ $v }}" @selected(old('position_id') == $v)>{{ $l }}</option>@endforeach</select></div>
				<div class="col-md-2"><select name="type" class="form-select form-select-sm">
					@foreach($assignmentTypes as $v => $l)<option value="{{ $v }}" @selected(old('type') === $v)>{{ $l }}</option>@endforeach</select></div>
				<div class="col-md-2"><input type="date" name="effective_from" required value="{{ old('effective_from', now()->toDateString()) }}" class="form-control form-control-sm" title="Mulai"></div>
				<div class="col-md-2"><input type="date" name="effective_to" value="{{ old('effective_to') }}" class="form-control form-control-sm" title="Selesai (opsional)"></div>
				<div class="col-md-2"><input name="decree_number" value="{{ old('decree_number') }}" maxlength="100" class="form-control form-control-sm" placeholder="Nomor SK"></div>
				<div class="col-12"><button type="submit" class="btn btn-sm btn-primary">Simpan penugasan</button></div>
			</form>
			@endif
		</div></div>
	</div>

	{{-- Data riwayat --}}
	<div x-show="tab === 'records' || @js(array_keys($recordTypes)).includes(tab)" x-cloak>
		@foreach($recordTypes as $type => $def)
		<div class="card" id="{{ $type }}" x-data="{ adding: false }"><div class="card-body">
			<div class="d-flex justify-content-between align-items-center mb-2">
				<h6 class="mb-0">{{ $def['title'] }}</h6>
				@if($canEdit)<button type="button" class="btn btn-sm btn-outline-primary" @click="adding = !adding" x-text="adding ? 'Batal' : 'Tambah'">Tambah</button>@endif
			</div>
			<table class="table table-sm mb-2">
				<thead><tr>@foreach($def['fields'] as $f)<th>{{ $f['label'] }}</th>@endforeach<th></th></tr></thead>
				<tbody>
				@forelse($records[$type] as $r)
					<tr>
						@foreach($def['fields'] as $name => $f)
							<td>@if(($f['type'] ?? '') === 'checkbox'){{ $r->{$name} ? 'Ya' : 'Tidak' }}@elseif(($f['type'] ?? '') === 'select'){{ $f['options'][$r->{$name}] ?? $r->{$name} }}@else{{ $r->{$name} ?? '–' }}@endif</td>
						@endforeach
						<td class="text-end">@if($canEdit)
							<form method="POST" action="{{ url('employees/'.$e->id.'/records/'.$type.'/'.$r->id) }}" onsubmit="return confirm('Hapus data ini?')">@csrf @method('DELETE')
								<button class="btn btn-sm btn-link text-danger p-0" type="submit">Hapus</button></form>@endif</td>
					</tr>
				@empty
					<tr><td colspan="{{ count($def['fields']) + 1 }}" class="text-muted">Belum ada data.</td></tr>
				@endforelse
				</tbody>
			</table>
			@if($canEdit)
			<form method="POST" action="{{ url('employees/'.$e->id.'/records/'.$type) }}" class="row g-2" x-show="adding" x-cloak>
				<input type="hidden" name="_tab" value="{{ $type }}">
				@csrf
				@foreach($def['fields'] as $name => $f)
					<div class="col-md">
						@if(($f['type'] ?? '') === 'select')
							<select name="{{ $name }}" class="form-select form-select-sm" @required(in_array('required', $f['rules'], true))><option value="">{{ $f['label'] }}</option>
								@foreach($f['options'] as $v => $l)<option value="{{ $v }}">{{ $l }}</option>@endforeach</select>
						@elseif(($f['type'] ?? '') === 'checkbox')
							<div class="form-check mt-1"><input class="form-check-input" type="checkbox" name="{{ $name }}" value="1" id="{{ $type.$name }}"><label class="form-check-label small" for="{{ $type.$name }}">{{ $f['label'] }}</label></div>
						@else
							<input type="{{ $f['type'] ?? 'text' }}" name="{{ $name }}" placeholder="{{ $f['label'] }}" title="{{ $f['label'] }}" class="form-control form-control-sm" @required(in_array('required', $f['rules'], true))>
						@endif
					</div>
				@endforeach
				<div class="col-md-auto"><button class="btn btn-sm btn-primary" type="submit">Simpan</button></div>
			</form>
			@endif
		</div></div>
		@endforeach
	</div>

	{{-- Riwayat status --}}
	<div x-show="tab === 'history'" x-cloak>
		<div class="card"><div class="card-body">
			<table class="table table-sm mb-0">
				<thead><tr><th>Tanggal efektif</th><th>Data</th><th>Lama</th><th>Baru</th><th>Keterangan</th><th>Dicatat</th></tr></thead>
				<tbody>
				@forelse($histories as $h)
					<tr><td>{{ $h->effective_date }}</td><td>{{ $h->field }}</td><td>{{ $h->old_value ?? '–' }}</td><td>{{ $h->new_value ?? '–' }}</td><td class="small">{{ $h->reason }}</td><td class="small text-muted">{{ $h->created_at }}</td></tr>
				@empty
					<tr><td colspan="6" class="text-muted">Belum ada riwayat.</td></tr>
				@endforelse
				</tbody>
			</table>
		</div></div>
	</div>

	{{-- Terminasi --}}
	<div x-show="tab === 'termination'" x-cloak>
		<div class="card"><div class="card-body">
			@foreach($terminations as $t)
				<div class="alert alert-{{ $t->state === 'processed' ? 'secondary' : ($t->state === 'scheduled' ? 'warning' : 'light') }} d-flex justify-content-between align-items-center">
					<div><strong>{{ $terminationTypes[$t->type] }}</strong> per {{ $t->termination_date }} —
						{{ ['scheduled' => 'Terjadwal', 'processed' => 'Diproses', 'cancelled' => 'Dibatalkan'][$t->state] }}
						<div class="small">{{ $t->reason }} · Dapat direkrut kembali: {{ $t->rehire_eligible ? 'Ya' : 'Tidak' }}</div></div>
					@if($canApprove && $t->state === 'scheduled')
						<form method="POST" action="{{ url('employees/'.$e->id.'/terminations/'.$t->id.'/cancel') }}">@csrf
							<button class="btn btn-sm btn-outline-dark" type="submit">Batalkan</button></form>
					@endif
				</div>
			@endforeach

			@if($canApprove && $e->status === 'active' && !$terminations->contains('state', 'scheduled'))
			<form method="POST" action="{{ url('employees/'.$e->id.'/terminate') }}" class="row g-2" data-tab="termination"
				onsubmit="return confirm('Proses terminasi pegawai ini? Tanggal hari ini atau lampau langsung diproses dan akun login dinonaktifkan.')">
				@csrf<input type="hidden" name="_tab" value="termination">
				<div class="col-md-3"><label class="form-label small">Tanggal efektif</label><input type="date" name="termination_date" required class="form-control form-control-sm"></div>
				<div class="col-md-3"><label class="form-label small">Jenis</label><select name="type" class="form-select form-select-sm">
					@foreach($terminationTypes as $v => $l)<option value="{{ $v }}">{{ $l }}</option>@endforeach</select></div>
				<div class="col-md-6"><label class="form-label small">Alasan</label><input name="reason" required maxlength="500" class="form-control form-control-sm"></div>
				<div class="col-12"><div class="form-check"><input class="form-check-input" type="checkbox" name="rehire_eligible" value="1" id="rehire" checked><label class="form-check-label" for="rehire">Dapat direkrut kembali</label></div></div>
				<div class="col-12"><button class="btn btn-sm btn-danger" type="submit">Proses terminasi</button></div>
			</form>
			@elseif($terminations->isEmpty())
				<p class="text-muted mb-0">Tidak ada data terminasi.</p>
			@endif
		</div></div>
	</div>
</div>
@endsection
