<div class="m-t" style="padding-top:25px;">	
    <div class="row m-b-lg animated fadeInDown delayp1 text-center">
        <h3> {{ $pageTitle }} <small> {{ $pageNote }} </small></h3>
        <hr />       
    </div>
</div>
<div class="m-t">
	<div class="table-responsive" > 	

		<table class="table table-striped table-bordered" >
			<tbody>	
		
			
					<tr>
						<td width='30%' class='label-view text-end'>Name</td>
						<td>{{ $row->name}} </td>
						
					</tr>
				
					<tr>
						<td width='30%' class='label-view text-end'>Method</td>
						<td>{{ $row->method}} </td>
						
					</tr>
				
					<tr>
						<td width='30%' class='label-view text-end'>Tablename</td>
						<td>{{ $row->tablename}} </td>
						
					</tr>
				
					<tr>
						<td width='30%' class='label-view text-end'>Success</td>
						<td>{{ $row->success}} </td>
						
					</tr>
				
					<tr>
						<td width='30%' class='label-view text-end'>Failed</td>
						<td>{{ $row->failed}} </td>
						
					</tr>
				
					<tr>
						<td width='30%' class='label-view text-end'>Redirect</td>
						<td>{{ $row->redirect}} </td>
						
					</tr>
						
					<tr>
						<td width='30%' class='label-view text-end'></td>
						<td> <a href="javascript:history.go(-1)" class="btn btn-primary"> Back To Grid <a> </td>
						
					</tr>					
				
			</tbody>	
		</table>   

	 
	
	</div>
</div>	