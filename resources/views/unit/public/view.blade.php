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
						<td width='30%' class='label-view text-end'>{{ SiteHelpers::activeLang('Id Unit', (isset($fields['id_unit']['language'])? $fields['id_unit']['language'] : array())) }}</td>
						<td>{{ $row->id_unit}} </td>
						
					</tr>
				
					<tr>
						<td width='30%' class='label-view text-end'>{{ SiteHelpers::activeLang('Slug Unit', (isset($fields['slug_unit']['language'])? $fields['slug_unit']['language'] : array())) }}</td>
						<td>{{ $row->slug_unit}} </td>
						
					</tr>
				
					<tr>
						<td width='30%' class='label-view text-end'>{{ SiteHelpers::activeLang('Parent Id', (isset($fields['parent_id']['language'])? $fields['parent_id']['language'] : array())) }}</td>
						<td>{{ $row->parent_id}} </td>
						
					</tr>
				
					<tr>
						<td width='30%' class='label-view text-end'>{{ SiteHelpers::activeLang('Urutan', (isset($fields['urutan']['language'])? $fields['urutan']['language'] : array())) }}</td>
						<td>{{ $row->urutan}} </td>
						
					</tr>
				
					<tr>
						<td width='30%' class='label-view text-end'>{{ SiteHelpers::activeLang('Unit Name', (isset($fields['unit_name']['language'])? $fields['unit_name']['language'] : array())) }}</td>
						<td>{{ $row->unit_name}} </td>
						
					</tr>
				
					<tr>
						<td width='30%' class='label-view text-end'>{{ SiteHelpers::activeLang('Unit Code', (isset($fields['unit_code']['language'])? $fields['unit_code']['language'] : array())) }}</td>
						<td>{{ $row->unit_code}} </td>
						
					</tr>
				
					<tr>
						<td width='30%' class='label-view text-end'>{{ SiteHelpers::activeLang('Id Unit Category', (isset($fields['id_unit_category']['language'])? $fields['id_unit_category']['language'] : array())) }}</td>
						<td>{{ $row->id_unit_category}} </td>
						
					</tr>
				
					<tr>
						<td width='30%' class='label-view text-end'>{{ SiteHelpers::activeLang('Id Corporate', (isset($fields['id_corporate']['language'])? $fields['id_corporate']['language'] : array())) }}</td>
						<td>{{ $row->id_corporate}} </td>
						
					</tr>
				
					<tr>
						<td width='30%' class='label-view text-end'>{{ SiteHelpers::activeLang('Unit Addres', (isset($fields['unit_addres']['language'])? $fields['unit_addres']['language'] : array())) }}</td>
						<td>{{ $row->unit_addres}} </td>
						
					</tr>
				
					<tr>
						<td width='30%' class='label-view text-end'>{{ SiteHelpers::activeLang('Unit Explan', (isset($fields['unit_explan']['language'])? $fields['unit_explan']['language'] : array())) }}</td>
						<td>{{ $row->unit_explan}} </td>
						
					</tr>
				
					<tr>
						<td width='30%' class='label-view text-end'>{{ SiteHelpers::activeLang('Id Unit Type', (isset($fields['id_unit_type']['language'])? $fields['id_unit_type']['language'] : array())) }}</td>
						<td>{{ $row->id_unit_type}} </td>
						
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