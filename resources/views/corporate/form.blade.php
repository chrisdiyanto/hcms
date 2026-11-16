@if($setting['form-method'] =='native')
<div class="card">
	<div class="card-body">
@endif
		<div class="form-ajax-box">
		{{-- HTML biasa: facade Form (laravelcollective/html) tidak tersedia di Laravel 13 --}}
		<form method="POST" action="{{ url('corporate?return='.$return) }}" accept-charset="UTF-8" class="form-horizontal  sximo-form validated" enctype="multipart/form-data" parsley-validate="" novalidate=" " id="corporateFormAjax">
		@csrf

			<div class="toolbar-nav">	
				<div class="row">	
					
					<div class="col-md-6">
						<a href="javascript://ajax" onclick="ajaxViewClose('#{{ $pageModule }}')" class="tips btn btn-sm btn-danger  " title="{{ __('core.btn_back') }}" ><i class="fa  fa-times"></i></a>
					</div>
					<div class="col-sm-6 text-end">	
						<div class="btn-group">
							<button type="submit" class="btn btn-sm btn-success" name="save">  {{ Lang::get('core.sb_save') }} </button>
						</div>	
					</div>	
							
				</div>
			</div>	
				<div class="row">

			
			<div class="col-md-12">
						<fieldset><legend> Corporate</legend>
				<input type="hidden" name="id_corporate" value="{{ $row['id_corporate'] }}"><input type="hidden" name="slug_corporate" value="{{ $row['slug_corporate'] }}">
				<?php 
				$limited = isset($fields['corporate_name']['limited']) ? $fields['corporate_name']['limited'] :'';
				if(SiteHelpers::filterColumn($limited )) { ?>
												
									  <div class="form-group row  " >
										<label for="Corporate Name" class=" control-label col-md-4 "> Corporate Name <span class="asterix"> * </span></label>
										<div class="col-md-8">
										  <textarea name='corporate_name' rows='5' id='corporate_name' class='form-control form-control-sm '  
				         required  >{{ $row['corporate_name'] }}</textarea> 
										 </div> 
										 
									  </div> 
				<?php } ?>					
									  <div class="form-group row  " >
										<label for="Code" class=" control-label col-md-4 "> Code <span class="asterix"> * </span></label>
										<div class="col-md-8">
										  <input  type='text' name='code' id='code' value='{{ $row['code'] }}' 
						required     class='form-control form-control-sm ' /> 
										 </div> 
										 
									  </div> 
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
	 
	
	 
				
	var form = $('#corporateFormAjax'); 
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
						var table = $('#corporateTable').DataTable();
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