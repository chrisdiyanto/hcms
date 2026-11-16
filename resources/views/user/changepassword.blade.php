@extends('layouts.login')
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

@section('content')

<div>
	<div style="position:fixed; top:50%; left:50%; transform:translate(-50%,-50%); width:95vw; height:95vh; border-radius:10px; padding:0">
		<div class="box box-info box-solid" style="height:100%; border-radius:10px; width:100%; box-shadow:0 0 10px rgba(0,0,0,0.15);">
      <div class="card-body" style="display: flex; justify-content: center; align-items: center; height: 100%;">
        <div class="col-md-5">

          <form action="{{ url('user/savepassword/') }}" method="POST" class="form-horizontal">
            @csrf
          
            <div class="form-group row">
              <label for="ipt" class=" control-label col-md-4"> {{ Lang::get('core.newpassword') }} </label>
              <div class="col-md-8">
              <input name="password" type="password" id="password" pattern="(?=.*\d)(?=.*[a-z])(?=.*[A-Z]).{8,}" class="form-control form-control-sm" required value="" /> 
              </div> 
            </div>  
            
            <div class="form-group row">
              <label for="ipt" class=" control-label col-md-4"> {{ Lang::get('core.conewpassword') }}  </label>
              <div class="col-md-8">
              <input name="password_confirmation" type="password" pattern="(?=.*\d)(?=.*[a-z])(?=.*[A-Z]).{8,}" id="password_confirmation" required class="form-control form-control-sm" value="" />  
              </div> 
            </div>    
          
          
            <div class="form-group row">
              <div class="col-md-12 text-center">
                <button class="btn btn-danger btn-sm" type="submit"> {{ Lang::get('core.sb_savechanges') }} </button>
              </div>
              
            </div>   
            </form>	

            <div id="message">
              <div class="col-md-12 text-center">
                <h3>Password must contain the following:</h3>
                <ul style="text-align:left; padding-left:20px;">
                  <li id="letter" class="invalid">A <b>lowercase</b> letter</li>
                  <li id="capital" class="invalid">A <b>capital (uppercase)</b> letter</li>
                  <li id="number" class="invalid">A <b>number</b></li>
                  <li id="length" class="invalid">Minimum <b>8 characters</b></li>
                </ul>
              </div>
            </div>

        </div>
      </div>
      
		</div>
	</div>
</div>

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
//  document.getElementById("message").style.display = "none";
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

@stop