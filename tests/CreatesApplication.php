<?php

namespace Tests;

use Illuminate\Contracts\Console\Kernel;

trait CreatesApplication
{
    /**
     * Creates the application.
     *
     * @return \Illuminate\Foundation\Application
     */
    public function createApplication()
    {
        $app = require __DIR__.'/../bootstrap/app.php';

        // Config ter-cache mengabaikan DB_DATABASE dari phpunit.xml: RefreshDatabase akan
        // mengosongkan database dev/produksi. Hentikan sebelum ada koneksi ke database.
        if (is_file($app->getCachedConfigPath())) {
            throw new \RuntimeException('Config ter-cache (bootstrap/cache/config.php). Jalankan "php artisan config:clear" sebelum menjalankan tes.');
        }

        $app->make(Kernel::class)->bootstrap();

        return $app;
    }
}
