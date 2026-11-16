@extends('layouts.app')

@section('content')
<div class="page-titles">
  <h2> {{ $pageTitle }} <small> {{ $pageNote }} </small></h2>
</div>
<div  class="card">
	<div class="card-body">

		<ul class="parsley-error-list">
			@foreach($errors->all() as $error)
				<li>{{ $error }}</li>
			@endforeach
		</ul>	

		<form action="{{ url('core/users?return='.$return) }}" class="form-vertical sximo-form validated" method="POST" enctype="multipart/form-data">
		
		<div class="row">
		<div class="col-md-6">
			<fieldset>
				<legend> Account Credit </legend>
					
					
				  <div class="form-group hidethis " style="display:none;">
					<label for="Id" class=" control-label "> Id </label>
					<div class="">
					<input type="text" name="id" value="{{ $row['id'] }}" class="form-control form-control-sm" placeholder="">
					 </div> 
					 
				  </div> 					
				  
				  <?php if(is_allowed('allcorporate')['is_detail'] ?? false):?>
					<div class="form-group  " >
						<label for="Corporate" class=" control-label "> Corporate <span class="asterix"> * </span></label>
						<div class="">
							<select name='id_corporate' id='id_corporate' onchange="reloadAllUnits()" class="select2 form-control form-control-sm">
								<option value="">Corporate</option>
								<?php foreach(db_get_all_data('corporate') as $cor):?>
									<option value="<?= $cor->id_corporate;?>" <?= (session('cid') == $cor->id_corporate) ? 'selected' : '' ?>><?= $cor->corporate_name ?></option>
								<?php endforeach; ?>
							</select>
						</div> 
					</div>
				  <?php else:?>
					<input type="hidden" name='id_corporate' id='id_corporate' value="<?= session('cid');?>">
				  <?php endif;?>
				  
				  <div class="form-group  " >
					<label for="Group / Level" class=" control-label "> Group / Level <span class="asterix"> * </span></label>
					<div class="">
					  <select name='group_id[]' rows='5' id='group_id' code='{$group_id}' class='select2 form-control form-control-sm' multiple required  ></select> 
					 
					  @if(isset($user_groups) && $user_groups->count() > 0)
					      <div class="mt-2 p-2 border rounded bg-light">
					          <small class="text-muted d-block mb-1"><strong>Group User Saat Ini:</strong></small>
					          @foreach($user_groups as $ug)
					              <span class="badge badge-info me-1 mb-1 p-2" style="font-size: 12px; font-weight: 500;">
					                  <i class="fa fa-users me-1"></i> {{ $ug->name }} (ID: {{ $ug->group_id }})
					              </span>
					          @endforeach
					      </div>
					  @endif
					 </div> 
					
				  </div>

				  <div class="form-group  " >
					<label for="nik" class=" control-label "> NIP <span class="asterix"> * </span></label>
					<div class="">
					  <input type="text" name="nik" id="nik" value="{{ $row['nik'] ?? '' }}" class="form-control form-control-sm" placeholder="" required>
					 </div> 
				  </div> 

				  <div class="form-group  " >
					<label for="hp" class=" control-label "> No Whatsapp <span class="asterix"> * </span></label>
					<div class="">
					  <input type="text" name="hp" value="{{ $row['hp'] ?? '' }}" class="form-control form-control-sm" placeholder="" required>
					 </div> 
				  </div> 

				  <div class="form-group  " >
					<label for="username" class=" control-label "> Username <span class="asterix"> * </span></label>
					<div class="">
					  
					<input type="text" name="username" value="{{ $row['username'] }}" class="form-control form-control-sm" placeholder="" required>

					 </div> 
					
				  </div> 
					<div class="row">
						<div class="col-md-6">
							  <div class="form-group  " >
								<label for="First Name" class=" control-label "> First Name <span class="asterix"> * </span></label>
								<div class="">
								<input type="text" name="first_name" value="{{ $row['first_name'] }}" class="form-control form-control-sm" placeholder="" required>
								 </div> 
								
							  </div> 
						</div>	  	
						<div class="col-md-6">				
							  <div class="form-group  " >
								<label for="Last Name" class=" control-label "> Last Name </label>
								<div class="">
								<input type="text" name="last_name" value="{{ $row['last_name'] }}" class="form-control form-control-sm" placeholder="">
								 </div> 
								 <div class="col-md-2">
								 	
								 </div>
							  </div> 	
						</div>
					</div>		  				
				  <div class="form-group  " >
					<label for="Email" class=" control-label "> Email <span class="asterix"> * </span></label>
					<div class="">
					<input type="email" name="email" value="{{ $row['email'] }}" class="form-control form-control-sm" placeholder="" required>
					 </div> 
					 
				  </div> 					
	
				  <div class="form-group  " >
					<label for="Status" class=" control-label "> Status <span class="asterix"> * </span></label>
					<div class="">
						<input type='radio' name='active' value ='1' required @if($row['active'] == '1') checked="checked" @endif class="filled-in" id="Active">  <label for="Active"> Active </label>  

						<input type='radio' name='active' value ='0' required @if($row['active'] == '0') checked="checked" @endif class="filled-in" id="Inactive">  <label for="Inactive"> Inactive </label> 

						<input type='radio' name='active' value ='2' required @if($row['active'] == '2') checked="checked" @endif class="filled-in" id="Banned">  <label for="Banned"> Banned </label>  
					 </div> 
					 
				  </div> 


				  <div class="form-group  " >
					<label for="Avatar" class=" control-label "> Avatar </label>
					<div class="">
					  <input  type='file' name='avatar' id='avatar' @if($row['avatar'] =='') class='required' @endif style='width:150px !important;'  />
						 	<div >
							{!! SiteHelpers::showUploadedFile($row['avatar'],'/uploads/users/') !!}
							
					</div>					
 
					 </div> 
					 
				  </div> 


	  			

			</div>

			
		</fieldset>

		<div class="col-md-6">	  
			<fieldset>
				<div class="form-group">
					<div class="d-flex justify-content-between align-items-center mb-2">
						<label class="control-label mb-0">Unit & Jabatan <span class="asterix"> * </span></label>
						<button type="button" class="btn btn-xs btn-outline-primary btn-sm" onclick="addUnitPositionModal()">
							<i class="fa fa-plus"></i> Tambah Unit & Jabatan
						</button>
					</div>

					<div class="table-responsive border rounded bg-white mb-2" style="box-shadow: 0 1px 3px rgba(0,0,0,0.04);">
						<table class="table table-sm table-bordered table-hover mb-0" id="table-user-units" style="font-size: 12px; vertical-align: middle;">
							<thead class="bg-light text-dark fw-bold" style="font-size: 11.5px;">
								<tr>
									<th style="width: 50%; min-width: 140px; padding: 7px 10px;">Unit / Jabatan <span class="asterix">*</span></th>
									<th style="width: 18%; min-width: 85px; padding: 7px 8px;">Mulai</th>
									<th style="width: 20%; min-width: 90px; padding: 7px 8px;">Status</th>
									<th style="width: 12%; min-width: 75px; padding: 7px 8px;" class="text-center">Aksi</th>
								</tr>
							</thead>
							<tbody id="unit-position-container">
								@php
									$hasUnits = isset($user_units) && $user_units->count() > 0;
								@endphp
								@if($hasUnits)
									@foreach($user_units as $idx => $uu)
									@php
										$hasEndDate = !empty($uu->end_date) && !is_zero_date($uu->end_date);
										$dispStart = !empty($uu->start_date) && !is_zero_date($uu->start_date) ? date('d/m/Y', strtotime($uu->start_date)) : '-';
										$valStart = !empty($uu->start_date) && !is_zero_date($uu->start_date) ? date('Y-m-d', strtotime($uu->start_date)) : '';
										$dispEnd = $hasEndDate ? date('d/m/Y', strtotime($uu->end_date)) : '';
										$valEnd = $hasEndDate ? date('Y-m-d', strtotime($uu->end_date)) : '';
									@endphp
									<tr class="unit-position-row {{ $hasEndDate ? 'table-secondary-row text-muted' : '' }}" data-index="{{ $idx }}">
										<td style="padding: 7px 10px; vertical-align: middle; cursor: pointer;" onclick="openEditUnitModal(this)" title="Klik untuk edit Unit & Jabatan">
											<input type="hidden" name="id_unit[]" class="val-unit" value="{{ $uu->id_unit ?? '' }}">
											<input type="hidden" name="id_position[]" class="val-position" value="{{ $uu->id_position ?? '' }}">
											<div class="d-flex align-items-center justify-content-between">
												<div>
													<div class="fw-bold disp-unit-name text-dark" style="font-size: 13px; line-height: 1.25;">{{ $uu->unit_name ?? '-' }}</div>
													<div class="disp-pos-name text-muted" style="font-size: 11px; margin-top: 2px;">
														<i class="fa fa-id-badge text-secondary me-1" style="font-size: 10px;"></i>{{ $uu->position_name ?? '-' }}
													</div>
												</div>
												<i class="fa fa-pencil text-muted ms-1" style="font-size: 11px; opacity: 0.5;" title="Edit"></i>
											</div>
										</td>
										<td style="padding: 7px 8px; vertical-align: middle; white-space: nowrap;">
											<input type="hidden" name="start_date[]" class="val-start-date" value="{{ $valStart }}">
											<span class="disp-start-date fw-normal">{{ $dispStart }}</span>
										</td>
										<td style="padding: 7px 8px; vertical-align: middle; white-space: nowrap;">
											<input type="hidden" name="end_date[]" class="val-end-date" value="{{ $valEnd }}">
											<span class="disp-status">
												@if($hasEndDate)
													<span class="badge bg-secondary" style="font-size: 10px;" title="Selesai: {{ $dispEnd }}">Selesai</span>
												@else
													<span class="badge badge-success" style="font-size: 10px;">Aktif</span>
												@endif
											</span>
										</td>
										<td style="padding: 7px 8px; vertical-align: middle; white-space: nowrap;" class="text-center">
											<div class="btn-group btn-group-sm" role="group">
												<button type="button" class="btn {{ $hasEndDate ? 'btn-outline-success' : 'btn-outline-warning' }} set-end-date-btn" onclick="toggleEndDateRow(this)" title="{{ $hasEndDate ? 'Aktifkan Kembali' : 'Set Selesai (Hari ini)' }}" style="padding: 2px 6px; font-size: 11px;">
													<i class="fa {{ $hasEndDate ? 'fa-undo' : 'fa-calendar-times' }}"></i>
												</button>
												<button type="button" class="btn btn-outline-danger remove-row-btn" onclick="removeUnitRow(this)" title="Hapus Baris" style="padding: 2px 6px; font-size: 11px;">
													<i class="fa fa-trash"></i>
												</button>
											</div>
										</td>
									</tr>
									@endforeach
								@elseif(!empty($row['id_unit']))
									@php
										$unitName = get_unit($row['id_unit'], 'unit_name') ?: '-';
										$posName = !empty($row['id_position']) ? (get_unit($row['id_position'], 'unit_name') ?: '-') : '-';
										$hasEndDate = !empty($row['end_date']) && !is_zero_date($row['end_date']);
										$dispStart = !empty($row['start_date']) && !is_zero_date($row['start_date']) ? date('d/m/Y', strtotime($row['start_date'])) : '-';
										$valStart = !empty($row['start_date']) && !is_zero_date($row['start_date']) ? date('Y-m-d', strtotime($row['start_date'])) : '';
										$dispEnd = $hasEndDate ? date('d/m/Y', strtotime($row['end_date'])) : '';
										$valEnd = $hasEndDate ? date('Y-m-d', strtotime($row['end_date'])) : '';
									@endphp
									<tr class="unit-position-row {{ $hasEndDate ? 'table-secondary-row text-muted' : '' }}" data-index="0">
										<td style="padding: 7px 10px; vertical-align: middle; cursor: pointer;" onclick="openEditUnitModal(this)" title="Klik untuk edit Unit & Jabatan">
											<input type="hidden" name="id_unit[]" class="val-unit" value="{{ $row['id_unit'] }}">
											<input type="hidden" name="id_position[]" class="val-position" value="{{ $row['id_position'] ?? '' }}">
											<div class="d-flex align-items-center justify-content-between">
												<div>
													<div class="fw-bold disp-unit-name text-dark" style="font-size: 13px; line-height: 1.25;">{{ $unitName }}</div>
													<div class="disp-pos-name text-muted" style="font-size: 11px; margin-top: 2px;">
														<i class="fa fa-id-badge text-secondary me-1" style="font-size: 10px;"></i>{{ $posName }}
													</div>
												</div>
												<i class="fa fa-pencil text-muted ms-1" style="font-size: 11px; opacity: 0.5;" title="Edit"></i>
											</div>
										</td>
										<td style="padding: 7px 8px; vertical-align: middle; white-space: nowrap;">
											<input type="hidden" name="start_date[]" class="val-start-date" value="{{ $valStart }}">
											<span class="disp-start-date fw-normal">{{ $dispStart }}</span>
										</td>
										<td style="padding: 7px 8px; vertical-align: middle; white-space: nowrap;">
											<input type="hidden" name="end_date[]" class="val-end-date" value="{{ $valEnd }}">
											<span class="disp-status">
												@if($hasEndDate)
													<span class="badge bg-secondary" style="font-size: 10px;" title="Selesai: {{ $dispEnd }}">Selesai</span>
												@else
													<span class="badge badge-success" style="font-size: 10px;">Aktif</span>
												@endif
											</span>
										</td>
										<td style="padding: 7px 8px; vertical-align: middle; white-space: nowrap;" class="text-center">
											<div class="btn-group btn-group-sm" role="group">
												<button type="button" class="btn {{ $hasEndDate ? 'btn-outline-success' : 'btn-outline-warning' }} set-end-date-btn" onclick="toggleEndDateRow(this)" title="{{ $hasEndDate ? 'Aktifkan Kembali' : 'Set Selesai (Hari ini)' }}" style="padding: 2px 6px; font-size: 11px;">
													<i class="fa {{ $hasEndDate ? 'fa-undo' : 'fa-calendar-times' }}"></i>
												</button>
												<button type="button" class="btn btn-outline-danger remove-row-btn" onclick="removeUnitRow(this)" title="Hapus Baris" style="padding: 2px 6px; font-size: 11px;">
													<i class="fa fa-trash"></i>
												</button>
											</div>
										</td>
									</tr>
								@else
									<tr id="empty-unit-row">
										<td colspan="4" class="text-center text-muted py-3" style="font-size: 11.5px;">
											<i class="fa fa-info-circle me-1"></i> Belum ada unit & jabatan. Klik <strong>+ Tambah Unit & Jabatan</strong> untuk menambahkan.
										</td>
									</tr>
								@endif
							</tbody>
						</table>
					</div>
				  </div>
			</fieldset>
			<fieldset>
				<legend> Password </legend>
				<p>
					@if($row['id'] !='')
							{{ Lang::get('core.notepassword') }}
						@else
							Create Password
						@endif	 
				</p>		


				<div class="form-group">
					<label for="ipt" class=" control-label col-md-4"> {{ Lang::get('core.newpassword') }} </label>
					<div class="col-md-8">
					<input name="password" type="password" id="password" class="form-control form-control-sm" value=""
					@if($row['id'] =='')
						required
					@endif
					 /> 
					 </div> 
				</div>  
				  
				  <div class="form-group">
					<label for="ipt" class=" control-label col-md-4"> {{ Lang::get('core.conewpassword') }} </label>
					<div class="col-md-8">
					<input name="password_confirmation" type="password" id="password_confirmation" class="form-control form-control-sm" value=""
					@if($row['id'] =='')
						required
					@endif		
					 />  
					 </div> 
				  </div>  				  
				  
			</fieldset>
			<fieldset>
				<legend> </legend>
				<div class="form-group">
					<label class="col-sm-4 text-end">&nbsp;</label>
					<div class="col-sm-8">	
						<button type="submit" name="apply" class="btn btn-info btn-sm" ><i class="fa  fa-check-circle"></i> {{ Lang::get('core.sb_apply') }}</button>
						<button type="submit" name="submit" class="btn btn-primary btn-sm" ><i class="fa  fa-save "></i> {{ Lang::get('core.sb_save') }}</button>
						<button type="button" onclick="location.href='{{ URL::to('core/users?return='.$return) }}' " class="btn btn-warning btn-sm "><i class="fa  fa-arrow-circle-left "></i>  {{ Lang::get('core.sb_cancel') }} </button>
					</div>	  
			
				  </div>

			</fieldset>	

			
		 
		 </div>			
			
			
			<div style="clear:both"></div>	
				
			
		 <input type="hidden" name="action_task" value="save" />
	</div>
	</form>
	</div>
