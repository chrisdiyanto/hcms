<?php

use Illuminate\Support\Facades\Route;

/*
| Route modul HCMS yang ditulis tangan (bukan hasil generator Sximo).
| File ini tidak disentuh generator, berbeda dengan routes/module.php.
| Dimuat di dalam grup middleware auth (routes/web.php).
*/

// Organization & Job Architecture
Route::resource('organization/job-families', 'Organization\JobFamiliesController')->except('show')->parameters(['job-families' => 'id']);
Route::resource('organization/job-levels', 'Organization\JobLevelsController')->except('show')->parameters(['job-levels' => 'id']);
Route::resource('organization/job-grades', 'Organization\JobGradesController')->except('show')->parameters(['job-grades' => 'id']);
Route::resource('organization/jobs', 'Organization\JobsController')->except('show')->parameters(['jobs' => 'id']);
Route::resource('organization/positions', 'Organization\PositionsController')->except('show')->parameters(['positions' => 'id']);

// Employee Management
Route::resource('employees', 'Employee\EmployeesController')->parameters(['employees' => 'id'])->whereNumber('id');
Route::post('employees/{id}/identity', 'Employee\EmployeesController@revealIdentity')->whereNumber('id')->middleware('throttle:20,1,identity');
Route::post('employees/{employee}/records/{type}', 'Employee\EmployeeRecordsController@store')->whereNumber('employee');
Route::delete('employees/{employee}/records/{type}/{record}', 'Employee\EmployeeRecordsController@destroy')->whereNumber(['employee', 'record']);
Route::post('employees/{employee}/assignments', 'Employee\EmployeeAssignmentsController@store')->whereNumber('employee');
Route::post('employees/{employee}/assignments/{assignment}/end', 'Employee\EmployeeAssignmentsController@end')->whereNumber(['employee', 'assignment']);
Route::post('employees/{employee}/terminate', 'Employee\EmployeeAssignmentsController@terminate')->whereNumber('employee');
Route::post('employees/{employee}/terminations/{termination}/cancel', 'Employee\EmployeeAssignmentsController@cancelTermination')->whereNumber(['employee', 'termination']);
