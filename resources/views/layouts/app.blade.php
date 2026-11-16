<?php 
$sximoconfig = config('sximo');
$isDarkMode = (isset($_COOKIE['dark_mode']) && $_COOKIE['dark_mode'] === 'true');
$themeClass = $isDarkMode ? 'dark-theme' : 'light-theme';

// Light theme configuration colors
$textColor = $sximoconfig['cnf_text_color'] ?? '';
$headerColor = $sximoconfig['cnf_header_color'] ?? '';
$logoColor = $sximoconfig['cnf_logo_color'] ?? '';
$sidebarColor = $sximoconfig['cnf_sidebar_color'] ?? '';
$cardColor = $sximoconfig['cnf_card_color'] ?? '';
$pageColor = $sximoconfig['cnf_page_color'] ?? '';

// Check if they are class names or hex codes
$bodyClasses = [];
if (!$isDarkMode) {
    if ($textColor && strpos($textColor, '#') !== 0) {
        $bodyClasses[] = $textColor;
    }
    if ($pageColor && strpos($pageColor, '#') !== 0) {
        $bodyClasses[] = $pageColor;
    }
    if ($cardColor && strpos($cardColor, '#') !== 0) {
        $bodyClasses[] = $cardColor;
    }
}
$extraBodyClasses = implode(' ', $bodyClasses);
?>

<!DOCTYPE html>
<html lang="en" data-bs-theme="{{ $isDarkMode ? 'dark' : 'light' }}">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <!-- Tell the browser to be responsive to screen width -->
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="">
    <meta name="author" content="">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <!-- Favicon icon -->
    <link rel="shortcut icon" href="{{ asset('favicon.ico')}}" type="image/x-icon">
    <title>{{ config('sximo.cnf_appname') }}</title>
    <!-- Vite Assets -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <!-- Bootstrap Core CSS -->
    <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Poppins:300,400,500,600,700" />

    @include('partials.bootstrap-css')
    <link href="{{ asset('')}}assets/plugins/perfect-scrollbar/dist/css/perfect-scrollbar.min.css" rel="stylesheet">
    <!-- This page CSS -->
    <!--c3 CSS -->
    
    <link href="{{ asset('')}}assets/plugins/c3-master/c3.min.css" rel="stylesheet">
    <!-- Custom CSS -->
    <link href="{{ asset('')}}assets/css/style.css" rel="stylesheet">
    <!-- Dashboard 1 Page CSS -->
    <link href="{{ asset('')}}assets/css/legacy.css" rel="stylesheet">
    <!-- You can change the theme colors from here -->
    <link href="{{ asset('')}}assets/css/colors/colors.css" id="themes" rel="stylesheet">
    <!-- Gaya A: polished theme on Bootstrap 5 (after the old theme, before the Config color overrides below) -->
    <link href="{{ asset('assets/css/sximo-theme.css') }}" rel="stylesheet">
    <!--Toaster Popup message CSS -->
    <link href="{{ asset('')}}assets/plugins/toast-master/css/jquery.toast.css" rel="stylesheet">
  <link href="{{ asset('')}}assets/plugins/bootstrap-material-datetimepicker/css/bootstrap-material-datetimepicker.css" rel="stylesheet">
  
    <script src="{{ asset('')}}assets/plugins/moment/moment.js"></script>
    <script src="{{ asset('')}}assets/js/sximo.min.js"></script>
    <!-- Bootstrap popper Core JavaScript -->
    @include('partials.bootstrap-js')
    
    <!-- slimscrollbar scrollbar JavaScript -->
    <script src="{{ asset('')}}assets/js/perfect-scrollbar.jquery.min.js"></script>
    <!--Wave Effects -->
    <script src="{{ asset('')}}assets/js/waves.js"></script>
    <!--Menu sidebar -->
    <script src="{{ asset('')}}assets/js/sidebarmenu.js"></script>