</div>

<!-- Modal Tambah / Edit Unit & Jabatan -->
<div class="modal fade" id="modalUnitPosition" tabindex="-1" role="dialog" aria-labelledby="modalUnitPositionLabel" aria-hidden="true">
	<div class="modal-dialog modal-dialog-centered" role="document">
		<div class="modal-content shadow">
			<div class="modal-header bg-light py-2 px-3">
				<h5 class="modal-title fw-bold" id="modalUnitPositionLabel" style="font-size: 14px;">
					<i class="fa fa-plus-circle text-primary me-1"></i> Tambah Unit & Jabatan
				</h5>
				<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close" style="padding: 10px;"></button>
			</div>
			<div class="modal-body p-3">
				<input type="hidden" id="modal_row_target" value="">

				<div class="form-group mb-3">
					<label class="control-label small fw-bold mb-1">Unit <span class="asterix text-danger">*</span></label>
					<select id="modal_id_unit" class="form-control select2" style="width: 100%;">
						<option value="">-- Pilih Unit --</option>
					</select>
				</div>

				<div class="form-group mb-3">
					<label class="control-label small fw-bold mb-1">Jabatan <span class="asterix text-danger">*</span></label>
					<select id="modal_id_position" class="form-control select2" style="width: 100%;">
						<option value="">-- Pilih Jabatan --</option>
					</select>
				</div>

				<div class="row">
					<div class="col-md-6 form-group mb-2">
						<label class="control-label small fw-bold mb-1">Start Date</label>
						<input type="date" id="modal_start_date" class="form-control form-control-sm">
					</div>
					<div class="col-md-6 form-group mb-2">
						<label class="control-label small fw-bold mb-1">End Date</label>
						<input type="date" id="modal_end_date" class="form-control form-control-sm">
						<small class="text-muted d-block mt-1" style="font-size: 10.5px;">Kosongkan jika penugasan aktif</small>
					</div>
				</div>
			</div>
			<div class="modal-footer bg-light py-2 px-3">
				<button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">
					<i class="fa fa-times me-1"></i> Batal
				</button>
				<button type="button" class="btn btn-primary btn-sm" id="btn-save-modal-unit" onclick="saveModalUnitPosition()">
					<i class="fa fa-check me-1"></i> Simpan
				</button>
			</div>
		</div>
	</div>
