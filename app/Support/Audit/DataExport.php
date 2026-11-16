<?php

namespace App\Support\Audit;

use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Ekspor data (blueprint 3.3): wajib alasan, ekspor massal butuh izin Approve
 * pada modul, setiap ekspor tercatat di data_export_logs + audit_logs, dan
 * berkas diberi watermark yang merujuk nomor log.
 */
final class DataExport
{
    /** Di atas jumlah baris ini dianggap ekspor massal. */
    public const MASS_THRESHOLD = 500;

    public const MIN_REASON = 10;

    public static function validReason(?string $reason): bool
    {
        return mb_strlen(trim((string) $reason)) >= self::MIN_REASON;
    }

    public static function allowed(int $rows, array $access): bool
    {
        return $rows <= self::MASS_THRESHOLD || ! empty($access['is_approve']);
    }

    public static function log(string $module, string $format, int $rows, string $reason, array $filters = []): int
    {
        $id = DB::table('data_export_logs')->insertGetId([
            'tenant_id' => app(TenantContext::class)->tenantId(),
            'user_id' => (int) auth()->id(),
            'module' => Str::limit($module, 60, ''),
            'format' => Str::limit($format, 20, ''),
            'row_count' => $rows,
            'reason' => Str::limit(trim($reason), 500, ''),
            'filters' => json_encode($filters, JSON_UNESCAPED_UNICODE),
            'approved_mass_export' => $rows > self::MASS_THRESHOLD,
            'ip_address' => request()->ip(),
            'created_at' => now(),
        ]);

        AuditLog::exported($module, $id, $rows.' baris ('.$format.'). Alasan: '.Str::limit(trim($reason), 300, ''));

        return $id;
    }

    public static function watermark(int $logId): string
    {
        $user = auth()->user();

        return 'RAHASIA — diekspor oleh '.($user->username ?? '?').' pada '.now()->format('Y-m-d H:i').' · log ekspor #'.$logId;
    }
}
