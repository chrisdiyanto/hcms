# Runbook — Dry Run Migrasi ×2 dan Pilot Satu Company

Fase 4 gelombang 3. Dokumen ini dipakai tim TI & HR untuk dua kali latihan migrasi data, lalu go-live pilot pada satu company. Setiap langkah punya **pemilik**, **bukti**, dan **kriteria lanjut/berhenti**.

**Prasyarat sebelum dry run pertama**
- [ ] Staging setara produksi: PHP 8.3+, MariaDB/MySQL, OPcache, `config:cache`, Redis untuk cache/sesi/antrean bila > 1 server, zona waktu `Asia/Jakarta`.
- [ ] Kunci API (Google Maps, Gemini, reCAPTCHA) sudah dirotasi pemilik akun dan hanya ada di `.env` server, bukan di repo.
- [ ] Data sumber diekspor dari sistem lama ke CSV sesuai template (Control Panel › Migrasi Data › unduh template) dan disimpan di lokasi terenkripsi dengan akses terbatas.
- [ ] Persetujuan DPO untuk memproses data pegawai asli di staging.
- [ ] Skrip UAT (`UAT-SKRIP.md`) dan materi pelatihan (`PANDUAN-PENGGUNA.md`) disepakati.

---

## 1. Persiapan lingkungan (setiap dry run dan go-live)

```bash
composer install --no-dev --optimize-autoloader
npm ci && npm run build
php artisan migrate --force
php artisan config:cache && php artisan route:cache && php artisan view:cache
```

- Jangan jalankan `DemoDataSeeder` atau `hcms:load-seed` di database pilot/produksi (DemoDataSeeder menolak berjalan di produksi).
- Jalankan PHPUnit hanya di lingkungan dev/CI, **bukan** saat config ter-cache.
- Penjadwal: cron `* * * * * php artisan schedule:run` (terminasi 00.15, eskalasi tiap jam, hitung ulang JFI Minggu 02.00, snapshot tanggal 1 pukul 01.30).
- Antrean: `php artisan queue:work` (atau Horizon) sebagai layanan.
- Ulangi `config:cache` setiap `.env` berubah. Bila memakai Octane: `php artisan octane:reload` setelah deploy/migrasi.

## 2. Dry run migrasi (dilakukan dua kali)

Dry run 1 menemukan masalah data; dry run 2 membuktikan prosesnya dapat diulang dengan hasil yang sama dan waktu yang terukur. Setiap dry run memakai **database staging yang dikosongkan** (`migrate:fresh` + seeder inti, bukan data demo).

| # | Langkah | Pemilik | Bukti |
|---|---|---|---|
| 2.1 | Catat waktu mulai. Kosongkan database staging, migrasi, buat tenant & company pilot (Control Panel › Tenant). | TI | Tangkapan layar tenant |
| 2.2 | Validasi kering per jenis, berurutan: `units` → `job_families` → `job_levels` → `job_grades` → `jobs` → `positions` → `employees`. `php artisan hcms:import <jenis> <berkas.csv> --tenant=KODE` | TI | Keluaran tabel validasi |
| 2.3 | Kirim daftar error per nomor baris ke HR; HR memperbaiki **di sumber**, bukan di staging. Ulangi 2.2 sampai 0 error. | HR | Daftar error & versi berkas |
| 2.4 | Commit per jenis dengan urutan yang sama: tambahkan `--commit`. | TI | Riwayat impor (Control Panel › Migrasi Data) |
| 2.5 | **Rekonsiliasi** (tabel di bawah). | HR + TI | Lembar rekonsiliasi |
| 2.6 | Lengkapi data yang tidak diimpor: kamus kompetensi & profil job family pilot, kebijakan kinerja, alur persetujuan, akun pengguna & peran, cakupan data (user unit). | HR | Tabel Cakupan pemetaan "Terpetakan penuh" |
| 2.7 | Jalankan skenario UAT bagian A–C pada data hasil migrasi. | HR | Skrip UAT terisi |
| 2.8 | Catat waktu selesai dan semua temuan. | TI | Log dry run |

**Rekonsiliasi minimum**

