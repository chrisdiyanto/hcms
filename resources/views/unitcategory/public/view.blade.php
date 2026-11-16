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
						<td width='30%' class='label-view text-end'>{{ SiteHelpers::activeLang('Id Unit Category', (isset($fields['id_unit_category']['language'])? $fields['id_unit_category']['language'] : array())) }}</td>
						<td>{{ $row->id_unit_category}} </td>
						
					</tr>
				
					<tr>
						<td width='30%' class='label-view text-end'>{{ SiteHelpers::activeLang('Slug Unit Category', (isset($fields['slug_unit_category']['language'])? $fields['slug_unit_category']['language'] : array())) }}</td>
						<td>{{ $row->slug_unit_category}} </td>
						
					</tr>
				
					<tr>
						<td width='30%' class='label-view text-end'>{{ SiteHelpers::activeLang('Unit Category', (isset($fields['unit_category']['language'])? $fields['unit_category']['language'] : array())) }}</td>
						<td>{{ $row->unit_category}} </td>
						
					</tr>
				
					<tr>
						<td width='30%' class='label-view text-end'>{{ SiteHelpers::activeLang('Unit Category Code', (isset($fields['unit_category_code']['language'])? $fields['unit_category_code']['language'] : array())) }}</td>
						<td>{{ $row->unit_category_code}} </td>
						
					</tr>
				
					<tr>
						<td width='30%' class='label-view text-end'>{{ SiteHelpers::activeLang('Unit Category Explan', (isset($fields['unit_category_explan']['language'])? $fields['unit_category_explan']['language'] : array())) }}</td>
						<td>{{ $row->unit_category_explan}} </td>
						
					</tr>
						
					<tr>
						<td width='30%' class='label-view text-end'></td>
						<td> <a href="javascript:history.go(-1)" class="btn btn-primary"> Back To Grid <a> </td>
						
					</tr>					
				
			</tbody>	
		</table>   

	 
	
	</div>
</div>	