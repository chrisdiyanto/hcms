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
						<td width='30%' class='label-view text-end'>{{ SiteHelpers::activeLang('Id', (isset($fields['id']['language'])? $fields['id']['language'] : array())) }}</td>
						<td>{{ $row->id}} </td>
						
					</tr>
				
					<tr>
						<td width='30%' class='label-view text-end'>{{ SiteHelpers::activeLang('Name', (isset($fields['name']['language'])? $fields['name']['language'] : array())) }}</td>
						<td>{{ $row->name}} </td>
						
					</tr>
				
					<tr>
						<td width='30%' class='label-view text-end'>{{ SiteHelpers::activeLang('Type', (isset($fields['type']['language'])? $fields['type']['language'] : array())) }}</td>
						<td>{{ $row->type}} </td>
						
					</tr>
				
					<tr>
						<td width='30%' class='label-view text-end'>{{ SiteHelpers::activeLang('Svg Code', (isset($fields['svg_code']['language'])? $fields['svg_code']['language'] : array())) }}</td>
						<td>{{ $row->svg_code}} </td>
						
					</tr>
				
					<tr>
						<td width='30%' class='label-view text-end'>{{ SiteHelpers::activeLang('File Path', (isset($fields['file_path']['language'])? $fields['file_path']['language'] : array())) }}</td>
						<td>{{ $row->file_path}} </td>
						
					</tr>
				
					<tr>
						<td width='30%' class='label-view text-end'>{{ SiteHelpers::activeLang('Icon Url', (isset($fields['icon_url']['language'])? $fields['icon_url']['language'] : array())) }}</td>
						<td>{{ $row->icon_url}} </td>
						
					</tr>
				
					<tr>
						<td width='30%' class='label-view text-end'>{{ SiteHelpers::activeLang('Icon Class', (isset($fields['icon_class']['language'])? $fields['icon_class']['language'] : array())) }}</td>
						<td>{{ $row->icon_class}} </td>
						
					</tr>
				
					<tr>
						<td width='30%' class='label-view text-end'>{{ SiteHelpers::activeLang('Category', (isset($fields['category']['language'])? $fields['category']['language'] : array())) }}</td>
						<td>{{ $row->category}} </td>
						
					</tr>
				
					<tr>
						<td width='30%' class='label-view text-end'>{{ SiteHelpers::activeLang('Category Id', (isset($fields['category_id']['language'])? $fields['category_id']['language'] : array())) }}</td>
						<td>{{ $row->category_id}} </td>
						
					</tr>
				
					<tr>
						<td width='30%' class='label-view text-end'>{{ SiteHelpers::activeLang('Created At', (isset($fields['created_at']['language'])? $fields['created_at']['language'] : array())) }}</td>
						<td>{{ $row->created_at}} </td>
						
					</tr>
				
					<tr>
						<td width='30%' class='label-view text-end'>{{ SiteHelpers::activeLang('Updated At', (isset($fields['updated_at']['language'])? $fields['updated_at']['language'] : array())) }}</td>
						<td>{{ $row->updated_at}} </td>
						
					</tr>
						
					<tr>
						<td width='30%' class='label-view text-end'></td>
						<td> <a href="javascript:history.go(-1)" class="btn btn-primary"> Back To Grid <a> </td>
						
					</tr>					
				
			</tbody>	
		</table>   

	 
	
	</div>
</div>	