

		 <form action="<?php echo url('allunit/savepublic'); ?>" method="post" class="form-horizontal" enctype="multipart/form-data" parsley-validate novalidate>

	@if(Session::has('messagetext'))
	  
		   {!! Session::get('messagetext') !!}
	   
	@endif
	<ul class="parsley-error-list">
		@foreach($errors->all() as $error)
			<li>{{ $error }}</li>
		@endforeach
	</ul>		


<div class="col-md-12">
						<fieldset><legend> All Unit</legend>
				<input type='hidden' name='id_unit' value='{{ $row['id_unit'] }}'>					
									  <div class="form-group row  " >
										<label for="Slug Unit" class=" control-label col-md-4 "> Slug Unit </label>
										<div class="col-md-8">
										  <input  type='text' name='slug_unit' id='slug_unit' value='{{ $row['slug_unit'] }}' 
						     class='form-control form-control-sm ' /> 
										 </div> 
										 
									  </div> 					
									  <div class="form-group row  " >
										<label for="Parent Id" class=" control-label col-md-4 "> Parent Id </label>
										<div class="col-md-8">
										  <input  type='text' name='parent_id' id='parent_id' value='{{ $row['parent_id'] }}' 
						     class='form-control form-control-sm ' /> 
										 </div> 
										 
									  </div> 					
									  <div class="form-group row  " >
										<label for="Urutan" class=" control-label col-md-4 "> Urutan </label>
										<div class="col-md-8">
										  <input  type='text' name='urutan' id='urutan' value='{{ $row['urutan'] }}' 
						     class='form-control form-control-sm ' /> 
										 </div> 
										 
									  </div> 					
									  <div class="form-group row  " >
										<label for="Unit Name" class=" control-label col-md-4 "> Unit Name </label>
										<div class="col-md-8">
										  <textarea name='unit_name' rows='5' id='unit_name' class='form-control form-control-sm '  
				           >{{ $row['unit_name'] }}</textarea> 
										 </div> 
										 
									  </div> 					
									  <div class="form-group row  " >
										<label for="Unit Code" class=" control-label col-md-4 "> Unit Code </label>
										<div class="col-md-8">
										  <input  type='text' name='unit_code' id='unit_code' value='{{ $row['unit_code'] }}' 
						     class='form-control form-control-sm ' /> 
										 </div> 
										 
									  </div> 					
									  <div class="form-group row  " >
										<label for="Id Unit Category" class=" control-label col-md-4 "> Id Unit Category </label>
										<div class="col-md-8">
										  <input  type='text' name='id_unit_category' id='id_unit_category' value='{{ $row['id_unit_category'] }}' 
						     class='form-control form-control-sm ' /> 
										 </div> 
										 
									  </div> 					
									  <div class="form-group row  " >
										<label for="Id Corporate" class=" control-label col-md-4 "> Id Corporate </label>
										<div class="col-md-8">
										  <input  type='text' name='id_corporate' id='id_corporate' value='{{ $row['id_corporate'] }}' 
						     class='form-control form-control-sm ' /> 
										 </div> 
										 
									  </div> 					
									  <div class="form-group row  " >
										<label for="Unit Addres" class=" control-label col-md-4 "> Unit Addres </label>
										<div class="col-md-8">
										  <textarea name='unit_addres' rows='5' id='unit_addres' class='form-control form-control-sm '  
				           >{{ $row['unit_addres'] }}</textarea> 
										 </div> 
										 
									  </div> 					
									  <div class="form-group row  " >
										<label for="Unit Explan" class=" control-label col-md-4 "> Unit Explan </label>
										<div class="col-md-8">
										  <textarea name='unit_explan' rows='5' id='unit_explan' class='form-control form-control-sm '  
				           >{{ $row['unit_explan'] }}</textarea> 
										 </div> 
										 
									  </div> 					
									  <div class="form-group row  " >
										<label for="Id Unit Type" class=" control-label col-md-4 "> Id Unit Type </label>
										<div class="col-md-8">
										  <input  type='text' name='id_unit_type' id='id_unit_type' value='{{ $row['id_unit_type'] }}' 
						     class='form-control form-control-sm ' /> 
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
