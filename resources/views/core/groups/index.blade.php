@extends('layouts.app')

@section('content')
{{--*/ usort($tableGrid, "SiteHelpers::_sort") /*--}}
<div class="page-titles">
  <h2> {{ $pageTitle }} <small> {{ $pageNote }} </small></h2>
</div>
<div  class="card">
	<div class="card-body">


		<div class="toolbar-nav">
			<div class="row">
				<div class="col-md-4 ">
					 
				</div>
				<div class="col-md-8 text-end"> 	
					<div class="btn-group">
						@if($access['is_add'] ==1)
						<a href="{{ url('core/groups/create?return='.$return) }}" class="btn  btn-sm btn-info"  
							title="{{ __('core.btn_create') }}"><i class=" fa fa-plus "></i> Create New </a>
						@endif 

						<div class="btn-group">
							<button type="button" class="btn  btn-sm btn-primary dropdown-toggle" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false"><i class="icon-menu5"></i> Bulk Action </button>
					        <ul class="dropdown-menu">
					        
								<li  class="nav-item"><a href="{{ url($pageModule) }}"  class="nav-link tips" > Clear Search </a></li>
					         @if($access['is_remove'] ==1)
								 <li class="nav-item"><a href="javascript://ajax"  onclick="SximoDelete();" class="nav-link tips" title="{{ __('core.btn_remove') }}"> Remove Selected </a></li>
							@endif 
					          
					        </ul>
					    </div>  
				     </div>
				</div>
				    
			</div>	
		</div>					
			<!-- End Toolbar Top -->

			<!-- Table Grid --><form action="{{ url('core/groups?'.$return) }}" class="form-horizontal m-t" id="SximoTable" method="POST">
			<div class="box box-info box-solid">
				<div class="box-header with-border" style="text-align:center; padding:5px">
					<h4 class="box-title" style="color:white"><b>Groups List</b></h4>
				</div>
				<div class=" table-responsive" >
				
				<form action="{{ url('core/groups?'.$return) }}" class="form-horizontal m-t" id="SximoTable" method="POST">
				
					<table id="grouplistTable" class="table  table-hover table-striped" id="{{ $pageModule }}Table">
						<thead>
							<tr>
								
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
	
	grouplist();

});	

function grouplist(){
    
	  $('#grouplistTable').DataTable({ 
          //   
          "processing": true, 
          "serverSide": true, 
          "order"     : [],
          "bDestroy"     : true,
		  "lengthMenu": [50],
		  "ajax": {
             "url"    : "{!! url('core/groups/data')!!}", 
             "type"   : "POST",
			 "data": {
				
               },
			
           },
           "columnDefs": [
               {
                  "targets": [0, 3, 4], 
                  "className": "text-center"
               }               
            ],
			 
       });
    
}

function hapusgroup(id){
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
      delgroup(id);
    }
  })

}

function delgroup(id){
  var data = [];
  data.push({name: 'group_id', value: id});
  $.ajax({
      url   : "{!! url('core/groups/delgroup')!!}",
      type  : 'POST',
      data  : data,
      success: function(respon)
      {
        var obj = tryjson(respon);
        if(obj.success === false){
            notyMessageError(obj.message);	
                return false;
        } else{
			grouplist();
        }
      }
  });
}
</script>	
	
@stop