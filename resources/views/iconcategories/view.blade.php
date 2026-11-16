@if($setting['view-method'] =='native')
<div class="card">
	<div class="card-body">	
		<div class="toolbar-nav">
			<div class="row">
				
				<div class="col-md-6  text-start" >
			   		<a href="{{ ($prevnext['prev'] != '' ? url('iconcategories/'.$prevnext['prev'].'?return='.$return ) : '#') }}" class="tips btn btn-sm btn-primary" onclick="ajaxViewDetail('#iconcategories',this.href); return false; "><i class="fa fa-arrow-left"></i>  </a>	
					<a href="{{ ($prevnext['next'] != '' ? url('iconcategories/'.$prevnext['next'].'?return='.$return ) : '#') }}" class="tips btn btn-sm btn-primary" onclick="ajaxViewDetail('#iconcategories',this.href); return false; "> <i class="fa fa-arrow-right"></i>  </a>					
				</div>	
				<div class="col-md-6 text-end" >
					<a href="javascript://ajax" onclick="ajaxViewClose('#{{ $pageModule }}')" class="tips btn btn-sm btn-danger  " title="{{ __('core.btn_back') }}"><i class="fa  fa-times"></i></a>		
				</div>
				

				
			</div>
		</div>
	
@endif	


		<table class="table  table-bordered" >
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
				
			</tbody>	
		</table>  
			
		 	
		 
@if($setting['view-method'] =='native')
	</div>
</div>
@endif		

@include('sximo.module.template.ajax.viewjavascript')