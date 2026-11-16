@if($setting['form-method'] =='native')
<div class="card">
	<div class="card-body">
@endif
		<div class="form-ajax-box">
		<form action="unit?return={{$return}}" class="form-horizontal sximo-form validated" id="unitFormAjax" method="post" enctype="multipart/form-data" parsley-validate novalidate>

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
						<fieldset><legend> Unit</legend>
				<input type='hidden' name='id_unit' value='{{ $row['id_unit'] }}'>					
				<input type='hidden' name='slug_unit' id='slug_unit' value='{{ $row['slug_unit'] }}'>					
									  <div class="form-group row  " >
										<label for="Parent" class=" control-label col-md-4 "> Parent </label>
										<div class="col-md-8">
										  <select name='parent_id' rows='5' id='parent_id' class='select2 '   ></select> 
										 </div> 
										 
									  </div> 					
									  <div class="form-group row  " >
										<label for="Urutan" class=" control-label col-md-4 "> Urutan </label>
										<div class="col-md-8">
										  <textarea name='urutan' rows='5' id='urutan' class='form-control form-control-sm '  
				           >{{ $row['urutan'] }}</textarea> 
										 </div> 
										 
									  </div> 					
									  <div class="form-group row  " >
										<label for="Category" class=" control-label col-md-4 "> Category </label>
										<div class="col-md-8">
										  <select name='id_unit_category' rows='5' id='id_unit_category' class='select2 '   ></select> 
										 </div> 
										 
									  </div> 					
									  <div class="form-group row  " >
										<label for="Unit" class=" control-label col-md-4 "> Unit </label>
										<div class="col-md-8">
										  <input  type='text' name='unit' id='unit' value='{{ $row['unit'] }}' 
						     class='form-control form-control-sm ' /> 
										 </div> 
										 
									  </div> 					
									  <div class="form-group row  " >
										<label for="Unit Code" class=" control-label col-md-4 "> Unit Code </label>
										<div class="col-md-8">
										  <textarea name='unit_code' rows='5' id='unit_code' class='form-control form-control-sm '  
				           >{{ $row['unit_code'] }}</textarea> 
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
										<label for="Addres" class=" control-label col-md-4 "> Addres </label>
										<div class="col-md-8">
										  <textarea name='addres' rows='5' id='addres' class='form-control form-control-sm '  
				           >{{ $row['addres'] }}</textarea> 
										 </div> 
										 
									  </div> 					
									  <div class="form-group row  " >
										<label for="Status" class=" control-label col-md-4 "> Status </label>
										<div class="col-md-8">
										  
					
					<input type='radio' name='status' value ='1'  @if($row['status'] == '1') checked="checked" @endif class='filled-in' id='status-0'> <label for='status-0'>Active </label>
					
					<input type='radio' name='status' value ='0'  @if($row['status'] == '0') checked="checked" @endif class='filled-in' id='status-1'> <label for='status-1'>Non Active </label> 
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
	
		$("#parent_id").jCombo("{!! url('unit/comboselect?filter=unit:id_unit_category:unit') !!}",
		{  selected_value : '{{ $row["parent_id"] }}' });
		
		$("#id_unit_category").jCombo("{!! url('unit/comboselect?filter=unit_category:id_unit_category:unit_category') !!}",
		{  selected_value : '{{ $row["id_unit_category"] }}' });
		 
		var original_parent_id = '{{ $row["parent_id"] }}';
		var original_slug_unit = '{{ $row["slug_unit"] }}';
		var original_unit_code = '{{ $row["unit_code"] }}';
		var id_unit = '{{ $row["id_unit"] }}';

		function generateSlugUnit() {
			var parent_id = $('#parent_id').val();
			
			if (id_unit && (parent_id == original_parent_id || (!parent_id && !original_parent_id))) {
				$('#slug_unit').val(original_slug_unit);
				if (!$('#unit_code').val()) {
					$('#unit_code').val(original_unit_code);
				}
				return;
			}

			$.ajax({
				url      : "{{ url('unit/generateslug') }}",
				method   : 'POST',
				data     : { parent_id: parent_id, id_unit: id_unit, _token: '{{ csrf_token() }}' },
				dataType : 'json',
				success  : function(respon) {
					if(respon.status === 'success') {
						$('#slug_unit').val(respon.slug_unit);
						if (!$('#unit_code').val() || $('#unit_code').val() === original_unit_code) {
							$('#unit_code').val(respon.unit_code);
						}
					} else {
						if (typeof notyMessageError === 'function') {
							notyMessageError(respon.message);
						}
					}
				}
			});
		}

		$('#parent_id').on('change', function() {
			generateSlugUnit();
		});

		if (!id_unit) {
			generateSlugUnit();
		}
				
	var form = $('#unitFormAjax'); 
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
						var table = $('#unitTable').DataTable();
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