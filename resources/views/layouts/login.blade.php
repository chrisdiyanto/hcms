<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{{config('sximo.cnf_appname') }}</title>
<link rel="shortcut icon" href="{{ asset('favicon.ico')}}" type="image/x-icon">

<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
@include('partials.bootstrap-css')
<script src="{{ asset('')}}assets/plugins/moment/moment.js"></script>
<script type="text/javascript" src="{{ asset('assets/js/sximo.min.js') }}"></script>
<script type="text/javascript" src="{{ asset('assets/js/sximo5.js') }}"></script>
@if(filter_var(config('sximo.cnf_recaptcha'), FILTER_VALIDATE_BOOLEAN))
<script src="https://www.google.com/recaptcha/api.js" async defer></script>
@endif

<style>
  * { box-sizing: border-box; margin: 0; padding: 0; }

  body {
    font-family: 'Poppins', sans-serif;
    min-height: 100vh;
    background: #8fa5b8;
    display: flex;
    align-items: center;
    justify-content: center;
    position: relative;
    overflow-x: hidden;
    overflow-y: auto;
    padding: 20px 0;
  }

  /* Gambar port.png transparan sebagai background body */
  body::before {
    content: '';
    position: fixed;
    inset: 0;
    background: url({{ asset('assets/images/marunda.png') }}) center center / cover no-repeat;
    opacity: 0.25;
    z-index: 0;
    pointer-events: none;
  }

  /* Pastikan login-wrapper tampil di atas layer masking */
  .login-wrapper {
    display: flex;
    width: 900px;
    max-width: 98vw;
    min-height: 560px;
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 20px 60px rgba(0,0,0,0.4);
    position: relative;
    z-index: 1;
  }

  /* ── LEFT PANEL SLIDESHOW ── */
  .panel-left {
    flex: 0 0 50%;
    position: relative;
    min-height: 560px;
    overflow: hidden;
  }
  .panel-left::after {
    content: '';
    position: absolute;
    inset: 0;
    background: rgba(15, 30, 60, 0.40);
    z-index: 1;
  }
  /* Slide items */
  .slide-item {
    position: absolute;
    inset: 0;
    background-size: cover;
    background-position: center center;
    background-repeat: no-repeat;
    opacity: 0;
    transition: opacity 1.5s ease-in-out;
    z-index: 0;
  }
  .slide-item.active {
    opacity: 1;
  }
  .slide-item:nth-child(1) { background-image: url({{ asset('assets/images/marunda.png') }}); }
  .slide-item:nth-child(2) { background-image: url({{ asset('assets/images/pabrik.jpeg') }}); }
  .slide-item:nth-child(3) { background-image: url({{ asset('assets/images/gudang.jpeg') }}); }
  .slide-item:nth-child(4) { background-image: url({{ asset('assets/images/petikemas.jpeg') }}); }

  /* ── RIGHT PANEL ── */
  .panel-right {
    flex: 0 0 50%;
    background: rgba(200, 215, 230, 0.92);
    backdrop-filter: blur(8px);
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 40px 48px;
    position: relative;
  }
  .panel-right::before {
    content: '';
    position: absolute;
    inset: 0;
    background: url({{ asset('assets/images/marunda.png') }}) center center / cover no-repeat;
    opacity: 0.18;
    z-index: 0;
  }
  .panel-right > * { position: relative; z-index: 1; }

  /* LOGO */
  .brand-logo {
    text-align: center;
    margin-bottom: 10px;
  }
  .brand-logo img {
    max-height: 70px;
    object-fit: contain;
  }
  .brand-name {
    font-size: 15px;
    font-weight: 700;
    color: #003580;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    text-align: center;
    line-height: 1.3;
  }
  .brand-desc {
    font-size: 11.5px;
    color: #4a6080;
    text-align: center;
    margin-bottom: 28px;
    letter-spacing: 0.2px;
  }

  /* FORM INPUTS */
  .login-form { width: 100%; }

  .input-group-login {
    display: flex;
    align-items: center;
    background: rgba(255,255,255,0.85);
    border: 1px solid rgba(0,53,128,0.25);
    border-radius: 6px;
    margin-bottom: 14px;
    padding: 0 14px;
    transition: border-color .2s, box-shadow .2s;
  }
  .input-group-login:focus-within {
    border-color: #0057c8;
    box-shadow: 0 0 0 3px rgba(0,87,200,0.15);
  }
  .input-group-login i {
    color: #6c8caa;
    font-size: 14px;
    margin-right: 10px;
    flex-shrink: 0;
  }
  .input-group-login input {
    flex: 1;
    border: none;
    background: transparent;
    height: 44px;
    font-size: 13.5px;
    color: #1a2a3a;
    outline: none;
  }
  .input-group-login input::placeholder { color: #8fa5bb; }
  .input-group-login .toggle-pw {
    cursor: pointer;
    color: #6c8caa;
    font-size: 13px;
    flex-shrink: 0;
  }

  .btn-login {
    width: 100%;
    height: 44px;
    background: #0057c8;
    color: #fff;
    font-weight: 600;
    font-size: 14px;
    letter-spacing: 1.5px;
    border: none;
    border-radius: 6px;
    cursor: pointer;
    transition: background .2s, box-shadow .2s;
    margin-top: 4px;
    text-transform: uppercase;
  }
  .btn-login:hover { background: #003ea0; box-shadow: 0 4px 16px rgba(0,87,200,0.35); }

  .recaptcha-container {
    display: flex;
    justify-content: center;
    margin-bottom: 14px;
    width: 100%;
  }

  .login-links {
    text-align: center;
    margin-top: 16px;
    font-size: 12px;
    color: #4a6080;
  }
  .login-links a {
    color: #4a6080;
    text-decoration: none;
    display: block;
    transition: color .2s;
  }
  .login-links a:hover { color: #0057c8; }

  /* ALERTS */
  .alert-box {
    width: 100%;
    margin-bottom: 12px;
    font-size: 12.5px;
    border-radius: 6px;
    padding: 10px 14px;
  }

  /* FORGOT PANEL */
  #tab-forgot { display: none; width: 100%; }
  .forgot-actions {
    display: flex;
    gap: 10px;
    margin-top: 12px;
  }
  .forgot-actions .btn { flex: 1; height: 40px; font-size: 13px; border-radius: 6px; }

  /* RESPONSIVE */
  @media (max-width: 640px) {
    .panel-left { display: none; }
    .panel-right { flex: 0 0 100%; padding: 36px 28px; }
    .login-wrapper { width: 100%; min-height: 100vh; border-radius: 0; }
  }
</style>

{{-- Font Awesome --}}
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">

    {{-- Alpine.js (halaman MFA) --}}
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>

<div class="login-wrapper">
  {{-- LEFT PANEL --}}
  <div class="panel-left">
    <div class="slide-item active"></div>
    <div class="slide-item"></div>
    <div class="slide-item"></div>
    <div class="slide-item"></div>
  </div>

  {{-- RIGHT PANEL --}}
  <div class="panel-right">

    {{-- Logo --}}
    <div class="brand-logo">
      @if(file_exists(public_path().'/uploads/images/'.config('sximo')['cnf_logo']) && config('sximo')['cnf_logo'] !='')
        <img src="{{ asset('uploads/images/'.config('sximo')['cnf_logo']) }}" alt="{{ config('sximo')['cnf_appname'] }}">
      @else
        <img src="{{ asset('uploads/logo.png') }}" alt="{{ config('sximo')['cnf_appname'] }}">
      @endif
    </div>

    <div class="brand-name">{{ config('sximo.cnf_appname') }}</div>
    <div class="brand-desc">{{ config('sximo.cnf_appdesc') }}</div>

    {{-- Content from login view --}}
    @yield('content')

  </div>
</div>

<script>
  (function() {
    var slides = document.querySelectorAll('.panel-left .slide-item');
    var current = 0;
    if (slides.length < 2) return;

    setInterval(function() {
      slides[current].classList.remove('active');
      current = (current + 1) % slides.length;
      slides[current].classList.add('active');
    }, 4000); // ganti gambar setiap 4 detik
  })();
</script>

</body>
</html>
