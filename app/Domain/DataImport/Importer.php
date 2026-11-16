<?php

namespace App\Domain\DataImport;

use App\Support\Tenancy\TenantScope;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Dasar importer per jenis data. Turunan mendefinisikan kolom dan cara
 * menyimpan satu baris (upsert berdasarkan kode alami: unit_code, code,
 * employee_number). Semua penulisan memakai aturan domain yang sama dengan
 * input manual (tenant, audit, validasi relasi).
 */
abstract class Importer
{
    /** @var array<string, array<string, int>> cache kode → id per tabel */
    private array $lookups = [];

    /** Opsi dari pengunggah, mis. izin data sensitif. */
    public function __construct(protected array $options = []) {}

    abstract public function title(): string;

    /** @return array<string, array{required?: bool, description: string, example?: string}> */
    abstract public function columns(): array;

    /** Simpan satu baris. @return 'created'|'updated' */
    abstract public function process(array $row, int $line): string;

    /** Tahap kedua sesudah semua baris (mis. reporting line). @return array<int, string[]> error per baris */
    public function finalize(): array
    {
        return [];
    }

    /** Jumlah baris di database sesudah impor, untuk rekonsiliasi. */
    abstract public function countInDatabase(): int;

    public function requiredColumns(): array
    {
        return array_keys(array_filter($this->columns(), fn ($c) => $c['required'] ?? false));
    }

    // ---------------------------------------------------------------- bantuan

    protected function validate(array $row, array $rules, array $attributes = []): array
    {
        $validator = Validator::make($row, $rules, [], $attributes);
        if ($validator->fails()) {
            throw ValidationException::withMessages($validator->errors()->toArray());
        }

        return $validator->validated();
    }

    protected function fail(string $message): never
    {
        throw ValidationException::withMessages(['row' => $message]);
    }

    /** Cari id berdasarkan kode di tenant aktif (cache per batch, diperbarui saat baris baru dibuat). */
    protected function idByCode(string $table, ?string $code, string $codeColumn = 'code', string $idColumn = 'id', bool $softDeletes = true): ?int
    {
        if ($code === null || $code === '') {
            return null;
        }
        $key = strtoupper($code);
        if (! isset($this->lookups[$table][$key])) {
            $id = TenantScope::table($table)->whereRaw('UPPER('.$codeColumn.') = ?', [$key])
                ->when($softDeletes, fn ($q) => $q->whereNull('deleted_at'))->value($idColumn);
            if ($id === null) {
                return null;
            }
            $this->lookups[$table][$key] = (int) $id;
        }

        return $this->lookups[$table][$key];
    }

    protected function remember(string $table, string $code, int $id): void
    {
        $this->lookups[$table][strtoupper($code)] = $id;
    }

    protected function requireId(string $table, ?string $code, string $label, string $codeColumn = 'code', string $idColumn = 'id', bool $softDeletes = true): int
    {
        return $this->idByCode($table, $code, $codeColumn, $idColumn, $softDeletes)
            ?? $this->fail($label.' "'.$code.'" tidak ditemukan. Impor data rujukannya lebih dulu.');
    }

    protected static function bool(?string $value, bool $default = false): bool
    {
        if ($value === null || $value === '') {
            return $default;
        }

        return in_array(strtolower($value), ['1', 'ya', 'y', 'yes', 'true', 'x', 'aktif'], true);
    }

    /** Tanggal Y-m-d atau d/m/Y (format Excel Indonesia). */
    protected static function date(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        foreach (['Y-m-d', 'd/m/Y', 'd-m-Y'] as $format) {
            try {
                $date = Carbon::createFromFormat('!'.$format, $value);
                if ($date && $date->format($format) === $value) {
                    return $date->toDateString();
                }
            } catch (\Throwable) {
            }
        }

        return $value; // biarkan aturan validasi `date` yang menolak
    }

    protected static function nullable(array $row): array
    {
        return array_map(fn ($v) => $v === '' ? null : $v, $row);
    }
}
