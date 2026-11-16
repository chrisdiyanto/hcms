<?php

namespace App\Domain\DataImport\Importers;

use App\Domain\DataImport\Importer;
use App\Support\Audit\AuditLog;
use App\Support\Tenancy\TenantScope;
use Illuminate\Support\Facades\DB;

/**
 * Dasar importer master job architecture (tabel dengan kolom standar:
 * code, uuid, tenant_id, created_by, deleted_at). Upsert berdasarkan `code`.
 */
abstract class MasterImporter extends Importer
{
    abstract protected function table(): string;

    /** Validasi + ubah baris CSV menjadi kolom tabel (tanpa `code`). */
    abstract protected function map(array $row): array;

    protected function codeRules(): array
    {
        return ['required', 'max:30', 'regex:/^[A-Za-z0-9._-]+$/'];
    }

    public function process(array $row, int $line): string
    {
        $row = self::nullable($row);
        $this->validate($row, ['code' => $this->codeRules()], ['code' => 'kode']);
        $code = strtoupper($row['code']);
        $data = $this->map($row) + ['updated_by' => auth()->id(), 'updated_at' => now()];

        $existing = TenantScope::table($this->table())->whereRaw('UPPER(code) = ?', [$code])->first();
        if ($existing) {
            if ($existing->deleted_at) {
                $this->fail('Kode '.$code.' milik data yang sudah dihapus; gunakan kode lain.');
            }
            TenantScope::table($this->table())->where('id', $existing->id)->update($data);
            AuditLog::updated($this->table(), $existing->id, $existing, TenantScope::table($this->table())->where('id', $existing->id)->first(), 'Impor data');
            $this->afterSave((int) $existing->id, $row);

            return 'updated';
        }

        $data = TenantScope::fillForInsert($this->table(), $data + ['code' => $code, 'created_by' => auth()->id(), 'created_at' => now()]);
        $id = (int) DB::table($this->table())->insertGetId($data);
        AuditLog::created($this->table(), $id, $data, 'Impor data');
        $this->afterSave($id, $row);
        $this->remember($this->table(), $code, $id);

        return 'created';
    }

    protected function afterSave(int $id, array $row): void {}

    public function countInDatabase(): int
    {
        return TenantScope::table($this->table())->whereNull('deleted_at')->count();
    }
}
