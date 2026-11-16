<?php
$sximoconfig = $sximoconfig ?? config('sximo');
$isDarkMode = $isDarkMode ?? (isset($_COOKIE['dark_mode']) && $_COOKIE['dark_mode'] === 'true');
$headerColor = $headerColor ?? ($sximoconfig['cnf_header_color'] ?? '');
$logoColor = $logoColor ?? ($sximoconfig['cnf_logo_color'] ?? '');
?>
        <header class="topbar {{ (!$isDarkMode && $headerColor && strpos($headerColor, '#') !== 0) ? $headerColor : '' }}" style="{{ (!$isDarkMode && $headerColor && strpos($headerColor, '#') === 0) ? 'background: ' . $headerColor . ' !important;' : '' }}">
            <nav class="navbar top-navbar navbar-expand-md navbar-light">
                <!-- ============================================================== -->
                <!-- Logo -->
                <!-- ============================================================== -->
                <div class="navbar-header {{ (!$isDarkMode && $logoColor && strpos($logoColor, '#') !== 0) ? $logoColor : '' }}" style="{{ (!$isDarkMode && $logoColor && strpos($logoColor, '#') === 0) ? 'background: ' . $logoColor . ' !important;' : '' }}">
                    <a class="navbar-brand" href="{{ url('dashboard')}}">
                        <!-- Logo icon -->
                        <b>
                            @if(file_exists(public_path().'/uploads/images/'.config('sximo')['cnf_logo']) && config('sximo')['cnf_logo'] !='')
                              <img src="{{ asset('uploads/images/'.config('sximo')['cnf_logo'])}}" alt="{{ config('sximo')['cnf_appname'] }}" width="40" />
                            @else
                              <img src="{{ asset('')}}assets/images/logo-icon.png" alt="homepage" class="dark-logo" />
                            @endif

                        </b>
                        <!--End Logo icon -->
                        <!-- Logo text -->
                        <span class="dark-logo" style=" font-weight: 500; padding-left: 10px;">
                            {{ config('sximo.cnf_appname') }} 
                        </span> 
                    </a>
                </div>
                <!-- ============================================================== -->
                <!-- End Logo -->
                <!-- ============================================================== -->
                <div class="navbar-collapse">
                    <!-- ============================================================== -->
                    <!-- toggle and nav items -->
                    <!-- ============================================================== -->
                    <ul class="navbar-nav me-auto ">
                        <!-- This is  -->
                        <li class="nav-item"> <a class="nav-link nav-toggler sidebartoggler  waves-effect waves-dark" href="javascript:void(0)"><i class="ti-menu"></i></a> </li>
                       
                        <li class="nav-item "> 
                            <a class="nav-link  waves-effect waves-dark" href="{{  url('')}}">
                                <i class="icon-globe"></i>
                            </a>
                        </li>
                       
                    </ul>

                    
                    <!-- ============================================================== -->
                    <!-- User profile and search -->
                    <!-- ============================================================== -->
                    <ul class="navbar-nav my-lg-0">
                        

                        <!-- Dark/Light Theme Toggle -->
                        <li class="nav-item" style="display: flex; align-items: center;">
                            <a class="nav-link waves-effect waves-dark" id="theme-toggle" href="javascript:void(0);" title="Toggle Dark/Light Mode" style="display: flex; align-items: center; justify-content: center; height: 100%; min-width: 50px; padding: 0 15px;">
                                <!-- Sun Icon (shown in dark mode) -->
                                <span id="theme-icon-sun" style="{{ $isDarkMode ? 'display: inline-block;' : 'display: none;' }}">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#ffffff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="5"></circle><line x1="12" y1="1" x2="12" y2="3"></line><line x1="12" y1="21" x2="12" y2="23"></line><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"></line><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"></line><line x1="1" y1="12" x2="3" y2="12"></line><line x1="21" y1="12" x2="23" y2="12"></line><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"></line><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"></line></svg>
                                </span>
                                <!-- Moon Icon (shown in light mode) -->
                                <span id="theme-icon-moon" style="{{ !$isDarkMode ? 'display: inline-block;' : 'display: none;' }}">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"></path></svg>
                                </span>
                            </a>
                        </li>

                        <!-- ============================================================== -->
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle waves-effect waves-dark" href="#" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false"> <i class="mdi mdi-message"></i>
                               
                            </a>
                            <div class="dropdown-menu dropdown-menu-end mailbox animated flipInX">
                                <ul>
                                    <li>
                                        <div class="drop-title">Notifications</div>
                                    </li>
                                    <li>
                                        <div class="message-center">
                                            <!-- Message -->
                                            <a href="#">
                                                <div class="btn btn-danger btn-circle"><i class="fa fa-link"></i></div>
                                                <div class="mail-contnet">
                                                    <h5>Sample Notification</h5> <span class="mail-desc">Just see the my new admin!</span> <span class="time">9:30 AM</span> </div>
                                            </a>
                                            
                                        </div>
                                    </li>
                                    <li>
                                        <a class="nav-link text-center" href="{{ url('notification') }}"> <strong>Check all notifications</strong> <i class="fa fa-angle-right"></i> </a>
                                    </li>
                                </ul>
                            </div>
                        </li>

                        @php
                            $activeUserUnits = (isset($userUnits) ? $userUnits : collect())->filter(function($u) {
                                return empty($u->end_date) || is_zero_date($u->end_date);
                            })->values();

                            $userUnitsCount = count($activeUserUnits);
                            $userGroupsCount = isset($userGroups) ? count($userGroups) : 0;
                            $hasMultiUnits = $userUnitsCount > 1;
                            $hasMultiGroups = $userGroupsCount > 1;
                            $currentUnit = (isset($userUnits) ? $userUnits : collect())->firstWhere('id_unit', $currentUnitId);
                            $currentGroup = (isset($userGroups) ? $userGroups : collect())->firstWhere('group_id', $currentGid);
                        @endphp

                        @if($hasMultiUnits || $hasMultiGroups)
                        <!-- Modern Unified Unit & Role Context Switcher -->
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle waves-effect waves-dark" href="#" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false" title="Ganti Unit Kerja & Peran">
                                <i class="fa fa-id-badge" style="position: relative; font-size: 17px;">
                                    <span class="context-indicator-dot" title="Multi-konteks tersedia"></span>
                                </i>
                            </a>
                            <div class="dropdown-menu dropdown-menu-end animated flipInX context-dropdown-menu">
                                <!-- Dropdown Header -->
                                <div class="context-dropdown-header">
                                    <div class="d-flex align-items-center justify-content-between">
                                        <div class="d-flex align-items-center">
                                            <div class="context-header-avatar">
                                                <i class="fa fa-id-badge"></i>
                                            </div>
                                            <div class="ms-2">
                                                <div class="context-header-title">Unit Kerja & Peran</div>
                                                <div class="context-header-subtitle">Pilih konteks kerja aktif Anda</div>
                                            </div>
                                        </div>
                                        <span class="badge context-count-badge">
                                            {{ ($hasMultiUnits && $hasMultiGroups) ? 'Unit & Peran' : ($hasMultiUnits ? 'Unit Kerja' : 'Peran') }}
                                        </span>
                                    </div>
                                    
                                    <!-- Current Active Context Summary Bar -->
                                    <div class="context-active-summary mt-2">
                                        <div class="active-summary-row">
                                            <span class="summary-label"><i class="fa fa-building me-1"></i> Unit:</span>
                                            <span class="summary-val text-truncate" title="{{ $currentUnit->unit_name ?? '-' }}">{{ $currentUnit->unit_name ?? '-' }}</span>
                                        </div>
                                        <div class="active-summary-row mt-1">
                                            <span class="summary-label"><i class="fa fa-users me-1"></i> Peran:</span>
                                            <span class="summary-val text-truncate" title="{{ $currentGroup->name ?? '-' }}">{{ $currentGroup->name ?? '-' }}</span>
                                        </div>
                                    </div>
                                </div>

                                <!-- Dropdown Body -->
                                <div class="context-dropdown-body custom-scrollbar">
                                    <!-- Hidden Forms for Switching -->
                                    <form id="form-switch-unit" action="{{ url('user/switch-unit') }}" method="POST" style="display: none;">
                                        @csrf
                                        <input type="hidden" name="id_unit" id="input-switch-unit" value="">
                                    </form>
                                    <form id="form-switch-group" action="{{ url('user/switch-group') }}" method="POST" style="display: none;">
                                        @csrf
                                        <input type="hidden" name="group_id" id="input-switch-group" value="">
                                    </form>

                                    @if($hasMultiUnits)
                                    <!-- Unit Kerja Section -->
                                    <div class="context-section">
                                        <div class="context-section-title">
                                            <span><i class="fa fa-building text-primary me-1"></i> Unit Kerja</span>
                                            <span class="context-section-counter">{{ $userUnitsCount }} pilihan</span>
                                        </div>
                                        <div class="context-items-list custom-scrollbar">
                                            @foreach($activeUserUnits as $u)
                                                @php $isUnitActive = ($currentUnitId == $u->id_unit); @endphp
                                                <div class="context-option-card {{ $isUnitActive ? 'active' : '' }}" 
                                                     onclick="handleSwitchUnit('{{ $u->id_unit }}', {{ $isUnitActive ? 'true' : 'false' }})"
                                                     onkeydown="if(event.key === 'Enter' || event.keyCode === 13) handleSwitchUnit('{{ $u->id_unit }}', {{ $isUnitActive ? 'true' : 'false' }})"
                                                     role="button"
                                                     tabindex="0"
                                                     title="{{ $isUnitActive ? 'Unit sedang aktif' : 'Klik untuk beralih ke unit ini' }}">
                                                    <div class="option-icon-box {{ $isUnitActive ? 'bg-primary-soft text-primary' : 'bg-muted-soft text-muted' }}">
                                                        <i class="fa fa-building"></i>
                                                    </div>
                                                    <div class="option-text-info">
                                                        <div class="option-main-text">{{ $u->unit_name }}</div>
                                                        @if(!empty($u->position_name))
                                                            <div class="option-sub-text"><i class="fa fa-briefcase me-1"></i>{{ $u->position_name }}</div>
                                                        @endif
                                                    </div>
                                                    <div class="option-state-indicator">
                                                        @if($isUnitActive)
                                                            <span class="active-check-badge"><i class="fa fa-check"></i> Aktif</span>
                                                        @else
                                                            <span class="inactive-bullet"></span>
                                                        @endif
                                                    </div>
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                    @endif

                                    @if($hasMultiUnits && $hasMultiGroups)
                                        <div class="context-section-divider"></div>
                                    @endif

                                    @if($hasMultiGroups)
                                    <!-- Peran / Group Section -->
                                    <div class="context-section">
                                        <div class="context-section-title">
                                            <span><i class="fa fa-users text-info me-1"></i> Peran / Group</span>
                                            <span class="context-section-counter">{{ $userGroupsCount }} pilihan</span>
                                        </div>
                                        <div class="context-items-list custom-scrollbar">
                                            @foreach($userGroups as $g)
                                                @php $isGroupActive = ($currentGid == $g->group_id); @endphp
                                                <div class="context-option-card {{ $isGroupActive ? 'active' : '' }}" 
                                                     onclick="handleSwitchGroup('{{ $g->group_id }}', {{ $isGroupActive ? 'true' : 'false' }})"
                                                     onkeydown="if(event.key === 'Enter' || event.keyCode === 13) handleSwitchGroup('{{ $g->group_id }}', {{ $isGroupActive ? 'true' : 'false' }})"
                                                     role="button"
                                                     tabindex="0"
                                                     title="{{ $isGroupActive ? 'Peran sedang aktif' : 'Klik untuk beralih ke peran ini' }}">
                                                    <div class="option-icon-box {{ $isGroupActive ? 'bg-info-soft text-info' : 'bg-muted-soft text-muted' }}">
                                                        <i class="fa fa-users"></i>
                                                    </div>
                                                    <div class="option-text-info">
                                                        <div class="option-main-text">{{ $g->name }}</div>
                                                    </div>
                                                    <div class="option-state-indicator">
                                                        @if($isGroupActive)
                                                            <span class="active-check-badge active-group-badge"><i class="fa fa-check"></i> Aktif</span>
                                                        @else
                                                            <span class="inactive-bullet"></span>
                                                        @endif
                                                    </div>
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                    @endif
                                </div>
                                
                                <!-- Dropdown Footer -->
                                <div class="context-dropdown-footer">
                                    <i class="fa fa-info-circle me-1 text-muted"></i> Klik salah satu item untuk berganti konteks
                                </div>
                            </div>
                        </li>
                        @endif
                        <!-- ============================================================== -->
                        <!-- End Comment -->
                        <!-- ============================================================== -->

                        <!-- Language -->
                        <!-- ============================================================== -->
                        @if($sximoconfig['cnf_multilang'] ==1)
                         <li class="nav-item dropdown  hidden-xs-down">
                          <?php 
                          $flag ='en';
                          $langname = 'English'; 
                          foreach(SiteHelpers::langOption() as $lang):
                            if($lang['folder'] == session('lang') or $lang['folder'] == $sximoconfig['cnf_lang']) {
                              $flag = $lang['folder'];
                              $langname = $lang['name']; 
                            }
                            
                          endforeach;?>
                          <a class="nav-link dropdown-toggle waves-effect waves-dark" href="#" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false"> <img class="flag-lang" src="{{ asset('assets/plugins/images/flags/'.$flag.'.png') }}" width="16" height="16" alt="lang" /> </a>

                          <div class="dropdown-menu dropdown-menu-end animated bounceInDown">
                             @foreach(SiteHelpers::langOption() as $lang)
                             <a class="dropdown-item" href="{{ url('home/lang/'.$lang['folder'])}}">
                                <img class="flag-lang" src="{{ asset('assets/plugins/images/flags/'. $lang['folder'].'.png')}}" width="16" height="16" alt="lang"  /> {{  $lang['name'] }}
                               </a>

                            @endforeach 


                          </div>
                        </li>  
                        @endif 
                        @php
                            $top_menus = SiteHelpers::menus('topnav');
                        @endphp
                        @foreach ($top_menus as $menu)
                            @if(count($menu['childs']) > 0)
                                <li class="nav-item dropdown">
                                    <a class="nav-link dropdown-toggle waves-effect waves-dark" href="#" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false" title="{{ (isset($menu['menu_lang']['title'][session('lang')]) ? $menu['menu_lang']['title'][session('lang')] : $menu['menu_name']) }}">
                                        @if(!empty($menu['menu_icons']))
                                            <i class="{{ $menu['menu_icons'] }}"></i>
                                        @else
                                            <i class="fa fa-bars"></i>
                                        @endif
                                    </a>
                                    <div class="dropdown-menu dropdown-menu-end animated">
                                        <ul class="dropdown-user">
                                            @foreach ($menu['childs'] as $menu2)
                                                @if($menu2['module'] == 'separator')
                                                    <li role="separator" class="divider"></li>
                                                @else
                                                    <li>
                                                        <a href="{{ url($menu2['module']) }}"
                                                            @if($menu2['module'] == 'sximo/server')
                                                                onclick="SximoModal(this.href, 'About Sximo'); return false "
                                                            @elseif($menu2['module'] == 'sximo/config/clearlog')
                                                                class="clearCache"
                                                            @endif
                                                        >
                                                            @if(!empty($menu2['menu_icons']))
                                                                <i class="{{ $menu2['menu_icons'] }}"></i>
                                                            @endif
                                                            {{ (isset($menu2['menu_lang']['title'][session('lang')]) ? $menu2['menu_lang']['title'][session('lang')] : $menu2['menu_name']) }}
                                                        </a>
                                                    </li>
                                                @endif
                                            @endforeach
                                        </ul>
                                    </div>
                                </li>
                            @else
                                <li class="nav-item">
                                    <a class="nav-link waves-effect waves-dark" href="{{ url($menu['module']) }}" title="{{ (isset($menu['menu_lang']['title'][session('lang')]) ? $menu['menu_lang']['title'][session('lang')] : $menu['menu_name']) }}">
                                        @if(!empty($menu['menu_icons']))
                                            <i class="{{ $menu['menu_icons'] }}"></i>
                                        @else
                                            <i class="fa fa-link"></i>
                                        @endif
                                    </a>
                                </li>
                            @endif
                        @endforeach
                        <li class="nav-item"><a href="{{ url('user/logout')}}" class="nav-link confirmLogout"><i class="fa fa-power-off"></i></a></li>
                      
                        
                    </ul>
                </div>
            </nav>
        </header>

