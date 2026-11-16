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
						<td width='30%' class='label-view text-end'>{{ SiteHelpers::activeLang('Id Unit Type', (isset($fields['id_unit_type']['language'])? $fields['id_unit_type']['language'] : array())) }}</td>
						<td>{{ $row->id_unit_type}} </td>
						
					</tr>
				
					<tr>
						<td width='30%' class='label-view text-end'>{{ SiteHelpers::activeLang('Unit Type', (isset($fields['unit_type']['language'])? $fields['unit_type']['language'] : array())) }}</td>
						<td>{{ $row->unit_type}} </td>
						
					</tr>
				
					<tr>
						<td width='30%' class='label-view text-end'>{{ SiteHelpers::activeLang('Keterangan', (isset($fields['keterangan']['language'])? $fields['keterangan']['language'] : array())) }}</td>
						<td>{{ $row->keterangan}} </td>
						
					</tr>
				
					<tr>
						<td width='30%' class='label-view text-end'>{{ SiteHelpers::activeLang('Status', (isset($fields['status']['language'])? $fields['status']['language'] : array())) }}</td>
						<td>{{ $row->status}} </td>
						
					</tr>
						
					<tr>
						<td width='30%' class='label-view text-end'></td>
						<td> <a href="javascript:history.go(-1)" class="btn btn-primary"> Back To Grid <a> </td>
						
					</tr>					
				
			</tbody>	
		</table>   

	 
	
	</div>
</div>	