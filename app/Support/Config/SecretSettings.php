<?php

namespace App\Support\Config;

use RuntimeException;

/**
 * Kunci rahasia pengaturan Sximo (API key AI, Google Maps, reCAPTCHA) disimpan di .env,
 * bukan di config/*.php yang masuk git. File config hanya berisi env('NAMA', '').
 *
 * Penulis config Sximo memanggil writeEnv() untuk nilai baru, lalu exportValue()
 * menghasilkan ekspresi env() alih-alih literal saat menulis ulang file config.
 */
final class SecretSettings
{
    /** config key => nama variabel .env */
    public const SXIMO = ['cnf_maps' => 'MAPS_API_KEY', 'cnf_recaptchaprivatekey' => 'RECAPTCHA_PRIVATE_KEY'];

    public const AI = ['ai_api_key' => 'AI_API_KEY'];

    /** Ekspresi PHP untuk satu baris config: env() untuk kunci rahasia, literal untuk lainnya. */
    public static function exportValue(array $secrets, string $key, mixed $value): string
    {
        if (isset($secrets[$key])) {
            return "env('".$secrets[$key]."', '')";
        }

        return var_export(is_array($value) ? $value : (string) $value, true);
    }

    /**
     * Tulis/ganti variabel di .env. Nilai dikutip ganda dan di-escape; baris baru ditolak.
     *
     * @param  array<string, string>  $values  NAMA => nilai
     */
    public static function writeEnv(array $values, ?string $path = null): void
    {
        $path ??= base_path('.env');
        $content = is_file($path) ? (string) file_get_contents($path) : '';

        foreach ($values as $name => $value) {
            if (! preg_match('/^[A-Z][A-Z0-9_]*$/', $name)) {
                throw new RuntimeException("Nama variabel .env tidak valid: {$name}");
            }
            $value = (string) $value;
            if (preg_match('/[\r\n]/', $value)) {
                throw new RuntimeException("Nilai {$name} tidak boleh berisi baris baru.");
            }
            $line = $name.'="'.addcslashes($value, '"\\$').'"';
            $pattern = '/^'.preg_quote($name, '/').'=.*$/m';
            $content = preg_match($pattern, $content)
                ? preg_replace_callback($pattern, fn () => $line, $content, 1)
                : rtrim($content, "\r\n")."\n".$line."\n";
        }

        if (@file_put_contents($path, $content, LOCK_EX) === false) {
            throw new RuntimeException('File .env tidak bisa ditulis. Periksa permission file di server.');
        }

        // Nilai baru berlaku untuk request ini juga (env() membaca repository yang sudah dimuat)
        foreach ($values as $name => $value) {
            $_ENV[$name] = $_SERVER[$name] = (string) $value;
            putenv($name.'='.$value);
        }
    }
}
