@if($setting['form-method'] =='native')
<div class="card">
	<div class="card-body">
@endif
		<div class="form-ajax-box">
		<form action="iconsmanagement?return={{$return}}" class="form-horizontal sximo-form validated" id="iconsmanagementFormAjax" method="post" enctype="multipart/form-data" parsley-validate novalidate>

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
				<fieldset><legend> Icons Management</legend>
				<input type='hidden' name='id' value='{{ $row['id'] }}'>

				<!-- Name -->
				<div class="form-group row mb-3">
					<label class="col-md-3 col-form-label text-md-end">Name</label>
					<div class="col-md-9">
						<input type="text" name="name" value="{{ $row['name'] }}" class="form-control" placeholder="Icon Name" required>
					</div>
				</div>

				<!-- Category -->
				<div class="form-group row mb-3">
					<label class="col-md-3 col-form-label text-md-end">Category</label>
					<div class="col-md-9">
						<select name="category_id" class="form-control" required>
							<option value="">Select Category</option>
							<!-- Ensure $categories is passed from controller, or use Sximo's lookup method -->
							@php $categories = \DB::table('tb_icon_categories')->get(); @endphp
							@foreach($categories as $category)
								<option value="{{ $category->id }}" {{ $row['category_id'] == $category->id ? 'selected' : '' }}>
									{{ $category->name }}
								</option>
							@endforeach
						</select>
					</div>
				</div>

				<!-- Type -->
				<div class="form-group row mb-3">
					<label class="col-md-3 col-form-label text-md-end">Icon Type</label>
					<div class="col-md-9">
						<div class="btn-group" role="group" aria-label="Icon Type">
							<input type="radio" class="btn-check d-none" name="type" id="type_code" value="code" {{ $row['type'] == 'code' || empty($row['type']) ? 'checked' : '' }} onchange="toggleType()">
							<label class="btn btn-outline-primary" for="type_code">SVG Code</label>

							<input type="radio" class="btn-check d-none" name="type" id="type_file" value="file" {{ $row['type'] == 'file' ? 'checked' : '' }} onchange="toggleType()">
							<label class="btn btn-outline-primary" for="type_file">SVG File</label>

							<input type="radio" class="btn-check d-none" name="type" id="type_url" value="url" {{ $row['type'] == 'url' ? 'checked' : '' }} onchange="toggleType()">
							<label class="btn btn-outline-primary" for="type_url">Image URL</label>

							<input type="radio" class="btn-check d-none" name="type" id="type_class" value="class" {{ $row['type'] == 'class' ? 'checked' : '' }} onchange="toggleType()">
							<label class="btn btn-outline-primary" for="type_class">Icon Class</label>
						</div>
					</div>
				</div>

				<!-- SVG Code Input -->
				<div class="form-group row mb-3 type-input" id="input_code" style="display: none;">
					<label class="col-md-3 col-form-label text-md-end">SVG Code</label>
					<div class="col-md-9">
						<textarea name="svg_code" class="form-control" rows="5" placeholder="Paste SVG code here">{{ $row['svg_code'] }}</textarea>
					</div>
				</div>

				<!-- SVG File Input -->
				<div class="form-group row mb-3 type-input" id="input_file" style="display: none;">
					<label class="col-md-3 col-form-label text-md-end">Upload SVG</label>
					<div class="col-md-9">
						<input type="file" name="file_path" class="form-control" accept=".svg,.png,.jpg,.jpeg">
						@if($row['file_path'])
							<div class="mt-2">
								<span class="text-muted">Current:</span> <a href="{{ asset($row['file_path']) }}" target="_blank">View File</a>
							</div>
						@endif
					</div>
				</div>

				<!-- URL Input -->
				<div class="form-group row mb-3 type-input" id="input_url" style="display: none;">
					<label class="col-md-3 col-form-label text-md-end">Image URL</label>
					<div class="col-md-9">
						<input type="url" name="icon_url" value="{{ $row['icon_url'] }}" class="form-control" placeholder="https://example.com/icon.png">
					</div>
				</div>

				<!-- Class Input -->
				<div class="form-group row mb-3 type-input" id="input_class" style="display: none;">
					<label class="col-md-3 col-form-label text-md-end">Icon Class</label>
					<div class="col-md-9">
						<input type="text" name="icon_class" value="{{ $row['icon_class'] }}" class="form-control" placeholder="e.g. fas fa-user">
						<div class="form-text">Enter the class name (e.g. <code>ti-home</code>, <code>fa-brands fa-facebook</code>).</div>
					</div>
				</div>

				</fieldset>
			</div>
			
			<input type="hidden" name="action_task" value="save" />
		</form>
		</div>

@if($setting['form-method'] =='native')
	</div>
</div>
@endif

@include('sximo.module.template.ajax.formjavascript')

<script type="text/javascript">
	function toggleType() {
		// Hide all inputs and disable fields inside them
		document.querySelectorAll('.type-input').forEach(el => {
			el.style.display = 'none';
			el.querySelectorAll('input:not([type="radio"]), textarea').forEach(field => field.disabled = true);
		});
		
		// Get selected type
		const type = document.querySelector('input[name="type"]:checked');
		if (type) {
			const inputId = 'input_' + type.value;
			const inputEl = document.getElementById(inputId);
			if (inputEl) {
				inputEl.style.display = 'flex'; // bootstrap row is flex
				// Enable fields inside the selected container
				inputEl.querySelectorAll('input:not([type="radio"]), textarea').forEach(field => field.disabled = false);
			}
		}
	}

$(document).ready(function() { 
	// Run on load check
	toggleType();

	 
	
	 
				
	var form = $('#iconsmanagementFormAjax'); 
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
						var table = $('#iconsmanagementTable').DataTable();
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