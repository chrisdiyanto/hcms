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

// HCMS: eskalasi tugas persetujuan yang melewati SLA (blueprint modul 5)
Artisan::command('hcms:escalate-approvals', function (App\Domain\Workflow\WorkflowEngine $engine) {
    $count = $engine->escalateOverdue();
    $this->info("Tugas dieskalasi: {$count}");
})->describe('Eskalasi tugas persetujuan yang melewati batas waktu (SLA)');

Illuminate\Support\Facades\Schedule::command('hcms:escalate-approvals')->hourly()->withoutOverlapping()->onOneServer();

// HCMS: impor CSV dari baris perintah (berkas besar, mis. 10.000 pegawai)
Artisan::command('hcms:import {type : units|job_families|job_levels|job_grades|jobs|positions|employees} {file} {--tenant= : Kode tenant} {--commit : Simpan; tanpa opsi ini hanya validasi kering}', function (App\Domain\DataImport\ImportRunner $runner) {
    $tenant = Illuminate\Support\Facades\DB::table('tenants')->where('code', strtoupper((string) $this->option('tenant')))->first();
    if (! $tenant) {
        $this->error('Tenant tidak ditemukan. Gunakan --tenant=KODE.');

        return 1;
    }

    return app(App\Support\Tenancy\TenantContext::class)->runAs((int) $tenant->id, null, function () use ($runner) {
        $importer = App\Domain\DataImport\ImportRegistry::make($this->argument('type'), ['sensitive' => true]);
        $csv = App\Domain\DataImport\CsvReader::read($this->argument('file'), 200000);
        $result = $runner->run($importer, $csv['headers'], $csv['rows'], (bool) $this->option('commit'));

        $this->table(['Baris', 'Baru', 'Diperbarui', 'Error', 'Sebelum', 'Sesudah', 'Tersimpan'], [[
            count($csv['rows']), $result['created'], $result['updated'], count($result['errors']), $result['before'], $result['after'], $result['committed'] ? 'ya' : 'tidak',
        ]]);
        foreach (array_slice($result['errors'], 0, 50, true) as $line => $messages) {
            $this->line("Baris {$line}: ".implode(' | ', $messages));
        }
        // Catat sebagai batch agar tampil di Riwayat impor beserta rekonsiliasinya
        $batchId = Illuminate\Support\Facades\DB::table('import_batches')->insertGetId(App\Support\Tenancy\TenantScope::fillForInsert('import_batches', [
            'uuid' => (string) Illuminate\Support\Str::uuid7(), 'type' => $this->argument('type'),
            'file_name' => Illuminate\Support\Str::limit('CLI: '.basename($this->argument('file')), 255, ''), 'stored_path' => 'cli',
            'status' => $result['committed'] ? 'committed' : ($result['errors'] === [] ? 'validated' : 'invalid'),
            'total_rows' => count($csv['rows']), 'error_rows' => count($result['errors']), 'created_rows' => $result['created'], 'updated_rows' => $result['updated'],
            'errors' => json_encode($result['errors'], JSON_UNESCAPED_UNICODE),
            'reconciliation' => json_encode(['rows_in_file' => count($csv['rows']), 'created' => $result['created'], 'updated' => $result['updated'],
                'db_before' => $result['before'], 'db_after' => $result['after'], 'committed' => $result['committed']]),
            'committed_at' => $result['committed'] ? now() : null, 'created_at' => now(), 'updated_at' => now(),
        ]));
        if ($result['committed']) {
            App\Support\Audit\AuditLog::security('import_batches', $batchId, 'Impor CLI '.$this->argument('type').' di-commit: '.$result['created'].' baru, '.$result['updated'].' diperbarui');
        }

        return $result['errors'] === [] ? 0 : 1;
    });
})->describe('Impor data CSV (validasi kering; tambahkan --commit untuk menyimpan)');
