<?php

namespace App\Http\Controllers\Core;

use App\Domain\DataImport\CsvReader;
use App\Domain\DataImport\ImportRegistry;
use App\Domain\DataImport\ImportRunner;
use App\Http\Controllers\Concerns\AuthorizesHcmsModule;
use App\Http\Controllers\Controller;
use App\Models\Sximo;
use App\Support\Audit\AuditLog;
use App\Support\Tenancy\TenantScope;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Migrasi data lewat CSV: unggah → validasi kering → commit utuh.
 * Berkas disimpan per tenant (tenants/{id}/imports), tidak publik.
 */
class DataImportController extends Controller
{
    use AuthorizesHcmsModule;

    protected function moduleName(): string
    {
        return 'dataimport';
    }

    public function index()
    {
        $this->authorizeTask('is_view');

        return view('core.import.index', [
            'pageTitle' => 'Migrasi Data',
            'pageNote' => 'Impor CSV dengan validasi kering sebelum disimpan',
            'types' => collect(ImportRegistry::TYPES)->map(fn ($class, $type) => ImportRegistry::make($type)->title())->all(),
            'batches' => TenantScope::table('import_batches')->leftJoin('tb_users', 'tb_users.id', '=', 'import_batches.created_by')
                ->orderByDesc('import_batches.id')->limit(20)->get(['import_batches.*', 'tb_users.username']),
            'canAdd' => $this->can('is_add'),
            'maxRows' => ImportRegistry::MAX_WEB_ROWS,
        ]);
    }

    /** Template CSV: header + satu baris contoh + baris keterangan kolom (dimulai #). */
    public function template(string $type)
    {
        $this->authorizeTask('is_view');
        $importer = ImportRegistry::make($type);
        $columns = $importer->columns();

        return response()->streamDownload(function () use ($columns) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, array_keys($columns));
            fputcsv($out, array_map(fn ($c) => $c['example'] ?? '', $columns));
            fclose($out);
        }, 'template-'.$type.'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function upload(Request $request, ImportRunner $runner)
    {
        $this->authorizeTask('is_add');
        $data = $request->validate([
            'type' => ['required', Rule::in(array_keys(ImportRegistry::TYPES))],
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:10240'],
        ]);

        $uuid = (string) Str::uuid7();
        $path = $request->file('file')->storeAs('tenants/'.TenantScope::tenantId().'/imports', $uuid.'.csv', 'local');

        $id = DB::table('import_batches')->insertGetId(TenantScope::fillForInsert('import_batches', [
            'uuid' => $uuid, 'type' => $data['type'], 'file_name' => Str::limit($request->file('file')->getClientOriginalName(), 255, ''),
            'stored_path' => $path, 'status' => 'validated', 'created_by' => auth()->id(), 'created_at' => now(), 'updated_at' => now(),
        ]));

        $this->execute($id, $runner, false);

        return redirect('core/import/'.$uuid);
    }

    public function show(string $uuid)
    {
        $this->authorizeTask('is_view');
        $batch = $this->batch($uuid);

        return view('core.import.show', [
            'pageTitle' => 'Hasil Validasi Impor',
            'pageNote' => $batch->file_name,
            'batch' => $batch,
            'title' => ImportRegistry::make($batch->type)->title(),
            'rowErrors' => json_decode((string) $batch->errors, true) ?: [],
            'reconciliation' => json_decode((string) $batch->reconciliation, true) ?: [],
            // Batch dari CLI tidak menyimpan berkas di server: commit lewat CLI (--commit)
            'canCommit' => $this->can('is_add') && $batch->status === 'validated' && $batch->stored_path !== 'cli',
        ]);
    }

    public function commit(string $uuid, ImportRunner $runner)
    {
        $this->authorizeTask('is_add');
        $batch = $this->batch($uuid);
        abort_unless($batch->status === 'validated' && $batch->stored_path !== 'cli', 422, 'Batch tidak dapat di-commit.');

        $result = $this->execute((int) $batch->id, $runner, true);

        return redirect('core/import/'.$uuid)->with('status', $result['committed'] ? 'success' : 'error')
            ->with('message', $result['committed'] ? 'Impor tersimpan.' : 'Impor dibatalkan: data berubah sejak validasi. Periksa error lalu unggah ulang.');
    }

    // ---------------------------------------------------------------- bantuan

    private function execute(int $batchId, ImportRunner $runner, bool $commit): array
    {
        $batch = TenantScope::table('import_batches')->where('id', $batchId)->first();
        $importer = ImportRegistry::make($batch->type, ['sensitive' => $this->canSensitiveEmployees()]);

        try {
            $csv = CsvReader::read(Storage::disk('local')->path($batch->stored_path), ImportRegistry::MAX_WEB_ROWS);
            $result = $runner->run($importer, $csv['headers'], $csv['rows'], $commit);
            $total = count($csv['rows']);
        } catch (ValidationException $e) {
            $result = ['created' => 0, 'updated' => 0, 'errors' => [0 => array_merge(...array_values($e->errors()))], 'committed' => false, 'before' => 0, 'after' => 0];
            $total = 0;
        }

        $status = $result['committed'] ? 'committed' : ($result['errors'] === [] ? 'validated' : ($commit ? 'failed' : 'invalid'));

        TenantScope::table('import_batches')->where('id', $batchId)->update([
            'status' => $status,
            'total_rows' => $total,
            'error_rows' => count(array_filter(array_keys($result['errors']))),
            'created_rows' => $result['created'],
            'updated_rows' => $result['updated'],
            'errors' => json_encode($result['errors'], JSON_UNESCAPED_UNICODE),
            'reconciliation' => json_encode([
                'rows_in_file' => $total, 'created' => $result['created'], 'updated' => $result['updated'],
                'db_before' => $result['before'], 'db_after' => $result['after'], 'committed' => $result['committed'],
            ]),
            'committed_at' => $result['committed'] ? now() : null,
            'updated_at' => now(),
        ]);

        if ($result['committed']) {
            AuditLog::security('import_batches', $batchId, 'Impor '.$batch->type.' di-commit: '.$result['created'].' baru, '.$result['updated'].' diperbarui');
        }

        return $result;
    }

    private function batch(string $uuid): object
    {
        return TenantScope::table('import_batches')->where('uuid', $uuid)->first() ?? abort(404);
    }

    private function canSensitiveEmployees(): bool
    {
        $model = new Sximo;
        $info = $model->makeInfo('employees');
        $access = isset($info['id']) ? $model->validAccess($info['id'], session('gid')) : false;

        return is_array($access) && ! empty($access['is_sensitive']);
    }
}
