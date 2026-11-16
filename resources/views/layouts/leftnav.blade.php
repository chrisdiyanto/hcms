<?php
$sidebar = SiteHelpers::menus('sidebar') ;
$active = Request::segment(1);

// Ensure theme variables are set
$sximoconfig = $sximoconfig ?? config('sximo');
$isDarkMode = $isDarkMode ?? (isset($_COOKIE['dark_mode']) && $_COOKIE['dark_mode'] === 'true');
$sidebarColor = $sidebarColor ?? ($sximoconfig['cnf_sidebar_color'] ?? '');
?>
<aside class="left-sidebar {{ (!$isDarkMode && $sidebarColor && strpos($sidebarColor, '#') !== 0) ? $sidebarColor : '' }}" style="{{ (!$isDarkMode && $sidebarColor && strpos($sidebarColor, '#') === 0) ? 'background: ' . $sidebarColor . ' !important;' : '' }}">
    <div class="sidebar-profile" >
        <div class="row">
            <div class="col-3">
                {!! SiteHelpers::avatar( 42 ) !!}
            </div>
            <div class="col-9">
                <a href="{{ url('user/profile') }}">
                    <b>{{ Session::get('fid')}}</b>
                    <p>{{ Session::get('eid')}} </p>
                </a>
            </div>
        </div>    
    </div>
            <!-- Sidebar scroll-->
            <div class="scroll-sidebar">
                <!-- Sidebar navigation-->
                <nav class="sidebar-nav">
                    <ul id="sidebarnav">
                @php
                    // Fetch all icons once to prevent N+1 queries during the loop
                    $allIcons = \DB::table('tb_icons')->get()->keyBy('icon_class');
                @endphp

                @foreach ($sidebar as $menu)   

                    @if($menu['module'] =='separator')
                      <li class="nav-small-cap"> <span> {{$menu['menu_name']}} </span></li>  

                    @else
                    <li>
                        @php
                            $renderedIcon = '<i class="'.e($menu['menu_icons']).'"></i>';
                            if(!empty($menu['menu_icons'])) {
                                $iconRecord = $allIcons->get($menu['menu_icons']);
                                if($iconRecord) {
                                    // SVG dirender inline di setiap halaman: hanya tampilkan bila bebas script
                                    if($iconRecord->type == 'code' && \SiteHelpers::isSafeSvgString($iconRecord->svg_code)) {
                                        $renderedIcon = '<span class="menu-svg-icon" style="width: 20px; height: 20px; display: inline-block; vertical-align: middle; margin-right: 10px;">'.$iconRecord->svg_code.'</span>';
                                    } elseif($iconRecord->type == 'file') {
                                        $renderedIcon = '<img src="'.e(asset($iconRecord->file_path)).'" alt="'.e($iconRecord->name).'" style="width: 20px; height: 20px; margin-right: 10px;">';
                                    } elseif($iconRecord->type == 'url' && preg_match('~^(https?:)?//|^/~i', (string) $iconRecord->icon_url)) {
                                        $renderedIcon = '<img src="'.e($iconRecord->icon_url).'" alt="'.e($iconRecord->name).'" style="width: 20px; height: 20px; margin-right: 10px;">';
                                    }
                                }
                            }
                        @endphp
                        
                        @if(count($menu['childs']) > 0 ) 
                            <a class="has-arrow waves-effect waves-dark" href="#" aria-expanded="false">
                                {!! $renderedIcon !!}
                                <span class="hide-menu">
                                    {{ (isset($menu['menu_lang']['title'][session('lang')]) ? $menu['menu_lang']['title'][session('lang')] : $menu['menu_name']) }}
                                </span>
                            </a>
                        @else
                            <a href="@if($menu['menu_type'] == 'external'){{ $menu['url'] }}@else{{ url($menu['module']) }}@endif" >
                                    {!! $renderedIcon !!}
                                    <span class="hide-menu">
                                        {{ (isset($menu['menu_lang']['title'][session('lang')]) ? $menu['menu_lang']['title'][session('lang')] : $menu['menu_name']) }}
                                    </span>
                            </a>                               

                        @endif 

                            <!-- LEVEL 2 -->
                         @if(count($menu['childs']) > 0 ) 
                            <ul aria-expanded="false" >
                             @foreach ($menu['childs'] as $menu2)
                                 <li>
                                    @if(count($menu2['childs']) > 0 )
                                    <a class="has-arrow" href="#" aria-expanded="false">
                                        {{ (isset($menu2['menu_lang']['title'][session('lang')]) ? $menu2['menu_lang']['title'][session('lang')] : $menu2['menu_name']) }}
                                    </a>
                                    @else 
                                    <a href="@if($menu2['menu_type'] == 'external'){{ $menu2['url'] }}@else{{ url($menu2['module']) }}@endif">
                                        {{ (isset($menu2['menu_lang']['title'][session('lang')]) ? $menu2['menu_lang']['title'][session('lang')] : $menu2['menu_name']) }}
                                    </a>

                                    @endif

                                    @if(count($menu2['childs']) > 0 )
                                        <ul aria-expanded="false" >
                                            @foreach ($menu2['childs'] as $menu3)
                                                 <li>
                                                    <a href="@if($menu3['menu_type'] == 'external'){{ $menu3['url'] }}@else{{ url($menu3['module']) }}@endif">
                                                        {{ (isset($menu3['menu_lang']['title'][session('lang')]) ? $menu3['menu_lang']['title'][session('lang')] : $menu3['menu_name']) }}
                                                    </a>
                                                  </li>
                                            @endforeach    


                                        </ul>


                                    @endif 


                                </li>

                             @endforeach

                            </ul> 
                         @endif


                    </li>      
                    @endif
                @endforeach        

            </ul>
        </nav>
        <!-- End Sidebar navigation -->
    </div>
    <!-- End Sidebar scroll-->
</aside>