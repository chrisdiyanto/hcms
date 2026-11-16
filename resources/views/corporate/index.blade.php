@extends('layouts.app')

@section('content')
<?php usort($tableGrid, "SiteHelpers::_sort"); ?>
<div class="page-titles">
  <h2> {{ $pageTitle }} <small> {{ $pageNote }} </small></h2>
</div>


	<div id="corporateView"></div>	
	<div id="corporateGrid">
		<div class="card">
			<div class="card-body">
			@include( $pageModule.'/toolbar')
		
		
			<div class="box box-info box-solid">
				<div class="box-header with-border" style="text-align:center; padding:5px">
					<h4 class="box-title" style="color:white"><b>Corporate</b></h4>
				</div>
				<div class="table-responsive container-fluid">		
					<table id="corporateTable" class="display table  table-striped table-hover" cellspacing="0" width="100%">
						<thead>
							<tr>
								<th>ID</th>	
								
							@if($setting['view-method'] =='expand')<th></th>@endif
						

							
						<?php foreach ($tableGrid as $t) :
							if($t['view'] =='1'):
								$limited = isset($t['limited']) ? $t['limited'] :'';
								if(SiteHelpers::filterColumn($limited ))
								{
									echo '<th align="'.$t['align'].'" width="'.$t['width'].'">'.\SiteHelpers::activeLang($t['label'],(isset($t['language'])? $t['language'] : array())).'</th>';				
								} 
							endif;
						endforeach; ?>
								<th width="30" class="text-end" > Action </th>
							</tr>

						</thead>	
							
					</table>
				</div>
			</div>	 
		</div>			
	</div>	
	</div>
	
</div>

<script type="text/javascript">

	$(document).ready(function() {
		$('.tips').tooltip();
		$('.dataselect').select2();
		var rows_selected = []; 			
	   	var table = $('#corporateTable').DataTable( {
	        "processing": true,
	       
	        "serverSide": true,
	        // "lengthMenu": [[10, 25, 50, -1], [10, 25, 50, "All"]],
	        "ajax": {
	            "url": "{!! url('corporate')!!}",
	            "type": "POST"
	             
        	},
        	"columnDefs": [{ 
        		"targets": [0],
                "visible": false,
                orderable: false,
            	className: 'select-checkbox'
        	}],
        	"columns": [<?php echo $column;?>],
        	'order': [[1, 'asc']],
        	<?php if($access['is_excel'] ==1 ) { ?>
        	dom: 'Bfltip',
        	buttons: [
            	'copy', 'csv', 'excel', 'pdf', 'print'
        	]
        	<?php } ?>
        	
	    });

	   	<?php if($setting['view-method'] =='expand'): ?>
		$('#corporateTable tbody').on('click', 'td.details-control', function () {
	        var tr = $(this).closest('tr');
	        var row = table.row( tr );
	        var id = row.data().rowId;

	        if ( row.child.isShown() ) {
	        	 row.child.hide();
            	tr.removeClass('shown');
	        }
	        else {
	            // Open this row
	            row.child.show();	            
	            row.child( expand_child(id) ).show();
	            tr.addClass('shown');
	            $.get('{{ url("corporate/")}}/'+id , function(callback){
	            	$('#'+id).html(callback);
	            	$('#'+id).addClass('data');
	            })
		        
	            
	        }
    	});
    	<?php endif; ?>
		$('.dosearch').keyup(function( e ){
			if (e.keyCode === 13) {
				val = $(this).val();
				table.search(val ).draw();
			}
		})  

	    $('#corporateTable').Sdtable({
	    	tableId : '#corporate',
	    	table   : table,
	    	action  : '{{ url("corporate")}}' 
	    });

	   
	});


    function expand_child( id )
    {
    	return '<div id="'+ id+'"><p class="text-center"> Loading Content .. Please wait</p></div>';	
    }

</script>


@endsection