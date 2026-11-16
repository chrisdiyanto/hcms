<?php

namespace App\Http\Middleware;

use Closure;

class Cors
{
    /**
     * Handle an incoming request.
     *
     * Header CORS hanya dikirim untuk origin yang diizinkan
     * (config app.cors_allowed_origins, default: APP_URL saja).
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        $origin = $request->headers->get('Origin');
        $allowed = $this->allowedOrigin($origin);

        // Preflight dari origin yang diizinkan langsung dijawab
        if ($allowed !== null && $request->isMethod('OPTIONS') && $request->headers->has('Access-Control-Request-Method')) {
            $response = response('', 204);
        } else {
            $response = $next($request);
        }

        if ($allowed === null) {
            return $response;
        }

        $response->headers->set('Access-Control-Allow-Origin', $allowed);
        $response->headers->set('Access-Control-Allow-Methods', 'GET, POST, PUT, PATCH, DELETE, OPTIONS');
        $response->headers->set('Access-Control-Allow-Headers', 'Origin, Content-Type, X-Auth-Token, Authorization, X-Requested-With');
        if ($allowed !== '*') {
            $response->headers->set('Vary', trim($response->headers->get('Vary').', Origin', ', '));
        }

        return $response;
    }

    protected function allowedOrigin($origin)
    {
        if (!$origin) {
            return null;
        }

        $list = array_filter(array_map('trim', explode(',', (string) config('app.cors_allowed_origins'))));
        if (in_array('*', $list, true)) {
            return '*';
        }

        $appUrl = parse_url((string) config('app.url'));
        if (isset($appUrl['scheme'], $appUrl['host'])) {
            $list[] = $appUrl['scheme'].'://'.$appUrl['host'].(isset($appUrl['port']) ? ':'.$appUrl['port'] : '');
        }

        foreach ($list as $item) {
            if (strcasecmp(rtrim($item, '/'), $origin) === 0) {
                return $origin;
            }
        }

        return null;
    }
}
