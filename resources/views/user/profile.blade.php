@extends('layouts.app')

@section('content')
<style>
	#message {
  display:none;
  background: #f1f1f1;
  color: #000;
  position: relative;
  padding: 20px;
  margin-top: 10px;
}

#message p {
  padding: 10px 35px;
  font-size: 18px;
}

/* Add a green text color and a checkmark when the requirements are right */
.valid {
  color: black;
}

.valid:before {
  position: relative;
  left: -35px;
}

/* Add a red text color and an "x" icon when the requirements are wrong */
.invalid {
  color: red;
}

.invalid:before {
  position: relative;
  left: -35px;
}
</style>
<script type="text/javascript" src="{{ asset('assets/plugins/jquery-asColor/dist/jquery-asColor.js') }}"></script>
<script type="text/javascript" src="{{ asset('assets/plugins/jquery-asGradient/dist/jquery-asGradient.js') }}"></script>
<script type="text/javascript" src="{{ asset('assets/plugins/jquery-asColorPicker-master/dist/jquery-asColorPicker.min.js') }}"></script>
<link href="{{ asset('assets/plugins/jquery-asColorPicker-master/dist/css/asColorPicker.css') }}"  rel="stylesheet"/>
<div class="page-titles">
  <h2> {{ $pageTitle }} <small> {{ $pageNote }} </small></h2>
</div>


	<div class="row">
		<div class="col-md-6">



<ul class="nav nav-tabs form-tab" >
  <li class="nav-item"><a href="#info" data-bs-toggle="tab" class="nav-link active"> {{ Lang::get('core.personalinfo') }} </a></li>
  <li class="nav-item"><a href="#pass" data-bs-toggle="tab" class="nav-link">{{ Lang::get('core.changepassword') }} </a></li>
</ul>	

