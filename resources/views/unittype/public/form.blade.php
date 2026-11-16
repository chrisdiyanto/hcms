

		 <form action="<?php echo url('unittype/savepublic'); ?>" method="post" class="form-horizontal" enctype="multipart/form-data" parsley-validate novalidate>

	@if(Session::has('messagetext'))
	  
		   {!! Session::get('messagetext') !!}
	   
	@endif
	<ul class="parsley-error-list">
		@foreach($errors->all() as $error)
			<li>{{ $error }}</li>
		@endforeach
	</ul>		


<div class="col-md-12">
						<fieldset><legend> Unit Type</legend>
				<input type='hidden' name='id_unit_type' value='{{ $row['id_unit_type'] }}'>					
									  <div class="form-group row  " >
										<label for="Unit Type" class=" control-label col-md-4 "> Unit Type </label>
										<div class="col-md-8">
										  <input  type='text' name='unit_type' id='unit_type' value='{{ $row['unit_type'] }}' 
						     class='form-control form-control-sm ' /> 
										 </div> 
										 
									  </div> 					
									  <div class="form-group row  " >
										<label for="Keterangan" class=" control-label col-md-4 "> Keterangan </label>
										<div class="col-md-8">
										  <textarea name='keterangan' rows='5' id='keterangan' class='form-control form-control-sm '  
				           >{{ $row['keterangan'] }}</textarea> 
										 </div> 
										 
									  </div> 					
									  <div class="form-group row  " >
										<label for="Status" class=" control-label col-md-4 "> Status </label>
										<div class="col-md-8">
										  <input  type='text' name='status' id='status' value='{{ $row['status'] }}' 
						     class='form-control form-control-sm ' /> 
										 </div> 
										 
									  </div> </fieldset></div>

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
