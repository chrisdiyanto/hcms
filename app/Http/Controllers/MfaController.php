<?php

namespace App\Http\Controllers;

use App\Domain\Security\Services\Mfa;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Pendaftaran dan tantangan MFA sesudah login kata sandi.
 * Halaman ini sengaja tidak memakai Controller Sximo (tidak butuh data modul).
 */
class MfaController extends BaseController
{
    private const SESSION_PENDING_SECRET = 'mfa_pending_secret';

    public function __construct(private Mfa $mfa) {}

    public function challenge(Request $request)
    {
        if ($request->session()->get(Mfa::SESSION_PASSED)) {
            return redirect('dashboard');
        }
        if (! $this->mfa->isEnabledFor(Auth::id())) {
            return redirect('user/mfa/setup');
        }

        return view('user.mfa.challenge', [
            'remaining' => $this->mfa->remainingRecoveryCodes(Auth::id()),
        ]);
    }

    public function verify(Request $request)
    {
        $request->validate(['code' => ['required', 'string', 'max:20']]);

        if (! $this->mfa->verify(Auth::id(), $request->input('code'))) {
            $this->log($request, 'Verifikasi MFA gagal');

            return back()->withErrors(['code' => 'Kode tidak valid atau sudah dipakai.']);
        }

        $this->markPassed($request);
        $this->log($request, 'Verifikasi MFA berhasil');

        return redirect()->intended('dashboard');
    }

    public function setup(Request $request)
    {
        if ($this->mfa->isEnabledFor(Auth::id())) {
            return redirect('user/mfa');
        }

        $secret = $request->session()->get(self::SESSION_PENDING_SECRET);
        if (! $secret) {
            $secret = $this->mfa->generateSecret();
            $request->session()->put(self::SESSION_PENDING_SECRET, $secret);
        }

        return view('user.mfa.setup', [
            'secret' => $secret,
            'qr' => $this->mfa->qrCodeSvg((string) Auth::user()->email, $secret),
            'required' => $this->mfa->isRequiredFor(Auth::id()),
        ]);
    }

    public function enable(Request $request)
    {
        $request->validate(['code' => ['required', 'string', 'max:10']]);

        $secret = $request->session()->get(self::SESSION_PENDING_SECRET);
        if (! $secret || $this->mfa->isEnabledFor(Auth::id())) {
            return redirect('user/mfa/setup');
        }

        if (! $this->mfa->verifyForSecret($secret, $request->input('code'))) {
            return back()->withErrors(['code' => 'Kode tidak cocok. Pastikan jam di ponsel sudah tepat lalu coba lagi.']);
        }

        $codes = $this->mfa->enable(Auth::id(), $secret);
        $request->session()->forget(self::SESSION_PENDING_SECRET);
        $this->markPassed($request);
        $this->log($request, 'MFA diaktifkan');

        return view('user.mfa.recovery', ['codes' => $codes]);
    }

    private function markPassed(Request $request): void
    {
        $request->session()->regenerate();
        $request->session()->put(Mfa::SESSION_PASSED, true);
    }

    private function log(Request $request, string $note): void
    {
        DB::table('tb_logs')->insert([
            'ipaddress' => (string) $request->ip(),
            'user_id' => (int) Auth::id(),
            'module' => 'mfa',
            'task' => $request->route()?->getActionMethod(),
            'note' => $note,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