<div class="tab-content pt-5">
  <div class="tab-pane active m-t" id="info">
	<form action="{{ url('user/saveprofile/') }}" method="POST" class="form-horizontal validated" enctype="multipart/form-data">
		@csrf
	<div class="form-group row">
		<label for="last_login" class=" control-label col-md-4"> Last Login </label>
		<div class="col-md-8">
		<input disabled="disabled" class="form-control form-control-sm" required  value="{{ $info->last_login }}" />  
		 </div> 
	  </div>    
	<div class="form-group row">
		<label for="ipt" class=" control-label col-md-4"> Unit </label>
		<div class="col-md-8">
		<input name="id_unit" type="text" id="id_unit" disabled="disabled" class="form-control form-control-sm" required  value="{{ get_unit($info->id_unit, 'unit_name') }}" />  
		 </div> 
	  </div>  
	  <div class="form-group row">
		<label for="ipt" class=" control-label col-md-4"> Position</label>
		<div class="col-md-8">
		<input name="id_position" type="text" id="id_position" disabled="disabled" class="form-control form-control-sm" required  value="{{ get_unit($info->id_position, 'unit_name') }}" />  
		 </div> 
	  </div>
	  @if(isset($user_units) && $user_units->count() > 1)
	  <div class="form-group row">
		<label class="control-label col-md-4"> Semua Unit & Jabatan </label>
		<div class="col-md-8">
			@foreach($user_units as $uu)
				<span class="badge badge-info me-1 mb-1 p-2">
					{{ $uu->unit_name ?? '-' }}{{ !empty($uu->position_name) ? ' - ' . $uu->position_name : '' }}
				</span>
			@endforeach
		</div>
	  </div>
	  @endif  
	  <div class="form-group row">
		<label for="ipt" class=" control-label col-md-4"> Username </label>
		<div class="col-md-8">
		<input name="username" type="text" id="username" class="form-control form-control-sm" required  value="{{ $info->username }}" />  
		 </div> 
	  </div>  
	  <div class="form-group row">
		<label for="ipt" class=" control-label col-md-4">{{ Lang::get('core.email') }} </label>
		<div class="col-md-8">
		<input name="email" type="text" id="email"  class="form-control form-control-sm" value="{{ $info->email }}" /> 
		 </div> 
	  </div> 	  
  
	  <div class="form-group row">
		<label for="ipt" class=" control-label col-md-4">{{ Lang::get('core.firstname') }} </label>
		<div class="col-md-8">
		<input name="first_name" type="text" id="first_name" class="form-control form-control-sm" required value="{{ $info->first_name }}" /> 
		 </div> 
	  </div>  
	  
	  <div class="form-group row">
		<label for="ipt" class=" control-label col-md-4">{{ Lang::get('core.lastname') }} </label>
		<div class="col-md-8">
		<input name="last_name" type="text" id="last_name" class="form-control form-control-sm" required value="{{ $info->last_name }}" />  
		 </div> 
	  </div>    

	  <div class="form-group row  " >
		<label for="ipt" class=" control-label col-md-4 text-end"> Avatar </label>
		<div class="col-md-8">
			
			<input type="file" name="avatar" id="avatar" class="inputfile" />
	<p>
		 Image Dimension 80 x 80 px <br />
		</p>
		 	<?php if( file_exists( './uploads/users/'.$info->avatar) && $info->avatar !='') { ?>
            <img src="{{  url('uploads/users').'/'.$info->avatar }} " border="0" width="60" class="avatar" />
            <?php  } else { ?> 
            <img alt="" src="http://www.gravatar.com/avatar/{{ md5($info->email) }}" width="60" class="avatar" />
            <?php } ?>
		
		
		 </div> 
	  </div>  

	  <div class="form-group row">
		<label for="ipt" class=" control-label col-md-4">&nbsp;</label>
		<div class="col-md-8">
			<button class="btn btn-success btn-sm" type="submit"> {{ Lang::get('core.sb_savechanges') }}</button>
		 </div> 
	  </div> 	
	
	</form>
  </div>

  <div class="tab-pane  m-t" id="pass">
	<form action="{{ url('user/savepassword/') }}" method="POST" class="form-horizontal">
		@csrf
	  
	  <div class="form-group row">
		<label for="ipt" class=" control-label col-md-4"> {{ Lang::get('core.newpassword') }} </label>
		<div class="col-md-8">
		<input name="password" type="password" id="password" pattern="(?=.*\d)(?=.*[a-z])(?=.*[A-Z]).{8,}" class="form-control form-control-sm" value="" /> 
		 </div> 
	  </div>  
	  
	  <div class="form-group row">
		<label for="ipt" class=" control-label col-md-4"> {{ Lang::get('core.conewpassword') }}  </label>
		<div class="col-md-8">
		<input name="password_confirmation" type="password" pattern="(?=.*\d)(?=.*[a-z])(?=.*[A-Z]).{8,}" id="password_confirmation" class="form-control form-control-sm" value="" />  
		 </div> 
	  </div>    
	 
	
	  <div class="form-group row">
		<label for="ipt" class=" control-label col-md-4">&nbsp;</label>
		<div class="col-md-8">
			<button class="btn btn-danger btn-sm" type="submit"> {{ Lang::get('core.sb_savechanges') }} </button>
		 </div> 
	  </div>   
	</form>
  	

  	</div>

	<div id="message">
		<h3>Password must contain the following:</h3>
		<ul>
		  <li id="letter" class="invalid">A <b>lowercase</b> letter</li>
		  <li id="capital" class="invalid">A <b>capital (uppercase)</b> letter</li>
		  <li id="number" class="invalid">A <b>number</b></li>
		  <li id="length" class="invalid">Minimum <b>8 characters</b></li>
		</ul>
	</div>
  		

  		
  		</div>
	</div>
	<div class="col-md-6">
  			<h4> Personalize </h4>
  			<div class="form-group row">
				<label for="ipt" class=" control-label col-md-4"> Header </label>
				<div class="col-md-8 c-h">
					<div class="box-theme  bg-dark" rel="color-head-dark"></div>
					<div class="box-theme  bg-white" rel="color-head-white"></div>
					<div class="box-theme  bg-blue" rel="color-head-blue"></div>
					<div class="box-theme  bg-indigo" rel="color-head-indigo"></div>
					<div class="box-theme  bg-deep-purple" rel="color-head-deep-purple"></div>
					<div class="box-theme  bg-purple" rel="color-head-purple"></div>
					<div class="box-theme  bg-pink" rel="color-head-pink"></div>
					<div class="box-theme  bg-red" rel="color-head-red"></div>
					<div class="box-theme  bg-teal" rel="color-head-teal"></div>
					<div class="box-theme  bg-cyan" rel="color-head-cyan"></div>
					<div class="box-theme  bg-light-blue" rel="color-head-light-blue"></div>
					<div class="box-theme  bg-light-green" rel="color-head-light-green"></div>
					<div class="box-theme  bg-green" rel="color-head-green"></div>
					<div class="box-theme  bg-orange" rel="color-head-orange"></div>
					<div class="box-theme  bg-yellow" rel="color-head-yellow"></div>
					<div class="box-theme  bg-blue-grey" rel="color-head-blue-grey"></div>
				 </div> 
			  </div>   
			  <div class="form-group row">
				<label for="ipt" class=" control-label col-md-4" > Logo </label>
				<div class="col-md-8 c-l">
					<div class="box-theme  bg-dark" rel="color-logo-dark"></div>
					<div class="box-theme  bg-white" rel="color-logo-white"></div>
					<div class="box-theme   bg-blue" rel="color-logo-blue"></div>
					<div class="box-theme   bg-indigo" rel="color-logo-indigo"></div>
					<div class="box-theme   bg-deep-purple" rel="color-logo-deep-purple"></div>
					<div class="box-theme   bg-purple" rel="color-logo-purple"></div>
					<div class="box-theme   bg-pink" rel="color-logo-pink"></div>
					<div class="box-theme   bg-red" rel="color-logo-red"></div>
					<div class="box-theme   bg-teal" rel="color-logo-teal"></div>
					<div class="box-theme   bg-cyan" rel="color-logo-cyan"></div>
					<div class="box-theme   bg-light-blue" rel="color-logo-light-blue"></div>				
					<div class="box-theme   bg-light-green" rel="color-logo-light-green"></div>
					<div class="box-theme   bg-green" rel="color-logo-green"></div>
					<div class="box-theme   bg-orange" rel="color-logo-orange"></div>
					<div class="box-theme   bg-yellow" rel="color-logo-yellow"></div>
					<div class="box-theme   bg-blue-grey" rel="color-logo-blue-grey"></div> 
				 </div> 
			  </div> 
			  <div class="form-group row">
				<label for="ipt" class=" control-label col-md-4" > Sidebar </label>
				<div class="col-md-8 c-s">
					<div class="box-theme  bg-dark" rel="color-sidebar-dark"></div>
					<div class="box-theme  bg-white" rel="color-sidebar-white"></div>
					<div class="box-theme   bg-blue" rel="color-sidebar-blue"></div>
					<div class="box-theme   bg-indigo" rel="color-sidebar-indigo"></div>
					<div class="box-theme   bg-deep-purple" rel="color-sidebar-deep-purple"></div>
					<div class="box-theme   bg-purple" rel="color-sidebar-purple"></div>
					<div class="box-theme   bg-pink" rel="color-sidebar-pink"></div>
					<div class="box-theme   bg-red" rel="color-sidebar-red"></div>
					<div class="box-theme  bg-teal" rel=" color-sidebar-teal"></div>
					<div class="box-theme   bg-cyan" rel="color-sidebar-cyan"></div>
					<div class="box-theme   bg-light-blue" rel="color-sidebar-light-blue"></div>
					
					<div class="box-theme   bg-light-green" rel="color-sidebar-light-green"></div>
					<div class="box-theme   bg-green" rel="color-sidebar-green"></div>
					<div class="box-theme   bg-orange" rel="color-sidebar-orange"></div>
					<div class="box-theme   bg-yellow" rel="color-sidebar-yellow"></div>
					<div class="box-theme   bg-blue-grey" rel="color-sidebar-blue-grey"></div> 
				 </div> 
			  </div>  
  		</div>
 </div> 		
 <style type="text/css">
 	.box-theme {
 		width: 20px;
 		height: 20px;
 		float: left;
 		margin: 0 10px 10px 0;
 		cursor: pointer;
 		border-radius: 2px;
 	}
 </style>
 <script>
