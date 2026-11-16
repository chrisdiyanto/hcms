

		 <form action="<?php echo url('iconsmanagement/savepublic'); ?>" method="post" class="form-horizontal" enctype="multipart/form-data" parsley-validate novalidate>

	@if(Session::has('messagetext'))
	  
		   {!! Session::get('messagetext') !!}
	   
	@endif
	<ul class="parsley-error-list">
		@foreach($errors->all() as $error)
			<li>{{ $error }}</li>
		@endforeach
	</ul>		


<div class="col-md-12">
						<fieldset><legend> Icons Management</legend>
				<input type='hidden' name='id' value='{{ $row['id'] }}'>					
									  <div class="form-group row  " >
										<label for="Name" class=" control-label col-md-4 "> Name </label>
										<div class="col-md-8">
										  <input  type='text' name='name' id='name' value='{{ $row['name'] }}' 
						     class='form-control form-control-sm ' /> 
										 </div> 
										 
									  </div> 					
									  <div class="form-group row  " >
										<label for="Type" class=" control-label col-md-4 "> Type </label>
										<div class="col-md-8">
										  <input  type='text' name='type' id='type' value='{{ $row['type'] }}' 
						     class='form-control form-control-sm ' /> 
										 </div> 
										 
									  </div> 					
									  <div class="form-group row  " >
										<label for="Svg Code" class=" control-label col-md-4 "> Svg Code </label>
										<div class="col-md-8">
										  <textarea name='svg_code' rows='5' id='svg_code' class='form-control form-control-sm '  
				           >{{ $row['svg_code'] }}</textarea> 
										 </div> 
										 
									  </div> 					
									  <div class="form-group row  " >
										<label for="File Path" class=" control-label col-md-4 "> File Path </label>
										<div class="col-md-8">
										  <input  type='text' name='file_path' id='file_path' value='{{ $row['file_path'] }}' 
						     class='form-control form-control-sm ' /> 
										 </div> 
										 
									  </div> 					
									  <div class="form-group row  " >
										<label for="Icon Url" class=" control-label col-md-4 "> Icon Url </label>
										<div class="col-md-8">
										  <input  type='text' name='icon_url' id='icon_url' value='{{ $row['icon_url'] }}' 
						     class='form-control form-control-sm ' /> 
										 </div> 
										 
									  </div> 					
									  <div class="form-group row  " >
										<label for="Icon Class" class=" control-label col-md-4 "> Icon Class </label>
										<div class="col-md-8">
										  <input  type='text' name='icon_class' id='icon_class' value='{{ $row['icon_class'] }}' 
						     class='form-control form-control-sm ' /> 
										 </div> 
										 
									  </div> 					
									  <div class="form-group row  " >
										<label for="Category" class=" control-label col-md-4 "> Category </label>
										<div class="col-md-8">
										  <input  type='text' name='category' id='category' value='{{ $row['category'] }}' 
						     class='form-control form-control-sm ' /> 
										 </div> 
										 
									  </div> 					
									  <div class="form-group row  " >
										<label for="Category Id" class=" control-label col-md-4 "> Category Id </label>
										<div class="col-md-8">
										  <input  type='text' name='category_id' id='category_id' value='{{ $row['category_id'] }}' 
						     class='form-control form-control-sm ' /> 
										 </div> 
										 
									  </div> 					
									  <div class="form-group row  " >
										<label for="Created At" class=" control-label col-md-4 "> Created At </label>
										<div class="col-md-8">
										  <input type='text' name='created_at' id='created_at' value='{{ $row['created_at'] }}' class='form-control form-control-sm datetime'> 
										 </div> 
										 
									  </div> 					
									  <div class="form-group row  " >
										<label for="Updated At" class=" control-label col-md-4 "> Updated At </label>
										<div class="col-md-8">
										  <input type='text' name='updated_at' id='updated_at' value='{{ $row['updated_at'] }}' class='form-control form-control-sm datetime'> 
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
