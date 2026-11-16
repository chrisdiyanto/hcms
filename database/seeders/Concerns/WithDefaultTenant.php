<?php

namespace Database\Seeders\Concerns;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Seeder data master lama: semua baris masuk ke tenant DEFAULT
 * (dibuat oleh migrasi 2026_11_16_100000_create_tenants_tables).
 */
trait WithDefaultTenant
{
    protected function defaultTenantId(): int
    {
        return (int) DB::table('tenants')->where('code', 'DEFAULT')->value('id');
    }

    protected function withTenant(array $rows, bool $uuid = false): array
    {
        $tenantId = $this->defaultTenantId();

        return array_map(function (array $row) use ($tenantId, $uuid) {
            $row['tenant_id'] = $tenantId;
            if ($uuid && empty($row['uuid'])) {
                $row['uuid'] = (string) Str::uuid7();
            }

            return $row;
        }, $rows);
    }
}
