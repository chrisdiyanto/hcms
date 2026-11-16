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
						<td width='30%' class='label-view text-end'>{{ SiteHelpers::activeLang('Id Corporate', (isset($fields['id_corporate']['language'])? $fields['id_corporate']['language'] : array())) }}</td>
						<td>{{ $row->id_corporate}} </td>
						
					</tr>
				
					<tr>
						<td width='30%' class='label-view text-end'>{{ SiteHelpers::activeLang('Corporate Name', (isset($fields['corporate_name']['language'])? $fields['corporate_name']['language'] : array())) }}</td>
						<td>{{ $row->corporate_name}} </td>
						
					</tr>
				
					<tr>
						<td width='30%' class='label-view text-end'>{{ SiteHelpers::activeLang('Address', (isset($fields['corporate_address']['language'])? $fields['corporate_address']['language'] : array())) }}</td>
						<td>{{ $row->corporate_address}} </td>
						
					</tr>
						
					<tr>
						<td width='30%' class='label-view text-end'></td>
						<td> <a href="javascript:history.go(-1)" class="btn btn-primary"> Back To Grid <a> </td>
						
					</tr>					
				
			</tbody>	
		</table>   

	 
	
	</div>
</div>	