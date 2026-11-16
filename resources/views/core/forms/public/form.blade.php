

		 <form action="{{ url('forms/savepublic') }}" class="form-vertical" method="POST" enctype="multipart/form-data" parsley-validate novalidate>

	@if(Session::has('messagetext'))
	  
		   {!! Session::get('messagetext') !!}
	   
	@endif
	<ul class="parsley-error-list">
		@foreach($errors->all() as $error)
			<li>{{ $error }}</li>
		@endforeach
	</ul>		


<div class="col-md-6">
						<fieldset><legend> Basic Form</legend>
						<input type="hidden" name="formID" value="{{ $row['formID'] }}">
									  <div class="form-group  " >
										<label for="ipt" class=" control-label "> Form Name  <span class="asterix"> * </span>  </label>									
										<input type="text" name="name" value="{{ $row['name'] }}" class="form-control" placeholder="" required>
									  </div> 					
									  <div class="form-group  " >
										<label for="ipt" class=" control-label "> Stored Method  <span class="asterix"> * </span>  </label>									
										  
					<label class='radio radio-inline'>
					<input type='radio' name='method' value ='eav' required @if($row['method'] == 'eav') checked="checked" @endif > Entey Attribute Value </label>
					<label class='radio radio-inline'>
					<input type='radio' name='method' value ='table' required @if($row['method'] == 'table') checked="checked" @endif > Based From Database </label> 						
									  </div> 					
									  <div class="form-group  " >
										<label for="ipt" class=" control-label "> Tablename    </label>									
										<input type="text" name="tablename" value="{{ $row['tablename'] }}" class="form-control" placeholder="">
									  </div> 					
									  <div class="form-group  " >
										<label for="ipt" class=" control-label "> Configuration    </label>									
										<input type="text" name="configuration" value="{{ $row['configuration'] }}" class="form-control" placeholder="">
									  </div> </fieldset>
			</div>
			
			<div class="col-md-6">
						<fieldset><legend> Message Note</legend>
									
									  <div class="form-group  " >
										<label for="ipt" class=" control-label "> Successed Note  <span class="asterix"> * </span>  </label>									
										  <textarea name='success' rows='5' id='success' class='form-control '  
				         required  >{{ $row['success'] }}</textarea> 						
									  </div> 					
									  <div class="form-group  " >
										<label for="ipt" class=" control-label "> Failed Note  <span class="asterix"> * </span>  </label>									
										  <textarea name='failed' rows='5' id='failed' class='form-control '  
				         required  >{{ $row['failed'] }}</textarea> 						
									  </div> 					
									  <div class="form-group  " >
										<label for="ipt" class=" control-label "> Redirect    </label>									
										<input type="text" name="redirect" value="{{ $row['redirect'] }}" class="form-control" placeholder="">
									  </div> </fieldset>
			</div>
			
			

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
