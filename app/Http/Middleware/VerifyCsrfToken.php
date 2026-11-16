<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken as Middleware;

class VerifyCsrfToken extends Middleware
{
    /**
     * Indicates whether the XSRF-TOKEN cookie should be set on the response.
     *
     * @var bool
     */
    protected $addHttpCookie = true;

    /**
     * The URIs that should be excluded from CSRF verification.
     *
     * @var array
     */
    protected $except = [
        'sximoapi*',
        // Editor kode (sximo/code*, sximo/module/source*, sximo/module/code*) sengaja
        // TIDAK dikecualikan: tanpa CSRF, halaman luar bisa menulis file PHP lewat sesi admin.
    ];
}
