<?php

namespace App\Support\Tenancy;

use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;
use Illuminate\Validation\Rules\Unique;

/**
 * Filter tenant untuk semua jalur akses data (pengganti PostgreSQL RLS, ADR 0001).
 *
 * Aturan: tabel yang punya kolom `tenant_id` hanya boleh dibaca/diubah dalam
 * tenant aktif. Tanpa tenant context, akses ke tabel tersebut gagal keras
 * (MissingTenantContext) — tidak pernah mengembalikan data.
 *
 * Tabel tanpa kolom `tenant_id` (tb_module, tb_menu, tb_groups, tenants, ...)
 * tidak terpengaruh.
 */
final class TenantScope
{
    public const COLUMN = 'tenant_id';

    /** @var array<string, array<string, bool>> */
    private static array $columns = [];

    public static function applies(string $table): bool
    {
        return self::hasColumn($table, self::COLUMN);
    }

    public static function hasColumn(string $table, string $column): bool
    {
        $table = self::normalize($table);

        return self::$columns[$table][$column] ??= Schema::hasColumn($table, $column);
    }

    /** Dipanggil setelah migrasi (tes) supaya cache kolom tidak basi. */
    public static function flushCache(): void
    {
        self::$columns = [];
    }

    public static function tenantId(): int
    {
        return app(TenantContext::class)->requireTenantId();
    }

    /**
     * Potongan SQL mentah untuk query Sximo (querySelect/queryWhere).
     * tenant_id dipaksa integer, jadi aman disisipkan langsung.
     */
    public static function sql(string $table, ?string $alias = null): string
    {
        if (! self::applies($table)) {
            return '';
        }

        return ' AND '.($alias ?? self::normalize($table)).'.'.self::COLUMN.' = '.self::tenantId().' ';
    }

    /**
     * @template T of QueryBuilder|EloquentBuilder
     *
     * @param  T  $query
     * @return T
     */
    public static function apply($query, string $table, ?string $alias = null)
    {
        if (self::applies($table)) {
            $query->where(($alias ?? self::normalize($table)).'.'.self::COLUMN, self::tenantId());
        }

        return $query;
    }

    /** DB::table() yang sudah difilter tenant aktif. */
    public static function table(string $table): QueryBuilder
    {
        return self::apply(DB::table($table), $table);
    }

    /**
     * Data untuk INSERT: tenant_id selalu ditimpa dengan tenant aktif (input
     * pengguna tidak bisa memilih tenant lain) dan uuid diisi bila kolomnya ada.
     */
    public static function fillForInsert(string $table, array $data): array
    {
        if (self::applies($table)) {
            $data[self::COLUMN] = self::tenantId();
        }

        if (self::hasColumn($table, 'uuid') && empty($data['uuid'])) {
            $data['uuid'] = (string) Str::uuid7();
        }

        return $data;
    }

    /** Aturan validasi: nilai harus merujuk baris milik tenant aktif. */
    public static function existsRule(string $table, string $column): Exists
    {
        $rule = Rule::exists($table, $column);

        return self::applies($table) ? $rule->where(self::COLUMN, self::tenantId()) : $rule;
    }

    /** Aturan validasi: nilai unik di dalam tenant aktif. */
    public static function uniqueRule(string $table, string $column): Unique
    {
        $rule = Rule::unique($table, $column);

        return self::applies($table) ? $rule->where(self::COLUMN, self::tenantId()) : $rule;
    }

    /** Data untuk UPDATE: tenant_id dan uuid tidak boleh diubah lewat form. */
    public static function guardForUpdate(string $table, array $data): array
    {
        unset($data[self::COLUMN], $data['uuid']);

        return $data;
    }

    private static function normalize(string $table): string
    {
        return strtolower(trim($table, "` \t\n\r"));
    }
}
