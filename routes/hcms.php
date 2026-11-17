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
Route::get('executive/dashboard', 'ExecutiveDashboardController@index');
Route::get('executive/analytics/{report}', 'ExecutiveAnalyticsController@show')->where('report', 'turnover|promotions|deib|workforce|development');
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
// Performance Management
Route::get('performance/policies', 'Performance\PoliciesController@index');
Route::post('performance/policies/draft', 'Performance\PoliciesController@createDraft');
Route::put('performance/policies/{policy}', 'Performance\PoliciesController@update')->whereNumber('policy');
Route::post('performance/policies/{policy}/publish', 'Performance\PoliciesController@publish')->whereNumber('policy');
Route::get('performance/periods', 'Performance\PeriodsController@index');
Route::post('performance/periods', 'Performance\PeriodsController@store');
Route::get('performance/periods/{period}', 'Performance\PeriodsController@show')->whereNumber('period');
Route::post('performance/periods/{period}/open', 'Performance\PeriodsController@open')->whereNumber('period');
Route::post('performance/periods/{period}/advance', 'Performance\PeriodsController@advance')->whereNumber('period');
Route::post('performance/periods/{period}/objectives', 'Performance\PeriodsController@storeObjective')->whereNumber('period');
Route::put('performance/objectives/{objective}', 'Performance\PeriodsController@updateObjective')->whereNumber('objective');
Route::delete('performance/objectives/{objective}', 'Performance\PeriodsController@destroyObjective')->whereNumber('objective');
Route::get('performance/my-plans', 'Performance\MyPlansController@index');
Route::get('performance/my-plans/{plan}', 'Performance\MyPlansController@edit')->whereNumber('plan');
Route::put('performance/my-plans/{plan}', 'Performance\MyPlansController@update')->whereNumber('plan')->middleware('throttle:30,1,plans');
Route::post('performance/my-plans/{plan}/cancel', 'Performance\MyPlansController@cancel')->whereNumber('plan');
Route::get('performance/plans/{plan}/monitoring', 'Performance\MonitoringController@show')->whereNumber('plan');
Route::post('performance/kpis/{objective}/updates', 'Performance\MonitoringController@storeUpdate')->whereNumber('objective')->middleware('throttle:30,1,kpi-updates');
Route::get('performance/evidences/{evidence}', 'Performance\MonitoringController@evidence')->whereNumber('evidence');
Route::post('performance/plans/{plan}/checkins', 'Performance\MonitoringController@storeCheckin')->whereNumber('plan');
Route::post('performance/checkins/{checkin}/complete', 'Performance\MonitoringController@completeCheckin')->whereNumber('checkin');
Route::get('performance/plans/{plan}/change-request', 'Performance\MonitoringController@changeForm')->whereNumber('plan');
Route::post('performance/plans/{plan}/change-request', 'Performance\MonitoringController@submitChange')->whereNumber('plan');
Route::get('performance/team', 'Performance\TeamController@index');
Route::get('performance/assessments', 'Performance\EvaluationController@index');
Route::get('performance/assessments/{plan}', 'Performance\EvaluationController@show')->whereNumber('plan');
Route::post('performance/assessments/{plan}/self', 'Performance\EvaluationController@saveSelf')->whereNumber('plan');
Route::post('performance/assessments/{plan}/manager', 'Performance\EvaluationController@saveManager')->whereNumber('plan');
Route::post('performance/assessments/{plan}/review', 'Performance\EvaluationController@review')->whereNumber('plan');
Route::get('performance/periods/{period}/calibration', 'Performance\CalibrationController@index')->whereNumber('period');
Route::post('performance/periods/{period}/calibration', 'Performance\CalibrationController@store')->whereNumber('period');
Route::get('performance/calibration/sessions/{session}', 'Performance\CalibrationController@show')->whereNumber('session');
Route::post('performance/calibration/sessions/{session}/move', 'Performance\CalibrationController@move')->whereNumber('session');
Route::post('performance/calibration/sessions/{session}/close', 'Performance\CalibrationController@close')->whereNumber('session');
Route::post('performance/periods/{period}/publish', 'Performance\RatingsController@publish')->whereNumber('period');
Route::post('performance/periods/{period}/finalize', 'Performance\RatingsController@finalize')->whereNumber('period');
Route::get('performance/periods/{period}/ratings', 'Performance\RatingsController@periodRatings')->whereNumber('period');
Route::match(['get', 'post'], 'performance/periods/{period}/ratings/export', 'Performance\RatingsController@export')->whereNumber('period')->middleware('throttle:10,1,exports');
Route::get('performance/my-ratings', 'Performance\RatingsController@mine');
Route::post('performance/ratings/{rating}/appeal', 'Performance\RatingsController@appeal')->whereNumber('rating')->middleware('throttle:5,1,appeals');
Route::get('performance/appeals', 'Performance\RatingsController@appeals');
Route::post('performance/appeals/{appeal}/decide', 'Performance\RatingsController@decide')->whereNumber('appeal');
Route::get('performance/pips', 'Performance\PipController@index');
Route::post('performance/pips', 'Performance\PipController@store');
Route::post('performance/pips/{pip}/close', 'Performance\PipController@close')->whereNumber('pip');
Route::get('executive/analytic/performance', 'Performance\DashboardController@index');
Route::redirect('performance/dashboard', '/executive/analytic/performance', 302);

Route::get('competency/reports', 'Competency\ReportsController@index');
Route::get('competency/reports/employees/{employee}', 'Competency\ReportsController@employee')->whereNumber('employee');

// Employee Self Service
Route::get('ess/profile', 'Employee\EssController@profile');
Route::get('ess/my-data', 'Employee\EssController@download')->middleware('throttle:5,1,my-data');
Route::get('ess/career', 'Employee\EssController@career');
Route::post('ess/career', 'Employee\EssController@saveCareer')->middleware('throttle:10,1,ess-career');
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

// Talent (prasyarat P3 CBHRM): kamus instrumen asesmen potensi berversi
Route::get('talent/potential-instruments', 'Talent\PotentialInstrumentsController@index');
Route::post('talent/potential-instruments/draft', 'Talent\PotentialInstrumentsController@createDraft');
Route::put('talent/potential-instruments/{id}', 'Talent\PotentialInstrumentsController@update')->whereNumber('id');
Route::post('talent/potential-instruments/{id}/publish', 'Talent\PotentialInstrumentsController@publish')->whereNumber('id');
Route::delete('talent/potential-instruments/{id}', 'Talent\PotentialInstrumentsController@destroy')->whereNumber('id');
