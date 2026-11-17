# Uji Beban — Fase 4 gelombang 2

Tanggal: 2026-11-17. Target blueprint: **10.000 pegawai / 1.000 pengguna bersamaan, p95 < 1 detik**.

## 1. Data uji

Database terpisah `hcms_load` (data dev/DEMO tidak tersentuh), diisi `php artisan hcms:load-seed` dalam ±38 detik:

| Data | Jumlah |
|---|---:|
| Unit organisasi (4 tingkat) | 249 |
| Posisi + reporting line solid | 10.000 |
| Pegawai + penugasan primer | 10.000 |
| Akun (1 HR, 249 atasan, 751 pegawai) | 1.001 |
| Skor kompetensi / gap / JFI | 25.000 / 25.000 / 5.000 |
| Rencana kinerja / KPI / realisasi | 10.000 / 40.000 / 80.000 |
| Rating final periode lalu | 10.000 |
| Audit trail | 200.000 |

Persiapan:

```bash
DB_DATABASE=hcms_load php artisan migrate --force
DB_DATABASE=hcms_load php artisan db:seed --class=TestCoreSeeder --force
DB_DATABASE=hcms_load php artisan hcms:load-seed --employees=10000 --users=1000
```

## 2. Latensi server per halaman (di dalam proses)

`php vendor/bin/phpunit -c phpunit.benchmark.xml` → `storage/app/benchmark/latest.md`. Setiap halaman: 2× pemanasan lalu 15 request; *cold* = request pertama (cache kosong). PHP 8.3 CLI, MariaDB 11.1, laptop dev Windows. `BENCH_TRACE=<potongan URI>` mencetak 5 query terlambat.

| Halaman | Peran | Sebelum p95 | Sesudah p95 | cold | Query/request |
|---|---|---:|---:|---:|---:|
| Laporan Kompetensi | HR | **3.398 ms** ❌ | 701 ms | 1.872 ms | 48 |
| Audit Trail | HR | **1.581 ms** ❌ | 93 ms | 309 ms | 47 |
| Rating periode (10.000 baris) | HR | 802 ms | 507 ms | 408 ms | 49 |
| Rekap periode kinerja (10.000 baris) | HR | 627 ms | 560 ms | 474 ms | 52 |
| Dasbor kinerja (periode berjalan) | HR | 826 ms | 731 ms | 577 ms | 53 |
| Daftar pegawai | HR | 585 ms | 718 ms | 890 ms | 48 |
| Kinerja Tim Saya | Atasan | 327 ms | 414 ms | 369 ms | 268 |
| Halaman layanan mandiri (profil, rencana, rating, asesmen) | Pegawai | ≤ 83 ms | ≤ 143 ms | ≤ 76 ms | 41–49 |

Semua 20 skenario kini **p95 < 1 detik** (hasil lengkap lokal: `storage/app/benchmark/latest.md` & `baseline.md`, tidak masuk git — tabel di atas adalah catatannya). Variasi ±100 ms antar-run adalah derau mesin dev.

### Perbaikan

1. **Laporan Kompetensi** — agregasi (sebaran band, gap terbesar, heatmap) dipindah dari PHP ke SQL; daftar pegawai dipaginasi 50; heatmap dibatasi 25 unit dengan gap terburuk. Filter "posisi saat ini" diubah dari `EXISTS` + `IN` (rencana MariaDB 4× lebih lambat) menjadi `JOIN`, dan filter cakupan "semua" tidak lagi menambah subquery (`EmployeeScope::apply`). Agregat di-cache per tenant + cakupan data + unit, kunci berversi yang naik setiap gap/JFI dihitung ulang (`CompetencyAssessment::bumpReportVersion`), TTL 10 menit.
2. **Audit Trail** — paginasi tanpa `COUNT` total (`simplePaginate`; COUNT 30 hari = 608 ms), urut `created_at` memakai indeks `(tenant_id, created_at)`, indeks baru `(tenant_id, auditable_type)` dan daftar tipe di-cache 10 menit (DISTINCT 819 ms).
3. **Rekap periode & daftar rating** — dipaginasi 50 dengan filter status/nama; rekap status dihitung di SQL (sebelumnya merender 10.000 baris HTML per request).

### Catatan

- Cold Laporan Kompetensi (± 1,9 dtk) hanya terjadi setelah asesmen ditutup atau cache kedaluwarsa; perubahan penugasan (mutasi) tercermin paling lambat 10 menit.
- Produksi multi-server wajib memakai cache bersama (Redis), bukan `file`.
- *Kinerja Tim Saya* menjalankan 268 query (N+1 progres per bawahan); masih 0,4 dtk untuk ±39 bawahan, tetapi tumbuh linear — kandidat optimasi bila rentang kendali besar.

## 3. Konkurensi

### Mesin dev (Laragon Apache + mod_php 8.4, laptop)

