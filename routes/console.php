<?php

use App\Domain\Analytics\MonthlySnapshot;
use App\Domain\Competency\Services\CompetencyAssessment;
use App\Domain\DataImport\CsvReader;
use App\Domain\DataImport\ImportRegistry;
use App\Domain\DataImport\ImportRunner;
use App\Domain\Employee\Actions\TerminateEmployee;
use App\Domain\Workflow\WorkflowEngine;
use App\Support\Audit\AuditLog;
use App\Support\Tenancy\TenantContext;
use App\Support\Tenancy\TenantScope;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Str;

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
Artisan::command('hcms:process-terminations', function (TerminateEmployee $action) {
    $count = $action->processDue();
    $this->info("Terminasi diproses: {$count}");
})->describe('Proses terminasi pegawai terjadwal yang sudah jatuh tempo');

Schedule::command('hcms:process-terminations')->dailyAt('00:15')->withoutOverlapping()->onOneServer();

// HCMS: eskalasi tugas persetujuan yang melewati SLA (blueprint modul 5)
Artisan::command('hcms:escalate-approvals', function (WorkflowEngine $engine) {
    $count = $engine->escalateOverdue();
    $this->info("Tugas dieskalasi: {$count}");
})->describe('Eskalasi tugas persetujuan yang melewati batas waktu (SLA)');

Schedule::command('hcms:escalate-approvals')->hourly()->withoutOverlapping()->onOneServer();

// HCMS: impor CSV dari baris perintah (berkas besar, mis. 10.000 pegawai)
Artisan::command('hcms:import {type : units|job_families|job_levels|job_grades|jobs|positions|employees} {file} {--tenant= : Kode tenant} {--commit : Simpan; tanpa opsi ini hanya validasi kering}', function (ImportRunner $runner) {
    $tenant = DB::table('tenants')->where('code', strtoupper((string) $this->option('tenant')))->first();
    if (! $tenant) {
        $this->error('Tenant tidak ditemukan. Gunakan --tenant=KODE.');

        return 1;
    }

    return app(TenantContext::class)->runAs((int) $tenant->id, null, function () use ($runner) {
        $importer = ImportRegistry::make($this->argument('type'), ['sensitive' => true]);
        $csv = CsvReader::read($this->argument('file'), 200000);
        $result = $runner->run($importer, $csv['headers'], $csv['rows'], (bool) $this->option('commit'));

        $this->table(['Baris', 'Baru', 'Diperbarui', 'Error', 'Sebelum', 'Sesudah', 'Tersimpan'], [[
            count($csv['rows']), $result['created'], $result['updated'], count($result['errors']), $result['before'], $result['after'], $result['committed'] ? 'ya' : 'tidak',
        ]]);
        foreach (array_slice($result['errors'], 0, 50, true) as $line => $messages) {
            $this->line("Baris {$line}: ".implode(' | ', $messages));
        }
        // Catat sebagai batch agar tampil di Riwayat impor beserta rekonsiliasinya
        $batchId = DB::table('import_batches')->insertGetId(TenantScope::fillForInsert('import_batches', [
            'uuid' => (string) Str::uuid7(), 'type' => $this->argument('type'),
            'file_name' => Str::limit('CLI: '.basename($this->argument('file')), 255, ''), 'stored_path' => 'cli',
            'status' => $result['committed'] ? 'committed' : ($result['errors'] === [] ? 'validated' : 'invalid'),
            'total_rows' => count($csv['rows']), 'error_rows' => count($result['errors']), 'created_rows' => $result['created'], 'updated_rows' => $result['updated'],
            'errors' => json_encode($result['errors'], JSON_UNESCAPED_UNICODE),
            'reconciliation' => json_encode(['rows_in_file' => count($csv['rows']), 'created' => $result['created'], 'updated' => $result['updated'],
                'db_before' => $result['before'], 'db_after' => $result['after'], 'committed' => $result['committed']]),
            'committed_at' => $result['committed'] ? now() : null, 'created_at' => now(), 'updated_at' => now(),
        ]));
        if ($result['committed']) {
            AuditLog::security('import_batches', $batchId, 'Impor CLI '.$this->argument('type').' di-commit: '.$result['created'].' baru, '.$result['updated'].' diperbarui');
        }

        return $result['errors'] === [] ? 0 : 1;
    });
})->describe('Impor data CSV (validasi kering; tambahkan --commit untuk menyimpan)');

