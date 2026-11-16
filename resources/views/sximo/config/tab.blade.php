<?php

$tabs = array(
		'' 		        => ''. Lang::get('core.tab_siteinfo'),
		'ai' 		    => ''. Lang::get('core.tab_ai'),
		'rag' 		    => ''. Lang::get('core.tab_rag'),
		'email'			=> ' '. Lang::get('core.tab_email'),
		'security'		=> ' '. Lang::get('core.tab_loginsecurity') ,
		'translation'	=>' '.Lang::get('core.tab_translation'),
		'design'		=>' '.Lang::get('core.tab_design'),
	);

?>
<div class="p-2">
<ul class="nav nav-tabs" class="p-2">
@foreach($tabs as $key=>$val)
	<li class="nav-item">
		 <a class="nav-link @if($key == $active) active @endif" href="{{ URL::to('sximo/config/'.$key)}}">{!! $val !!} </a>
	</li>
@endforeach

</ul>
</div>