var myInput = document.getElementById("password");
var letter = document.getElementById("letter");
var capital = document.getElementById("capital");
var number = document.getElementById("number");
var length = document.getElementById("length");

// When the user clicks on the password field, show the message box
myInput.onfocus = function() {
  document.getElementById("message").style.display = "block";
}

// When the user clicks outside of the password field, hide the message box
myInput.onblur = function() {
  document.getElementById("message").style.display = "none";
}

// When the user starts to type something inside the password field
myInput.onkeyup = function() {
  // Validate lowercase letters
  var lowerCaseLetters = /[a-z]/g;
  if(myInput.value.match(lowerCaseLetters)) {
    letter.classList.remove("invalid");
    letter.classList.add("valid");
  } else {
    letter.classList.remove("valid");
    letter.classList.add("invalid");
}

  // Validate capital letters
  var upperCaseLetters = /[A-Z]/g;
  if(myInput.value.match(upperCaseLetters)) {
    capital.classList.remove("invalid");
    capital.classList.add("valid");
  } else {
    capital.classList.remove("valid");
    capital.classList.add("invalid");
  }

  // Validate numbers
  var numbers = /[0-9]/g;
  if(myInput.value.match(numbers)) {
    number.classList.remove("invalid");
    number.classList.add("valid");
  } else {
    number.classList.remove("valid");
    number.classList.add("invalid");
  }

  // Validate length
  if(myInput.value.length >= 8) {
    length.classList.remove("invalid");
    length.classList.add("valid");
  } else {
    length.classList.remove("valid");
    length.classList.add("invalid");
  }
}
</script>
@endsection