Apache Bench (`ab`), database dev, halaman terautentikasi memakai sesi akun uji pegawai:

| Skenario | Tanpa OPcache | + OPcache | + OPcache + `config:cache` |
|---|---|---|---|
| `/up` c=10 | 12 req/dtk, p95 1,0 dtk | 35 req/dtk, p95 0,43 dtk | — |
| `/up` c=50 | 17 req/dtk, p95 4,5 dtk | 29 req/dtk, p95 3,8 dtk | 36 req/dtk, p95 2,9 dtk |
| `/performance/my-plans` c=10 | — | 25 req/dtk, **8 gagal (500)** | 28 req/dtk, p95 0,55 dtk, 0 gagal |
| `/performance/my-plans` c=50 | — | 18 req/dtk, **44 gagal** | 25 req/dtk, p95 2,6 dtk, 0 gagal |
| `/ess/profile` c=50 | — | 20 req/dtk, 16 gagal | 24 req/dtk, p95 2,7 dtk, 0 gagal |
| `/performance/my-ratings` c=50 | — | 18 req/dtk, 38 gagal | 22 req/dtk, p95 2,9 dtk, 0 gagal |

Temuan:

1. **OPcache** tidak aktif di PHP Apache Laragon (setiap request mengompilasi ulang Laravel ± 700 ms). Diaktifkan di `php.ini` PHP 8.4.27 (cadangan `php.ini.bak-20261117-opcache`): satu request `/up` 1.095 → 155 ms.
2. **Error 500 acak di bawah konkurensi tanpa config cache**: log berisi `Access denied for user 'forge'` — pada mod_php Windows (thread-safe) pembacaan `.env` lewat `putenv/getenv` tidak aman antar-thread, sehingga sebagian request jatuh ke nilai default. Dengan `php artisan config:cache` kegagalan menjadi **0**. Karena itu:
   - **Produksi wajib `config:cache`** (dan Octane, yang membaca `.env` sekali per worker). Dua pemanggilan `env()` langsung di kode (nama database di AiChat, `PYTHON_BINARY`) dipindah ke config agar aman di-cache; penulis konfigurasi AI kini menghapus config cache seperti penulis konfigurasi Sximo.
   - Tes kini **menolak berjalan bila config ter-cache** (`tests/CreatesApplication.php`), karena config ter-cache mengabaikan `DB_DATABASE=hcms_test` dan `RefreshDatabase` akan mengosongkan database dev.
   - Di mesin dev biarkan config **tidak** di-cache (`.env` sering berubah); error acak hanya muncul pada beban paralel tinggi.
3. **Kapasitas laptop dev ± 25–35 request/detik** (Laravel di-boot ulang setiap request tanpa Octane). Pada 10 koneksi p95 tetap < 1 dtk; pada 50 koneksi antrean membuat p95 2,6–2,9 dtk. 1.000 pengguna dengan waktu baca 2–5 dtk ≈ 200–300 request/detik, sehingga butuh Octane + beberapa core/server — diukur di staging.

### Staging (wajib sebelum pilot)

Skrip: `tests/Load/k6-hcms.js` — 1.000 VU (akun `load.u00001…01000`), ramp 3 menit, 10 menit beban puncak, ambang `p(95)<1000` dan error < 1%.

Prasyarat staging: Octane (FrankenPHP/Swoole) + OPcache, Redis untuk cache/sesi/antrean, MariaDB/MySQL terpisah, data dari `hcms:load-seed`. Login dibatasi 5×/menit per IP — jalankan dari beberapa generator atau kecualikan IP generator selama uji.

```bash
k6 run -e BASE_URL=https://<staging> -e PASSWORD=<sandi akun uji beban> tests/Load/k6-hcms.js
```

## 4. Status

| Butir | Status |
|---|---|
| Data 10.000 pegawai | ✅ |
| p95 < 1 dtk per halaman (latensi server) | ✅ 20/20 skenario |
| Konkurensi di mesin dev | ✅ 0 kegagalan dengan OPcache + `config:cache`; ± 25–35 req/dtk (batas laptop tanpa Octane) |
| 1.000 pengguna bersamaan | ⏳ di staging dengan Octane (skrip k6 siap); kebutuhan ± 200–300 req/dtk |

## 5. Checklist deploy (dari hasil uji beban)

- `composer install --no-dev --optimize-autoloader`, `php artisan config:cache`, `route:cache`, `view:cache`; ulangi `config:cache` setiap `.env` berubah.
- OPcache aktif (`opcache.validate_timestamps=0` di produksi, reload worker saat deploy).
- Octane + `php artisan octane:reload` setelah deploy/migrasi.
- Cache, sesi, dan antrean di Redis (bukan `file`) bila lebih dari satu server.
- Login dibatasi 5×/menit per IP: pastikan IP asli klien diteruskan dari load balancer (`TrustProxies`), jika tidak semua pengguna di balik proxy berbagi batas yang sama.
