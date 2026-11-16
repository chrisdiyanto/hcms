<form action="{{ url('home/proccess/1') }}" id="formconfiguration" class="form-vertical" method="POST" enctype="multipart/form-data" parsley-validate novalidate>
@if(Session::has('message'))	  
		{!! Session::get('message') !!}
@endif	
	
<ul class="parsley-error-list">
	@foreach($errors->all() as $error)
		<li>{{ $error }}</li>
	@endforeach
</ul>

<div class="form-group row  " >
					<label for="ipt" class="  "> Full Name  </label>
					<input type="text" name="name" class="form-control" placeholder="" required>
		</div>

		<div class="form-group row  " >
					<label for="ipt" class="  "> Subject  </label>
					<input type="text" name="subject" class="form-control" placeholder="" required>
		</div>

		<div class="form-group row  " >
					<label for="ipt" class="  "> Email Address  </label>
					<input type="email" name="email" class="form-control" placeholder="" required>
		</div>

		<div class="form-group row  " >
					<label for="ipt" class="  "> Your Message  </label>
				<textarea name='message' rows='5' id='message' class='form-control '  
				         required  ></textarea>
		</div>

		
		<div class="form-group row  " >					
				<button type="submit" name="submit" class="btn btn-primary"><i class="icon-checkmark-circle2"></i> Submit </button>
		</div>

</form>


<script type="text/javascript">
	$(document).ready(function(){
	});

	
</script>