<style>
/* Modern Unified Context Switcher Dropdown */
.context-indicator-dot {
    position: absolute;
    top: -2px;
    right: -4px;
    width: 7px;
    height: 7px;
    background: #10b981;
    border: 1.5px solid #ffffff;
    border-radius: 50%;
    box-shadow: 0 0 0 1px rgba(16, 185, 129, 0.4);
    display: inline-block;
}
body.dark-theme .context-indicator-dot {
    border-color: #141822;
}

.context-dropdown-menu {
    min-width: 340px !important;
    max-width: 380px !important;
    padding: 0 !important;
    border-radius: 14px !important;
    border: 1px solid var(--border-color, #e2e8f0) !important;
    background: var(--surface-bg, #ffffff) !important;
    box-shadow: 0 20px 45px rgba(15, 23, 42, 0.16), 0 4px 12px rgba(15, 23, 42, 0.08) !important;
    overflow: hidden;
    margin-top: 10px !important;
}

.context-dropdown-header {
    padding: 14px 16px 12px;
    background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%);
    color: #ffffff;
}
body.dark-theme .context-dropdown-header {
    background: linear-gradient(135deg, #18202d 0%, #0d1219 100%);
    border-bottom: 1px solid #242c3d;
}

.context-header-avatar {
    width: 34px;
    height: 34px;
    border-radius: 9px;
    background: rgba(255, 255, 255, 0.12);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 16px;
    color: #38bdf8;
    backdrop-filter: blur(4px);
}

.context-header-title {
    font-size: 13.5px;
    font-weight: 700;
    letter-spacing: -0.2px;
    color: #ffffff;
    line-height: 1.2;
}
.context-header-subtitle {
    font-size: 11px;
    color: rgba(255, 255, 255, 0.72);
    margin-top: 2px;
}

.context-count-badge {
    background: rgba(56, 189, 248, 0.2) !important;
    color: #38bdf8 !important;
    border: 1px solid rgba(56, 189, 248, 0.35);
    font-size: 10.5px !important;
    font-weight: 600;
    padding: 3px 9px !important;
    border-radius: 6px !important;
}

.context-active-summary {
    background: rgba(255, 255, 255, 0.08);
    border: 1px solid rgba(255, 255, 255, 0.12);
    border-radius: 8px;
    padding: 7px 10px;
    font-size: 11.5px;
}
.active-summary-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
}
.summary-label {
    color: rgba(255, 255, 255, 0.75);
    font-size: 11px;
}
.summary-val {
    font-weight: 600;
    color: #ffffff;
    max-width: 210px;
    text-align: right;
}

.context-dropdown-body {
    padding: 10px 12px;
    max-height: 380px;
    overflow-y: auto;
}

.context-section-title {
    display: flex;
    align-items: center;
    justify-content: space-between;
    font-size: 10.5px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.6px;
    color: var(--text-muted, #64748b);
    padding: 4px 4px 6px;
}
.context-section-counter {
    background: var(--body-bg, #f1f5f9);
    color: var(--text-muted, #64748b);
    padding: 1px 6px;
    border-radius: 4px;
    font-size: 9.5px;
}
body.dark-theme .context-section-counter {
    background: #1e293b;
    color: #94a3b8;
}

.context-items-list {
    display: flex;
    flex-direction: column;
    gap: 6px;
    max-height: 165px;
    overflow-y: auto;
    padding-right: 2px;
}

.context-option-card {
    display: flex;
    align-items: center;
    padding: 8px 10px;
    border-radius: 9px;
    background: var(--surface-card, #ffffff);
    border: 1px solid var(--border-color, #e2e8f0);
    cursor: pointer;
    transition: all 0.18s ease;
    user-select: none;
    outline: none;
}
.context-option-card:hover {
    background: var(--body-bg, #f8fafc);
    border-color: #cbd5e1;
    transform: translateY(-1px);
    box-shadow: 0 3px 8px rgba(0, 0, 0, 0.04);
}
.context-option-card:focus {
    border-color: #0284c7;
    box-shadow: 0 0 0 2px rgba(2, 132, 199, 0.2);
}
.context-option-card.active {
    background: rgba(2, 132, 199, 0.08) !important;
    border-color: rgba(2, 132, 199, 0.45) !important;
    box-shadow: 0 2px 6px rgba(2, 132, 199, 0.08);
}
body.dark-theme .context-option-card {
    background: #181d27;
    border-color: #262f3e;
}
body.dark-theme .context-option-card:hover {
    background: #1e2636;
    border-color: #3b475d;
}
body.dark-theme .context-option-card.active {
    background: rgba(2, 132, 199, 0.18) !important;
    border-color: #0284c7 !important;
}

.option-icon-box {
    width: 30px;
    height: 30px;
    border-radius: 7px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 13px;
    flex-shrink: 0;
    margin-right: 9px;
}
.bg-primary-soft {
    background: rgba(2, 132, 199, 0.12);
    color: #0284c7;
}
.bg-info-soft {
    background: rgba(6, 182, 212, 0.12);
    color: #0891b2;
}
.bg-muted-soft {
    background: var(--body-bg, #f1f5f9);
    color: var(--text-muted, #64748b);
}
body.dark-theme .bg-muted-soft {
    background: #202736;
    color: #8b9bb4;
}

.option-text-info {
    flex: 1;
    min-width: 0;
}
.option-main-text {
    font-size: 12.5px;
    font-weight: 600;
    color: var(--text-main, #1e293b);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    line-height: 1.3;
}
.context-option-card.active .option-main-text {
    color: #0284c7;
    font-weight: 700;
}
body.dark-theme .context-option-card.active .option-main-text {
    color: #38bdf8;
}

.option-sub-text {
    font-size: 11px;
    color: var(--text-muted, #64748b);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    margin-top: 1px;
}

.option-state-indicator {
    margin-left: 8px;
    flex-shrink: 0;
}
.active-check-badge {
    background: #10b981;
    color: #ffffff;
    font-size: 10px;
    font-weight: 700;
    padding: 2px 7px;
    border-radius: 12px;
    display: inline-flex;
    align-items: center;
    gap: 3px;
    box-shadow: 0 2px 4px rgba(16, 185, 129, 0.3);
}
.active-group-badge {
    background: #0284c7;
    box-shadow: 0 2px 4px rgba(2, 132, 199, 0.3);
}

.inactive-bullet {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    border: 1.5px solid var(--border-color, #cbd5e1);
    display: inline-block;
    transition: all 0.15s ease;
}
.context-option-card:hover .inactive-bullet {
    border-color: #0284c7;
    background: rgba(2, 132, 199, 0.2);
}

.context-section-divider {
    height: 1px;
    background: var(--border-color, #e2e8f0);
    margin: 10px 4px;
}
body.dark-theme .context-section-divider {
    background: #242c3d;
}

.context-dropdown-footer {
    padding: 8px 14px;
    background: var(--body-bg, #f8fafc);
    border-top: 1px solid var(--border-color, #e2e8f0);
    font-size: 10.5px;
    color: var(--text-muted, #64748b);
    text-align: center;
}
body.dark-theme .context-dropdown-footer {
    background: #10141d;
    border-top-color: #212836;
    color: #8b9bb4;
}

.custom-scrollbar::-webkit-scrollbar {
    width: 4px;
}
.custom-scrollbar::-webkit-scrollbar-track {
    background: transparent;
}
.custom-scrollbar::-webkit-scrollbar-thumb {
    background: rgba(148, 163, 184, 0.4);
    border-radius: 4px;
}
.custom-scrollbar::-webkit-scrollbar-thumb:hover {
    background: rgba(100, 116, 139, 0.6);
}
</style>

<script>
function handleSwitchUnit(unitId, isActive) {
    if (isActive) return;
    var form = document.getElementById('form-switch-unit');
    var input = document.getElementById('input-switch-unit');
    if (form && input) {
        input.value = unitId;
        form.submit();
    }
}
function handleSwitchGroup(groupId, isActive) {
    if (isActive) return;
    var form = document.getElementById('form-switch-group');
    var input = document.getElementById('input-switch-group');
    if (form && input) {
        input.value = groupId;
        form.submit();
    }
}
</script>