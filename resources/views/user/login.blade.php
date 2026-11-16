@extends('layouts.login')

@section('content')

<div class="ajaxLoading"></div>

{{-- Alert messages --}}
<div class="login-form">

  @if(Session::has('status'))
    @if(session('status') =='success')
      <div class="alert alert-success alert-box">
        {!! Session::get('message') !!}
      </div>
    @else
      <div class="alert alert-danger alert-box">
        {!! Session::get('message') !!}
      </div>
    @endif		
  @endif

  <p class="message alert alert-danger alert-box" style="display:none;"></p>

  @if($errors->any())
    <div class="alert alert-danger alert-box">
      <ul class="mb-0 ps-3">
        @foreach($errors->all() as $error)
          <li>{{ $error }}</li>
        @endforeach
      </ul>
    </div>
  @endif

  {{-- ── Sign In Form ── --}}
  <div id="tab-sign-in">
    <form action="{{ url('user/signin') }}" id="LoginAjax" method="POST" parsley-validate novalidate>
      @csrf

      <div class="input-group-login">
        <i class="fa fa-user"></i>
        <input type="text" id="inputEmail" name="email" placeholder="{{ __('core.email') }}" required autofocus>
      </div>

      <div class="input-group-login">
        <i class="fa fa-lock"></i>
        <input type="password" id="inputPassword" name="password" placeholder="{{ __('core.password') }}" required>
        <span class="toggle-pw" onclick="togglePw()"><i class="fa fa-lock" id="pwIcon"></i></span>
      </div>

      @if(filter_var(config('sximo.cnf_recaptcha'), FILTER_VALIDATE_BOOLEAN)) 
        <div class="recaptcha-container">
          <div class="g-recaptcha" data-sitekey="{{ config('sximo.cnf_recaptchapublickey') }}"></div>
        </div>
      @endif

      <button class="btn-login" type="submit">Login</button>

    </form>
  </div>

  {{-- ── Forgot password links ── --}}
  <div class="login-links" style="margin-top:18px;">
    <a href="javascript:void(0)" class="forgot">@lang('core.forgotpassword')?</a>
    {{-- <a href="{{ url('user/register') }}">Create Account</a> --}}
  </div>

  {{-- ── Forgot password form ── --}}
  <div id="tab-forgot">
    <form action="{{ url('user/request') }}" method="POST" parsley-validate novalidate>
      @csrf
      <div class="input-group-login">
        <i class="fa fa-envelope"></i>
        <input type="text" name="credit_email" placeholder="{{ Lang::get('core.enteremailforgot') }}" required>
      </div>
      <div class="forgot-actions">
        <button type="button" class="btn btn-warning forgot">Cancel</button>
        <button type="submit" class="btn btn-primary">{{ Lang::get('core.sb_submit') }}</button>
      </div>
    </form>
  </div>

</div>{{-- /.login-form --}}

<script type="text/javascript">
  $(document).ready(function(){
    $('.forgot').on('click', function(){
      $('#tab-forgot').toggle();
      $('#tab-sign-in').toggle();
      $('.login-links').toggle();
    });

    var form = $('#LoginAjax'); 
    form.parsley();
    form.submit(function(){
      if(form.parsley().isValid()){
        @if(filter_var(config('sximo.cnf_recaptcha'), FILTER_VALIDATE_BOOLEAN))
        if(typeof grecaptcha !== 'undefined' && $('.g-recaptcha').length && !grecaptcha.getResponse()){
          $('.message').html('Silakan centang verifikasi reCAPTCHA terlebih dahulu.').show();
          return false;
        }
        @endif
        var options = { 
          dataType: 'json', 
          beforeSubmit: showRequest,
          success: showResponse,
          error: showError
        };
        $(this).ajaxSubmit(options); 
        return false;
      } else {
        return false;
      }
    });
  });

  function togglePw() {
    var pw = document.getElementById('inputPassword');
    var icon = document.getElementById('pwIcon');
    if(pw.type === 'password'){
      pw.type = 'text';
      icon.className = 'fa fa-unlock';
    } else {
      pw.type = 'password';
      icon.className = 'fa fa-lock';
    }
  }

  function showRequest(formData) {
    if(typeof grecaptcha !== 'undefined' && $('.g-recaptcha').length){
      var token = grecaptcha.getResponse();
      var found = false;
      for(var i = 0; i < formData.length; i++){
        if(formData[i].name === 'g-recaptcha-response'){
          formData[i].value = token;
          found = true;
          break;
        }
      }
      if(!found){
        formData.push({ name: 'g-recaptcha-response', value: token });
      }
    }
    $('.message').hide();
    $('.ajaxLoading').show();
  }

  function showResponse(data) {
    if(data.status == 'success'){
      window.location.href = data.url;	
      $('.ajaxLoading').hide();
    } else {
      $('.message').html(data.message || 'Login gagal, silakan coba lagi.').show();
      $('.ajaxLoading').hide();
      if(typeof grecaptcha !== 'undefined' && $('.g-recaptcha').length){
        grecaptcha.reset();
      }
      return false;
    }	
  }

  function showError(xhr) {
    var msg = 'Terjadi kesalahan saat memproses login. Silakan coba lagi.';
    if(xhr && xhr.status === 429){
      msg = 'Terlalu banyak percobaan login. Silakan tunggu beberapa saat.';
    } else if(xhr && xhr.status === 419){
      msg = 'Sesi halaman telah berakhir. Silakan muat ulang halaman.';
    } else if(xhr && xhr.responseJSON && xhr.responseJSON.message){
      msg = xhr.responseJSON.message;
    }
    $('.message').html(msg).show();
    $('.ajaxLoading').hide();
    if(typeof grecaptcha !== 'undefined' && $('.g-recaptcha').length){
      grecaptcha.reset();
    }
  }
</script>

@stop
