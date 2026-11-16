<?php $sximoconfig  = config('sximo');?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{{ config('sximo.cnf_appname') }}</title>
<link rel="shortcut icon" href="{{ asset('favicon.ico')}}" type="image/x-icon">

<!-- Bootstrap Core CSS -->
    @include('partials.bootstrap-css')
	<link href="{{ asset('sximo5/sximo.min.css')}}" rel="stylesheet">
    <!-- Legacy  Custom CSS for old sximo layout -->
	<link href="{{ asset('sximo5/css/core.css')}}" rel="stylesheet">
	@if(session('themes') !='')
	<link href="{{ asset('')}}assets/template/css/colors/{{ session('themes')}}.css" id="theme" rel="stylesheet">
	@else
	<link href="{{ asset('')}}assets/template/css/colors/gray.css" id="theme" rel="stylesheet">
	@endif
    <!-- jQuery UI 1.11.4 -->
	<script type="text/javascript" src="{{ asset('sximo5/sximo.min.js') }}"></script>
	@include('partials.bootstrap-js')
		<!--[if lt IE 9]>
			<script src="https://oss.maxcdn.com/libs/html5shiv/3.7.0/html5shiv.js"></script>
			<script src="https://oss.maxcdn.com/libs/respond.js/1.4.2/respond.min.js"></script>
		<![endif]-->	

	
	
  	</head>

	<body onload="window.print()">
		{!! $html !!}
	
		<script type="text/javascript">
			$(function(){
				$('.box-header').hide();
			})
		</script>
	</body>

</html>