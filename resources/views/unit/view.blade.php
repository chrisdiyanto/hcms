@if($setting['view-method'] =='native')
<div class="card">
	<div class="card-body">	
		<div class="toolbar-nav">
			<div class="row">
				
				<div class="col-md-6  text-start" >
			   		<a href="{{ ($prevnext['prev'] != '' ? url('unit/'.$prevnext['prev'].'?return='.$return ) : '#') }}" class="tips btn btn-sm btn-primary" onclick="ajaxViewDetail('#unit',this.href); return false; "><i class="fa fa-arrow-left"></i>  </a>	
					<a href="{{ ($prevnext['next'] != '' ? url('unit/'.$prevnext['next'].'?return='.$return ) : '#') }}" class="tips btn btn-sm btn-primary" onclick="ajaxViewDetail('#unit',this.href); return false; "> <i class="fa fa-arrow-right"></i>  </a>					
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
				
			</tbody>	
		</table>  
			
		 	
		 
@if($setting['view-method'] =='native')
	</div>
</div>
@endif		

@include('sximo.module.template.ajax.viewjavascript')