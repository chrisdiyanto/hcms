<?php

namespace App\Domain\Security\Listeners;

use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Mencatat login, logout, dan login gagal ke login_histories.
 *
 * UserController@postSignin mencoba Auth::attempt dua kali (email lalu username),
 * jadi satu login sukses lewat username memicu event Failed lebih dulu. Kegagalan
 * karena itu ditahan di atribut request dan baru ditulis saat request selesai,
 * hanya bila tidak ada login sukses pada request yang sama.
 */
class RecordLoginHistory
{
    private const PENDING = 'hcms.login_failed';

    public function onLogin(Login $event): void
    {
        request()->attributes->remove(self::PENDING);
        $this->write('login', $event->user->getAuthIdentifier(), $event->user->tenant_id ?? null, $event->user->email ?? null);
    }

    public function onLogout(Logout $event): void
    {
        if ($event->user) {
            $this->write('logout', $event->user->getAuthIdentifier(), $event->user->tenant_id ?? null, $event->user->email ?? null);
        }
    }

    public function onFailed(Failed $event): void
    {
        $request = request();
        $login = $event->credentials['email'] ?? $event->credentials['username'] ?? null;

        // Simpan kegagalan terakhir; ditulis di terminating() bila tidak disusul login sukses
        $first = ! $request->attributes->has(self::PENDING);
        $request->attributes->set(self::PENDING, [
            'user_id' => $event->user?->getAuthIdentifier(),
            'tenant_id' => $event->user->tenant_id ?? null,
            'login' => $login,
        ]);

        if ($first) {
            app()->terminating(function () use ($request) {
                $pending = $request->attributes->get(self::PENDING);
                if ($pending) {
                    $request->attributes->remove(self::PENDING);
                    $this->write('failed', $pending['user_id'], $pending['tenant_id'], $pending['login']);
                }
            });
        }
    }

    private function write(string $event, $userId, $tenantId, ?string $login): void
    {
        $request = request();

        DB::table('login_histories')->insert([
            'tenant_id' => $tenantId,
            'user_id' => $userId,
            'login' => $login !== null ? Str::limit($login, 150, '') : null,
            'event' => $event,
            'ip_address' => $request->ip(),
            'user_agent' => Str::limit((string) $request->userAgent(), 255, ''),
            'created_at' => now(),
        ]);
    }
}
