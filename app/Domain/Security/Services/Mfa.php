<?php

namespace App\Domain\Security\Services;

use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use PragmaRX\Google2FA\Google2FA;

/**
 * MFA berbasis TOTP (Google Authenticator, Microsoft Authenticator, dsb.).
 *
 * - Secret disimpan terenkripsi dengan APP_KEY.
 * - Kode pemulihan disimpan sebagai hash; setiap kode hanya bisa dipakai sekali.
 * - Kode TOTP yang sama tidak bisa dipakai ulang (mfa_last_timestep).
 * - Wajib untuk pengguna yang punya minimal satu peran dengan requires_mfa.
 */
final class Mfa
{
    public const SESSION_PASSED = 'mfa_passed';

    public const RECOVERY_CODE_COUNT = 8;

    public function __construct(private Google2FA $google2fa = new Google2FA) {}

    public function isRequiredFor(int $userId): bool
    {
        return DB::table('tb_users_groups')
            ->join('tb_groups', 'tb_groups.group_id', '=', 'tb_users_groups.group_id')
            ->where('tb_users_groups.user_id', $userId)
            ->where('tb_groups.requires_mfa', 1)
            ->exists();
    }

    public function isEnabledFor(int $userId): bool
    {
        return DB::table('tb_users')->where('id', $userId)->whereNotNull('mfa_enabled_at')->whereNotNull('mfa_secret')->exists();
    }

    public function generateSecret(): string
    {
        return $this->google2fa->generateSecretKey(32);
    }

    public function qrCodeSvg(string $accountName, string $secret): string
    {
        $url = $this->google2fa->getQRCodeUrl((string) config('sximo.cnf_appname', 'HCMS'), $accountName, $secret);
        $writer = new Writer(new ImageRenderer(new RendererStyle(200, 1), new SvgImageBackEnd));

        return $writer->writeString($url);
    }

    /** Cocokkan kode dengan secret yang belum tersimpan (saat pendaftaran). */
    public function verifyForSecret(string $secret, string $code): bool
    {
        return (bool) $this->google2fa->verifyKey($secret, $this->normalize($code), 1);
    }

    /** Aktifkan MFA; mengembalikan kode pemulihan (hanya ditampilkan sekali). */
    public function enable(int $userId, string $secret): array
    {
        $codes = $this->newRecoveryCodes();

        DB::table('tb_users')->where('id', $userId)->update([
            'mfa_secret' => Crypt::encryptString($secret),
            'mfa_recovery_codes' => json_encode(array_map(fn ($c) => Hash::make($c), $codes)),
            'mfa_enabled_at' => now(),
            'mfa_last_timestep' => null,
        ]);

        return $codes;
    }

    public function disable(int $userId): void
    {
        DB::table('tb_users')->where('id', $userId)->update([
            'mfa_secret' => null,
            'mfa_recovery_codes' => null,
            'mfa_enabled_at' => null,
            'mfa_last_timestep' => null,
        ]);
    }

    /** Verifikasi kode TOTP atau kode pemulihan milik pengguna. */
    public function verify(int $userId, string $input): bool
    {
        $user = DB::table('tb_users')->where('id', $userId)->first(['mfa_secret', 'mfa_recovery_codes', 'mfa_last_timestep']);
        if (! $user || ! $user->mfa_secret) {
            return false;
        }

        $code = $this->normalize($input);

        if (preg_match('/^\d{6}$/', $code)) {
            $secret = Crypt::decryptString($user->mfa_secret);
            // oldTimestamp tidak boleh null: dengan null library mengembalikan true (bukan
            // nomor timestep) sehingga perlindungan pemakaian ulang tidak berfungsi
            $timestep = $this->google2fa->verifyKeyNewer($secret, $code, (int) ($user->mfa_last_timestep ?? 0), 1);
            if ($timestep === false || $timestep === true) {
                return false;
            }
            DB::table('tb_users')->where('id', $userId)->update(['mfa_last_timestep' => $timestep]);

            return true;
        }

        return $this->useRecoveryCode($userId, $input, (string) $user->mfa_recovery_codes);
    }

    public function remainingRecoveryCodes(int $userId): int
    {
        return count(json_decode((string) DB::table('tb_users')->where('id', $userId)->value('mfa_recovery_codes'), true) ?: []);
    }

    private function useRecoveryCode(int $userId, string $input, string $stored): bool
    {
        $candidate = strtoupper(trim($input));
        $hashes = json_decode($stored, true) ?: [];

        foreach ($hashes as $i => $hash) {
            if (Hash::check($candidate, $hash)) {
                unset($hashes[$i]);
                DB::table('tb_users')->where('id', $userId)->update(['mfa_recovery_codes' => json_encode(array_values($hashes))]);

                return true;
            }
        }

        return false;
    }

    private function newRecoveryCodes(): array
    {
        return array_map(
            fn () => strtoupper(Str::random(4).'-'.Str::random(4)),
            range(1, self::RECOVERY_CODE_COUNT)
        );
    }

    private function normalize(string $code): string
    {
        return preg_replace('/\s+/', '', $code);
    }
}
