<?php

namespace App\Providers;

use App\Domain\Security\Listeners\RecordLoginHistory;
use App\Domain\Security\Services\Mfa;
use App\Support\Privacy\SmallGroups;
use App\Support\Tenancy\TenantContext;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Support\Facades\Event;
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
        $this->app->scoped(TenantContext::class);
        // cache hak "buka data individual" per request (penyamaran kelompok kecil, K9)
        $this->app->scoped(SmallGroups::class);
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        // Riwayat login (IAM)
        $history = RecordLoginHistory::class;
        Event::listen(Login::class, [$history, 'onLogin']);
        Event::listen(Logout::class, [$history, 'onLogout']);
        Event::listen(Failed::class, [$history, 'onFailed']);

        // Setiap login baru wajib melewati MFA lagi (status lama di sesi tidak terbawa)
        Event::listen(Login::class, function () {
            if (request()->hasSession()) {
                request()->session()->forget(Mfa::SESSION_PASSED);
            }
        });

        // Hanya percaya header X-Forwarded-* dari proxy yang terdaftar (TRUSTED_PROXIES),
        // supaya IP klien (dipakai IP block & rate limit) tidak bisa dipalsukan.
        // Fallback alamat pengirim email ke email aplikasi yang diatur di Sximo (cnf_email)
        if (! config('mail.from.address') && filter_var(config('sximo.cnf_email'), FILTER_VALIDATE_EMAIL)) {
            config(['mail.from.address' => config('sximo.cnf_email')]);
        }

        $proxies = trim((string) config('app.trusted_proxies'));
        if ($proxies !== '') {
            TrustProxies::at($proxies === '*' ? '*' : array_map('trim', explode(',', $proxies)));
        }
    }
}
