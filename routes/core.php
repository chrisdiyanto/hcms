<?php

//-------------------------------------------------------------------------
/* Start Users Routes */
// -- Get Method --
Route::resource('core/users', 'Core\UsersController');
Route::get('core/users/blast', 'Core\UsersController@getBlast');
// -- Post Method --
Route::post('core/users/doblast', 'Core\UsersController@postDoblast');
// Cakupan data (user_data_scopes)
Route::get('core/users/{user}/scopes', 'Core\DataScopesController@index')->whereNumber('user');
Route::post('core/users/{user}/scopes', 'Core\DataScopesController@store')->whereNumber('user');
Route::delete('core/users/{user}/scopes/{scope}', 'Core\DataScopesController@destroy')->whereNumber(['user', 'scope']);
Route::post('core/users/{user}/mfa-reset', 'Core\DataScopesController@resetMfa')->whereNumber('user');
/* End Users Routes */

//-------------------------------------------------------------------------
/* Start Tenants Routes (Superadmin) */
Route::resource('core/tenants', 'Core\TenantsController')->only(['index', 'create', 'store', 'edit', 'update']);
Route::post('core/tenants/{tenant}/status', 'Core\TenantsController@status');
Route::get('core/tenants/{tenant}/break-glass', 'Core\TenantsController@breakGlassForm');
Route::post('core/tenants/{tenant}/break-glass', 'Core\TenantsController@breakGlassStart')->middleware('throttle:5,1,break-glass');
Route::post('core/break-glass/end', 'Core\TenantsController@breakGlassEnd');
Route::get('core/audit', 'Core\AuditTrailController@index');
// Migrasi data (impor CSV)
Route::get('core/import', 'Core\DataImportController@index');
Route::get('core/import/template/{type}', 'Core\DataImportController@template')->where('type', '[a-z_]+');
Route::post('core/import', 'Core\DataImportController@upload')->middleware('throttle:20,1,import');
Route::get('core/import/{uuid}', 'Core\DataImportController@show')->whereUuid('uuid');
Route::post('core/import/{uuid}/commit', 'Core\DataImportController@commit')->whereUuid('uuid');
/* End Tenants Routes */

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
