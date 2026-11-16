@extends('layouts.app')

@section('content')
<div class="page-header">
	<h2> {{ $pageTitle }}  <small> {{ $pageNote }} </small> </h2>
</div>

<div class="card">
	<div class="card-body">
		@include('sximo.config.tab')
		
		<div class="pt-3"> 
			<form action="{{ url('sximo/config/design') }}" class="form-horizontal validated" method="POST">
				@csrf
				<input type="hidden" name="cnf_header_color" id="cnf_header_color" value="{{ $sximoconfig['cnf_header_color'] ?? '' }}" />
				<input type="hidden" name="cnf_logo_color" id="cnf_logo_color" value="{{ $sximoconfig['cnf_logo_color'] ?? '' }}" />
				<input type="hidden" name="cnf_sidebar_color" id="cnf_sidebar_color" value="{{ $sximoconfig['cnf_sidebar_color'] ?? '' }}" />
				<input type="hidden" name="cnf_card_color" id="cnf_card_color" value="{{ $sximoconfig['cnf_card_color'] ?? '' }}" />
				<input type="hidden" name="cnf_page_color" id="cnf_page_color" value="{{ $sximoconfig['cnf_page_color'] ?? '' }}" />
				<input type="hidden" name="cnf_text_color" id="cnf_text_color" value="{{ $sximoconfig['cnf_text_color'] ?? '' }}" />
				<input type="hidden" name="cnf_footer_color" id="cnf_footer_color" value="{{ $sximoconfig['cnf_footer_color'] ?? '' }}" />

				<h4> Default Design </h4>
				<hr>
				<div class="row">
					<div class="col-sm-6">
						<div class="form-group row">
							<label for="ipt" class="control-label col-md-3">Text & icon color</label>
							<div class="col-md-8 c-t">
								<div class="box-theme bg-dark @if(($sximoconfig['cnf_text_color'] ?? '') == 'color-text-dark') active @endif" rel="color-text-dark"></div>
								<div class="box-theme bg-white @if(($sximoconfig['cnf_text_color'] ?? '') == 'color-text-white') active @endif" rel="color-text-white"></div>
								<div class="box-theme bg-blue @if(($sximoconfig['cnf_text_color'] ?? '') == 'color-text-blue') active @endif" rel="color-text-blue"></div>
								<div class="box-theme bg-indigo @if(($sximoconfig['cnf_text_color'] ?? '') == 'color-text-indigo') active @endif" rel="color-text-indigo"></div>
								<div class="box-theme bg-deep-purple @if(($sximoconfig['cnf_text_color'] ?? '') == 'color-text-deep-purple') active @endif" rel="color-text-deep-purple"></div>
								<div class="box-theme bg-purple @if(($sximoconfig['cnf_text_color'] ?? '') == 'color-text-purple') active @endif" rel="color-text-purple"></div>
								<div class="box-theme bg-pink @if(($sximoconfig['cnf_text_color'] ?? '') == 'color-text-pink') active @endif" rel="color-text-pink"></div>
								<div class="box-theme bg-red @if(($sximoconfig['cnf_text_color'] ?? '') == 'color-text-red') active @endif" rel="color-text-red"></div>
								<div class="box-theme bg-teal @if(($sximoconfig['cnf_text_color'] ?? '') == 'color-text-teal') active @endif" rel="color-text-teal"></div>
								<div class="box-theme bg-cyan @if(($sximoconfig['cnf_text_color'] ?? '') == 'color-text-cyan') active @endif" rel="color-text-cyan"></div>
								<div class="box-theme bg-light-blue @if(($sximoconfig['cnf_text_color'] ?? '') == 'color-text-light-blue') active @endif" rel="color-text-light-blue"></div>
								<div class="box-theme bg-light-green @if(($sximoconfig['cnf_text_color'] ?? '') == 'color-text-light-green') active @endif" rel="color-text-light-green"></div>
								<div class="box-theme bg-green @if(($sximoconfig['cnf_text_color'] ?? '') == 'color-text-green') active @endif" rel="color-text-green"></div>
								<div class="box-theme bg-orange @if(($sximoconfig['cnf_text_color'] ?? '') == 'color-text-orange') active @endif" rel="color-text-orange"></div>
								<div class="box-theme bg-yellow @if(($sximoconfig['cnf_text_color'] ?? '') == 'color-text-yellow') active @endif" rel="color-text-yellow"></div>
								<div class="box-theme bg-blue-grey @if(($sximoconfig['cnf_text_color'] ?? '') == 'color-text-blue-grey') active @endif" rel="color-text-blue-grey"></div>
								@php $is_custom_text = (strpos($sximoconfig['cnf_text_color'] ?? '', '#') === 0); @endphp
								<input type="color" class="box-theme-custom @if($is_custom_text) active @endif" value="{{ $is_custom_text ? $sximoconfig['cnf_text_color'] : '#ffffff' }}" title="Custom Color" />
							</div>
						</div>
						<div class="form-group row">
							<label for="ipt" class=" control-label col-md-3"> Header </label>
							<div class="col-md-8 c-h">
								<div class="box-theme bg-dark @if(($sximoconfig['cnf_header_color'] ?? '') == 'color-head-dark') active @endif" rel="color-head-dark"></div>
								<div class="box-theme bg-white @if(($sximoconfig['cnf_header_color'] ?? '') == 'color-head-white') active @endif" rel="color-head-white"></div>
								<div class="box-theme bg-blue @if(($sximoconfig['cnf_header_color'] ?? '') == 'color-head-blue') active @endif" rel="color-head-blue"></div>
								<div class="box-theme bg-indigo @if(($sximoconfig['cnf_header_color'] ?? '') == 'color-head-indigo') active @endif" rel="color-head-indigo"></div>
								<div class="box-theme bg-deep-purple @if(($sximoconfig['cnf_header_color'] ?? '') == 'color-head-deep-purple') active @endif" rel="color-head-deep-purple"></div>
								<div class="box-theme bg-purple @if(($sximoconfig['cnf_header_color'] ?? '') == 'color-head-purple') active @endif" rel="color-head-purple"></div>
								<div class="box-theme bg-pink @if(($sximoconfig['cnf_header_color'] ?? '') == 'color-head-pink') active @endif" rel="color-head-pink"></div>
								<div class="box-theme bg-red @if(($sximoconfig['cnf_header_color'] ?? '') == 'color-head-red') active @endif" rel="color-head-red"></div>
								<div class="box-theme bg-teal @if(($sximoconfig['cnf_header_color'] ?? '') == 'color-head-teal') active @endif" rel="color-head-teal"></div>
								<div class="box-theme bg-cyan @if(($sximoconfig['cnf_header_color'] ?? '') == 'color-head-cyan') active @endif" rel="color-head-cyan"></div>
								<div class="box-theme bg-light-blue @if(($sximoconfig['cnf_header_color'] ?? '') == 'color-head-light-blue') active @endif" rel="color-head-light-blue"></div>
								<div class="box-theme bg-light-green @if(($sximoconfig['cnf_header_color'] ?? '') == 'color-head-light-green') active @endif" rel="color-head-light-green"></div>
								<div class="box-theme bg-green @if(($sximoconfig['cnf_header_color'] ?? '') == 'color-head-green') active @endif" rel="color-head-green"></div>
								<div class="box-theme bg-orange @if(($sximoconfig['cnf_header_color'] ?? '') == 'color-head-orange') active @endif" rel="color-head-orange"></div>
								<div class="box-theme bg-yellow @if(($sximoconfig['cnf_header_color'] ?? '') == 'color-head-yellow') active @endif" rel="color-head-yellow"></div>
								<div class="box-theme bg-blue-grey @if(($sximoconfig['cnf_header_color'] ?? '') == 'color-head-blue-grey') active @endif" rel="color-head-blue-grey"></div>
								@php $is_custom_header = (strpos($sximoconfig['cnf_header_color'] ?? '', '#') === 0); @endphp
								<input type="color" class="box-theme-custom @if($is_custom_header) active @endif" value="{{ $is_custom_header ? $sximoconfig['cnf_header_color'] : '#ffffff' }}" title="Custom Color" />
							</div> 
						</div>   
						
						<div class="form-group row">
							<label for="ipt" class=" control-label col-md-3" > Logo </label>
							<div class="col-md-8 c-l">
								<div class="box-theme bg-dark @if(($sximoconfig['cnf_logo_color'] ?? '') == 'color-logo-dark') active @endif" rel="color-logo-dark"></div>
								<div class="box-theme bg-white @if(($sximoconfig['cnf_logo_color'] ?? '') == 'color-logo-white') active @endif" rel="color-logo-white"></div>
								<div class="box-theme bg-blue @if(($sximoconfig['cnf_logo_color'] ?? '') == 'color-logo-blue') active @endif" rel="color-logo-blue"></div>
								<div class="box-theme bg-indigo @if(($sximoconfig['cnf_logo_color'] ?? '') == 'color-logo-indigo') active @endif" rel="color-logo-indigo"></div>
								<div class="box-theme bg-deep-purple @if(($sximoconfig['cnf_logo_color'] ?? '') == 'color-logo-deep-purple') active @endif" rel="color-logo-deep-purple"></div>
								<div class="box-theme bg-purple @if(($sximoconfig['cnf_logo_color'] ?? '') == 'color-logo-purple') active @endif" rel="color-logo-purple"></div>
								<div class="box-theme bg-pink @if(($sximoconfig['cnf_logo_color'] ?? '') == 'color-logo-pink') active @endif" rel="color-logo-pink"></div>
								<div class="box-theme bg-red @if(($sximoconfig['cnf_logo_color'] ?? '') == 'color-logo-red') active @endif" rel="color-logo-red"></div>
								<div class="box-theme bg-teal @if(($sximoconfig['cnf_logo_color'] ?? '') == 'color-logo-teal') active @endif" rel="color-logo-teal"></div>
								<div class="box-theme bg-cyan @if(($sximoconfig['cnf_logo_color'] ?? '') == 'color-logo-cyan') active @endif" rel="color-logo-cyan"></div>
								<div class="box-theme bg-light-blue @if(($sximoconfig['cnf_logo_color'] ?? '') == 'color-logo-light-blue') active @endif" rel="color-logo-light-blue"></div>				
								<div class="box-theme bg-light-green @if(($sximoconfig['cnf_logo_color'] ?? '') == 'color-logo-light-green') active @endif" rel="color-logo-light-green"></div>
								<div class="box-theme bg-green @if(($sximoconfig['cnf_logo_color'] ?? '') == 'color-logo-green') active @endif" rel="color-logo-green"></div>
								<div class="box-theme bg-orange @if(($sximoconfig['cnf_logo_color'] ?? '') == 'color-logo-orange') active @endif" rel="color-logo-orange"></div>
								<div class="box-theme bg-yellow @if(($sximoconfig['cnf_logo_color'] ?? '') == 'color-logo-yellow') active @endif" rel="color-logo-yellow"></div>
								<div class="box-theme bg-blue-grey @if(($sximoconfig['cnf_logo_color'] ?? '') == 'color-logo-blue-grey') active @endif" rel="color-logo-blue-grey"></div> 
								@php $is_custom_logo = (strpos($sximoconfig['cnf_logo_color'] ?? '', '#') === 0); @endphp
								<input type="color" class="box-theme-custom @if($is_custom_logo) active @endif" value="{{ $is_custom_logo ? $sximoconfig['cnf_logo_color'] : '#ffffff' }}" title="Custom Color" />
							</div> 
						</div> 

						<div class="form-group row">
							<label for="ipt" class=" control-label col-md-3" > Sidebar </label>
							<div class="col-md-8 c-s">
								<div class="box-theme bg-dark @if(($sximoconfig['cnf_sidebar_color'] ?? '') == 'color-sidebar-dark') active @endif" rel="color-sidebar-dark"></div>
								<div class="box-theme bg-white @if(($sximoconfig['cnf_sidebar_color'] ?? '') == 'color-sidebar-white') active @endif" rel="color-sidebar-white"></div>
								<div class="box-theme bg-blue @if(($sximoconfig['cnf_sidebar_color'] ?? '') == 'color-sidebar-blue') active @endif" rel="color-sidebar-blue"></div>
								<div class="box-theme bg-indigo @if(($sximoconfig['cnf_sidebar_color'] ?? '') == 'color-sidebar-indigo') active @endif" rel="color-sidebar-indigo"></div>
								<div class="box-theme bg-deep-purple @if(($sximoconfig['cnf_sidebar_color'] ?? '') == 'color-sidebar-deep-purple') active @endif" rel="color-sidebar-deep-purple"></div>
								<div class="box-theme bg-purple @if(($sximoconfig['cnf_sidebar_color'] ?? '') == 'color-sidebar-purple') active @endif" rel="color-sidebar-purple"></div>
								<div class="box-theme bg-pink @if(($sximoconfig['cnf_sidebar_color'] ?? '') == 'color-sidebar-pink') active @endif" rel="color-sidebar-pink"></div>
								<div class="box-theme bg-red @if(($sximoconfig['cnf_sidebar_color'] ?? '') == 'color-sidebar-red') active @endif" rel="color-sidebar-red"></div>
								<div class="box-theme bg-teal @if(($sximoconfig['cnf_sidebar_color'] ?? '') == 'color-sidebar-teal') active @endif" rel="color-sidebar-teal"></div>
								<div class="box-theme bg-cyan @if(($sximoconfig['cnf_sidebar_color'] ?? '') == 'color-sidebar-cyan') active @endif" rel="color-sidebar-cyan"></div>
								<div class="box-theme bg-light-blue @if(($sximoconfig['cnf_sidebar_color'] ?? '') == 'color-sidebar-light-blue') active @endif" rel="color-sidebar-light-blue"></div>
								<div class="box-theme bg-light-green @if(($sximoconfig['cnf_sidebar_color'] ?? '') == 'color-sidebar-light-green') active @endif" rel="color-sidebar-light-green"></div>
								<div class="box-theme bg-green @if(($sximoconfig['cnf_sidebar_color'] ?? '') == 'color-sidebar-green') active @endif" rel="color-sidebar-green"></div>
								<div class="box-theme bg-orange @if(($sximoconfig['cnf_sidebar_color'] ?? '') == 'color-sidebar-orange') active @endif" rel="color-sidebar-orange"></div>
								<div class="box-theme bg-yellow @if(($sximoconfig['cnf_sidebar_color'] ?? '') == 'color-sidebar-yellow') active @endif" rel="color-sidebar-yellow"></div>
								<div class="box-theme bg-blue-grey @if(($sximoconfig['cnf_sidebar_color'] ?? '') == 'color-sidebar-blue-grey') active @endif" rel="color-sidebar-blue-grey"></div> 
								@php $is_custom_sidebar = (strpos($sximoconfig['cnf_sidebar_color'] ?? '', '#') === 0); @endphp
								<input type="color" class="box-theme-custom @if($is_custom_sidebar) active @endif" value="{{ $is_custom_sidebar ? $sximoconfig['cnf_sidebar_color'] : '#ffffff' }}" title="Custom Color" />
							</div> 
						</div>  
						<div class="form-group row">
							<label for="ipt_card_color" class=" control-label col-md-3">Card Body</label>
							<div class="col-md-8 c-c">
								<div class="box-theme bg-dark @if(($sximoconfig['cnf_card_color'] ?? '') == 'color-card-dark') active @endif" rel="color-card-dark"></div>
								<div class="box-theme bg-white @if(($sximoconfig['cnf_card_color'] ?? '') == 'color-card-white') active @endif" rel="color-card-white"></div>
								<div class="box-theme bg-blue @if(($sximoconfig['cnf_card_color'] ?? '') == 'color-card-blue') active @endif" rel="color-card-blue"></div>
								<div class="box-theme bg-indigo @if(($sximoconfig['cnf_card_color'] ?? '') == 'color-card-indigo') active @endif" rel="color-card-indigo"></div>
								<div class="box-theme bg-deep-purple @if(($sximoconfig['cnf_card_color'] ?? '') == 'color-card-deep-purple') active @endif" rel="color-card-deep-purple"></div>
								<div class="box-theme bg-purple @if(($sximoconfig['cnf_card_color'] ?? '') == 'color-card-purple') active @endif" rel="color-card-purple"></div>
								<div class="box-theme bg-pink @if(($sximoconfig['cnf_card_color'] ?? '') == 'color-card-pink') active @endif" rel="color-card-pink"></div>
								<div class="box-theme bg-red @if(($sximoconfig['cnf_card_color'] ?? '') == 'color-card-red') active @endif" rel="color-card-red"></div>
								<div class="box-theme bg-teal @if(($sximoconfig['cnf_card_color'] ?? '') == 'color-card-teal') active @endif" rel="color-card-teal"></div>
								<div class="box-theme bg-cyan @if(($sximoconfig['cnf_card_color'] ?? '') == 'color-card-cyan') active @endif" rel="color-card-cyan"></div>
								<div class="box-theme bg-light-blue @if(($sximoconfig['cnf_card_color'] ?? '') == 'color-card-light-blue') active @endif" rel="color-card-light-blue"></div>
								<div class="box-theme bg-light-green @if(($sximoconfig['cnf_card_color'] ?? '') == 'color-card-light-green') active @endif" rel="color-card-light-green"></div>
								<div class="box-theme bg-green @if(($sximoconfig['cnf_card_color'] ?? '') == 'color-card-green') active @endif" rel="color-card-green"></div>
								<div class="box-theme bg-orange @if(($sximoconfig['cnf_card_color'] ?? '') == 'color-card-orange') active @endif" rel="color-card-orange"></div>
								<div class="box-theme bg-yellow @if(($sximoconfig['cnf_card_color'] ?? '') == 'color-card-yellow') active @endif" rel="color-card-yellow"></div>
								<div class="box-theme bg-blue-grey @if(($sximoconfig['cnf_card_color'] ?? '') == 'color-card-blue-grey') active @endif" rel="color-card-blue-grey"></div> 
								@php $is_custom_card = (strpos($sximoconfig['cnf_card_color'] ?? '', '#') === 0); @endphp
								<input type="color" class="box-theme-custom @if($is_custom_card) active @endif" value="{{ $is_custom_card ? $sximoconfig['cnf_card_color'] : '#ffffff' }}" title="Custom Color" />
							</div> 
						</div>
						<div class="form-group row">
							<label for="ipt_page_color" class=" control-label col-md-3">Page Body</label>
							<div class="col-md-8 c-p">
								<div class="box-theme @if(($sximoconfig['cnf_page_color'] ?? '') == 'color-page-soft') active @endif" rel="color-page-soft" style="background:#f5f5fa" title="Soft (default)"></div>
								<div class="box-theme bg-dark @if(($sximoconfig['cnf_page_color'] ?? '') == 'color-page-dark') active @endif" rel="color-page-dark"></div>
								<div class="box-theme bg-white @if(($sximoconfig['cnf_page_color'] ?? '') == 'color-page-white') active @endif" rel="color-page-white"></div>
								<div class="box-theme bg-blue @if(($sximoconfig['cnf_page_color'] ?? '') == 'color-page-blue') active @endif" rel="color-page-blue"></div>
								<div class="box-theme bg-indigo @if(($sximoconfig['cnf_page_color'] ?? '') == 'color-page-indigo') active @endif" rel="color-page-indigo"></div>
								<div class="box-theme bg-deep-purple @if(($sximoconfig['cnf_page_color'] ?? '') == 'color-page-deep-purple') active @endif" rel="color-page-deep-purple"></div>
								<div class="box-theme bg-purple @if(($sximoconfig['cnf_page_color'] ?? '') == 'color-page-purple') active @endif" rel="color-page-purple"></div>
								<div class="box-theme bg-pink @if(($sximoconfig['cnf_page_color'] ?? '') == 'color-page-pink') active @endif" rel="color-page-pink"></div>
								<div class="box-theme bg-red @if(($sximoconfig['cnf_page_color'] ?? '') == 'color-page-red') active @endif" rel="color-page-red"></div>
								<div class="box-theme bg-teal @if(($sximoconfig['cnf_page_color'] ?? '') == 'color-page-teal') active @endif" rel="color-page-teal"></div>
								<div class="box-theme bg-cyan @if(($sximoconfig['cnf_page_color'] ?? '') == 'color-page-cyan') active @endif" rel="color-page-cyan"></div>
								<div class="box-theme bg-light-blue @if(($sximoconfig['cnf_page_color'] ?? '') == 'color-page-light-blue') active @endif" rel="color-page-light-blue"></div>
								<div class="box-theme bg-light-green @if(($sximoconfig['cnf_page_color'] ?? '') == 'color-page-light-green') active @endif" rel="color-page-light-green"></div>
								<div class="box-theme bg-green @if(($sximoconfig['cnf_page_color'] ?? '') == 'color-page-green') active @endif" rel="color-page-green"></div>
								<div class="box-theme bg-orange @if(($sximoconfig['cnf_page_color'] ?? '') == 'color-page-orange') active @endif" rel="color-page-orange"></div>
								<div class="box-theme bg-yellow @if(($sximoconfig['cnf_page_color'] ?? '') == 'color-page-yellow') active @endif" rel="color-page-yellow"></div>
								<div class="box-theme bg-blue-grey @if(($sximoconfig['cnf_page_color'] ?? '') == 'color-page-blue-grey') active @endif" rel="color-page-blue-grey"></div> 
								@php $is_custom_page = (strpos($sximoconfig['cnf_page_color'] ?? '', '#') === 0); @endphp
								<input type="color" class="box-theme-custom @if($is_custom_page) active @endif" value="{{ $is_custom_page ? $sximoconfig['cnf_page_color'] : '#ffffff' }}" title="Custom Color" />
							</div> 
						</div>

						<div class="form-group row">
							<label for="ipt_footer_color" class=" control-label col-md-3">Footer</label>
							<div class="col-md-8 c-f">
								<div class="box-theme bg-dark @if(($sximoconfig['cnf_footer_color'] ?? '') == 'color-footer-dark') active @endif" rel="color-footer-dark"></div>
								<div class="box-theme bg-white @if(($sximoconfig['cnf_footer_color'] ?? '') == 'color-footer-white') active @endif" rel="color-footer-white"></div>
								<div class="box-theme bg-blue @if(($sximoconfig['cnf_footer_color'] ?? '') == 'color-footer-blue') active @endif" rel="color-footer-blue"></div>
								<div class="box-theme bg-indigo @if(($sximoconfig['cnf_footer_color'] ?? '') == 'color-footer-indigo') active @endif" rel="color-footer-indigo"></div>
								<div class="box-theme bg-deep-purple @if(($sximoconfig['cnf_footer_color'] ?? '') == 'color-footer-deep-purple') active @endif" rel="color-footer-deep-purple"></div>
								<div class="box-theme bg-purple @if(($sximoconfig['cnf_footer_color'] ?? '') == 'color-footer-purple') active @endif" rel="color-footer-purple"></div>
								<div class="box-theme bg-pink @if(($sximoconfig['cnf_footer_color'] ?? '') == 'color-footer-pink') active @endif" rel="color-footer-pink"></div>
								<div class="box-theme bg-red @if(($sximoconfig['cnf_footer_color'] ?? '') == 'color-footer-red') active @endif" rel="color-footer-red"></div>
								<div class="box-theme bg-teal @if(($sximoconfig['cnf_footer_color'] ?? '') == 'color-footer-teal') active @endif" rel="color-footer-teal"></div>
								<div class="box-theme bg-cyan @if(($sximoconfig['cnf_footer_color'] ?? '') == 'color-footer-cyan') active @endif" rel="color-footer-cyan"></div>
								<div class="box-theme bg-light-blue @if(($sximoconfig['cnf_footer_color'] ?? '') == 'color-footer-light-blue') active @endif" rel="color-footer-light-blue"></div>
								<div class="box-theme bg-light-green @if(($sximoconfig['cnf_footer_color'] ?? '') == 'color-footer-light-green') active @endif" rel="color-footer-light-green"></div>
								<div class="box-theme bg-green @if(($sximoconfig['cnf_footer_color'] ?? '') == 'color-footer-green') active @endif" rel="color-footer-green"></div>
								<div class="box-theme bg-orange @if(($sximoconfig['cnf_footer_color'] ?? '') == 'color-footer-orange') active @endif" rel="color-footer-orange"></div>
								<div class="box-theme bg-yellow @if(($sximoconfig['cnf_footer_color'] ?? '') == 'color-footer-yellow') active @endif" rel="color-footer-yellow"></div>
								<div class="box-theme bg-blue-grey @if(($sximoconfig['cnf_footer_color'] ?? '') == 'color-footer-blue-grey') active @endif" rel="color-footer-blue-grey"></div> 
								@php $is_custom_footer = (strpos($sximoconfig['cnf_footer_color'] ?? '', '#') === 0); @endphp
								<input type="color" class="box-theme-custom @if($is_custom_footer) active @endif" value="{{ $is_custom_footer ? $sximoconfig['cnf_footer_color'] : '#ffffff' }}" title="Custom Color" />
							</div> 
						</div>
					</div>

					<div class="col-sm-6">
						<div class="form-group row">
							<label for="cnf_theme" class="control-label col-sm-4"> {{ Lang::get('core.fr_fronttemplate') }} </label>
							<div class="col-sm-8">
								<select class="form-control form-control-sm" name="cnf_theme" required="true">
									<option value="">Select Frontend Template</option>
									@foreach(SiteHelpers::themeOption() as $t)
										<option value="{{ $t['folder'] }}" @if($sximoconfig['cnf_theme'] == $t['folder']) selected @endif>
											{{ $t['name'] }}
										</option>
									@endforeach
								</select>
							</div>
						</div>

						<div class="form-group row">
							<label for="cnf_backend" class="control-label col-sm-4"> Backend Template </label>
							<div class="col-sm-8">
								<select class="form-control form-control-sm" name="cnf_backend" required="true">
									<option value="minimal">Select Backend Template</option>
									@foreach(SiteHelpers::backendOption() as $t)
										<option value="{{ $t['folder'] }}" @if($sximoconfig['cnf_backend'] == $t['folder']) selected @endif>
											{{ $t['name'] }}
										</option>
									@endforeach
								</select>
							</div>
						</div>

						<div class="form-group row">
							<label class="control-label col-sm-4">&nbsp;</label>
							<div class="col-sm-8">
								<button class="btn btn-primary" type="submit">{{ Lang::get('core.sb_savechanges') }}</button>
							</div>
						</div>
					</div>
				</div>
			</form>
		</div>
	</div>
