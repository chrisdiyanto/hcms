@extends('layouts.app')

@section('content')
<div class="page-header">
	<h2> {{ $pageTitle }}  <small> {{ $pageNote }} </small> </h2>
</div>

<div class="card">
	<div class="card-body">
		@include('sximo.config.tab')
		
		<div class="pt-5"> 
			<form action="{{ url('sximo/config/ai') }}" class="form-horizontal validated" method="POST" enctype="multipart/form-data">
				@csrf
				
				<div class="row">
					<div class="col-sm-6">
						<div class="form-group row">
							<label for="ai_provider" class="control-label col-sm-4">AI Provider</label>
							<div class="col-sm-8">
								<select class="form-control form-control-sm" name="ai_provider" id="ai_provider" required="true">
									<option value="">Select AI Provider</option>
									<option value="gemini" @if($ai_provider == 'gemini') selected @endif>Google Gemini</option>
									<option value="openai" @if($ai_provider == 'openai') selected @endif>OpenAI (ChatGPT)</option>
									<option value="claude" @if($ai_provider == 'claude') selected @endif>Anthropic Claude</option>
									<option value="deepseek" @if($ai_provider == 'deepseek') selected @endif>DeepSeek</option>
									<option value="local" @if($ai_provider == 'local') selected @endif>Local Ollama / Custom API</option>
								</select>
							</div>
						</div>

						<div class="form-group row">
							<label for="ai_api_key" class="control-label col-sm-4">API Key</label>
							<div class="col-sm-8">
								<input type="password" class="form-control form-control-sm" name="ai_api_key" id="ai_api_key" value="{{ $ai_api_key }}" placeholder="Enter AI API Key" />
								<small class="text-muted">Stored inside config/ai.php file.</small>
							</div>
						</div>

						<div class="form-group row">
							<label for="ai_api_endpoint" class="control-label col-sm-4">API Endpoint (Optional)</label>
							<div class="col-sm-8">
								<input type="text" class="form-control form-control-sm" name="ai_api_endpoint" id="ai_api_endpoint" value="{{ $ai_api_endpoint }}" placeholder="https://api.openai.com/v1" />
								<small class="text-muted">Custom endpoint URL (e.g. proxy, local model server, or Ollama).</small>
							</div>
						</div>

						<div class="form-group row">
							<label for="ai_model" class="control-label col-sm-4">Model Name (Optional)</label>
							<div class="col-sm-8">
								<input type="text" class="form-control form-control-sm" name="ai_model" id="ai_model" value="{{ $ai_model }}" placeholder="gemini-1.5-pro, gpt-4o, etc." />
								<small class="text-muted">Target model identifier.</small>
							</div>
						</div>

						<div class="form-group row">
							<label for="ai_system_instruction" class="control-label col-sm-4">System Instruction</label>
							<div class="col-sm-8">
								<textarea class="form-control form-control-sm" name="ai_system_instruction" id="ai_system_instruction" rows="4" placeholder="Enter permanent system instructions (e.g. role, tone, etc.)">{{ $ai_system_instruction }}</textarea>
								<small class="text-muted">Basic permanent instructions to configure the AI's role, tone, and constraints.</small>
							</div>
						</div>
						
						<div class="form-group row">
							<label for="ai_name" class="control-label col-sm-4">AI Name</label>
							<div class="col-sm-8">
								<input type="text" class="form-control form-control-sm" name="ai_name" id="ai_name" value="{{ $ai_name }}" placeholder="Enter AI Name (e.g. Igma)" />
								<small class="text-muted">Display name for your chatbot assistant.</small>
							</div>
						</div>

						<div class="form-group row">
							<label for="ai_avatar" class="control-label col-sm-4">AI Profile Photo</label>
							<div class="col-sm-8">
								<input type="file" class="d-block w-100" name="ai_avatar" id="ai_avatar" accept="image/*" />
								<small class="text-muted">Upload profile photo for the AI.</small>
								@if($ai_avatar && file_exists(public_path('uploads/ai/' . $ai_avatar)))
									<div class="mt-2">
										<img src="{{ asset('uploads/ai/' . $ai_avatar) }}" class="rounded-circle img-thumbnail" style="width: 60px; height: 60px; object-fit: cover;" />
									</div>
								@endif
							</div>
						</div>
					</div>

					<div class="col-sm-6">
						<div class="form-group row">
							<label class="control-label col-sm-4">Allowed User Groups</label>
							<div class="col-sm-8">
								@foreach($groups as $group)
									<div class="checkbox mb-2">
										<input type="checkbox" name="ai_allowed_groups[]" value=" {{$group->group_id }}" @if(in_array($group->group_id, $allowed_groups)) checked @endif class="minimal-green" id="group-{{ $group->group_id }}" />
										<label for="group-{{ $group->group_id }}"> {{ $group->name }}</label>
									</div>
								@endforeach
								<small class="text-muted">Only checked groups will be authorized to access/use AI features.</small>
							</div>
						</div>

						<div class="form-group row mt-4">
							<div class="col-sm-8 offset-sm-4">
								<button class="btn btn-primary" type="submit">{{ Lang::get('core.sb_savechanges') }}</button>
							</div>
						</div>

						@if(session('gid') == '1')
							<div class="mt-4 p-3 alert alert-warning" style="border-radius: 6px; border: 1px solid rgba(0,0,0,0.05);">
								<h5 class="alert-heading" style="font-size: 14px; font-weight: bold; margin-bottom: 5px;"><i class="fa fa-database"></i> Chatbot Database Setup</h5>
								<p class="mb-2" style="font-size: 13px;">If you haven't initialized the database tables for the chatbot conversation histories yet, click below to trigger the migration:</p>
								<a href="{{ url('sximo/aichat/run-migration') }}" class="btn btn-xs btn-warning" target="_blank" style="font-weight: 500;">
									<i class="fa fa-play me-1"></i> Run Migration Now
								</a>
							</div>
						@endif
					</div>
				</div>
			</form>
		</div>
	</div>
</div>
@stop
