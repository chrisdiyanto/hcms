<?php

use Illuminate\Foundation\Inspiring;

/*
|--------------------------------------------------------------------------
| Console Routes
|--------------------------------------------------------------------------
|
| This file is where you may define all of your Closure based console
| commands. Each Closure is bound to a command instance allowing a
| simple approach to interacting with each command's IO methods.
|
*/

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->describe('Display an inspiring quote');

// HCMS: proses terminasi terjadwal yang jatuh tempo (blueprint 11.1)
Artisan::command('hcms:process-terminations', function (App\Domain\Employee\Actions\TerminateEmployee $action) {
    $count = $action->processDue();
    $this->info("Terminasi diproses: {$count}");
})->describe('Proses terminasi pegawai terjadwal yang sudah jatuh tempo');

Illuminate\Support\Facades\Schedule::command('hcms:process-terminations')->dailyAt('00:15')->withoutOverlapping()->onOneServer();