</div>
		 
   <script type="text/javascript">
	$(document).ready(function() { 
		
		$("#group_id").jCombo("{{ URL::to('core/users/comboselect?filter=tb_groups:group_id:name') }}",
		{  selected_value : '{{ $selected_groups ?? "" }}' });

		if ($.fn.select2) {
			$('#modal_id_unit').select2({
				dropdownParent: $('#modalUnitPosition'),
				width: '100%',
				placeholder: '-- Pilih Unit --'
			});

			$('#modal_id_position').select2({
				dropdownParent: $('#modalUnitPosition'),
				width: '100%',
				placeholder: '-- Pilih Jabatan --'
			});
		}

		$('#modal_id_unit').on('change', function() {
			var pendingPos = $(this).attr('data-pending-pos') || '';
			$(this).removeAttr('data-pending-pos');
			loadModalPositions(pendingPos);
		});
	});

	function addUnitPositionModal() {
		$('#modal_row_target').val('');
		$('#modalUnitPositionLabel').html('<i class="fa fa-plus-circle text-primary me-1"></i> Tambah Unit & Jabatan');
		$('#btn-save-modal-unit').html('<i class="fa fa-check me-1"></i> Tambahkan');

		var today = new Date().toISOString().split('T')[0];
		$('#modal_start_date').val(today);
		$('#modal_end_date').val('');

		loadModalUnits('', '');
		$('#modalUnitPosition').modal('show');
	}

	function addUnitPositionRow() {
		addUnitPositionModal();
	}

	function openEditUnitModal(btn) {
		var $row = $(btn).closest('.unit-position-row');
		var rowIndex = $row.index();
		$('#modal_row_target').val(rowIndex);
		$('#modalUnitPositionLabel').html('<i class="fa fa-pencil text-info me-1"></i> Edit Unit & Jabatan');
		$('#btn-save-modal-unit').html('<i class="fa fa-check me-1"></i> Simpan Perubahan');

		var unitId = $row.find('.val-unit').val();
		var posId = $row.find('.val-position').val();
		var startDate = $row.find('.val-start-date').val();
		var endDate = $row.find('.val-end-date').val();

		$('#modal_start_date').val(startDate);
		$('#modal_end_date').val(endDate);

		loadModalUnits(unitId, posId);
		$('#modalUnitPosition').modal('show');
	}

	function loadModalUnits(selectedUnit, selectedPos) {
		var id = $("[name='id']").val();
		var cor = $('#id_corporate').val();
		var $unitSelect = $('#modal_id_unit');
		var $posSelect = $('#modal_id_position');

		if (selectedPos) {
			$unitSelect.attr('data-pending-pos', selectedPos);
		} else {
			$unitSelect.removeAttr('data-pending-pos');
		}

		$unitSelect.html('<option value="">Memuat unit...</option>');
		$posSelect.html('<option value="">-- Pilih Unit terlebih dahulu --</option>');

		$.ajax({
			url      : "{!! url('core/users/unit')!!}",
			method   : "POST",
			data     : {
				id           : id,
				id_corporate : cor,
				selected_unit: selectedUnit
			},
			dataType : 'json',
			success  : function(respon) {
				var obj = tryjson(respon);
				if (obj.success === false) {
					notyMessageError(obj.message);
					return false;
				} else {
					$unitSelect.html(obj.callback);
					if (selectedUnit) {
						$unitSelect.val(selectedUnit);
					}
					$unitSelect.trigger('change');
				}
			}
		});
	}

	function loadModalPositions(selectedPos) {
		var id = $("[name='id']").val();
		var unitVal = $('#modal_id_unit').val();
		var $posSelect = $('#modal_id_position');

		if (!unitVal) {
			$posSelect.html('<option value="">-- Pilih Unit terlebih dahulu --</option>');
			if ($.fn.select2) {
				$posSelect.trigger('change.select2');
			}
			return;
		}

		$posSelect.html('<option value="">Memuat jabatan...</option>');

		$.ajax({
			url      : "{!! url('core/users/position')!!}",
			method   : "POST",
			data     : {
				id               : id,
				id_unit          : unitVal,
				selected_position: selectedPos
			},
			dataType : 'json',
			success  : function(respon) {
				var obj = tryjson(respon);
				if (obj.success === false) {
					notyMessageError(obj.message);
					return false;
				} else {
					$posSelect.html(obj.callback);
					if (selectedPos) {
						$posSelect.val(selectedPos);
					}
					if ($.fn.select2) {
						$posSelect.trigger('change.select2');
					}
				}
			}
		});
	}

	function saveModalUnitPosition() {
		var unitId = $('#modal_id_unit').val();
		var unitText = $('#modal_id_unit option:selected').text();
		var posId = $('#modal_id_position').val();
		var posText = $('#modal_id_position option:selected').text();
		var startDate = $('#modal_start_date').val();
		var endDate = $('#modal_end_date').val();
		var targetIndex = $('#modal_row_target').val();

		if (!unitId) {
			alert('Silakan pilih Unit terlebih dahulu');
			$('#modal_id_unit').focus();
			return;
		}
		if (!posId) {
			alert('Silakan pilih Jabatan terlebih dahulu');
			$('#modal_id_position').focus();
			return;
		}

		var hasEndDate = Boolean(endDate && endDate !== '0000-00-00');
		var dispStart = formatDateDisplay(startDate);
		var dispEnd = hasEndDate ? formatDateDisplay(endDate) : '';
		var statusBadge = hasEndDate 
			? '<span class="badge bg-secondary" style="font-size: 10px;" title="Selesai: ' + dispEnd + '">Selesai</span>' 
			: '<span class="badge badge-success" style="font-size: 10px;">Aktif</span>';

		if (targetIndex !== '') {
			var $row = $('#unit-position-container .unit-position-row').eq(targetIndex);
			$row.find('.val-unit').val(unitId);
			$row.find('.val-position').val(posId);
			$row.find('.val-start-date').val(startDate);
			$row.find('.val-end-date').val(endDate);

			$row.find('.disp-unit-name').text(unitText);
			$row.find('.disp-pos-name').html('<i class="fa fa-id-badge text-secondary me-1" style="font-size: 10px;"></i>' + escapeHtml(posText));
			$row.find('.disp-start-date').text(dispStart);
			$row.find('.disp-status').html(statusBadge);

			if (hasEndDate) {
				$row.addClass('table-secondary-row text-muted');
				$row.find('.set-end-date-btn')
					.removeClass('btn-outline-warning').addClass('btn-outline-success')
					.attr('title', 'Hapus End Date (Aktifkan Kembali)')
					.html('<i class="fa fa-undo"></i>');
			} else {
				$row.removeClass('table-secondary-row text-muted');
				$row.find('.set-end-date-btn')
					.removeClass('btn-outline-success').addClass('btn-outline-warning')
					.attr('title', 'Set Selesai (Hari ini)')
					.html('<i class="fa fa-calendar-times"></i>');
			}
		} else {
			$('#empty-unit-row').remove();
			var newIndex = $('#unit-position-container .unit-position-row').length;
			var rowHtml = `
			<tr class="unit-position-row ${hasEndDate ? 'table-secondary-row text-muted' : ''}" data-index="${newIndex}">
				<td style="padding: 7px 10px; vertical-align: middle; cursor: pointer;" onclick="openEditUnitModal(this)" title="Klik untuk edit Unit & Jabatan">
					<input type="hidden" name="id_unit[]" class="val-unit" value="${unitId}">
					<input type="hidden" name="id_position[]" class="val-position" value="${posId}">
					<div class="d-flex align-items-center justify-content-between">
						<div>
							<div class="fw-bold disp-unit-name text-dark" style="font-size: 13px; line-height: 1.25;">
								${escapeHtml(unitText)}
							</div>
							<div class="disp-pos-name text-muted" style="font-size: 11px; margin-top: 2px;">
								<i class="fa fa-id-badge text-secondary me-1" style="font-size: 10px;"></i>${escapeHtml(posText)}
							</div>
						</div>
						<i class="fa fa-pencil text-muted ms-1" style="font-size: 11px; opacity: 0.5;" title="Edit"></i>
					</div>
				</td>
				<td style="padding: 7px 8px; vertical-align: middle; white-space: nowrap;">
					<input type="hidden" name="start_date[]" class="val-start-date" value="${startDate}">
					<span class="disp-start-date fw-normal">${dispStart}</span>
				</td>
				<td style="padding: 7px 8px; vertical-align: middle; white-space: nowrap;">
					<input type="hidden" name="end_date[]" class="val-end-date" value="${endDate}">
					<span class="disp-status">${statusBadge}</span>
				</td>
				<td style="padding: 7px 8px; vertical-align: middle; white-space: nowrap;" class="text-center">
					<div class="btn-group btn-group-sm" role="group">
						<button type="button" class="btn ${hasEndDate ? 'btn-outline-success' : 'btn-outline-warning'} set-end-date-btn" onclick="toggleEndDateRow(this)" title="${hasEndDate ? 'Aktifkan Kembali' : 'Set Selesai (Hari ini)'}" style="padding: 2px 6px; font-size: 11px;">
							<i class="fa ${hasEndDate ? 'fa-undo' : 'fa-calendar-times'}"></i>
						</button>
						<button type="button" class="btn btn-outline-danger remove-row-btn" onclick="removeUnitRow(this)" title="Hapus Baris" style="padding: 2px 6px; font-size: 11px;">
							<i class="fa fa-trash"></i>
						</button>
					</div>
				</td>
			</tr>`;
			$('#unit-position-container').append(rowHtml);
		}

		$('#modalUnitPosition').modal('hide');
	}

	function toggleEndDateRow(btn) {
		var $row = $(btn).closest('.unit-position-row');
		var $endDateInput = $row.find('.val-end-date');
		var currentVal = $endDateInput.val();
		var today = new Date().toISOString().split('T')[0];

		if (!currentVal || currentVal === '0000-00-00') {
			$endDateInput.val(today);
			var dispEnd = formatDateDisplay(today);
			$row.find('.disp-status').html('<span class="badge bg-secondary" style="font-size: 10px;" title="Selesai: ' + dispEnd + '">Selesai</span>');
			$row.addClass('table-secondary-row text-muted');
			$(btn).removeClass('btn-outline-warning').addClass('btn-outline-success')
				.attr('title', 'Hapus End Date (Aktifkan Kembali)')
				.html('<i class="fa fa-undo"></i>');
		} else {
			$endDateInput.val('');
			$row.find('.disp-status').html('<span class="badge badge-success" style="font-size: 10px;">Aktif</span>');
			$row.removeClass('table-secondary-row text-muted');
			$(btn).removeClass('btn-outline-success').addClass('btn-outline-warning')
				.attr('title', 'Set Selesai (Hari ini)')
				.html('<i class="fa fa-calendar-times"></i>');
		}
	}

	function removeUnitRow(btn) {
		if (confirm('Apakah Anda yakin ingin menghapus baris unit & jabatan ini?')) {
			var $row = $(btn).closest('.unit-position-row');
			$row.remove();
			if ($('#unit-position-container .unit-position-row').length === 0) {
				$('#unit-position-container').html(`
					<tr id="empty-unit-row">
						<td colspan="4" class="text-center text-muted py-3" style="font-size: 11.5px;">
							<i class="fa fa-info-circle me-1"></i> Belum ada unit & jabatan. Klik <strong>+ Tambah Unit & Jabatan</strong> untuk menambahkan.
						</td>
					</tr>
				`);
			}
		}
	}

	function formatDateDisplay(dateStr) {
		if (!dateStr || dateStr === '0000-00-00') return '-';
		var parts = dateStr.split('-');
		if (parts.length === 3) {
			return parts[2] + '/' + parts[1] + '/' + parts[0];
		}
		return dateStr;
	}

	function escapeHtml(text) {
		if (!text) return '';
		return $('<div>').text(text).html();
	}
	</script>

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
#modalUnitPosition .select2-container {
	width: 100% !important;
}
#modalUnitPosition .select2-selection--single {
	height: 35px !important;
	border-color: #ced4da;
}
#modalUnitPosition .select2-selection__rendered {
	line-height: 33px !important;
	font-size: 13px;
}
#modalUnitPosition .select2-selection__arrow {
	height: 33px !important;
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
body.dark-theme #modalUnitPosition .modal-content {
	background-color: #1a2230;
	color: #e2e8f0;
}
body.dark-theme #modalUnitPosition .modal-header,
body.dark-theme #modalUnitPosition .modal-footer {
	background-color: #141822 !important;
	border-color: #263244;
}
</style>
@stop