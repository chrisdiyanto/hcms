<?php

namespace App\Domain\DataImport;

use Illuminate\Validation\ValidationException;

/**
 * Pembaca CSV untuk migrasi data: UTF-8 (BOM dibuang), pemisah `,` atau `;`
 * (Excel Indonesia memakai `;`), header dinormalisasi ke snake_case.
 *
 * @return array{headers: string[], rows: array<int, array<string, string>>} baris diberi nomor baris file
 */
final class CsvReader
{
    public static function read(string $path, int $maxRows): array
    {
        $handle = fopen($path, 'r');
        if (! $handle) {
            throw ValidationException::withMessages(['file' => 'Berkas tidak dapat dibaca.']);
        }

        $first = (string) fgets($handle);
        $first = preg_replace('/^\xEF\xBB\xBF/', '', $first);
        $delimiter = substr_count($first, ';') > substr_count($first, ',') ? ';' : ',';
        $headers = array_map(fn ($h) => self::normalize($h), str_getcsv(trim($first), $delimiter));

        if ($headers === [] || $headers === ['']) {
            throw ValidationException::withMessages(['file' => 'Baris pertama harus berisi nama kolom.']);
        }

        $rows = [];
        $line = 1;
        while (($data = fgetcsv($handle, 0, $delimiter)) !== false) {
            $line++;
            if ($data === [null] || trim(implode('', $data)) === '') {
                continue;
            }
            if (count($rows) >= $maxRows) {
                fclose($handle);
                throw ValidationException::withMessages(['file' => "Maksimal {$maxRows} baris per berkas. Pecah berkas atau gunakan command `php artisan hcms:import`."]);
            }
            $row = [];
            foreach ($headers as $i => $header) {
                $row[$header] = trim((string) ($data[$i] ?? ''));
            }
            $rows[$line] = $row;
        }
        fclose($handle);

        return ['headers' => $headers, 'rows' => $rows];
    }

    public static function normalize(string $header): string
    {
        return trim(preg_replace('/[^a-z0-9]+/', '_', strtolower(trim($header))), '_');
    }
}
