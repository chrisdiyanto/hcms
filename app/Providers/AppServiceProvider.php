<?php

namespace App\Providers;

use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        // scoped: di-reset tiap request Octane dan tiap job antrean
        $this->app->scoped(\App\Support\Tenancy\TenantContext::class);
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        // Riwayat login (IAM)
        $history = \App\Domain\Security\Listeners\RecordLoginHistory::class;
        \Illuminate\Support\Facades\Event::listen(\Illuminate\Auth\Events\Login::class, [$history, 'onLogin']);
        \Illuminate\Support\Facades\Event::listen(\Illuminate\Auth\Events\Logout::class, [$history, 'onLogout']);
        \Illuminate\Support\Facades\Event::listen(\Illuminate\Auth\Events\Failed::class, [$history, 'onFailed']);

        // Hanya percaya header X-Forwarded-* dari proxy yang terdaftar (TRUSTED_PROXIES),
        // supaya IP klien (dipakai IP block & rate limit) tidak bisa dipalsukan.
        // Fallback alamat pengirim email ke email aplikasi yang diatur di Sximo (cnf_email)
        if (!config('mail.from.address') && filter_var(config('sximo.cnf_email'), FILTER_VALIDATE_EMAIL)) {
            config(['mail.from.address' => config('sximo.cnf_email')]);
        }

        $proxies = trim((string) config('app.trusted_proxies'));
        if ($proxies !== '') {
            TrustProxies::at($proxies === '*' ? '*' : array_map('trim', explode(',', $proxies)));
        }
    }
}