| Pemeriksaan | Cara | Lulus bila |
|---|---|---|
| Jumlah baris per jenis | Baris berkas vs "baru + diperbarui" di riwayat impor vs jumlah di sistem | Sama persis |
| Pegawai aktif per unit | Ekspor sistem lama vs Ringkasan Eksekutif (bulan berjalan) per unit | Sama persis |
| Pegawai tanpa penugasan primer | Ringkasan Eksekutif / daftar pegawai | 0 (atau daftar pengecualian disetujui HR) |
| Struktur atasan | Sampel 30 pegawai: atasan langsung di sistem lama vs Tugas Persetujuan | 30/30 cocok |
| Posisi & kapasitas | Jumlah posisi aktif dan kapasitas per unit | Sama |
| Identitas (NIK, NPWP) | Sampel 20 pegawai dibuka dengan izin sensitif | 20/20 cocok; tidak ada nilai polos di audit |
| Status kerja & tanggal | Sampel 30: status, tanggal bergabung, akhir kontrak | 30/30 cocok |

**Kriteria lanjut ke dry run 2:** semua jenis 0 error, rekonsiliasi lulus, temuan kritis UAT A–C ditutup.
**Kriteria lanjut ke go-live:** dry run 2 lulus tanpa perubahan manual di staging, durasi tercatat dan masuk jendela go-live, hasil identik dengan dry run 1 untuk data yang sama.

## 3. Go-live pilot (satu company)

**H-7 s.d. H-1**
- [ ] Pentest independen selesai; temuan tinggi/kritis ditutup (keputusan 2026-11-17: pentest setelah pengembangan selesai, sebelum pilot).
- [ ] Uji beban k6 1.000 pengguna di staging lulus (`docs/LOAD-TEST.md` §3): p95 < 1 dtk, error < 1%.
- [ ] Pelatihan sesi 1–6 selesai; daftar hadir.
- [ ] UAT ditandatangani (`UAT-SKRIP.md`).
- [ ] Cadangan otomatis database harian + uji pemulihan satu kali.
- [ ] Pembekuan data sumber diumumkan (tanggal & jam).

**Hari H**

| Jam | Langkah | Pemilik |
|---|---|---|
| T−0 | Bekukan sistem lama (hanya baca). Ekspor final CSV. | HR |
| T+0:30 | Cadangkan database produksi kosong; deploy (§1). | TI |
| T+1:00 | Impor seperti §2.2–2.4 dengan berkas final. | TI |
| T+… | Rekonsiliasi §2.5 (wajib lulus sebelum dibuka). | HR + TI |
| T+… | Konfigurasi §2.6 dari catatan dry run 2. | HR |
| T+… | Uji asap: login per peran, B1, C3 (asesmen uji lalu batalkan), Ringkasan Eksekutif. | HR |
| Go/No-go | Pemilik produk memutuskan berdasarkan rekonsiliasi & uji asap. | Pemilik produk |
| Buka | Umumkan ke pengguna pilot; aktifkan akun. | HR |

**Rollback** (bila No-go atau gangguan kritis dalam 48 jam pertama): tutup akses aplikasi, pulihkan cadangan T+0:30 atau kembali ke sistem lama yang dibekukan, catat penyebab. Data yang dimasukkan pengguna setelah buka dicatat manual untuk dimasukkan ulang.

## 4. Stabilisasi (4 minggu pertama)

| Pantau | Frekuensi | Ambang tindakan |
|---|---|---|
| Error 5xx & log aplikasi | Harian | Ada error berulang |
| p95 halaman utama | Mingguan | > 1 detik |
| Antrean & penjadwal (terminasi, eskalasi, hitung ulang JFI, snapshot) | Harian | Job gagal/tertunda |
| Tugas persetujuan tertunda > SLA | Mingguan | Lonjakan eskalasi |
| Audit akses sensitif & ekspor | Mingguan (DPO) | Pola tidak wajar |
| Tiket pengguna | Harian | Topik berulang → materi pelatihan |

**Selesai pilot** bila: 4 minggu tanpa insiden kritis, satu siklus perencanaan kinerja berjalan di sistem, rekonsiliasi bulanan pertama (snapshot tanggal 1) cocok dengan data HR, dan pemilik produk menyetujui perluasan ke company berikutnya.
