<form action="{{ url($module) }}" method="POST" enctype="multipart/form-data" class="form-vertical" data-parsley-validate novalidate id="importForm">

<div class="row " id="importUpload">
	<div class="col-md-7">
		<p class="alert alert-warning">
			<i class="fa fa-warning"></i>  Please read this note !  
		</p>	
		<p>
		Please Make sure your CSV file format field column same as database fields. <br />please download template bellow 
		</p><br />
		<a href="{{ url( $module.'/import?template=true')}}" class="btn btn-sm btn-default d-block w-100"><i class="fa fa-download"></i> Download template </a>
	</div>
	<div class="col-md-5">
		<div class="form-group  " >
			<div class="fileUpload btn " > 
				    <span>  <i class="fa fa-copy"></i>  </span>
				    <div class="title">  Upload CSV File </div>
				    <input type="file" name="fileimport" class="upload"         />
				</div>
		</div>

		<div class="form-group  " >
		
		<button type="submit" class="btn btn-primary btn-sm d-block w-100 " name="submit" > Import Now  </button>
		<input type="hidden" name="action_task" value="import"></input>
		</div>
	</div>

</div>		

<div class="row " >
	<div class="col-md-12" id="importPreview">

	</div>	
</div>
</form>

<script type="text/javascript">
	$(function(){
		$(".inputfile").on('change',function () { 
			 uploadPreview(this) 
		});

		var form = $('#importForm'); 
		form.parsley();
		form.submit(function(){
			
			if(form.parsley().isValid()) {			
				var options = { 
					dataType:      'json', 
					beforeSubmit : function() {
						
					},
					success: function(data){
						if(data.status =='success')
						{
							notyMessage(data.message);	
							$('#sximo-modal').modal('hide');
							window.location.href = '{{ $url }}';
						} else {
							notyMessageError(data.message);	
						}
						
					}  
				}  
				$(this).ajaxSubmit(options); 
				return false;
							
			} else {
				return false;
			}		
		
		});


	})
</script>