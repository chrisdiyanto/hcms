@extends('layouts.app')

@section('content')
<div class="page-titles">
  <h2> {{ $pageTitle }} <small> {{ $pageNote }} </small></h2>
</div>
<div  class="card">
	<div class="card-body">


		 <form action="{{ url('core/groups?return='.$return) }}" class="form-horizontal sximo-form validated" method="POST" enctype="multipart/form-data" parsley-validate novalidate>
									
								  <div class="form-group row hidethis " style="display:none;">
									<label for="Group Id" class=" control-label col-md-3 text-end"> Group Id </label>
									<div class="col-md-8">
									<input type="text" name="group_id" value="{{ $row['group_id'] }}" class="form-control form-control-sm" placeholder="">
									 </div> 
									 <div class="col-md-2">
									 	
									 </div>
								  </div> 					
								  <div class="form-group row  " >
									<label for="Name" class=" control-label col-md-3 text-end"> Name <span class="asterix"> * </span></label>
									<div class="col-md-8">
									<input type="text" name="name" value="{{ $row['name'] }}" class="form-control form-control-sm" placeholder="" required>
									 </div> 
									 <div class="col-md-2">
									 	
									 </div>
								  </div> 					
								  <div class="form-group row  " >
									<label for="Description" class=" control-label col-md-3 text-end"> Description </label>
									<div class="col-md-8">
									  <textarea name='description' rows='2' id='description' class='form-control '  
				           >{{ $row['description'] }}</textarea> 
									 </div> 
									 <div class="col-md-2">
									 	
									 </div>
								  </div> 					
								  <div class="form-group row  " >
									<label for="Level" class=" control-label col-md-3 text-end"> Level <span class="asterix"> * </span></label>
									<div class="col-md-8">
									<input type="number" name="level" value="{{ $row['level'] }}" class="form-control form-control-sm" placeholder="" required>
									 </div> 
									 <div class="col-md-2">
									 	
									 </div>
								  </div> 
			
			
			<div style="clear:both"></div>	
				
					
				  <div class="form-group row">
					<label class="col-sm-3 text-end">&nbsp;</label>
					<div class="col-sm-8">	
					<button type="submit" name="apply" class="btn btn-info btn-sm" ><i class="fa  fa-check-circle"></i> {{ Lang::get('core.sb_apply') }}</button>
					<button type="submit" name="submit" class="btn btn-primary btn-sm" ><i class="fa  fa-save "></i> {{ Lang::get('core.sb_save') }}</button>
					<button type="button" onclick="location.href='{{ URL::to('core/groups?return='.$return) }}' " class="btn btn-success btn-sm "><i class="fa  fa-arrow-circle-left "></i>  {{ Lang::get('core.sb_cancel') }} </button>
					</div>	  
			
				  </div> 
		 	<input type="hidden" name="action_task" value="save" />
		 	</form>

	</div>
</div>		 
   <script type="text/javascript">
	$(document).ready(function() { 
		
		 
	});
	</script>		 
@stop