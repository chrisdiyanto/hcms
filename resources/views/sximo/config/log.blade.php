@extends('layouts.app')

@section('content')
<div class="page-content row">
	<div class="page-content-wrapper m-t">

		<div class="sbox">
			<div class="sbox-title">
				<h4 class="page-title">{{ $pageTitle }} <small> {{ $pageNote }} </small> </h4> 
			</div>
			<div class="sbox-content">

			@include('sximo.config.tab')	
			  
			<form action="{{ url('config/email/') }}" class="form-vertical row" method="POST">
			
			<div class="col-sm-6 m-t">
			

				
				  <div class="form-group">
					<label for="ipt" class=" control-label"> Template Cache </label>		
						
				  </div>  
				  
				<div class="form-group">   
					<a href="{{ URL::to('sximo/config/clearlog') }}" class="btn btn-primary btn-flat clearCache" ><i class="fa fa-trash"></i> {{ Lang::get('core.dash_clearcache') }} </a>	 
				</div>

			</div> 
	 		</form>


			</div>
		</div>
	</div>
</div>

@endsection