<!--stickey kit -->
    <script src="{{ asset('')}}assets/plugins/sticky-kit-master/dist/sticky-kit.min.js"></script>
    <script src="{{ asset('')}}assets/plugins/sparkline/jquery.sparkline.min.js"></script>
    <!--Custom JavaScript -->
    <script src="{{ asset('')}}assets/js/custom.min.js"></script>
   
    <!--c3 JavaScript 
    <script src="{{ asset('')}}assets/plugins/d3/d3.min.js"></script>
    <script src="{{ asset('')}}assets/plugins/c3-master/c3.min.js"></script> --> 
     <script src="{{ asset('')}}assets/js/sximo5.js"></script>
     <script src="{{ asset('')}}assets/plugins/styleswitcher/jQuery.style.switcher.js"></script>
    <!-- Chart JS -->
   
    <!-- HTML5 Shim and Respond.js IE8 support of HTML5 elements and media queries -->
    <!-- WARNING: Respond.js doesn't work if you view the page via file:// -->
    <!--[if lt IE 9]>
      <script src="https://oss.maxcdn.com/libs/html5shiv/3.7.0/html5shiv.js"></script>
      <script src="https://oss.maxcdn.com/libs/respond.js/1.4.2/respond.min.js"></script>
      <![endif]-->

    <!-- Sweet alert -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <!-- Sweet alert -->

    <script type="text/javascript" src="https://www.gstatic.com/charts/loader.js"></script>

    <style>
        .dataTables_filter {
            float: right;
            margin-right: 60px
        }
        #theme-icon-sun, #theme-icon-moon {
            display: none !important;
        }
        body.dark-theme #theme-icon-sun {
            display: inline-block !important;
        }
        body.light-theme #theme-icon-moon {
            display: inline-block !important;
        }
    </style>

    <style id="sximo-dynamic-styles">
        /* --- Dynamic Light Mode CSS Classes Mapping --- */
        body.color-card-dark .card, body.color-card-dark .card-body { background-color: #000000!important; color: #fff !important; }
        body.color-card-white .card, body.color-card-white .card-body { background-color: #fff!important; color: #333 !important; }
        body.color-card-blue .card, body.color-card-blue .card-body { background-color: #1565C0!important; color: #fff !important; }
        body.color-card-indigo .card, body.color-card-indigo .card-body { background-color: #283593!important; color: #fff !important; }
        body.color-card-deep-purple .card, body.color-card-deep-purple .card-body { background-color: #4527A0!important; color: #fff !important; }
        body.color-card-purple .card, body.color-card-purple .card-body { background-color: #6A1B9A!important; color: #fff !important; }
        body.color-card-pink .card, body.color-card-pink .card-body { background-color: #AD1457!important; color: #fff !important; }
        body.color-card-red .card, body.color-card-red .card-body { background-color: #C62828!important; color: #fff !important; }
        body.color-card-teal .card, body.color-card-teal .card-body { background-color: #00695C!important; color: #fff !important; }
        body.color-card-cyan .card, body.color-card-cyan .card-body { background-color: #00838F!important; color: #fff !important; }
        body.color-card-light-blue .card, body.color-card-light-blue .card-body { background-color: #0277BD!important; color: #fff !important; }
        body.color-card-light-green .card, body.color-card-light-green .card-body { background-color: #558B2F!important; color: #fff !important; }
        body.color-card-green .card, body.color-card-green .card-body { background-color: #2E7D32!important; color: #fff !important; }
        body.color-card-orange .card, body.color-card-orange .card-body { background-color: #EF6C00!important; color: #fff !important; }
        body.color-card-yellow .card, body.color-card-yellow .card-body { background-color: #F9A825!important; color: #fff !important; }
        body.color-card-blue-grey .card, body.color-card-blue-grey .card-body { background-color: #607D8B!important; color: #fff !important; }

        body.color-page-dark, body.color-page-dark #main-wrapper, body.color-page-dark .page-wrapper { background-color: #000000!important; }
        body.color-page-white, body.color-page-white #main-wrapper, body.color-page-white .page-wrapper { background-color: #fff!important; }
        body.color-page-soft, body.color-page-soft #main-wrapper, body.color-page-soft .page-wrapper { background-color: #f5f5fa!important; }
        body.color-page-blue, body.color-page-blue #main-wrapper, body.color-page-blue .page-wrapper { background-color: #1565C0!important; }
        body.color-page-indigo, body.color-page-indigo #main-wrapper, body.color-page-indigo .page-wrapper { background-color: #283593!important; }
        body.color-page-deep-purple, body.color-page-deep-purple #main-wrapper, body.color-page-deep-purple .page-wrapper { background-color: #4527A0!important; }
        body.color-page-purple, body.color-page-purple #main-wrapper, body.color-page-purple .page-wrapper { background-color: #6A1B9A!important; }
        body.color-page-pink, body.color-page-pink #main-wrapper, body.color-page-pink .page-wrapper { background-color: #AD1457!important; }
        body.color-page-red, body.color-page-red #main-wrapper, body.color-page-red .page-wrapper { background-color: #C62828!important; }
        body.color-page-teal, body.color-page-teal #main-wrapper, body.color-page-teal .page-wrapper { background-color: #00695C!important; }
        body.color-page-cyan, body.color-page-cyan #main-wrapper, body.color-page-cyan .page-wrapper { background-color: #00838F!important; }
        body.color-page-light-blue, body.color-page-light-blue #main-wrapper, body.color-page-light-blue .page-wrapper { background-color: #0277BD!important; }
        body.color-page-light-green, body.color-page-light-green #main-wrapper, body.color-page-light-green .page-wrapper { background-color: #558B2F!important; }
        body.color-page-green, body.color-page-green #main-wrapper, body.color-page-green .page-wrapper { background-color: #2E7D32!important; }
        body.color-page-orange, body.color-page-orange #main-wrapper, body.color-page-orange .page-wrapper { background-color: #EF6C00!important; }
        body.color-page-yellow, body.color-page-yellow #main-wrapper, body.color-page-yellow .page-wrapper { background-color: #F9A825!important; }
        body.color-page-blue-grey, body.color-page-blue-grey #main-wrapper, body.color-page-blue-grey .page-wrapper { background-color: #607D8B!important; }

        @if(!$isDarkMode)
            /* Dynamic inline configurations for Light Mode */
            @if($textColor && strpos($textColor, '#') === 0)
                body, body i, body label, body p, body span:not(.badge):not(.btn), body a:not(.btn), body td, body th, body h1, body h2, body h3, body h4, body h5, body h6, body li {
                    color: {{ $textColor }} !important;
                }
            @endif
            @if($headerColor && strpos($headerColor, '#') === 0)
                .topbar {
                    background: {{ $headerColor }} !important;
                }
            @endif
            @if($logoColor && strpos($logoColor, '#') === 0)
                .navbar-header {
                    background: {{ $logoColor }} !important;
                }
            @endif
            @if($sidebarColor && strpos($sidebarColor, '#') === 0)
                .left-sidebar {
                    background: {{ $sidebarColor }} !important;
                }
            @endif
            @if($cardColor && strpos($cardColor, '#') === 0)
                .card, .card-body {
                    background: {{ $cardColor }} !important;
                    color: #fff !important;
                }
                .card label, .card p, .card h1, .card h2, .card h3, .card h4, .card h5, .card h6 {
                    color: #fff !important;
                }
            @endif
            @if($pageColor && strpos($pageColor, '#') === 0)
                #main-wrapper, .page-wrapper, body {
                    background: {{ $pageColor }} !important;
                }
            @endif
        @endif
    </style>

</head>

<style>
    table.dataTable tbody td {
		white-space: collapse;
		vertical-align: top;
	}

    #gridview table.table tr td {
        /* width: 100% !important; */
        white-space: nowrap;
        word-wrap: break-word;
        white-space: normal !important;
        word-break: break-word;
    }
</style>

<body class="fix-header fix-sidebar {{ $themeClass }} {{ $extraBodyClasses }}">
    <!-- ============================================================== -->
    <!-- Preloader - style you can find in spinners.css -->
    <!-- ============================================================== -->
    <div class="preloader">
        <div class="loader">
            <div class="loader__figure"></div>
            <p class="loader__label"> {{ config('sximo.cnf_appname') }} </p>
        </div>
    </div>


    <div id="main-wrapper">
      @include('layouts.topnav')
      @include('layouts.leftnav')   

        <!-- Page Content -->
        <div class="page-wrapper">
          <div class="container-fluid">
          <div class="ajaxLoading"></div>
         
            @yield('content') 
          </div>      
    </div>
    <!-- /#wrapper -->


</div>

<div class="modal" id="sximo-modal" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel1">
    <div class="modal-dialog  " role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h4 class="modal-title" id="exampleModalLabel1">New message</h4>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="sximo-modal-content" class="formajax">
                
            </div>
           
        </div>
    </div>
</div>



    <!-- /#wrapper -->
{{ SiteHelpers::showNotification() }} 
<script>
    var BASE_URL = "{{ asset('')}}";
    $.ajaxSetup({
    headers: {
        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
    }
    });
</script>
<!-- Additional -->
<link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.13.4/css/jquery.dataTables.min.css"/>
<script src="{{ asset('')}}assets/js/general.js"></script>

<script>
    $(document).ready(function() {
        var themeToggle = $('#theme-toggle');
        var sunIcon = $('#theme-icon-sun');
        var moonIcon = $('#theme-icon-moon');

        // Ensure dynamic styles are disabled on load if dark mode is active
        if ($('body').hasClass('dark-theme')) {
            $('#sximo-dynamic-styles').prop('disabled', true);
        }

        function getCookie(name) {
            var nameEQ = name + "=";
            var ca = document.cookie.split(';');
            for(var i=0; i < ca.length; i++) {
                var c = ca[i];
                while (c.charAt(0)==' ') c = c.substring(1,c.length);
                if (c.indexOf(nameEQ) == 0) return c.substring(nameEQ.length,c.length);
            }
            return null;
        }

        function setCookie(name, value, days) {
            var expires = "";
            if (days) {
                var date = new Date();
                date.setTime(date.getTime() + (days*24*60*60*1000));
                expires = "; expires=" + date.toUTCString();
            }
            document.cookie = name + "=" + (value || "")  + expires + "; path=/";
        }

        themeToggle.on('click', function() {
            var body = $('body');
            var currentlyDark = body.hasClass('dark-theme');
            if (currentlyDark) {
                body.removeClass('dark-theme').addClass('light-theme');
                document.documentElement.setAttribute('data-bs-theme', 'light');
                setCookie('dark_mode', 'false', 365);
                $('#sximo-dynamic-styles').prop('disabled', false);
            } else {
                body.removeClass('light-theme').addClass('dark-theme');
                document.documentElement.setAttribute('data-bs-theme', 'dark');
                setCookie('dark_mode', 'true', 365);
                $('#sximo-dynamic-styles').prop('disabled', true);
            }
        });
    });
</script>

@php
    $allowedGroupsStr = config('ai.ai_allowed_groups') ?? env('AI_ALLOWED_GROUPS', '');
    $allowedGroups = $allowedGroupsStr !== '' ? explode(',', $allowedGroupsStr) : [];
    $allowedGroups = array_map('trim', $allowedGroups);
    $showAiWidget = in_array(session('gid'), $allowedGroups);
@endphp
@if($showAiWidget)
    <!-- AI Chat Widget Floating Button -->
    <button id="ai-widget-trigger" title="Chat with AI" class="waves-effect waves-light">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 28 28" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="ai-trigger-svg">
            <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
            <path d="M26 10a2 2 0 0 0-2-2h-3v5a2 2 0 0 1-2 2H9v3a2 2 0 0 0 2 2h10l4 4v-12a2 2 0 0 0-2-2z"></path>
        </svg>
    </button>

    <!-- AI Chat Dialog Widget -->
    <div id="ai-chat-widget">
        <!-- Header -->
        <div class="ai-header">
            <div class="ai-profile">
                <div class="ai-avatar">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 28 28" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="ai-avatar-svg">
                        <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
                        <path d="M26 10a2 2 0 0 0-2-2h-3v5a2 2 0 0 1-2 2H9v3a2 2 0 0 0 2 2h10l4 4v-12a2 2 0 0 0-2-2z"></path>
                    </svg>
                    <span class="ai-status-dot"></span>
                </div>
                <div class="ai-bio">
                    <div class="ai-name">Igma</div>
                    <div class="ai-title">teman diskusi Anda</div>
                </div>
            </div>
            <div class="ai-controls">
                <button type="button" id="ai-btn-reset" title="New Conversation"><i class="fa fa-plus"></i></button>
                <button type="button" id="ai-btn-close" title="Close"><i class="fa fa-times"></i></button>
            </div>
        </div>

        <!-- Info/Source Disclaimer -->
        <div class="ai-disclaimer">
            Jawaban dapat ditelusuri ke sumbernya.
        </div>

        <!-- Scrollable Messages Area -->
        <div class="ai-body" id="ai-chat-messages">
            <!-- Welcome placeholder -->
            <div class="ai-welcome" id="ai-welcome-screen">
                <div class="ai-welcome-icon-box">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 28 28" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="ai-welcome-svg">
                        <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
                        <path d="M26 10a2 2 0 0 0-2-2h-3v5a2 2 0 0 1-2 2H9v3a2 2 0 0 0 2 2h10l4 4v-12a2 2 0 0 0-2-2z"></path>
                    </svg>
                </div>
                <h4 class="ai-welcome-heading">Ada yang mau ditanyakan?</h4>
                <p class="ai-welcome-text"></p>
                
                <div class="ai-quick-questions">
                    <div class="ai-section-title">COBA TANYA</div>
                    <button class="ai-pill" onclick="sendAiQuickQuestion('Berapa risiko saya?');">Berapa risiko saya?</button>
                    <button class="ai-pill" onclick="sendAiQuickQuestion('Risiko per kategori');">Risiko per kategori</button>
                    <button class="ai-pill" onclick="sendAiQuickQuestion('Inheren vs residual');">Inheren vs residual</button>
                    <button class="ai-pill" onclick="sendAiQuickQuestion('Menentukan penyebab risiko');">Menentukan penyebab risiko</button>
                </div>
            </div>
        </div>

        <!-- Footer Input Form -->
        <div class="ai-footer">
            <form id="ai-chat-form" onsubmit="submitAiMessage(event);">
                <textarea id="ai-message-input" maxlength="1500" placeholder="Tanyakan sesuatu — cara MR atau angka risiko Anda..." required></textarea>
                <div class="ai-footer-actions">
                    <span class="ai-char-counter" id="ai-char-count">0/1500</span>
                    <button type="submit" class="ai-btn-send" id="ai-send-btn" disabled>
                        <i class="fa fa-paper-plane me-1"></i> Kirim
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Styles -->
    <style>
        #ai-widget-trigger {
            position: fixed;
            bottom: 25px;
            right: 25px;
            width: 56px;
            height: 56px;
            border-radius: 50%;
            background-color: #0f6861 !important;
            color: #ffffff !important;
            border: none;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            z-index: 99999;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: transform 0.2s, background-color 0.2s;
            outline: none;
        }
        #ai-widget-trigger:hover {
            transform: scale(1.05);
            background-color: #0a4f49 !important;
        }
        .ai-trigger-svg {
            width: 26px;
            height: 26px;
        }

        #ai-chat-widget {
            position: fixed;
            bottom: 95px;
            right: 25px;
            width: 380px;
            height: 580px;
            background-color: #ffffff;
            border-radius: 16px;
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.15);
            border: 1px solid rgba(0,0,0,0.08);
            z-index: 99999;
            display: flex;
            flex-direction: column;
            overflow: hidden;
            font-family: inherit;
            transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
            opacity: 0;
            visibility: hidden;
            transform: translateY(20px);
        }
        #ai-chat-widget.active {
            opacity: 1;
            visibility: visible;
            transform: translateY(0);
        }

        .ai-header {
            background-color: #ffffff;
            padding: 16px 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-bottom: 1px solid rgba(0,0,0,0.06);
            flex-shrink: 0;
        }
        .ai-profile {
            display: flex;
            align-items: center;
        }
        .ai-avatar {
            position: relative;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background-color: #0f6861;
            color: #ffffff;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 12px;
        }
        .ai-avatar-svg {
            width: 20px;
            height: 20px;
        }
        .ai-status-dot {
            position: absolute;
            bottom: 0;
            right: 0;
            width: 10px;
            height: 10px;
            background-color: #10b981;
            border: 2px solid #ffffff;
            border-radius: 50%;
        }
        .ai-bio {
            display: flex;
            flex-direction: column;
        }
        .ai-name {
            font-weight: 700;
            font-size: 15px;
            color: #1f2937;
            line-height: 1.2;
        }
        .ai-title {
            font-size: 11px;
            color: #6b7280;
        }
        .ai-controls button {
            background: none;
            border: none;
            color: #9ca3af;
            font-size: 16px;
            cursor: pointer;
            padding: 4px;
            margin-left: 8px;
            outline: none;
            transition: color 0.2s;
        }
        .ai-controls button:hover {
            color: #4b5563;
        }

        .ai-disclaimer {
            background-color: #f9fafb;
            padding: 8px 20px;
            font-size: 12px;
            color: #6b7280;
            border-bottom: 1px solid rgba(0,0,0,0.05);
            flex-shrink: 0;
        }

        .ai-body {
            flex-grow: 1;
            flex-shrink: 1;
            min-height: 0;
            padding: 20px;
            overflow-y: auto;
            display: flex;
            flex-direction: column;
            background-color: #ffffff;
        }

        .ai-welcome {
            margin: auto;
            text-align: center;
            display: flex;
            flex-direction: column;
            align-items: center;
            width: 100%;
        }
        .ai-welcome-icon-box {
            width: 52px;
            height: 52px;
            border-radius: 12px;
            background-color: rgba(15, 104, 97, 0.1);
            color: #0f6861;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 16px;
        }
        .ai-welcome-svg {
            width: 26px;
            height: 26px;
        }
        .ai-welcome-heading {
            font-size: 17px;
            font-weight: 700;
            color: #111827;
            margin-bottom: 8px;
        }
        .ai-welcome-text {
            font-size: 13px;
            color: #4b5563;
            max-width: 280px;
            margin-bottom: 24px;
            line-height: 1.5;
        }
        .ai-quick-questions {
            width: 100%;
            display: flex;
            flex-direction: column;
            align-items: flex-start;
        }
        .ai-section-title {
            font-size: 10px;
            font-weight: 700;
            color: #6b7280;
            letter-spacing: 0.5px;
            margin-bottom: 8px;
            text-transform: uppercase;
        }
        .ai-pill {
            background-color: #f0fdfa;
            color: #0d9488;
            border: 1px solid #ccfbf1;
            border-radius: 20px;
            padding: 8px 16px;
            font-size: 13px;
            margin-bottom: 8px;
            cursor: pointer;
            text-align: left;
            width: fit-content;
            transition: all 0.2s;
            outline: none;
        }
        .ai-pill:hover {
            background-color: #e6fffa;
            border-color: #99f6e4;
        }

        .ai-msg-row {
            display: flex;
            width: 100%;
            margin-bottom: 15px;
        }
        .ai-msg-row.msg-user {
            justify-content: flex-end;
        }
        .ai-msg-row.msg-assistant {
            justify-content: flex-start;
        }
        .ai-bubble {
            max-width: 80%;
            padding: 12px 16px;
            border-radius: 12px;
            font-size: 13px;
            line-height: 1.5;
            position: relative;
            word-wrap: break-word;
        }
        .ai-msg-row.msg-user .ai-bubble {
            background-color: #0f6861;
            color: #ffffff;
            border-bottom-right-radius: 2px;
        }
        .ai-msg-row.msg-assistant .ai-bubble {
            background-color: #f3f4f6;
            color: #1f2937;
            border-bottom-left-radius: 2px;
            border: 1px solid #e5e7eb;
        }
        .ai-bubble-role {
            font-size: 10px;
            font-weight: 700;
            color: #0f6861;
            margin-bottom: 3px;
            text-transform: uppercase;
        }
        .ai-bubble-time {
            font-size: 9px;
            margin-top: 4px;
            text-align: right;
            opacity: 0.6;
        }

        .ai-footer {
            border-top: 1px solid rgba(0,0,0,0.06);
            padding: 16px 20px;
            background-color: #ffffff;
            flex-shrink: 0;
        }
        .ai-footer form {
            display: flex;
            flex-direction: column;
        }
        #ai-message-input {
            width: 100%;
            height: 64px;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            padding: 10px 14px;
            font-size: 13px;
            resize: none;
            outline: none;
            transition: border-color 0.2s;
            font-family: inherit;
        }
        #ai-message-input:focus {
            border-color: #0f6861;
        }
        .ai-footer-actions {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-top: 8px;
        }
        .ai-char-counter {
            font-size: 11px;
            color: #9ca3af;
        }
        .ai-btn-send {
            background-color: #0f6861;
            color: #ffffff;
            border: none;
            border-radius: 6px;
            padding: 6px 16px;
            font-size: 13px;
            font-weight: 500;
            cursor: pointer;
            display: flex;
            align-items: center;
            transition: background-color 0.2s;
            outline: none;
        }
        .ai-btn-send:disabled {
            background-color: #9ca3af;
            cursor: not-allowed;
        }
        .ai-btn-send:not(:disabled):hover {
            background-color: #0a4f49;
        }

        /* Dark Theme overrides */
        body.dark-theme #ai-chat-widget {
            background-color: #161b22;
            border-color: #30363d;
        }
        body.dark-theme .ai-header {
            background-color: #161b22;
            border-bottom-color: #30363d;
        }
        body.dark-theme .ai-avatar {
            border: 1px solid #30363d;
        }
        body.dark-theme .ai-status-dot {
            border-color: #161b22;
        }
        body.dark-theme .ai-name {
            color: #c9d1d9;
        }
        body.dark-theme .ai-title {
            color: #8b949e;
        }
        body.dark-theme .ai-controls button:hover {
            color: #c9d1d9;
        }
        body.dark-theme .ai-disclaimer {
            background-color: #0d1117;
            color: #8b949e;
            border-bottom-color: #30363d;
        }
        body.dark-theme .ai-body {
            background-color: #0d1117;
        }
        body.dark-theme .ai-welcome-heading {
            color: #c9d1d9;
        }
        body.dark-theme .ai-welcome-text {
            color: #8b949e;
        }
        body.dark-theme .ai-pill {
            background-color: rgba(13, 148, 136, 0.15);
            color: #58a6ff;
            border-color: rgba(88, 166, 255, 0.3);
        }
        body.dark-theme .ai-pill:hover {
            background-color: rgba(13, 148, 136, 0.25);
            border-color: #58a6ff;
        }
        body.dark-theme .ai-msg-row.msg-user .ai-bubble {
            background-color: #0f6861;
            color: #ffffff;
        }
        body.dark-theme .ai-msg-row.msg-assistant .ai-bubble {
            background-color: #161b22;
            color: #c9d1d9;
            border-color: #30363d;
        }
        body.dark-theme .ai-bubble-role {
            color: #58a6ff;
        }
        body.dark-theme .ai-footer {
            background-color: #161b22;
            border-top-color: #30363d;
        }
        body.dark-theme #ai-message-input {
            background-color: #0d1117;
            border-color: #30363d;
            color: #c9d1d9;
        }
        body.dark-theme #ai-message-input:focus {
            border-color: #0f6861;
        }
    </style>

    <script>
        var aiSessionId = null;

        function toggleAiWidget() {
            var widget = $('#ai-chat-widget');
            widget.toggleClass('active');
            if (widget.hasClass('active')) {
                scrollAiToBottom();
                loadAiActiveSession();
            }
        }

        function scrollAiToBottom() {
            var container = document.getElementById('ai-chat-messages');
            if (container) {
                container.scrollTop = container.scrollHeight;
            }
        }

        function updateAiCharCounter() {
            var input = document.getElementById('ai-message-input');
            var counter = document.getElementById('ai-char-count');
            var sendBtn = document.getElementById('ai-send-btn');
            var len = input.value.trim().length;
            
            counter.innerText = len + '/1500';
            sendBtn.disabled = (len === 0);
        }

        function escapeAiHtml(text) {
            if (!text) return '';
            var map = {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            };
            return text.replace(/[&<>"']/g, function(m) { return map[m]; });
        }

        function loadAiActiveSession() {
            if (aiSessionId !== null) return;

            $.ajax({
                url: "{{ url('sximo/aichat/active-session') }}",
                type: "GET",
                dataType: "json",
                success: function(response) {
                    if (response.status === 'success') {
                        aiSessionId = response.sessionId;
                        
                        $('#ai-welcome-screen').remove();
                        
                        var messagesContainer = $('#ai-chat-messages');
                        messagesContainer.empty();
                        
                        response.messages.forEach(function(msg) {
                            var now = new Date(msg.created_at);
                            var timeStr = ('0' + now.getHours()).slice(-2) + ':' + ('0' + now.getMinutes()).slice(-2);
                            appendAiBubble(msg.role, msg.message, timeStr);
                        });
                        
                        scrollAiToBottom();
                    }
                }
            });
        }

        function appendAiBubble(role, message, timeStr) {
            var container = $('#ai-chat-messages');
            var bubbleClass = (role === 'user') ? 'msg-user' : 'msg-assistant';
            
            var roleHeader = '';
            if (role === 'assistant') {
                roleHeader = '<div class="ai-bubble-role">AI Assistant</div>';
            }
            
            var bubbleHtml = `
                <div class="ai-msg-row ${bubbleClass}">
                    <div class="ai-bubble">
                        ${roleHeader}
                        <div style="white-space: pre-line;">${escapeAiHtml(message)}</div>
                        <div class="ai-bubble-time">${timeStr}</div>
                    </div>
                </div>
            `;
            container.append(bubbleHtml);
        }

        function sendAiQuickQuestion(questionText) {
            $('#ai-message-input').val(questionText);
            updateAiCharCounter();
            $('#ai-chat-form').submit();
        }

        async function submitAiMessage(event) {
            event.preventDefault();
            
            var input = document.getElementById('ai-message-input');
            var message = input.value.trim();
            if (!message) return;

            input.value = '';
            updateAiCharCounter();

            $('#ai-welcome-screen').remove();

            var now = new Date();
            var timeStr = ('0' + now.getHours()).slice(-2) + ':' + ('0' + now.getMinutes()).slice(-2);

            appendAiBubble('user', message, timeStr);
            scrollAiToBottom();

            var loaderId = 'ai-stream-' + Date.now();
            var loaderHtml = `
                <div class="ai-msg-row msg-assistant" id="${loaderId}">
                    <div class="ai-bubble">
                        <div class="ai-bubble-role">AI Assistant</div>
                        <div class="ai-msg-text" style="white-space: pre-line;"><i class="fa fa-spinner fa-spin me-1"></i> Sedang mengetik...</div>
                        <div class="ai-bubble-time">${timeStr}</div>
                    </div>
                </div>
            `;
            $('#ai-chat-messages').append(loaderHtml);
            scrollAiToBottom();

            var messageElement = $('#' + loaderId).find('.ai-msg-text');
            var accumulatedText = '';
            var isFirstToken = true;

            try {
                const response = await fetch("{{ url('sximo/aichat/stream') }}", {
                    method: "POST",
                    headers: {
                        "Content-Type": "application/json",
                        "X-CSRF-TOKEN": "{{ csrf_token() }}",
                        "Accept": "text/event-stream"
                    },
                    body: JSON.stringify({
                        message: message,
                        session_id: aiSessionId
                    })
                });

                if (!response.ok) {
                    const errorJson = await response.json().catch(() => ({}));
                    throw new Error(errorJson.message || 'HTTP error ' + response.status);
                }

                const reader = response.body.getReader();
                const decoder = new TextDecoder("utf-8");
                let buffer = '';

                while (true) {
                    const { done, value } = await reader.read();
                    if (done) break;

                    buffer += decoder.decode(value, { stream: true });
                    const lines = buffer.split("\n");
                    buffer = lines.pop();

                    for (const line of lines) {
                        const trimmed = line.trim();
                        if (trimmed.startsWith('data: ')) {
                            const jsonStr = trimmed.slice(6).trim();
                            if (!jsonStr) continue;

                            try {
                                const data = JSON.parse(jsonStr);
                                if (data.event === 'start') {
                                    if (!aiSessionId && data.sessionId) {
                                        aiSessionId = data.sessionId;
                                    }
                                } else if (data.event === 'token') {
                                    if (isFirstToken) {
                                        messageElement.empty();
                                        isFirstToken = false;
                                    }
                                    accumulatedText += data.text;
                                    messageElement.text(accumulatedText);
                                    scrollAiToBottom();
                                } else if (data.event === 'done') {
                                    if (data.fullMessage) {
                                        accumulatedText = data.fullMessage;
                                    }
                                    messageElement.text(accumulatedText);
                                    scrollAiToBottom();
                                }
                            } catch(err) {
                                console.error('SSE parse error:', err);
                            }
                        }
                    }
                }

                if (accumulatedText) {
                    messageElement.text(accumulatedText);
                }

            } catch (err) {
                $('#' + loaderId).remove();
                appendAiError(err.message || 'Connection to server failed.');
            }
        }

        function appendAiError(errorMsg) {
            var container = $('#ai-chat-messages');
            var errorHtml = `
                <div class="ai-msg-row msg-assistant">
                    <div class="ai-bubble" style="border-color: #f87171 !important;">
                        <div class="ai-bubble-role text-danger">Connection Error</div>
                        <div class="text-danger"><i class="fa fa-exclamation-triangle me-1"></i> ${escapeAiHtml(errorMsg)}</div>
                    </div>
                </div>
            `;
            container.append(errorHtml);
            scrollAiToBottom();
        }

        $(document).ready(function() {
            $('#ai-widget-trigger').on('click', toggleAiWidget);
            $('#ai-btn-close').on('click', toggleAiWidget);

            $('#ai-message-input').on('input propertychange', updateAiCharCounter);
            
            $('#ai-message-input').on('keydown', function(e) {
                if (e.which === 13 && !e.shiftKey) {
                    e.preventDefault();
                    $('#ai-chat-form').submit();
                }
            });

            $('#ai-btn-reset').on('click', function() {
                if (!confirm('Start a new conversation thread?')) return;
                
                $.ajax({
                    url: "{{ url('sximo/aichat/reset') }}",
                    type: "POST",
                    data: { _token: "{{ csrf_token() }}" },
                    dataType: "json",
                    success: function(response) {
                        if (response.status === 'success') {
                            aiSessionId = response.sessionId;
                            
                            var messagesContainer = $('#ai-chat-messages');
                            messagesContainer.empty();
                            
                            var welcomeHtml = `
                                <div class="ai-welcome" id="ai-welcome-screen">
                                    <div class="ai-welcome-icon-box">
                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 28 28" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="ai-welcome-svg">
                                            <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
                                            <path d="M26 10a2 2 0 0 0-2-2h-3v5a2 2 0 0 1-2 2H9v3a2 2 0 0 0 2 2h10l4 4v-12a2 2 0 0 0-2-2z"></path>
                                        </svg>
                                    </div>
                                    <h4 class="ai-welcome-heading">Ada yang mau ditanyakan?</h4>
                                    <p class="ai-welcome-text">Tanya cara & metodologi manajemen risiko, atau angka portofolio risiko Anda.</p>
                                    
                                    <div class="ai-quick-questions">
                                        <div class="ai-section-title">COBA TANYA</div>
                                        <button class="ai-pill" onclick="sendAiQuickQuestion('Berapa risiko saya?');">Berapa risiko saya?</button>
                                        <button class="ai-pill" onclick="sendAiQuickQuestion('Risiko per kategori');">Risiko per kategori</button>
                                        <button class="ai-pill" onclick="sendAiQuickQuestion('Inheren vs residual');">Inheren vs residual</button>
                                        <button class="ai-pill" onclick="sendAiQuickQuestion('Menentukan penyebab risiko');">Menentukan penyebab risiko</button>
                                    </div>
                                </div>
                            `;
                            messagesContainer.append(welcomeHtml);
                            scrollAiToBottom();
                        }
                    }
                });
            });

            loadAiActiveSession();
        });
    </script>
@endif

</body>
</html>

