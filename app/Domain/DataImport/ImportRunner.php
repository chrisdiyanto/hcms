<?php

namespace App\Domain\DataImport;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

/**
 * Menjalankan importer atas baris CSV.
 *
 * - Dry run: semua baris diproses di dalam transaksi lalu di-rollback, sehingga
 *   validasi memakai aturan & data sebenarnya (termasuk rujukan antar-baris)
 *   tanpa menyimpan apa pun.
 * - Commit: proses yang sama; bila ada satu saja error, seluruh batch di-rollback.
 * Error dikumpulkan per nomor baris file agar bisa diperbaiki sekaligus.
 */
final class ImportRunner
{
    /**
     * @return array{created: int, updated: int, errors: array<int, string[]>, committed: bool, before: int, after: int}
     */
    public function run(Importer $importer, array $headers, array $rows, bool $commit): array
    {
        $missing = array_diff($importer->requiredColumns(), $headers);
        if ($missing !== []) {
            throw ValidationException::withMessages(['file' => 'Kolom wajib tidak ada: '.implode(', ', $missing).'.']);
        }

        $result = ['created' => 0, 'updated' => 0, 'errors' => [], 'committed' => false, 'before' => $importer->countInDatabase(), 'after' => 0];

        DB::beginTransaction();
        try {
            foreach ($rows as $line => $row) {
                try {
                    // Savepoint per baris: baris gagal tidak meninggalkan sisa perubahan
                    $outcome = DB::transaction(fn () => $importer->process($row, (int) $line));
                    $result[$outcome]++;
                } catch (ValidationException $e) {
                    $result['errors'][$line] = array_merge(...array_values($e->errors()));
                } catch (HttpExceptionInterface $e) {
                    $result['errors'][$line] = [$e->getMessage() ?: 'Data rujukan tidak ditemukan atau tidak diizinkan.'];
                }
            }

            if ($result['errors'] === []) {
                foreach ($importer->finalize() as $line => $messages) {
                    $result['errors'][$line] = array_merge($result['errors'][$line] ?? [], $messages);
                }
            }

            $result['after'] = $importer->countInDatabase();

            if ($commit && $result['errors'] === []) {
                DB::commit();
                $result['committed'] = true;
            } else {
                DB::rollBack();
            }
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        ksort($result['errors']);

        return $result;
    }
}
