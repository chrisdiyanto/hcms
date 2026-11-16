@if($setting['form-method'] =='native')
<div class="card">
	<div class="card-body">
@endif
		<div class="form-ajax-box">
		<form action="iconcategories?return={{$return}}" class="form-horizontal sximo-form validated" id="iconcategoriesFormAjax" method="post" enctype="multipart/form-data" parsley-validate novalidate>

			<div class="toolbar-nav">	
				<div class="row">	
					
					<div class="col-md-6">
						<a href="javascript://ajax" onclick="ajaxViewClose('#{{ $pageModule }}')" class="tips btn btn-sm btn-danger  " title="{{ __('core.btn_back') }}" ><i class="fa  fa-times"></i></a>
					</div>
					<div class="col-sm-6 text-end">	
						<div class="btn-group">
							<button type="submit" class="btn btn-sm  btn-primary " name="apply"> {{ Lang::get('core.sb_apply') }} </button>
							<button type="submit" class="btn btn-sm btn-success" name="save">  {{ Lang::get('core.sb_save') }} </button>
						</div>	
					</div>	
							
				</div>
			</div>	
				<div class="row">

			
			<div class="col-md-12">
						<fieldset><legend> Icon Categories</legend>
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
				
			<input type="hidden" name="action_task" value="save" />				
						


			

			
		</form>
		</div>

@if($setting['form-method'] =='native')

	</div>
</div>
@endif

@include('sximo.module.template.ajax.formjavascript')

<script type="text/javascript">
$(document).ready(function() { 
	 
	
	 
				
	var form = $('#iconcategoriesFormAjax'); 
	form.parsley();
	form.submit(function(){
		
		if(form.parsley().isValid()){			
			var options = { 
				dataType:      'json', 
				beforeSubmit :  function(){
				},
				success: function(data) {
					if(data.status == 'success')
					{
						ajaxViewClose('#{{ $pageModule }}');
						var table = $('#iconcategoriesTable').DataTable();
						table.ajax.reload();
						notyMessage(data.message);	
						$('#sximo-modal').modal('hide');	
					} else {
						notyMessageError(data.message);	
						return false;
					}
				}  
			}  
			$(this).ajaxSubmit(options); 
			return false;
						
		} else {
			return false;
		}		
	
	});

});

</script>		 