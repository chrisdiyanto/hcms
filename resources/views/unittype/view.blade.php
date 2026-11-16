@if($setting['view-method'] =='native')
<div class="card">
	<div class="card-body">	
		<div class="toolbar-nav">
			<div class="row">
				
				<div class="col-md-6  text-start" >
			   		<a href="{{ ($prevnext['prev'] != '' ? url('unittype/'.$prevnext['prev'].'?return='.$return ) : '#') }}" class="tips btn btn-sm btn-primary" onclick="ajaxViewDetail('#unittype',this.href); return false; "><i class="fa fa-arrow-left"></i>  </a>	
					<a href="{{ ($prevnext['next'] != '' ? url('unittype/'.$prevnext['next'].'?return='.$return ) : '#') }}" class="tips btn btn-sm btn-primary" onclick="ajaxViewDetail('#unittype',this.href); return false; "> <i class="fa fa-arrow-right"></i>  </a>					
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
				
			</tbody>	
		</table>  
			
		 	
		 
@if($setting['view-method'] =='native')
	</div>
</div>
@endif		

@include('sximo.module.template.ajax.viewjavascript')