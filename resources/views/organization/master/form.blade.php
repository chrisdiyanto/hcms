@extends('layouts.app')

@section('content')
<div class="page-titles">
	<h4 class="mb-0"> {{ $pageTitle }} <small> {{ $pageNote }} </small> </h4>
</div>

@include('core.tenants.partials.flash')

<form method="POST" action="{{ $row ? url($baseUrl.'/'.$row->id) : url($baseUrl) }}">
	@csrf
	@if($row) @method('PUT') @endif

	<div class="card">
		<div class="card-body">
			<div class="row g-3">
				@foreach($fields as $name => $field)
					@php
						$type = $field['type'] ?? 'text';
						$current = array_key_exists('value', $field) ? $field['value'] : ($row->{$name} ?? ($field['default'] ?? null));
						$value = old($name, $current);
						$required = in_array('required', (array) ($field['rules'] ?? []), true);
					@endphp
					<div class="col-md-{{ $field['col'] ?? 12 }}">
						@if($type === 'checkbox')
							<div class="form-check mt-2">
								<input type="hidden" name="{{ $name }}" value="0">
								<input class="form-check-input" type="checkbox" value="1" id="{{ $name }}" name="{{ $name }}" @checked($value)>
								<label class="form-check-label" for="{{ $name }}">{{ $field['label'] }}</label>
							</div>
						@else
							<label class="form-label" for="{{ $name }}">{{ $field['label'] }} @if($required)<span class="text-danger">*</span>@endif</label>
							@if($type === 'textarea')
								<textarea id="{{ $name }}" name="{{ $name }}" rows="3" class="form-control form-control-sm @error($name) is-invalid @enderror">{{ $value }}</textarea>
							@elseif($type === 'select')
								<select id="{{ $name }}" name="{{ $name }}" class="form-select form-select-sm select2 @error($name) is-invalid @enderror" @required($required)>
									<option value=""></option>
									@foreach($field['options'] ?? [] as $optValue => $optLabel)
										<option value="{{ $optValue }}" @selected((string) $value === (string) $optValue)>{{ $optLabel }}</option>
									@endforeach
								</select>
							@elseif($type === 'multiselect')
								@php($selected = array_map('strval', (array) $value))
								<select id="{{ $name }}" name="{{ $name }}[]" multiple class="form-select form-select-sm select2 @error($name) is-invalid @enderror">
									@foreach($field['options'] ?? [] as $optValue => $optLabel)
										<option value="{{ $optValue }}" @selected(in_array((string) $optValue, $selected, true))>{{ $optLabel }}</option>
									@endforeach
								</select>
							@else
								<input type="{{ in_array($type, ['number', 'date'], true) ? $type : 'text' }}" id="{{ $name }}" name="{{ $name }}" value="{{ $value }}"
									class="form-control form-control-sm @error($name) is-invalid @enderror" @required($required)>
							@endif
						@endif
						@error($name)<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
						@if(!empty($field['help']))<div class="form-text">{{ $field['help'] }}</div>@endif
					</div>
				@endforeach
			</div>
		</div>
	</div>

	@if($partial)
		@include($partial, $partialData)
	@endif

	<div class="mt-3">
		<button type="submit" class="btn btn-sm btn-primary">Simpan</button>
		<a href="{{ url($baseUrl) }}" class="btn btn-sm btn-outline-secondary">Batal</a>
	</div>
</form>
@endsection
