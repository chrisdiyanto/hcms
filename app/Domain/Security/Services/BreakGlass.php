<?php

namespace App\Domain\Security\Services;

use App\Support\Security\Roles;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Akses darurat Super Administrator ke data satu tenant (blueprint 3.4 & 19.1):
 * wajib beralasan, berbatas waktu, setiap request tercatat, dan Tenant
 * Administrator tenant tersebut langsung diberi notifikasi.
 *
 * Data platform: tabel dibaca dengan DB::table langsung (bukan TenantScope),
 * karena break-glass justru yang menentukan tenant context.
 */
final class BreakGlass
{
    public const SESSION_KEY = 'break_glass_id';

    public const DURATIONS = [15, 30, 60];

    public function start(int $userId, int $tenantId, string $reason, int $minutes, ?string $ip): int
    {
        $minutes = in_array($minutes, self::DURATIONS, true) ? $minutes : self::DURATIONS[0];

        return DB::transaction(function () use ($userId, $tenantId, $reason, $minutes, $ip) {
            // Satu sesi break-glass aktif per pengguna
            DB::table('break_glass_accesses')->where('user_id', $userId)->whereNull('ended_at')
                ->where('expires_at', '>', now())->update(['ended_at' => now(), 'updated_at' => now()]);

            $id = DB::table('break_glass_accesses')->insertGetId([
                'tenant_id' => $tenantId,
                'user_id' => $userId,
                'reason' => $reason,
                'starts_at' => now(),
                'expires_at' => now()->addMinutes($minutes),
                'ip_address' => $ip,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $this->notifyTenantAdmins($tenantId, $userId, $reason, $minutes);

            return $id;
        });
    }

    public function active(int $userId, ?int $id): ?object
    {
        if (! $id) {
            return null;
        }

        return DB::table('break_glass_accesses')
            ->join('tenants', 'tenants.id', '=', 'break_glass_accesses.tenant_id')
            ->where('break_glass_accesses.id', $id)
            ->where('break_glass_accesses.user_id', $userId)
            ->whereNull('break_glass_accesses.ended_at')
            ->where('break_glass_accesses.expires_at', '>', now())
            ->first(['break_glass_accesses.*', 'tenants.code as tenant_code', 'tenants.name as tenant_name']);
    }

    public function end(int $userId, ?int $id): void
    {
        if ($id) {
            DB::table('break_glass_accesses')->where('id', $id)->where('user_id', $userId)->whereNull('ended_at')
                ->update(['ended_at' => now(), 'updated_at' => now()]);
        }
    }

    /** Catat satu request selama break-glass. */
    public function record(object $access, string $method, string $path, ?string $ip): void
    {
        DB::table('break_glass_accesses')->where('id', $access->id)->increment('request_count');
        DB::table('tb_logs')->insert([
            'ipaddress' => (string) $ip,
            'user_id' => $access->user_id,
            'module' => 'breakglass',
            'task' => Str::limit($method, 50, ''),
            'note' => Str::limit('Tenant '.$access->tenant_code.' #'.$access->id.': '.$method.' /'.ltrim($path, '/'), 1000, ''),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function notifyTenantAdmins(int $tenantId, int $actorId, string $reason, int $minutes): void
    {
        $actor = DB::table('tb_users')->where('id', $actorId)->value('username');
        $admins = DB::table('tb_users')
            ->join('tb_users_groups', 'tb_users_groups.user_id', '=', 'tb_users.id')
            ->where('tb_users.tenant_id', $tenantId)
            ->where('tb_users_groups.group_id', Roles::id(Roles::TENANT_ADMIN))
            ->pluck('tb_users.id');

        foreach ($admins as $adminId) {
            DB::table('tb_notification')->insert([
                'userid' => $adminId,
                'url' => 'dashboard',
                'title' => 'Akses darurat (break-glass) ke data organisasi Anda',
                'note' => Str::limit('Super Administrator '.$actor.' membuka akses darurat selama '.$minutes.' menit. Alasan: '.$reason, 1000, ''),
                'created' => now(),
                'icon' => 'fa fa-shield-alt',
                'is_read' => '0',
                'postedBy' => $actorId,
            ]);
        }
    }
}
