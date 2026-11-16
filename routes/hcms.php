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
Route::get('employees/export', 'Employee\EmployeesController@export')->middleware('throttle:10,1,export');
Route::resource('employees', 'Employee\EmployeesController')->parameters(['employees' => 'id'])->whereNumber('id');
Route::post('employees/{id}/identity', 'Employee\EmployeesController@revealIdentity')->whereNumber('id')->middleware('throttle:20,1,identity');
Route::post('employees/{employee}/records/{type}', 'Employee\EmployeeRecordsController@store')->whereNumber('employee');
Route::delete('employees/{employee}/records/{type}/{record}', 'Employee\EmployeeRecordsController@destroy')->whereNumber(['employee', 'record']);
Route::post('employees/{employee}/assignments', 'Employee\EmployeeAssignmentsController@store')->whereNumber('employee');
Route::post('employees/{employee}/assignments/{assignment}/end', 'Employee\EmployeeAssignmentsController@end')->whereNumber(['employee', 'assignment']);
Route::post('employees/{employee}/terminate', 'Employee\EmployeeAssignmentsController@terminate')->whereNumber('employee');
Route::post('employees/{employee}/terminations/{termination}/cancel', 'Employee\EmployeeAssignmentsController@cancelTermination')->whereNumber(['employee', 'termination']);

// Competency Management
Route::get('competency/framework', 'Competency\FrameworkController@index');
Route::post('competency/framework/draft', 'Competency\FrameworkController@createDraft');
Route::post('competency/framework/{version}/publish', 'Competency\FrameworkController@publish')->whereNumber('version');
Route::get('competency/framework/{version}/competencies/create', 'Competency\FrameworkController@create')->whereNumber('version');
Route::post('competency/framework/{version}/competencies', 'Competency\FrameworkController@store')->whereNumber('version');
Route::get('competency/competencies/{competency}/edit', 'Competency\FrameworkController@edit')->whereNumber('competency');
Route::put('competency/competencies/{competency}', 'Competency\FrameworkController@update')->whereNumber('competency');
Route::delete('competency/competencies/{competency}', 'Competency\FrameworkController@destroy')->whereNumber('competency');
Route::get('competency/job-profiles', 'Competency\JobProfilesController@index');
Route::get('competency/job-profiles/{job}', 'Competency\JobProfilesController@edit')->whereNumber('job');
Route::put('competency/job-profiles/{job}', 'Competency\JobProfilesController@update')->whereNumber('job');
Route::get('competency/positions/{position}', 'Competency\JobProfilesController@position')->whereNumber('position');
Route::put('competency/positions/{position}', 'Competency\JobProfilesController@updatePosition')->whereNumber('position');
Route::get('competency/assessments', 'Competency\AssessmentsController@index');
Route::get('competency/assessments/create', 'Competency\AssessmentsController@create');
Route::post('competency/assessments', 'Competency\AssessmentsController@store');
Route::get('competency/assessments/{assessment}', 'Competency\AssessmentsController@show')->whereNumber('assessment');
Route::post('competency/assessments/{assessment}/assessment-center', 'Competency\AssessmentsController@assessmentCenter')->whereNumber('assessment');
Route::post('competency/assessments/{assessment}/close', 'Competency\AssessmentsController@close')->whereNumber('assessment');
Route::post('competency/assessments/{assessment}/cancel', 'Competency\AssessmentsController@cancel')->whereNumber('assessment');
Route::get('competency/my-assessments', 'Competency\MyAssessmentsController@index');
Route::get('competency/my-assessments/{rater}', 'Competency\MyAssessmentsController@edit')->whereNumber('rater');
Route::put('competency/my-assessments/{rater}', 'Competency\MyAssessmentsController@update')->whereNumber('rater')->middleware('throttle:20,1,assessments');
Route::get('competency/reports', 'Competency\ReportsController@index');
Route::get('competency/reports/employees/{employee}', 'Competency\ReportsController@employee')->whereNumber('employee');

// Employee Self Service
Route::get('ess/profile', 'Employee\EssController@profile');
Route::post('ess/change-requests', 'Employee\EssController@submit')->middleware('throttle:10,1,ess');

// Workflow & approval
Route::get('workflow/tasks', 'Workflow\TasksController@index');
Route::get('workflow/tasks/{task}', 'Workflow\TasksController@show')->whereNumber('task');
Route::post('workflow/tasks/{task}/approve', 'Workflow\TasksController@approve')->whereNumber('task');
Route::post('workflow/tasks/{task}/reject', 'Workflow\TasksController@reject')->whereNumber('task');
Route::post('workflow/requests/{instance}/cancel', 'Workflow\TasksController@cancel')->whereNumber('instance');
Route::get('workflow/delegations', 'Workflow\DelegationsController@index');
Route::post('workflow/delegations', 'Workflow\DelegationsController@store');
Route::post('workflow/delegations/{delegation}/revoke', 'Workflow\DelegationsController@revoke')->whereNumber('delegation');
Route::get('workflow/definitions', 'Workflow\DefinitionsController@index');
Route::get('workflow/definitions/{code}/edit', 'Workflow\DefinitionsController@edit')->where('code', '[a-z_]+');
Route::put('workflow/definitions/{code}', 'Workflow\DefinitionsController@update')->where('code', '[a-z_]+');
