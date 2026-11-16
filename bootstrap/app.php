<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(dirname(__DIR__))
    ->withRouting(
        function () {
            // Health check untuk load balancer/monitoring. Didaftarkan di sini karena
            // Laravel mengabaikan argumen $health bila closure routing kustom dipakai.
            \Illuminate\Support\Facades\Route::get('/up', function () {
                \Illuminate\Support\Facades\Event::dispatch(new \Illuminate\Foundation\Events\DiagnosingHealth);

                return response('OK', 200);
            });

            \Illuminate\Support\Facades\Route::middleware('api')
                ->prefix('api')
                ->namespace('App\Http\Controllers')
                ->group(base_path('routes/api.php'));

            \Illuminate\Support\Facades\Route::middleware('web')
                ->namespace('App\Http\Controllers')
                ->group(base_path('routes/web.php'));
                
            // Seluruh panel Sximo (builder, config, kode, database) wajib login
            \Illuminate\Support\Facades\Route::middleware(['web', 'auth'])
                ->namespace('App\Http\Controllers\Sximo')
                ->group(base_path('routes/sximo.php'));
        },
        null,
        null,
        __DIR__.'/../routes/console.php', // commands
        null,
        null,
        '/up', // health
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->redirectGuestsTo(fn () => url('user/login'));
        $middleware->append(\App\Http\Middleware\Cors::class);

        // Tenant aktif diisi sesudah sesi & auth tersedia (grup web)
        $middleware->web(append: [\App\Http\Middleware\SetTenantContext::class]);

        $middleware->alias([
            'ipblocked' => \App\Http\Middleware\IpblockedMiddleware::class,
            'cors' => \App\Http\Middleware\Cors::class, 
            'sximoauth' => \App\Http\Middleware\SximoAuth::class,
        ]);

        $middleware->priority([
            \Illuminate\Session\Middleware\StartSession::class,
            \Illuminate\View\Middleware\ShareErrorsFromSession::class,
            \App\Http\Middleware\Authenticate::class,
            \Illuminate\Routing\Middleware\ThrottleRequests::class,
            \Illuminate\Session\Middleware\AuthenticateSession::class,
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
            \Illuminate\Auth\Middleware\Authorize::class,
        ]);

        // Trusted proxies diatur lewat config app.trusted_proxies (lihat AppServiceProvider)
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
