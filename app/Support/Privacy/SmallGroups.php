<?php

namespace App\Support\Privacy;

use App\Models\Sximo;

/**
 * Penyamaran kelompok kecil (kamus metrik K9 / prasyarat P5 CBHRM).
 *
 * Agregat yang mewakili 1–4 orang ditampilkan "< 5" (nol tetap nol: tidak mengungkap siapa pun)
 * HANYA bagi pengguna yang tidak berhak membuka data individual dimensi itu — keputusan
 * 2026-11-17: pengguna yang dapat membuka baris pegawainya sendiri tidak dilindungi apa pun oleh
 * penyamaran. Hak "membuka data individual" = izin is_detail pada modul sumber barisnya
 * (mis. `employees` untuk data kepegawaian, `perfperiods` untuk daftar rating).
 * Ekspor agregat selalu disamarkan (masks(force: true)).
 */
final class SmallGroups
{
    public const MIN = 5;

    public const LABEL = '< 5';

    /** @var array<string, bool> per request (instance scoped di container) */
    private array $cache = [];

    /** Apakah agregat dimensi modul ini perlu disamarkan untuk pengguna saat ini. */
    public function masks(string $rowModule, bool $force = false): bool
    {
        if ($force) {
            return true;
        }
        $gid = (int) session('gid');

        return $this->cache[$rowModule.':'.$gid] ??= ! $this->canOpenRows($rowModule, $gid);
    }

    public static function hidden(int|float|string|null $count): bool
    {
        return $count !== null && (float) $count > 0 && (float) $count < self::MIN;
    }

    /** Jumlah orang untuk ditampilkan: angka berformat id-ID atau "< 5". */
    public static function count(int|float|string|null $count, bool $mask): string
    {
        if ($mask && self::hidden($count)) {
            return self::LABEL;
        }

        return number_format((float) $count, 0, ',', '.');
    }

    /** Nilai turunan (persen, rata-rata) dari kelompok berukuran $n: disembunyikan bila kelompoknya disamarkan. */
    public static function derived(int|float|string|null $n, bool $mask, string $formatted): string
    {
        return $mask && self::hidden($n) ? '—' : $formatted;
    }

    /**
     * Untuk data grafik: nilai 1–4 menjadi null (tidak digambar) bila disamarkan.
     *
     * @param  array<int, int|float|null>  $values
     * @return array<int, int|float|null>
     */
    public static function chartValues(array $values, bool $mask): array
    {
        return $mask ? array_map(fn ($v) => self::hidden($v) ? null : $v, $values) : $values;
    }

    private function canOpenRows(string $module, int $gid): bool
    {
        $model = new Sximo;
        $info = $model->makeInfo($module);
        $access = isset($info['id']) ? $model->validAccess($info['id'], $gid) : false;

        return is_array($access) && ! empty($access['is_detail']);
    }
}
