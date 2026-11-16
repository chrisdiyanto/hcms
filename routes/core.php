<?php

//-------------------------------------------------------------------------
/* Start Users Routes */
// -- Get Method --
Route::resource('core/users', 'Core\UsersController');
Route::get('core/users/blast', 'Core\UsersController@getBlast');
// -- Post Method --
Route::post('core/users/doblast', 'Core\UsersController@postDoblast');
/* End Users Routes */

//-------------------------------------------------------------------------
/* Start Groups Routes */
// -- Get Method --
Route::resource('core/groups', 'Core\GroupsController');
/* End Groups Routes */


//-------------------------------------------------------------------------
/* Start Posts & Pages Routes */
// -- Get Method --
Route::resource('cms/pages', 'Core\PagesController');
Route::resource('cms/posts', 'Core\PostsController');
Route::post('cms/posts/config', 'Core\PostsController@postConfig');
Route::resource('cms/categories', 'Core\CategoriesController');
/* End Posts Routes */

//-------------------------------------------------------------------------
/* Start Logs Routes */
// -- Get Method --
Route::resource('core/logs', 'Core\LogsController');
/* End Logs Routes */


//-------------------------------------------------------------------------
/* Start Forms Routes */
// -- Get Method --
Route::resource('core/forms', 'Core\FormsController');

//-------------------------------------------------------------------------
/* Start Elfinder Routes */
// -- Get Method --
Route::get('core/elfinder', 'Core\ElfinderController@getIndex');
/* End  Elfinder Routes */
