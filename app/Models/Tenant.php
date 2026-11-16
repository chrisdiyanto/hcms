<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Tenant = batas hukum & kontrak (blueprint 2.3). Data platform, bukan data tenant,
 * jadi tidak memakai filter tenant. Hanya Superadmin yang mengelolanya.
 */
class Tenant extends Model
{
    use HasUuids, SoftDeletes;

    public const STATUSES = ['active', 'suspended', 'terminated'];

    public const LICENSE_TYPES = ['enterprise', 'saas'];

    protected $fillable = [
        'code', 'name', 'legal_name', 'status', 'license_type', 'max_employees',
        'data_region', 'contact_name', 'contact_email', 'contact_phone',
        'activated_at', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'activated_at' => 'datetime',
        'max_employees' => 'integer',
    ];

    /** PK tetap BIGINT; HasUuids hanya mengisi kolom uuid (ADR 0001). */
    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }
}