// HCMS: snapshot analitik bulanan per tenant × unit (prasyarat P2 laporan CBHRM)
Artisan::command('hcms:analytics-snapshot {--period= : YYYY-MM; bawaan bulan lalu} {--from= : YYYY-MM; bangun ulang rentang sampai --period} {--tenant= : Kode tenant; bawaan semua tenant aktif} {--force : Buat versi baru walau snapshot bulan itu sudah ada}', function (MonthlySnapshot $snapshots) {
    $to = $this->option('period') ?: now()->subMonthNoOverflow()->format('Y-m');
    $from = $this->option('from') ?: $to;
    foreach ([$from, $to] as $value) {
        if (! preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $value)) {
            $this->error('Periode harus berformat YYYY-MM.');

            return 1;
        }
    }
    $tenants = DB::table('tenants')->where('status', 'active')
        ->when($this->option('tenant'), fn ($q, $code) => $q->where('code', strtoupper($code)))->get(['id', 'code']);
    if ($tenants->isEmpty()) {
        $this->error('Tenant tidak ditemukan.');

        return 1;
    }

    $rows = [];
    for ($month = Carbon\Carbon::createFromFormat('Y-m-d', $from.'-01'); $month->format('Y-m') <= $to; $month->addMonthNoOverflow()) {
        foreach ($tenants as $tenant) {
            $result = app(TenantContext::class)->runAs((int) $tenant->id, null,
                fn () => $snapshots->generate((int) $tenant->id, $month->format('Y-m'), (bool) $this->option('force')));
            $rows[] = [$tenant->code, $month->format('Y-m'), $result['skipped'] ? 'sudah ada' : 'dibuat', $result['version'], $result['rows']];
        }
    }
    $this->table(['Tenant', 'Periode', 'Status', 'Versi', 'Baris'], $rows);

    return 0;
})->describe('Buat snapshot analitik bulanan (metrik aditif per unit) untuk People Analytics');

Schedule::command('hcms:analytics-snapshot')->monthlyOn(1, '01:30')->withoutOverlapping()->onOneServer();

// HCMS: hitung ulang gap & JFI dari skor yang masih berlaku (perubahan aturan kamus metrik, profil, atau kedaluwarsa)
Artisan::command('hcms:competency-recalculate {--tenant= : Kode tenant; bawaan semua tenant aktif}', function (CompetencyAssessment $assessments) {
    $tenants = DB::table('tenants')->where('status', 'active')
        ->when($this->option('tenant'), fn ($q, $code) => $q->where('code', strtoupper($code)))->get(['id', 'code']);
    $rows = [];
    foreach ($tenants as $tenant) {
        $count = app(TenantContext::class)->runAs((int) $tenant->id, null, function () use ($assessments) {
            $pairs = TenantScope::table('job_fit_results')->get(['employee_id', 'position_id']);
            foreach ($pairs as $pair) {
                DB::transaction(fn () => $assessments->refreshFit((int) $pair->employee_id, (int) $pair->position_id));
            }

            return $pairs->count();
        });
        $rows[] = [$tenant->code, $count];
    }
    $this->table(['Tenant', 'JFI dihitung ulang'], $rows);

    return 0;
})->describe('Hitung ulang gap & JFI semua pegawai dari skor kompetensi yang masih berlaku');

Schedule::command('hcms:competency-recalculate')->weeklyOn(0, '02:00')->withoutOverlapping()->onOneServer();
