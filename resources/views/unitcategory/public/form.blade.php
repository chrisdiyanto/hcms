

		 <form action="<?php echo url('unitcategory/savepublic'); ?>" method="post" class="form-horizontal" enctype="multipart/form-data" parsley-validate novalidate>

	@if(Session::has('messagetext'))
	  
		   {!! Session::get('messagetext') !!}
	   
	@endif
	<ul class="parsley-error-list">
		@foreach($errors->all() as $error)
			<li>{{ $error }}</li>
		@endforeach
	</ul>		


<div class="col-md-12">
						<fieldset><legend> Unit Category</legend>
				<input type='hidden' name='id_unit_category' value='{{ $row['id_unit_category'] }}'>					
									  <div class="form-group row  " >
										<label for="Slug Unit Category" class=" control-label col-md-4 "> Slug Unit Category </label>
										<div class="col-md-8">
										  <input  type='text' name='slug_unit_category' id='slug_unit_category' value='{{ $row['slug_unit_category'] }}' 
						     class='form-control form-control-sm ' /> 
										 </div> 
										 
									  </div> 					
									  <div class="form-group row  " >
										<label for="Unit Category" class=" control-label col-md-4 "> Unit Category </label>
										<div class="col-md-8">
										  <input  type='text' name='unit_category' id='unit_category' value='{{ $row['unit_category'] }}' 
						     class='form-control form-control-sm ' /> 
										 </div> 
										 
									  </div> 					
									  <div class="form-group row  " >
										<label for="Unit Category Code" class=" control-label col-md-4 "> Unit Category Code </label>
										<div class="col-md-8">
										  <input  type='text' name='unit_category_code' id='unit_category_code' value='{{ $row['unit_category_code'] }}' 
						     class='form-control form-control-sm ' /> 
										 </div> 
										 
									  </div> 					
									  <div class="form-group row  " >
										<label for="Unit Category Explan" class=" control-label col-md-4 "> Unit Category Explan </label>
										<div class="col-md-8">
										  <textarea name='unit_category_explan' rows='5' id='unit_category_explan' class='form-control form-control-sm '  
				           >{{ $row['unit_category_explan'] }}</textarea> 
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