</div>

<style type="text/css">
	.box-theme {
		width: 20px;
		height: 20px;
		float: left;
		margin: 0 10px 10px 0;
		cursor: pointer;
		border-radius: 2px;
	}
	.box-theme.active {
		outline: 2px solid #5d9cec;
		outline-offset: 2px;
	}
	.box-theme-custom {
		width: 20px;
		height: 20px;
		float: left;
		margin: 0 10px 10px 0;
		cursor: pointer;
		border: 1px solid #ddd;
		border-radius: 2px;
		padding: 0;
		background: none;
	}
	.box-theme-custom.active {
		outline: 2px solid #5d9cec;
		outline-offset: 2px;
	}
</style>

<script type="text/javascript">
$(document).ready(function() {
	$('.c-t .box-theme').click(function() {
		var color = $(this).attr('rel');
		$('#cnf_text_color').val(color);
		$('.c-t .box-theme').removeClass('active');
		$('.c-t .box-theme-custom').removeClass('active');
		$(this).addClass('active');
		$('body').removeAttr('style').removeClass(function(i, c) {
			return (c.match(/(^|\s)color-text-\S+/g) || []).join(' ');
		}).addClass(color);
	});
	$('.c-t .box-theme-custom').on('input change', function() {
		var color = $(this).val();
		$('#cnf_text_color').val(color);
		$('.c-t .box-theme').removeClass('active');
		$(this).addClass('active');
		$('body, body i, body label, body p, body span:not(.badge):not(.btn), body a:not(.btn), body td, body th, body h1, body h2, body h3, body h4, body h5, body h6, body li').attr('style', function(i, s) { return (s || '') + 'color: ' + color + ' !important;'; });
	});

	$('.c-h .box-theme').click(function() {
		var color = $(this).attr('rel');
		$('#cnf_header_color').val(color);
		$('.c-h .box-theme').removeClass('active');
		$('.c-h .box-theme-custom').removeClass('active');
		$(this).addClass('active');
		$('.topbar').removeAttr('style').removeClass(function(i, c) {
			return (c.match(/(^|\s)color-head-\S+/g) || []).join(' ');
		}).addClass('topbar ' + color);
	});
	$('.c-h .box-theme-custom').on('input change', function() {
		var color = $(this).val();
		$('#cnf_header_color').val(color);
		$('.c-h .box-theme').removeClass('active');
		$(this).addClass('active');
		$('.topbar').attr('style', 'background: ' + color + ' !important;');
	});

	$('.c-l .box-theme').click(function() {
		var color = $(this).attr('rel');
		$('#cnf_logo_color').val(color);
		$('.c-l .box-theme').removeClass('active');
		$('.c-l .box-theme-custom').removeClass('active');
		$(this).addClass('active');
		$('.navbar-header').removeAttr('style').removeClass(function(i, c) {
			return (c.match(/(^|\s)color-logo-\S+/g) || []).join(' ');
		}).addClass('navbar-header ' + color);
	});
	$('.c-l .box-theme-custom').on('input change', function() {
		var color = $(this).val();
		$('#cnf_logo_color').val(color);
		$('.c-l .box-theme').removeClass('active');
		$(this).addClass('active');
		$('.navbar-header').attr('style', 'background: ' + color + ' !important;');
	});

	$('.c-s .box-theme').click(function() {
		var color = $(this).attr('rel');
		$('#cnf_sidebar_color').val(color);
		$('.c-s .box-theme').removeClass('active');
		$('.c-s .box-theme-custom').removeClass('active');
		$(this).addClass('active');
		$('.left-sidebar').removeAttr('style').removeClass(function(i, c) {
			return (c.match(/(^|\s)color-sidebar-\S+/g) || []).join(' ');
		}).addClass('left-sidebar ' + color);
	});
	$('.c-s .box-theme-custom').on('input change', function() {
		var color = $(this).val();
		$('#cnf_sidebar_color').val(color);
		$('.c-s .box-theme').removeClass('active');
		$(this).addClass('active');
		$('.left-sidebar').attr('style', 'background: ' + color + ' !important;');
	});

	$('.c-c .box-theme').click(function() {
		var color = $(this).attr('rel');
		$('#cnf_card_color').val(color);
		$('.c-c .box-theme').removeClass('active');
		$('.c-c .box-theme-custom').removeClass('active');
		$(this).addClass('active');
		$('.card-body').removeAttr('style').removeClass(function (index, className) {
			return (className.match(/(^|\s)color-card-\S+/g) || []).join(' ');
		}).addClass(color);
		$.cookie("sximo-card", color , {expires: 365, path: '/'});
	});
	$('.c-c .box-theme-custom').on('input change', function() {
		var color = $(this).val();
		$('#cnf_card_color').val(color);
		$('.c-c .box-theme').removeClass('active');
		$(this).addClass('active');
		$('.card-body').attr('style', 'background: ' + color + ' !important; color: #fff !important;');
		$('.card-body label, .card-body p, .card-body h4').attr('style', 'color: #fff !important;');
		$.cookie("sximo-card", color , {expires: 365, path: '/'});
	});

	$('.c-p .box-theme').click(function() {
		var color = $(this).attr('rel');
		$('#cnf_page_color').val(color);
		$('.c-p .box-theme').removeClass('active');
		$('.c-p .box-theme-custom').removeClass('active');
		$(this).addClass('active');
		$('#pagebody').removeAttr('style').removeClass(function (index, className) {
			return (className.match(/(^|\s)color-page-\S+/g) || []).join(' ');
		}).addClass(color);
		$.cookie("sximo-page", color , {expires: 365, path: '/'});
	});
	$('.c-p .box-theme-custom').on('input change', function() {
		var color = $(this).val();
		$('#cnf_page_color').val(color);
		$('.c-p .box-theme').removeClass('active');
		$(this).addClass('active');
		$('#pagebody').attr('style', 'background: ' + color + ' !important;');
		$.cookie("sximo-page", color , {expires: 365, path: '/'});
	});

	$('.c-f .box-theme').click(function() {
		var color = $(this).attr('rel');
		$('#cnf_footer_color').val(color);
		$('.c-f .box-theme').removeClass('active');
		$('.c-f .box-theme-custom').removeClass('active');
		$(this).addClass('active');
		$('.footer').removeAttr('style').removeClass(function (index, className) {
			return (className.match(/(^|\s)color-footer-\S+/g) || []).join(' ');
		}).addClass(color);
		$.cookie("sximo-footer", color , {expires: 365, path: '/'});
	});
	$('.c-f .box-theme-custom').on('input change', function() {
		var color = $(this).val();
		$('#cnf_footer_color').val(color);
		$('.c-f .box-theme').removeClass('active');
		$(this).addClass('active');
		$('.footer').attr('style', 'background: ' + color + ' !important; color: #fff !important;');
		$.cookie("sximo-footer", color , {expires: 365, path: '/'});
	});
});
</script>
@stop
