<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application.
|
*/

// Root / Home Route
Route::get('/', 'HomeController@index');

// User Auth & Account Routes
Route::get('user/login', 'UserController@getLogin');
Route::post('user/signin', 'UserController@postSignin')->middleware('throttle:5,1,login');
Route::get('user/logout', 'UserController@getLogout');
Route::get('user/register', 'UserController@getRegister');
Route::post('user/create', 'UserController@postCreate')->middleware('throttle:5,1,register');
Route::get('user/activation', 'UserController@getActivation')->middleware('throttle:10,1,activation');
Route::get('user/profile', 'UserController@getProfile');
Route::get('user/changepswd', 'UserController@getProfile');
Route::post('user/saveprofile', 'UserController@postSaveprofile');
Route::post('user/savepassword', 'UserController@postSavepassword')->middleware('throttle:5,1,savepassword');
Route::get('user/reminder', 'UserController@getReminder');
Route::post('user/request', 'UserController@postRequest')->middleware('throttle:3,1,reset-request');
Route::get('user/reset/{token?}', 'UserController@getReset')->middleware('throttle:10,1,reset-view');
Route::post('user/doreset/{token?}', 'UserController@postDoreset')->middleware('throttle:5,1,reset-submit');
Route::get('user/switch-group/{gid?}', 'UserController@getSwitchGroup');
Route::post('user/switch-group', 'UserController@postSwitchGroup');
Route::get('user/switch-unit/{id_unit?}', 'UserController@getSwitchUnit');
Route::post('user/switch-unit', 'UserController@postSwitchUnit');

// Verifikasi dua langkah (MFA)
Route::middleware('auth')->group(function () {
    Route::get('user/mfa', 'MfaController@challenge');
    Route::post('user/mfa', 'MfaController@verify')->middleware('throttle:5,1,mfa');
    Route::get('user/mfa/setup', 'MfaController@setup');
    Route::post('user/mfa/setup', 'MfaController@enable')->middleware('throttle:5,1,mfa-setup');
});

// Dashboard Route
Route::get('dashboard', 'DashboardController@index');

// Additional Modular Routes
// pages.php berisi halaman publik; route admin di dalamnya dilindungi sendiri
include base_path('routes/pages.php');

// Route backend / CRUD wajib login
Route::middleware('auth')->group(function () {
    include base_path('routes/core.php');
    include base_path('routes/services.php');
    include base_path('routes/module.php');
});
