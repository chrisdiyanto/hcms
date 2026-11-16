@extends('layouts.login')

@section('content')
<div class="form-signin">
		
	
 <form action="{{ url('user/create') }}" method="POST" class="form-signup form-material" parsley-validate novalidate id="register-form">
	@csrf
	    	@if(Session::has('message'))
				{!! Session::get('message') !!}
			@endif
		<ul class="parsley-error-list">
			@foreach($errors->all() as $error)
				<li>{{ $error }}</li>
			@endforeach
		</ul>

	<div class="form-group has-feedback">
		
	<input type="text" name="username" class="form-control" required="true" placeholder="{{ __('core.username') }}">
		
	</div>	
	<div class="form-group has-feedback row">
		<div class="col-md-6">
		
		<input type="text" name="firstname" class="form-control" required="true" placeholder="{{ __('core.firstname') }}">
			
		</div>
		<div class="col-md-6">
			
		<input type="text" name="lastname" class="form-control" required="true" placeholder="{{ __('core.lastname') }}">
			
		</div>	
	</div>
	
	
	<div class="form-group has-feedback">
	<input type="text" name="email" class="form-control" required="true" placeholder="{{ __('core.email') }}">
	</div>

	<div class="form-group has-feedback row">
		<div class="col-md-6">
			
		<input type="password" name="password" class="form-control" required="true" placeholder="{{ __('core.password') }}">
			
		</div>
		<div class="col-md-6">
			
		<input type="password" name="password_confirmation" class="form-control" required="true" placeholder="{{ __('core.password_confirmation') }}">
			
		</div>	
	</div>

		@if(filter_var(config('sximo.cnf_recaptcha'), FILTER_VALIDATE_BOOLEAN)) 
		<div class="form-group has-feedback animated fadeInLeft delayp1">
			<label class="text-start"> Are u human ? </label>
			<div class="g-recaptcha" data-sitekey="{{ config('sximo.cnf_recaptchapublickey') }}"></div>
			
			<div class="clr"></div>
		</div>	
	 	@endif						

      <div class="row form-group">
        <div class="col-sm-12">
          <button type="submit" style="width:100%;" class="btn btn-primary float-end"><i class="icon-user-plus"></i> @lang('core.signup')	</button>
       </div>
      </div>
	  <p style="padding:10px 0" class="text-center">
	  <a href="{{ URL::to('user/login')}}"> @lang('core.signin')   </a> | <a href="{{ URL::to('')}}"> @lang('core.backtosite')   </a> 
   		</p>
 </form>
</div>
<script type="text/javascript">
	$(document).ready(function(){
		$('#register-form').parsley();
	})
</script>		
@stop
