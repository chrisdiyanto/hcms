@extends('layouts.app')

@section('content')
{{--*/ usort($tableGrid, "SiteHelpers::_sort") /*--}}
<div class="page-titles">
	<h4 class="mb-0">  {{ $pageTitle }} <small> {{ $pageNote }} </small> </h4>
</div>

<div class="card">
	
	<div class="card-body">

	


<div class="toolbar-nav">
	<div class="row">
		<div class="col-md-4 ">
			<div class="input-group">
			     
			    </div>
		</div> 
		<div class="col-md-8 text-end"> 	
			<div class="btn-group">
				@if($access['is_add'] ==1)
				<a href="{{ url('core/users/create?return='.$return) }}" class="btn  btn-sm btn-info"  
					title="{{ __('core.btn_create') }}"> Add New </a>
				@endif 
				<div class="btn-group">
					<button type="button" class="btn  btn-sm btn-primary dropdown-toggle" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false"> Bulk Action </button>
			        <ul class="dropdown-menu">
			         @if($access['is_excel'] ==1)
						<li class="nav-item"><a href="{{ url( $pageModule .'/export?do=excel&return='.$return) }}" class="nav-link"> Export CSV </a></li>	
					@endif
					@if($access['is_add'] ==1)
						<li class="nav-item"><a href="{{ url($pageModule .'/import?return='.$return) }}" onclick="SximoModal(this.href, 'Import CSV'); return false;" class="nav-link"> Import CSV</a></li>
						<li class="nav-item"><a href="javascript://ajax" class=" nav-link copy " title="Copy" > Copy selected</a></li>
					@endif	
						<li class="nav-item"><a href="{{ url($pageModule) }}"  class="nav-link">Clear Search </a></li>
			          	<div class="dropdown-divider"></div>
			         
			          
			        </ul>
			    </div>   
			    
			</div>	
		</div>
		 
	</div>	  
</div>					
	<!-- End Toolbar Top -->

	<!-- Table Grid -->
	<div class="box box-info box-solid">
		<div class="box-header with-border" style="text-align:center; padding:5px">
			<h4 class="box-title" style="color:white"><b>Users List</b></h4>
		</div>
		<div class="table-responsive container-fluid"> 
			
 			<form action="{{ url('core/users?'.$return) }}" class="form-horizontal m-t" id="SximoTable" method="POST">
			
		    <table id="userlistTable" class="table table-striped  table-hover " id="{{ $pageModule }}Table">
		        <thead>
					<tr>
						<th style="width: 3% !important;" class="number"> No </th>
						
						@foreach ($tableGrid as $t)
							@if($t['view'] =='1')				
								<?php $limited = isset($t['limited']) ? $t['limited'] :''; 
								if(SiteHelpers::filterColumn($limited ))
								{
									$addClass='class="tbl-sorting" ';
									if($insort ==$t['field'])
									{
										$dir_order = ($inorder =='desc' ? 'sort-desc' : 'sort-asc'); 
										$addClass='class="tbl-sorting '.$dir_order.'" ';
									}
									echo '<th align="'.$t['align'].'" '.$addClass.' width="'.$t['width'].'">'.\SiteHelpers::activeLang($t['label'],(isset($t['language'])? $t['language'] : array())).'</th>';				
								} 
								?>
							@endif
						@endforeach
						<th  style="width: 10% !important;">{{ __('core.btn_action') }}</th>
					  </tr>
		        </thead>

		        <tbody>        						
		            
		              
		        </tbody>
		      
		    </table>
		    
			<input type="hidden" name="action_task" value="" />
			
			</form>
			</div>
		</div>	

			<!-- End Table Grid -->
		</div>
	</div>		
</div>


<script>
$(document).ready(function(){
	$('.copy').click(function() {
		var total = $('input[class="ids"]:checkbox:checked').length;
		if(confirm('are u sure Copy selected rows ?'))
		{
			$('input[name="action_task"]').val('copy');
			$('#SximoTable').submit();// do the rest here	
		}
	})	
	
	userlist();

});	

function userlist(){
    
	$('#userlistTable').DataTable({ 
		//   
		"processing": true, 
		"serverSide": true, 
		"order"     : [],
		"bDestroy"     : true,
		"lengthMenu": [50],
		"ajax": {
		   "url"    : "{!! url('core/users/data')!!}", 
		   "type"   : "POST",
		   "data": {
			  
			 },
		  
		 },
		 "columnDefs": [
			 {
				"targets": [0, 1, 7, 8], 
				"className": "text-center"
			 }               
		  ],
		   
	 });
  
}

function hapususer(id){
Swal.fire({
  title: 'Are you sure?',
  text: "You won't be able to revert this!",
  icon: 'warning',
  showCancelButton: true,
  confirmButtonColor: '#3085d6',
  cancelButtonColor: '#d33',
  confirmButtonText: 'Yes, delete it!'
}).then((result) => {
  if (result.isConfirmed) {
	deluser(id);
  }
})

}

function deluser(id){
var data = [];
data.push({name: 'user_id', value: id});
$.ajax({
	url   : "{!! url('core/users/deluser')!!}",
	type  : 'POST',
	data  : data,
	success: function(respon)
	{
	  var obj = tryjson(respon);
	  if(obj.success === false){
		  notyMessageError(obj.message);	
			  return false;
	  } else{
		  userlist();
	  }
	}
});
}

</script>	
	
@stop
