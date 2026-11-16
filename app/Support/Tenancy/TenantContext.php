<?php

namespace App\Support\Tenancy;

use App\Support\Tenancy\Exceptions\MissingTenantContext;

/**
 * Tenant dan company yang aktif untuk request/job yang sedang berjalan.
 *
 * Didaftarkan sebagai binding `scoped` di AppServiceProvider supaya di-reset
 * setiap request Octane/FrankenPHP dan setiap job antrean. Jangan menyimpan
 * tenant aktif di properti static atau singleton lain.
 *
 * Belum dipasang ke middleware/model; penerapannya dikerjakan di Fase 1
 * (lihat docs/TAHAPAN-PEMBANGUNAN-HCMS.md bagian B.1).
 */
class TenantContext
{
    private ?int $tenantId = null;

    private ?int $companyId = null;

    public function set(int $tenantId, ?int $companyId = null): void
    {
        $this->tenantId = $tenantId;
        $this->companyId = $companyId;
    }

    public function clear(): void
    {
        $this->tenantId = null;
        $this->companyId = null;
    }

    public function has(): bool
    {
        return $this->tenantId !== null;
    }

    public function tenantId(): ?int
    {
        return $this->tenantId;
    }

    public function companyId(): ?int
    {
        return $this->companyId;
    }

    /**
     * Tenant aktif, atau gagal keras. Dipakai di query data bisnis supaya
     * query tanpa tenant context tidak pernah mengembalikan data.
     */
    public function requireTenantId(): int
    {
        if ($this->tenantId === null) {
            throw new MissingTenantContext;
        }

        return $this->tenantId;
    }

    /**
     * Jalankan $callback dengan tenant lain, lalu kembalikan konteks semula.
     * Untuk job antrean, command, dan seeder.
     */
    public function runAs(int $tenantId, ?int $companyId, callable $callback): mixed
    {
        $previous = [$this->tenantId, $this->companyId];
        $this->set($tenantId, $companyId);

        try {
            return $callback();
        } finally {
            [$this->tenantId, $this->companyId] = $previous;
        }
    }
}
