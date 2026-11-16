@extends('layouts.app')

@section('content')
<div class="page-titles d-flex justify-content-between align-items-center flex-wrap gap-2">
	<h4 class="mb-0"> {{ $pageTitle }} <small>{{ $pageNote }}</small></h4>
	<a href="{{ url('performance/my-plans') }}" class="btn btn-sm btn-outline-secondary">Kembali</a>
</div>

@include('core.tenants.partials.flash')

<div class="d-flex flex-wrap gap-2 align-items-center mb-2">
	<span class="badge {{ ['approved' => 'bg-success', 'submitted' => 'bg-info', 'returned' => 'bg-warning text-dark', 'draft' => 'bg-light text-dark'][$plan->status] }}">{{ $statuses[$plan->status] }}</span>
	<span class="small text-muted">{{ $plan->full_name }} · {{ $plan->position_title }}</span>
	@if($canCancel)
		<form method="POST" action="{{ url('performance/my-plans/'.$plan->id.'/cancel') }}" class="ms-auto">@csrf<button class="btn btn-sm btn-outline-secondary" type="submit">Tarik pengajuan</button></form>
	@endif
</div>
@if($plan->status === 'returned' && $plan->return_note)
	<div class="alert alert-warning"><strong>Catatan atasan:</strong> {{ $plan->return_note }}</div>
@endif


<form method="POST" action="{{ url('performance/my-plans/'.$plan->id) }}" x-data="kpiEditor(@js($items), @js($rules), @js((bool) $canEdit))">
	@csrf @method('PUT')
	@include('performance.plans._kpi_editor')

	<template x-if="canEdit">
		<div class="d-flex flex-wrap gap-2 mt-2">
			<button type="button" class="btn btn-sm btn-outline-primary" @click="add()" :disabled="items.length >= rules.max_count">+ Tambah KPI</button>
			<button class="btn btn-sm btn-secondary ms-auto" type="submit" name="action" value="draft">Simpan draft</button>
			<button class="btn btn-sm btn-primary" type="submit" name="action" value="submit" :disabled="!countOk || !totalOk"
				onclick="return confirm('Ajukan rencana ke atasan? Dengan mengajukan, Anda menyetujui KPI ini.')">Ajukan ke atasan</button>
		</div>
	</template>
</form>
@endsection