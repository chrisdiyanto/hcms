<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Rapikan URL berawalan garis miring ganda (mis. "//executive/dashboard" dari tautan yang
 * disalin atau dibuat dengan base URL ber-slash) menjadi path biasa, untuk semua halaman GET.
 * Hanya mengalihkan ke host yang sama, sehingga tidak bisa dipakai sebagai open redirect.
 */
class NormalizeExecutiveDashboardPath
{
    public function handle(Request $request, Closure $next): Response
    {
        $uri = (string) $request->server->get('REQUEST_URI', '');
        if ($request->isMethod('GET') && preg_match('#^/{2,}([^/].*)?$#', $uri)) {
            // Location relatif ("/path"): selalu host yang sama, tidak bergantung header Host
            return new RedirectResponse('/'.ltrim($uri, '/'), 301);
        }

        return $next($request);
    }
}
