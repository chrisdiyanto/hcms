@extends('layouts.app')

@section('content')
<div class="page-titles"><h4 class="mb-0"> {{ $pageTitle }} </h4></div>

@include('core.tenants.partials.flash')

@foreach($rows as $row)
<div class="card"><div class="card-body">
	<div class="d-flex justify-content-between align-items-center mb-2">
		<h5 class="mb-0">{{ $row['name'] }} <small class="text-muted">versi {{ $row['version'] }}</small></h5>
		@if($canEdit)<a href="{{ url('workflow/definitions/'.$row['code'].'/edit') }}" class="btn btn-sm btn-outline-primary">Ubah langkah</a>@endif
	</div>
	<ol class="mb-0">
		@foreach($row['steps'] as $step)
			<li>{{ $step['name'] }} —
				@php($a = $step['approver'])
				{{ $types[$a['type']] ?? $a['type'] }}
				@if($a['type'] === 'supervisor') ({{ $a['levels'] ?? 1 }} tingkat ke atas)@endif
				@if($a['type'] === 'role') ({{ $roles[$a['role']] ?? $a['role'] }})@endif
				· SLA {{ $step['sla_hours'] }} jam</li>
		@endforeach
	</ol>
</div></div>
@endforeach
@endsection
