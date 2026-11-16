@extends('errors.layout')
@section('title', 'Autentikasi diperlukan')
@section('code', '401')
@section('heading', 'Silakan masuk terlebih dahulu')
@section('message', 'Anda perlu masuk ke akun RiskCue untuk membuka halaman ini.')
@section('actions')
<a class="btn" href="{{ url('user/login') }}">Masuk</a><a class="btn btn-light" href="{{ url('/') }}">Kembali ke Beranda</a>
@endsection
