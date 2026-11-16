<?php 
Route::get('service', 'HomeController@index');
Route::get('about-us', 'HomeController@index');
Route::get('contact-us', 'HomeController@index');
Route::get('privacy', 'HomeController@index');
Route::get('new-sximo-5-ultimate-powefull-cms', 'HomeController@index');
Route::get('toc', 'HomeController@index');
Route::get('printing-and-typesetting-industry', 'HomeController@index');
Route::get('new-sximo-5.1.6-sept-2016', 'HomeController@index');
Route::get('-remaining-essentially-unchanged', 'HomeController@index');
Route::get('page-backend', 'HomeController@index');
Route::get('knowledge', 'HomeController@index');
Route::get('galleries', 'HomeController@index');
Route::get('portpolio', 'HomeController@index');
Route::get('table', 'HomeController@index');
Route::get('userguide', 'HomeController@index');
Route::get('faq', 'HomeController@index');
Route::get('team', 'HomeController@index');

// Additional file (backend, wajib login)
Route::middleware('auth')->group(function () {
	Route::post('unit/generateslug','UnitController@generateslug');
	Route::post('core/users/data','Core\UsersController@AjaxData');
	Route::post('core/users/deluser','Core\UsersController@deluser');
	Route::post('core/users/unit','Core\UsersController@unit');
	Route::post('core/users/position','Core\UsersController@position');

	Route::post('core/groups/data','Core\GroupsController@AjaxData');
	Route::post('core/groups/delgroup','Core\GroupsController@delgroup');
});



?>