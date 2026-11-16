# ADR 0001 — Deviasi dari Blueprint CBHRMS v1.2 karena master Sximo

- **Status:** Disetujui pemilik produk (2026-11-16)
- **Tanggal:** 2026-11-16
- **Konteks:** Blueprint v1.2 menetapkan Vue 3 + Inertia, PostgreSQL + RLS, dan UUIDv7. HCMS dibangun di atas master Sximo (Laravel 13) yang memakai Blade, MySQL, dan module builder dengan SQL mentah.

## Keputusan

| # | Blueprint | Keputusan HCMS | Alasan |
|---|---|---|---|
| 1 | PostgreSQL + Row-Level Security | **MySQL 8**. Isolasi tenant di aplikasi: `TenantContext` + trait `BelongsToTenant` + filter `tenant_id` di base `App\Models\Sximo` + tes isolasi wajib per modul | `Sximo::getRows()` memakai `LIMIT offset, n` dan migrasi memakai `ALTER ... MODIFY` (khas MySQL); pindah ke PostgreSQL berarti menulis ulang inti Sximo |
| 2 | Vue 3 + Inertia + Tailwind | **Blade + Bootstrap 5 + jQuery bawaan master, tanpa Vue** (disetujui pemilik produk 2026-11-16). Plugin yang sudah ada di `public/assets/plugins` dipakai untuk semua layar; ECharts satu-satunya library chart; **Alpine.js 3** (via Vite) untuk form KPI, asesmen 360°, dan kalibrasi | Generator CRUD Sximo menghasilkan Blade; satu stack untuk seluruh tim; plugin yang dibutuhkan sudah tersedia. Frontend lain (mobile/PWA) tetap bisa ditambahkan kelak lewat `/api/v1` karena logika bisnis ada di `app/Domain` |
| 3 | UUIDv7 sebagai PK | **BIGINT auto-increment** + kolom `uuid` (UUIDv7) untuk ID yang terekspos di URL/API | Grid, form, dan permission Sximo mengandalkan PK numerik |
| 4 | Folder per modul | Hasil generator tetap datar; logika bisnis di `app/Domain/{Modul}` | Generator Sximo tidak mendukung struktur modular |
| 5 | Redis + Horizon | Dev: queue `database`; produksi Linux: Redis + Horizon | Horizon tidak berjalan di Windows/Laragon |
| 6 | Pest | PHPUnit 11 (sudah terpasang); Pest boleh ditambahkan kemudian | Hindari perubahan tooling di Fase 0 |
| 7 | `EXCLUDE USING gist` untuk periode tumpang tindih | Validasi di service + lock baris dalam transaksi | MySQL tidak punya exclusion constraint |
| 8 | Skor akhir default What 70% / How 30% (model 60/30/10 opsional) | **Default 60/30/10**: Performance Score 60% + Competency/Behavior Score 30% + dimensi ketiga 10%, tetap dapat dikonfigurasi per company dan job level | Keputusan pemilik produk. Sesuai syarat blueprint 8.5, dimensi kompetensi wajib didefinisikan sebagai perilaku yang diamati selama periode (bukan profil kompetensi teknis dari modul Competency); isi dimensi 10% dikunci di dokumen kebijakan penilaian Fase 0. **Diputuskan 2026-11-17: dimensi 10% = Kontribusi tambahan** (inisiatif, proyek lintas unit, tugas di luar KPI), dinilai atasan 1–5 dengan rubrik dan dikonversi memakai tabel How (50/65/75/85/95) |

## Konsekuensi
- Risiko kebocoran antar-tenant lebih tinggi daripada RLS: **setiap** query Sximo (`querySelect`/`queryWhere`), export, dan API wajib lewat filter tenant. Gerbang keluar Fase 1 adalah tes otomatis "tenant A tidak dapat melihat data tenant B" di semua modul.
- Kriteria MVP blueprint nomor 19 ("query tanpa tenant context tidak mengembalikan data (RLS)") dipenuhi lewat `TenantContext::requireTenantId()` yang melempar exception, bukan oleh database.
- Tanpa Vue, layar dengan state rumit berisiko menjadi kode jQuery yang sulit dirawat. Mitigasi: rumus hanya di server, Alpine.js untuk state form, JS jQuery per layar di file terpisah.
- Migrasi ke PostgreSQL + RLS tetap terbuka pasca-MVP bila diwajibkan klien Enterprise.
