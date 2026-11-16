@if($setting['form-method'] =='native')
<div class="card">
	<div class="card-body">
@endif
		<div class="form-ajax-box">
		<form action="allunit?return={{$return}}" class="form-horizontal sximo-form validated" id="allunitFormAjax" method="post" enctype="multipart/form-data" parsley-validate novalidate>

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
	 
	
	 
				
	var form = $('#allunitFormAjax'); 
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
						var table = $('#allunitTable').DataTable();
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