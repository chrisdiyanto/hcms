@extends('layouts.app')

@section('content')
<div class="page-header ">
	<h2> {{ $pageTitle }} <small> {{ $pageNote }} </small></h2>
</div>
	<div class="toolbar-nav">

		<div class="row">
			<div class="col-md-6">
				
			</div>	
			<div class="col-md-6 text-end" >
				<a href="{{ url('core/users?return='.$return) }}" class="tips btn btn-sm "  title="{{ Lang::get('core.btn_back') }}" ><i class="fa  fa-times"></i></a> 		
			</div>

			
		</div>
</div>

<div class="card">
	<div class="card-body">
		<div class="row">
			<div class="col-md-6">
				<fieldset>
					<legend> Account Credit </legend>

					@php
						$corpName = '-';
						if(!empty($row->id_corporate)) {
							$cor = \DB::table('corporate')->where('id_corporate', $row->id_corporate)->first();
							$corpName = $cor->corporate_name ?? '-';
						} elseif(session('cid')) {
							$cor = \DB::table('corporate')->where('id_corporate', session('cid'))->first();
							$corpName = $cor->corporate_name ?? '-';
						}
					@endphp

					@if(is_allowed('allcorporate')['is_detail'] ?? false)
						<div class="form-group">
							<label for="Corporate" class="control-label fw-bold mb-1"> Corporate </label>
							<input type="text" class="form-control form-control-sm bg-light" value="{{ $corpName }}" readonly disabled>
						</div>
					@endif

					<div class="form-group">
						<label for="Group / Level" class="control-label fw-bold mb-1"> Group / Level </label>
						<div>
							@if(isset($user_groups) && $user_groups->count() > 0)
								@foreach($user_groups as $ug)
									<span class="badge badge-info me-1 mb-1 p-2" style="font-size: 12px; font-weight: 500;">
										<i class="fa fa-users me-1"></i> {{ $ug->name }} (ID: {{ $ug->group_id }})
									</span>
								@endforeach
							@else
								<span class="badge bg-secondary p-2">{{ SiteHelpers::gridDisplayView($row->group_id,'group_id','1:tb_groups:group_id:name') }}</span>
							@endif
						</div>
					</div>

					<div class="form-group">
						<label for="nik" class="control-label fw-bold mb-1"> NIP </label>
						<input type="text" class="form-control form-control-sm bg-light" value="{{ $row->nik ?? '-' }}" readonly disabled>
					</div>

					<div class="form-group">
						<label for="hp" class="control-label fw-bold mb-1"> No Whatsapp </label>
						<input type="text" class="form-control form-control-sm bg-light" value="{{ $row->hp ?? '-' }}" readonly disabled>
					</div>

					<div class="form-group">
						<label for="username" class="control-label fw-bold mb-1"> Username </label>
						<input type="text" class="form-control form-control-sm bg-light" value="{{ $row->username }}" readonly disabled>
					</div>

					<div class="row">
						<div class="col-md-6">
							<div class="form-group">
								<label for="First Name" class="control-label fw-bold mb-1"> First Name </label>
								<input type="text" class="form-control form-control-sm bg-light" value="{{ $row->first_name }}" readonly disabled>
							</div>
						</div>
						<div class="col-md-6">
							<div class="form-group">
								<label for="Last Name" class="control-label fw-bold mb-1"> Last Name </label>
								<input type="text" class="form-control form-control-sm bg-light" value="{{ $row->last_name ?? '-' }}" readonly disabled>
							</div>
						</div>
					</div>

					<div class="form-group">
						<label for="Email" class="control-label fw-bold mb-1"> Email </label>
						<input type="email" class="form-control form-control-sm bg-light" value="{{ $row->email }}" readonly disabled>
					</div>

					<div class="form-group">
						<label for="Status" class="control-label fw-bold mb-1"> Status </label>
						<div>
							@if($row->active == '1')
								<span class="badge badge-success p-2" style="font-size: 11.5px;"><i class="fa fa-check-circle me-1"></i> Active</span>
							@elseif($row->active == '2')
								<span class="badge bg-dark p-2" style="font-size: 11.5px;"><i class="fa fa-ban me-1"></i> Banned</span>
							@else
								<span class="badge badge-danger p-2" style="font-size: 11.5px;"><i class="fa fa-times-circle me-1"></i> Inactive</span>
							@endif
						</div>
					</div>

					<div class="form-group">
						<label for="Avatar" class="control-label fw-bold mb-1"> Avatar </label>
						<div>
							@if(file_exists('./uploads/users/'.$row->avatar) && !empty($row->avatar))
								<img src="{{ URL::to('uploads/users/'.$row->avatar) }}" class="rounded-circle border shadow-sm" width="65" height="65" style="object-fit: cover;" alt="Avatar" />
							@else
								<img src="http://www.gravatar.com/avatar/{{ md5($row->email) }}?s=65&d=mm" class="rounded-circle border shadow-sm" width="65" height="65" alt="Avatar" />
							@endif
						</div>
					</div>
				</fieldset>
			</div>

			<div class="col-md-6">
				<fieldset>
					<div class="form-group">
						<label class="control-label fw-bold mb-2">Unit & Jabatan</label>
						<div class="table-responsive border rounded bg-white mb-2" style="box-shadow: 0 1px 3px rgba(0,0,0,0.04);">
							<table class="table table-sm table-bordered table-hover mb-0" id="table-user-units" style="font-size: 12px; vertical-align: middle;">
								<thead class="bg-light text-dark fw-bold" style="font-size: 11.5px;">
									<tr>
										<th style="width: 55%; padding: 7px 10px;">Unit / Jabatan</th>
										<th style="width: 22%; padding: 7px 8px;">Mulai</th>
										<th style="width: 23%; padding: 7px 8px;">Status</th>
									</tr>
								</thead>
								<tbody>
									@if(isset($user_units) && $user_units->count() > 0)
										@foreach($user_units as $idx => $uu)
										@php
											$hasEndDate = !empty($uu->end_date) && !is_zero_date($uu->end_date);
											$dispStart = !empty($uu->start_date) && !is_zero_date($uu->start_date) ? date('d/m/Y', strtotime($uu->start_date)) : '-';
											$dispEnd = $hasEndDate ? date('d/m/Y', strtotime($uu->end_date)) : '';
										@endphp
										<tr class="{{ $hasEndDate ? 'table-secondary-row text-muted' : '' }}">
											<td style="padding: 7px 10px; vertical-align: middle;">
												<div class="fw-bold disp-unit-name text-dark" style="font-size: 13px; line-height: 1.25;">
													{{ $uu->unit_name ?? '-' }}
												</div>
												<div class="disp-pos-name text-muted" style="font-size: 11px; margin-top: 2px;">
													<i class="fa fa-id-badge text-secondary me-1" style="font-size: 10px;"></i>{{ $uu->position_name ?? '-' }}
												</div>
											</td>
											<td style="padding: 7px 8px; vertical-align: middle; white-space: nowrap;">
												<span class="fw-normal">{{ $dispStart }}</span>
											</td>
											<td style="padding: 7px 8px; vertical-align: middle; white-space: nowrap;">
												@if($hasEndDate)
													<span class="badge bg-secondary" style="font-size: 10px;" title="Selesai: {{ $dispEnd }}">Selesai ({{ $dispEnd }})</span>
												@else
													<span class="badge badge-success" style="font-size: 10px;">Aktif</span>
												@endif
											</td>
										</tr>
										@endforeach
									@else
										<tr id="empty-unit-row">
											<td colspan="3" class="text-center text-muted py-3" style="font-size: 11.5px;">
												<i class="fa fa-info-circle me-1"></i> Belum ada unit & jabatan.
											</td>
										</tr>
									@endif
								</tbody>
							</table>
						</div>
					</div>
				</fieldset>

				<fieldset>
					<legend> Account Activity </legend>
					<div class="form-group">
						<label class="control-label fw-bold mb-1"> Created At </label>
						<input type="text" class="form-control form-control-sm bg-light" value="{{ $row->created_at ?? '-' }}" readonly disabled>
					</div>

					<div class="form-group">
						<label class="control-label fw-bold mb-1"> Last Login </label>
						<input type="text" class="form-control form-control-sm bg-light" value="{{ $row->last_login ?? '-' }}" readonly disabled>
					</div>

					<div class="form-group">
						<label class="control-label fw-bold mb-1"> Updated At </label>
						<input type="text" class="form-control form-control-sm bg-light" value="{{ $row->updated_at ?? '-' }}" readonly disabled>
					</div>
				</fieldset>

				<div class="form-group text-end mt-4">
					<a href="{{ url('core/users?return='.$return) }}" class="btn btn-warning btn-sm">
						<i class="fa fa-arrow-circle-left me-1"></i> {{ Lang::get('core.sb_cancel') }}
					</a>
					<a href="{{ url('core/users/'.$row->id.'/edit?return='.$return) }}" class="btn btn-primary btn-sm ms-1">
						<i class="fa fa-pencil me-1"></i> {{ Lang::get('core.btn_edit') }}
					</a>
				</div>
			</div>
		</div>
	</div>
</div>

<style>
#table-user-units {
	border-collapse: separate;
	border-spacing: 0;
}
#table-user-units th {
	background-color: #f8fafc;
	color: #475569;
	font-weight: 600;
	border-bottom: 2px solid #e2e8f0;
}
#table-user-units td {
	border-color: #e2e8f0;
}
#table-user-units tr.table-secondary-row {
	background-color: rgba(148, 163, 184, 0.12) !important;
}
body.dark-theme #table-user-units {
	background-color: #141822;
}
body.dark-theme #table-user-units th {
	background-color: #1a2230;
	color: #94a3b8;
	border-color: #263244;
}
body.dark-theme #table-user-units td {
	border-color: #263244;
}
body.dark-theme #table-user-units tr.table-secondary-row {
	background-color: rgba(255, 255, 255, 0.04) !important;
}
</style>

	  
@stop