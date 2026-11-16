@if($setting['view-method'] =='native')
<div class="card">
	<div class="card-body">	
		<div class="toolbar-nav">
			<div class="row">
				
				<div class="col-md-6  text-start" >
			   		<a href="{{ ($prevnext['prev'] != '' ? url('corporate/'.$prevnext['prev'].'?return='.$return ) : '#') }}" class="tips btn btn-sm btn-primary" onclick="ajaxViewDetail('#corporate',this.href); return false; "><i class="fa fa-arrow-left"></i>  </a>	
					<a href="{{ ($prevnext['next'] != '' ? url('corporate/'.$prevnext['next'].'?return='.$return ) : '#') }}" class="tips btn btn-sm btn-primary" onclick="ajaxViewDetail('#corporate',this.href); return false; "> <i class="fa fa-arrow-right"></i>  </a>					
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
						<td width='30%' class='label-view text-end'>{{ SiteHelpers::activeLang('No.', (isset($fields['id_corporate']['language'])? $fields['id_corporate']['language'] : array())) }}</td>
						<td>{{ $row->id_corporate}} </td>
						
					</tr>
				
					<tr>
						<td width='30%' class='label-view text-end'>{{ SiteHelpers::activeLang('Corporate Name', (isset($fields['corporate_name']['language'])? $fields['corporate_name']['language'] : array())) }}</td>
						<td>{{ $row->corporate_name}} </td>
						
					</tr>
				
					<tr>
						<td width='30%' class='label-view text-end'>{{ SiteHelpers::activeLang('Code', (isset($fields['code']['language'])? $fields['code']['language'] : array())) }}</td>
						<td>{{ $row->code}} </td>
						
					</tr>
				
					<tr>
						<td width='30%' class='label-view text-end'>{{ SiteHelpers::activeLang('Address', (isset($fields['corporate_address']['language'])? $fields['corporate_address']['language'] : array())) }}</td>
						<td>{{ $row->corporate_address}} </td>
						
					</tr>
				
			</tbody>	
		</table>  
			
		 	
		 
@if($setting['view-method'] =='native')
	</div>
</div>
@endif		

@include('sximo.module.template.ajax.viewjavascript')