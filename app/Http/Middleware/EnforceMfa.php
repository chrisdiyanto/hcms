<?php

namespace App\Http\Middleware;

use App\Domain\Security\Services\Mfa;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Sesudah login kata sandi, pengguna harus lolos MFA bila:
 * - MFA-nya sudah aktif (diminta kode), atau
 * - salah satu perannya mewajibkan MFA (diarahkan ke pendaftaran).
 * Pengguna lain langsung lolos (ditandai di sesi agar tidak dicek tiap request).
 */
class EnforceMfa
{
    private const EXEMPT = ['user/mfa', 'user/mfa/*', 'user/logout', 'user/login', 'user/signin', 'up'];

    public function __construct(private Mfa $mfa) {}

    public function handle(Request $request, Closure $next)
    {
        if (! Auth::check() || $request->session()->get(Mfa::SESSION_PASSED) || $request->is(...self::EXEMPT)) {
            return $next($request);
        }

        $userId = (int) Auth::id();

        if ($this->mfa->isEnabledFor($userId)) {
            $target = 'user/mfa';
        } elseif ($this->mfa->isRequiredFor($userId)) {
            $target = 'user/mfa/setup';
        } else {
            $request->session()->put(Mfa::SESSION_PASSED, true);

            return $next($request);
        }

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Verifikasi dua langkah diperlukan.',
                'redirect' => url($target),
            ], 403);
        }

        return redirect()->guest(url($target));
    }
}
