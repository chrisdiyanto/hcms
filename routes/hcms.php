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
