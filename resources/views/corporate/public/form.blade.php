

		 <form method="POST" action="{{ url('corporate/savepublic') }}" accept-charset="UTF-8" class="form-horizontal" enctype="multipart/form-data" parsley-validate="" novalidate=" ">
		 @csrf

	@if(Session::has('messagetext'))
	  
		   {!! Session::get('messagetext') !!}
	   
	@endif
	<ul class="parsley-error-list">
		@foreach($errors->all() as $error)
			<li>{{ $error }}</li>
		@endforeach
	</ul>		


<div class="col-md-12">
						<fieldset><legend> Corporate</legend>
				<input type="hidden" name="id_corporate" value="{{ $row['id_corporate'] }}"><input type="hidden" name="slug_corporate" value="{{ $row['slug_corporate'] }}">
				<?php 
				$limited = isset($fields['corporate_name']['limited']) ? $fields['corporate_name']['limited'] :'';
				if(SiteHelpers::filterColumn($limited )) { ?>
												
									  <div class="form-group row  " >
										<label for="Corporate Name" class=" control-label col-md-4 "> Corporate Name <span class="asterix"> * </span></label>
										<div class="col-md-8">
										  <input  type='text' name='corporate_name' id='corporate_name' value='{{ $row['corporate_name'] }}' 
						required     class='form-control form-control-sm ' /> 
										 </div> 
										 
									  </div> 
				<?php } ?>
				<?php 
				$limited = isset($fields['corporate_address']['limited']) ? $fields['corporate_address']['limited'] :'';
				if(SiteHelpers::filterColumn($limited )) { ?>
												
									  <div class="form-group row  " >
										<label for="Address" class=" control-label col-md-4 "> Address <span class="asterix"> * </span></label>
										<div class="col-md-8">
										  <textarea name='corporate_address' rows='5' id='corporate_address' class='form-control form-control-sm '  
				         required  >{{ $row['corporate_address'] }}</textarea> 
										 </div> 
										 
									  </div> 
				<?php } ?></fieldset></div>

			<div style="clear:both"></div>	
				
					
				  <div class="form-group">
					<label class="col-sm-4 text-end">&nbsp;</label>
					<div class="col-sm-8">	
					<button type="submit" name="apply" class="btn btn-info btn-sm" ><i class="fa  fa-check-circle"></i> {{ Lang::get('core.sb_apply') }}</button>
					<button type="submit" name="submit" class="btn btn-primary btn-sm" ><i class="fa  fa-save "></i> {{ Lang::get('core.sb_save') }}</button>
				  </div>	  
			
		</div> 
		 
		 </form>
		 
   <script type="text/javascript">
	$(document).ready(function() { 
		
		 

		$('.removeCurrentFiles').on('click',function(){
			var removeUrl = $(this).attr('href');
			$.get(removeUrl,function(response){});
			$(this).parent('div').empty();	
			return false;
		});		
		
	});
	</script>		 
