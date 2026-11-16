<?php

namespace App\Support\Audit;

use App\Domain\Security\Services\BreakGlass;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Audit trail (blueprint 3.3): perubahan data, akses data sensitif, ekspor, dan
 * aksi keamanan. Tabel audit_logs append-only (trigger database).
 *
 * - Update hanya mencatat kolom yang berubah.
 * - Kolom rahasia (password, secret MFA, identitas negara) tidak pernah disimpan
 *   nilainya; hanya fakta bahwa kolom itu berubah.
 * - Kegagalan menulis audit tidak boleh diam-diam diabaikan: exception diteruskan
 *   sehingga transaksi bisnis ikut gagal.
 */
final class AuditLog
{
    public const MASK = '[disembunyikan]';

    public const SENSITIVE = [
        'password', 'mfa_secret', 'mfa_recovery_codes', 'mfa_last_timestep', 'remember_token', 'reminder', 'activation',
        'nik_ktp', 'nik_hash', 'npwp', 'bpjs_kesehatan', 'bpjs_ketenagakerjaan',
    ];

    private const IGNORED = ['created_at', 'updated_at', 'updated_by', 'last_activity', 'last_login', 'login_attempt'];

    public static function created(string $type, $id, array|object|null $values, ?string $description = null): void
    {
        self::write('created', $type, $id, null, self::clean(self::toArray($values)), $description);
    }

    public static function updated(string $type, $id, array|object|null $before, array|object|null $after, ?string $description = null): void
    {
        $before = self::toArray($before);
        $after = self::toArray($after);

        $old = $new = [];
        foreach ($after as $key => $value) {
            if (in_array($key, self::IGNORED, true)) {
                continue;
            }
            if (! array_key_exists($key, $before) || (string) $before[$key] !== (string) $value) {
                $old[$key] = $before[$key] ?? null;
                $new[$key] = $value;
            }
        }

        if ($new === []) {
            return;
        }

        self::write('updated', $type, $id, self::mask($old), self::mask($new), $description);
    }

    public static function deleted(string $type, $id, array|object|null $before, ?string $description = null): void
    {
        self::write('deleted', $type, $id, self::clean(self::toArray($before)), null, $description);
    }

    public static function sensitiveViewed(string $type, $id, string $description): void
    {
        self::write('sensitive_viewed', $type, $id, null, null, $description);
    }

    public static function exported(string $module, int $exportLogId, string $description): void
    {
        self::write('exported', $module, $exportLogId, null, null, $description);
    }

    public static function security(string $type, $id, string $description, array $values = []): void
    {
        self::write('security', $type, $id, null, $values === [] ? null : self::mask($values), $description);
    }

    // ---------------------------------------------------------------- bantuan

    private static function write(string $event, string $type, $id, ?array $old, ?array $new, ?string $description): void
    {
        $request = app()->runningInConsole() && ! app()->runningUnitTests() ? null : request();
        $context = app(TenantContext::class);

        DB::table('audit_logs')->insert([
            'tenant_id' => $context->tenantId() ?? (is_array($new) && isset($new['tenant_id']) ? (int) $new['tenant_id'] : (is_array($old) && isset($old['tenant_id']) ? (int) $old['tenant_id'] : null)),
            'user_id' => auth()->id(),
            'event' => $event,
            'auditable_type' => Str::limit($type, 60, ''),
            'auditable_id' => $id === null ? null : Str::limit((string) $id, 64, ''),
            'old_values' => $old === null ? null : json_encode($old, JSON_UNESCAPED_UNICODE),
            'new_values' => $new === null ? null : json_encode($new, JSON_UNESCAPED_UNICODE),
            'description' => $description === null ? null : Str::limit($description, 500, ''),
            'ip_address' => $request?->ip(),
            'user_agent' => $request ? Str::limit((string) $request->userAgent(), 255, '') : null,
            'url' => $request ? Str::limit($request->method().' '.$request->path(), 500, '') : 'console',
            'break_glass_id' => $request && $request->hasSession() ? ($request->session()->get(BreakGlass::SESSION_KEY) ?: null) : null,
            'created_at' => now(),
        ]);
    }

    private static function toArray(array|object|null $values): array
    {
        return $values === null ? [] : (array) $values;
    }

    /** Buang kolom teknis dan samarkan kolom rahasia. */
    private static function clean(array $values): array
    {
        return self::mask(array_diff_key($values, array_flip(self::IGNORED)));
    }

    private static function mask(array $values): array
    {
        foreach ($values as $key => $value) {
            if (in_array($key, self::SENSITIVE, true) && $value !== null) {
                $values[$key] = self::MASK;
            }
        }

        return $values;
    }
}
