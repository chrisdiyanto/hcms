<style>
	.module-create-form {
		--module-accent: #4568dc;
		--module-border: #e6eaf2;
		color: #344054;
	}

	.module-create-form .module-create-heading {
		margin: -15px -15px 24px;
		padding: 24px 28px;
		background: linear-gradient(135deg, #f4f7ff 0%, #edf2ff 100%);
		border-bottom: 1px solid var(--module-border);
	}

	.module-create-form .module-create-heading h3 {
		margin: 0 0 6px;
		color: #1f2a44;
		font-size: 20px;
		font-weight: 600;
	}

	.module-create-form .module-create-heading p {
		margin: 0;
		color: #667085;
		font-size: 13px;
	}

	.module-create-form .module-form-section {
		margin-bottom: 22px;
		padding-bottom: 20px;
		border-bottom: 1px solid #edf0f5;
	}

	.module-create-form .module-section-title {
		margin: 0 0 16px;
		color: #344054;
		font-size: 14px;
		font-weight: 600;
	}

	.module-create-form .form-group {
		margin-bottom: 16px;
	}

	.module-create-form .module-field-label {
		padding-top: 7px;
		color: #475467;
		font-size: 13px;
		font-weight: 500;
	}

	.module-create-form .form-control {
		min-height: 38px;
		border-color: #d9deea;
		border-radius: 6px;
		box-shadow: none;
	}

	.module-create-form textarea.form-control {
		min-height: 82px;
		resize: vertical;
	}

	.module-create-form .form-control:focus {
		border-color: var(--module-accent);
		box-shadow: 0 0 0 3px rgba(69, 104, 220, .12);
	}

	.module-create-form .module-field-help {
		display: block;
		margin-top: 5px;
		color: #8490a3;
		font-size: 12px;
	}

	.module-create-form .module-template-list {
		display: grid;
		grid-template-columns: repeat(auto-fit, minmax(190px, 1fr));
		gap: 10px;
	}

	.module-create-form .module-template-option {
		display: flex;
		align-items: flex-start;
		gap: 10px;
		min-width: 0;
		margin: 0;
		padding: 13px 14px;
		background: #fff;
		border: 1px solid var(--module-border);
		border-radius: 8px;
		cursor: pointer;
		transition: border-color .15s ease, background-color .15s ease, box-shadow .15s ease;
	}

	.module-create-form .module-template-option.is-selected {
		background: #f6f8ff;
		border-color: var(--module-accent);
		box-shadow: 0 0 0 2px rgba(69, 104, 220, .08);
	}

	.module-create-form .module-template-option input {
		flex: 0 0 auto;
		margin: 3px 0 0;
		accent-color: var(--module-accent);
	}

	.module-create-form .module-template-option:focus-within {
		outline: 2px solid rgba(69, 104, 220, .3);
		outline-offset: 2px;
	}

	.module-create-form .module-template-name {
		display: block;
		margin-bottom: 3px;
		color: #344054;
		font-size: 13px;
		font-weight: 600;
	}

	.module-create-form .module-template-note {
		display: block;
		color: #7b8798;
		font-size: 12px;
		font-weight: 400;
		line-height: 1.5;
	}

	.module-create-form .module-sql-options {
		display: flex;
		flex-wrap: wrap;
		gap: 10px;
	}

	.module-create-form .module-sql-option {
		display: inline-flex;
		align-items: center;
		gap: 8px;
		margin: 0;
		padding: 9px 13px;
		background: #fff;
		border: 1px solid var(--module-border);
		border-radius: 6px;
		color: #475467;
		cursor: pointer;
		font-size: 13px;
	}

	.module-create-form .module-sql-option input {
		margin: 0;
		accent-color: var(--module-accent);
	}

	.module-create-form .module-submit-row {
		display: flex;
		justify-content: flex-end;
		padding-top: 2px;
	}

	.module-create-form .module-submit-button {
		padding: 9px 16px;
		border: 0;
		border-radius: 6px;
		background: var(--module-accent);
		box-shadow: 0 4px 10px rgba(69, 104, 220, .2);
		font-weight: 500;
	}

	.module-create-form .module-submit-button:hover,
	.module-create-form .module-submit-button:focus {
		background: #3656c4;
	}

	body.dark-theme .module-create-form .module-create-heading {
		background: linear-gradient(135deg, #161b22 0%, #1c2430 100%);
		border-color: #30363d;
	}

	body.dark-theme .module-create-form .module-create-heading h3,
	body.dark-theme .module-create-form .module-section-title,
	body.dark-theme .module-create-form .module-field-label,
	body.dark-theme .module-create-form .module-template-name {
		color: #e6edf3 !important;
	}

	body.dark-theme .module-create-form .module-create-heading p,
	body.dark-theme .module-create-form .module-field-help,
	body.dark-theme .module-create-form .module-template-note {
		color: #8b949e !important;
	}

	body.dark-theme .module-create-form .module-form-section {
		border-color: #21262d;
	}

	body.dark-theme .module-create-form .module-template-option,
	body.dark-theme .module-create-form .module-sql-option {
		background: #161b22 !important;
		border-color: #30363d !important;
		color: #c9d1d9 !important;
	}

	body.dark-theme .module-create-form .module-template-option.is-selected {
		background: #1c2a41 !important;
		border-color: #58a6ff !important;
	}

	@media (max-width: 575.98px) {
		.module-create-form .module-create-heading {
			margin: -15px -15px 20px;
			padding: 20px;
		}

		.module-create-form .module-field-label {
			padding-top: 0;
			margin-bottom: 6px;
			text-align: left !important;
		}
	}
</style>

<form action="{{ url('sximo/module/create/') }}" class="form-horizontal validated module-create-form" method="POST" parsley-validate novalidate>
	<div class="module-create-heading">
		<h3>Create a New Module</h3>
		<p>Configure the module details and data source to generate your application.</p>
	</div>

	@if($errors->any())
		<div class="alert alert-danger" role="alert">
			<strong>Please review the following:</strong>
			<ul class="parsley-error-list mb-0">
				@foreach($errors->all() as $error)
					<li>{{ $error }}</li>
				@endforeach
			</ul>
		</div>
	@endif

	<section class="module-form-section" aria-labelledby="module-details-title">
		<h4 class="module-section-title" id="module-details-title">Module details</h4>

		<div class="form-group row">
			<label class="col-sm-3 text-sm-end module-field-label">{{ Lang::get('core.fr_modtitle') }}</label>
			<div class="col-sm-9">
				<input type="text" name="module_title" class="form-control" placeholder="Title Name" required>
				<small class="module-field-help">The display title shown for this module.</small>
			</div>
		</div>

		<div class="form-group row">
			<label class="col-sm-3 text-sm-end module-field-label">{{ Lang::get('core.fr_modnote') }}</label>
			<div class="col-sm-9">
				<input type="text" name="module_note" class="form-control" placeholder="Short description module" required>
				<small class="module-field-help">A short description to help identify this module.</small>
			</div>
		</div>

		<div class="form-group row">
			<label class="col-sm-3 text-sm-end module-field-label">Template</label>
			<div class="col-sm-9">
				<div class="module-template-list">
					@foreach($cruds as $crud)
						<label class="module-template-option" for="basic_checkbox_{{ $crud->type }}">
							<input name="module_template" type="radio" id="basic_checkbox_{{ $crud->type }}" value="{{ $crud->type }}" required>
							<span>
								<span class="module-template-name">{{ $crud->name }}</span>
								<small class="module-template-note">{{ $crud->note }}</small>
							</span>
						</label>
					@endforeach
				</div>
			</div>
		</div>

		<div class="form-group row">
			<label class="col-sm-3 text-sm-end module-field-label">Class Controller</label>
			<div class="col-sm-9">
				<input type="text" name="module_name" class="form-control" placeholder="Class Controller / Module Name" required>
				<small class="module-field-help">Used as the controller and module class name.</small>
			</div>
		</div>

		<div class="form-group row mb-0">
			<label class="col-sm-3 text-sm-end module-field-label">{{ Lang::get('core.fr_modtable') }}</label>
			<div class="col-sm-9">
				<select name="module_db" class="form-control" required>
					@foreach($tables as $key => $val)
						<option value="{{ $key }}">{{ $val }}</option>
					@endforeach
				</select>
				<small class="module-field-help">Choose the database table this module will use.</small>
			</div>
		</div>
	</section>

	<div class="form-group row switchSql">
		<label class="col-sm-3 text-sm-end module-field-label">Data source</label>
		<div class="col-sm-9">
			<div class="module-sql-options">
				<label class="module-sql-option" for="auto">
					<input type="radio" name="creation" value="auto" id="auto" checked="checked" class="minimal-green">
					<span>{{ Lang::get('core.fr_modautosql') }}</span>
				</label>
				<label class="module-sql-option" for="manual">
					<input type="radio" name="creation" value="manual" id="manual">
					<span>{{ Lang::get('core.fr_modmanualsql') }}</span>
				</label>
			</div>
		</div>
	</div>

	<div class="form-group row manualsql">
		<label class="col-sm-3 text-sm-end module-field-label">SQL Select</label>
		<div class="col-sm-9">
			<textarea name="sql_select" rows="3" id="sql_select" class="form-control" placeholder="SQL Select &amp; Join Statement"></textarea>
		</div>
	</div>

	<div class="form-group row manualsql">
		<label class="col-sm-3 text-sm-end module-field-label">SQL Where</label>
		<div class="col-sm-9">
			<textarea name="sql_where" rows="2" id="sql_where" class="form-control" placeholder="SQL Where Statement"></textarea>
		</div>
	</div>

	<div class="form-group row manualsql">
		<label class="col-sm-3 text-sm-end module-field-label">SQL Group</label>
		<div class="col-sm-9">
			<textarea name="sql_group" rows="2" id="sql_group" class="form-control" placeholder="SQL Grouping Statement"></textarea>
		</div>
	</div>

	<div class="form-group row" style="display:none;">
		<label class="col-sm-3 text-sm-end module-field-label">Author</label>
		<div class="col-sm-9">
			<input type="text" name="module_author" class="form-control" placeholder="Author">
		</div>
	</div>

	<div class="module-submit-row">
		<button type="submit" class="btn btn-primary module-submit-button">{{ Lang::get('core.sb_submit') }} AND GENERATE MODULE</button>
	</div>
</form>

<script type="text/javascript">
	$(document).ready(function() {
		$('.module-create-form input[name="module_template"]').on('change', function() {
			$('.module-template-option').removeClass('is-selected');
			$(this).closest('.module-template-option').addClass('is-selected');
		});

		$('.manualsql').hide();
		$('.switchSql input:radio').on('change', function() {
			const isManual = $(this).val() === 'manual';
			$('.manualsql').toggle(isManual);

			if (isManual) {
				$('#sql_select, #sql_where').attr('required', 'required');
			} else {
				$('#sql_select, #sql_where').removeAttr('required');
			}
		});
	});
</script>
