# Tahapan Pembangunan HCMS (CBHRMS) di atas Master Sximo Laravel

Acuan: **CBHRMS – System Blueprint & Development Specification v1.2**.
Basis kode: master Sximo di `C:\laragon\www\hcms` (Laravel 13, PHP 8.3, Blade + Bootstrap 5, MySQL, Octane/FrankenPHP).

Dokumen ini menerjemahkan roadmap blueprint (bagian 20) ke langkah kerja konkret yang memakai apa yang sudah ada di master: module builder Sximo, `tb_groups`/`tb_groups_access`, `corporate`, `unit`, `user_unit`, `tb_logs`, `tb_notification`, serta layanan AI (Gemini + RAG).

---

## A. Penyesuaian blueprint terhadap master

Blueprint ditulis untuk Vue 3 + Inertia + PostgreSQL (RLS) + UUIDv7. Master ini berbeda di beberapa titik mendasar, jadi beberapa keputusan perlu diambil sebelum coding. Keputusan resmi dicatat di `docs/adr/0001-deviasi-blueprint.md`.

| Aspek | Blueprint | Master saat ini | Rekomendasi untuk MVP |
|---|---|---|---|
| Frontend | Vue 3 + Inertia + Tailwind | Blade + Bootstrap 5 + jQuery/DataTables (Sximo) | **Tanpa Vue.** Semua layar memakai Blade Sximo + plugin jQuery yang sudah ada di `public/assets/plugins` (lihat B.4). **Alpine.js** (terpasang lewat Vite) untuk layar dengan state rumit: form rencana KPI, form asesmen 360°, kalibrasi. Chart distandarkan ke ECharts. |
| Database | PostgreSQL + RLS | MySQL; inti Sximo memakai SQL mentah khas MySQL (`LIMIT offset, n`, `ALTER ... MODIFY`) | **Tetap MySQL 8** untuk MVP. Pindah ke PostgreSQL berarti menulis ulang `App\Models\Sximo::getRows` dan builder. RLS diganti isolasi berlapis di aplikasi + uji otomatis (lihat B.1). Catat sebagai deviasi resmi dari blueprint. |
| Primary key | UUIDv7 di semua tabel | Integer auto-increment; grid/form Sximo mengandalkan PK sederhana | **BIGINT auto-increment** sebagai PK (kompatibel Sximo) + kolom `uuid` (UUIDv7, `HasUuids`) untuk ID yang terekspos di URL/API. |
| Struktur modul | Modular monolith per folder modul | Sximo generator menghasilkan file datar: `app/Http/Controllers/XController.php`, `app/Models/X.php`, `resources/views/x/`, `routes/module.php` | Biarkan hasil generator datar. Logika bisnis ditaruh di `app/Domain/{Modul}/{Actions,Services,Events,Policies}`. Controller Sximo hanya memanggil service. |
| RBAC | roles/permissions + data scope + SoD | `tb_groups` + `tb_groups_access.access_data` (JSON: is_view, is_add, is_edit, is_remove, is_excel, ...) per modul; `session('gid')` | **Perluas** model Sximo: tambah kunci `is_approve`, `is_export`, `is_sensitive` di `access_data`; tambah tabel `user_data_scopes` untuk cakupan organisasi. Group = role. |
| Tenant / company | tenants → companies | `corporate` + `session('cid')` | Tabel baru `tenants`; `corporate` dipakai sebagai **company** (tambah `tenant_id`, `settings`). |
| Organisasi | organization_units + closure + effective dating | `unit` (parent_id, id_corporate, id_unit_category, id_unit_type), `unit_type`, `unit_category` | **Perluas `unit`**: tambah `tenant_id`, `effective_from/to`; buat `org_unit_closure`. `unit_type` = org_unit_types. |
| Penempatan | employee_assignments | `user_unit` (id_user, id_unit, id_position, start/end) | `user_unit` tetap untuk **switch unit login & data scope**. Penempatan HR resmi memakai tabel baru `employee_assignments` yang terikat ke `positions`. |
| Audit | audit_logs berpartisi | `tb_logs` (ip, user, module, task, note) | Tabel baru `audit_logs` (before/after JSON, tenant_id, subject); `tb_logs` tetap untuk log akses Sximo. |
| Notifikasi | notifications + template | `tb_notification` + mail | Pakai `tb_notification` untuk in-app (tambah `tenant_id`), tambah `notification_templates`. |
| Antrean | Redis + Horizon | `QUEUE_CONNECTION=sync` | Dev (Windows/Laragon): queue `database`. Produksi (Linux): Redis + Horizon. |
| Auth | Sanctum, OIDC, SAML, MFA | Sanctum + Socialite sudah terpasang | Sanctum untuk `/api/v1`, Socialite untuk OIDC; SAML dan TOTP-MFA butuh paket tambahan (P0 untuk peran HR/Admin). |
| AI | Layanan Python terpisah, rekomendasi saja | Gemini (`google-gemini-php`), RAG, folder `python/` | Pakai ulang `RagService`/Gemini lewat job antrean, dengan pseudonimisasi; fitur AI bukan bagian P0. |
| Pengujian | Pest + Playwright + k6 | PHPUnit 11 | Tetap PHPUnit (atau tambah Pest bila tim lebih nyaman). Tes arsitektur bisa memakai Pest Arch atau aturan PHPStan. |
| Runtime | — | Octane + FrankenPHP | Tenant context **wajib di-reset per request** (jangan simpan di properti static/singleton tanpa flush). |

> **Konsekuensi penting dari Sximo:** model Sximo menjalankan SQL mentah (`querySelect()` + `queryWhere()`), sehingga **global scope Eloquent tidak berlaku** di grid Sximo. Filter tenant/company/data scope harus disuntikkan di `queryWhere()` setiap model (lihat B.1).

---

## B. Fondasi teknis yang harus ada sebelum modul bisnis

### B.1 Isolasi tenant (pengganti RLS)
1. Tabel `tenants`; kolom `tenant_id` (dan `company_id` bila perlu) di semua tabel bisnis, termasuk tabel detail; unique index selalu menyertakan `tenant_id`.
2. `App\Support\Tenancy\TenantContext` (scoped binding, di-flush tiap request Octane) diisi middleware dari sesi login (`session('tid')`, `session('cid')`).
3. Trait `BelongsToTenant` untuk model Eloquent: global scope + isi otomatis `tenant_id` saat create.
4. Untuk model Sximo: ubah base `App\Models\Sximo` agar `getRows()`, `getRow()`, `insertRow()`, `updateDataRow()`, `deleteDataRow()` selalu menambahkan `AND {table}.tenant_id = ?` (pakai binding, bukan string concat) bila tabel memiliki kolom `tenant_id`.
5. Job antrean membawa `tenant_id`; key cache diberi prefiks `t:{tenant_id}:`; file disimpan di `tenants/{tenant_id}/...`.
6. **Uji otomatis**: tiap modul punya tes "user tenant A tidak bisa melihat/mengubah data tenant B" lewat grid, form, export, dan API. Ini gerbang keluar Fase 1.
7. Opsional pasca-MVP: MySQL view per tenant atau migrasi ke PostgreSQL + RLS.

### B.2 Otorisasi (RBAC + data scope + SoD)
- Seed 15 group sesuai blueprint bagian 3.1 (Super Administrator, Tenant Administrator, HR Administrator, HRBP, Executive, Department Head, Line Manager, Employee, Assessor, Talent Committee, Recruiter, Training Admin, DPO, Auditor, API Client).
- Tambah kunci `is_approve`, `is_export`, `is_sensitive` ke `access_data` dan ke UI permission Sximo (`sximo/module/permission/{module}`).
- `user_data_scopes` (user, unit, include_children) + `org_unit_closure` → helper `DataScope::unitIds($user)` yang dipakai di `queryWhere()`.
- Aturan SoD sebagai Policy di `app/Domain/Security/Policies`.

### B.3 Konvensi kode per modul
Setiap modul bisnis dikerjakan dengan resep yang sama:
1. Migration di `database/migrations` (kolom standar: `tenant_id`, `company_id`, `effective_from/to` bila berlaku-tanggal, `version`, `created_by`, `updated_by`, timestamps, `deleted_at` hanya untuk master).
2. Generate CRUD lewat **Sximo → Module Builder** (`/sximo/module/create`), atur grid, form, relasi, dan sub-form.
3. Sesuaikan `queryWhere()` model untuk tenant + data scope.
4. Logika bisnis/kalkulasi di `app/Domain/{Modul}/Services` atau `Actions`; controller Sximo memanggilnya di `store`/`update`.
5. Tes unit (rumus) + tes feature (akses, isolasi).
6. Seed menu (`tb_menu`), modul (`tb_module`), dan hak akses group (`tb_groups_access`) agar bisa dipasang ulang di environment lain.

### B.4 Konvensi frontend (tanpa Vue)
Layar dibangun dengan Blade + Bootstrap 5 + jQuery memakai plugin yang sudah ada di master:

| Kebutuhan | Plugin standar |
|---|---|
| Grid data, filter, export | DataTables (bawaan Sximo) |
| Pohon organisasi, cascading KPI | bootstrap-treeview / nestable; bagan organisasi dengan d3 |
| Kalibrasi & Nine Box (drag & drop) | jQuery UI sortable atau gridstack |
| Dashboard, heatmap kompetensi, radar, gauge | **ECharts** (satu-satunya library chart) |
| Input realisasi KPI di tabel | jsgrid / jquery-datatables-editable |
| Unggah bukti & dokumen | dropzone / dropify |
| Pilihan pegawai/posisi, konfirmasi, validasi | select2, sweetalert2, parsley |
| Wizard (ESS change request, onboarding) | bootstrap-nav-wizard |

Aturan:
- **Server yang menentukan.** Semua rumus (normalisasi KPI, cap, prorata, JFI, skor akhir) hanya ada di service PHP `app/Domain`. JavaScript boleh menampilkan pratinjau (mis. total bobot KPI), tetapi validasi di Form Request/service yang mengikat.
- Layar dengan state rumit memakai **Alpine.js 3** (paket npm `alpinejs`, dimuat lewat `@vite` di `layouts/app.blade.php`, `window.Alpine` tersedia global). Pembagian peran: **Alpine** untuk state & reaktivitas form (bobot, total, baris dinamis, tampil/sembunyi); **jQuery/plugin** untuk widget yang sudah ada (select2, DataTables, sortable, dropzone).
  - Komponen didefinisikan di Blade halaman terkait dengan `document.addEventListener('alpine:init', () => Alpine.data('namaKomponen', () => ({ ... })))` lalu dipakai `x-data="namaKomponen"`. Komponen yang dipakai di banyak halaman dipindah ke `resources/js/components/` dan di-import di `app.js`.
  - Pakai `x-cloak` untuk elemen yang belum siap (CSS sudah ada di `resources/css/app.css`).
  - Konten dari AJAX Sximo (modal form) ikut diinisialisasi Alpine otomatis; widget jQuery di dalamnya tetap diinisialisasi seperti biasa.
  - Setelah mengubah `resources/js` atau `resources/css`, jalankan `npm run build` (deploy wajib menjalankan `npm ci && npm run build`, karena `public/build` tidak masuk git).
- Kode JS jQuery per layar ditaruh di file terpisah, bukan inline panjang di Blade.
- Library chart lain di master (Chart.js, c3, chartist, flot, morris, peity) tidak dipakai di modul baru.
- Data dinamis diambil lewat endpoint JSON yang sama dengan `/api/v1` bila memungkinkan, sehingga frontend lain (mobile/PWA) bisa ditambahkan kelak tanpa mengubah logika bisnis.
- Responsif Bootstrap 5 untuk portal ESS & Manager di HP; teks UI lewat file `lang` (Indonesia & Inggris).

---

## C. Tahapan pembangunan

Durasi mengikuti blueprint (MVP P0 = 40 minggu, tim ±12 orang). Bila tim lebih kecil, urutan tetap sama dan durasi disesuaikan.

### Fase 0 — Blueprint & persiapan master (minggu 1–6)

**Bisnis/analisis**
- Kunci kebijakan penilaian: bobot skor 60/30/10 (diputuskan; dimensi ketiga = Kontribusi tambahan), cap, band rating, tabel konversi, aturan prorata.
- ERD detail 14 domain dan data dictionary, disesuaikan dengan tabel master yang dipakai ulang (bagian A).
- Wireframe portal: ESS, Manager, HR, Executive, di atas layout Sximo (`layouts/app.blade.php`).
- Audit kualitas data pegawai lama, verifikasi hukum PDP.

**Teknis (penyesuaian master)**
- `git init`, branch strategy, `.env.example` (saat ini belum ada), ganti `APPNAME`/`MAIL_FROM_NAME` lama ke HCMS.
- Putuskan dan dokumentasikan deviasi: MySQL, PK integer + uuid, Blade + jQuery tanpa Vue (ADR 0001).
- Standarkan library frontend (B.4): ECharts untuk chart; Alpine.js terpasang via Vite (selesai 2026-11-16).
- Ubah `QUEUE_CONNECTION` ke `database`, siapkan scheduler (`php artisan schedule:work` di dev).
- Bersihkan contoh modul bawaan di `app/Http/Controllers/Services` (Customer, Orders, Product, dll.) bila tidak dipakai.
- CI: Pint, PHPUnit, dependency audit (`composer audit`).
- Kerangka `app/Domain/` dan `TenantContext` (B.1) — belum diterapkan ke modul.

**Gerbang keluar:** dokumen kebijakan penilaian ditandatangani pemilik bisnis; ERD disetujui.

### Fase 1 — Core Foundation (minggu 7–14)

Urutan pengerjaan:

1. **Tenant & Company**
   - Migration `tenants`, `tenant_settings`; tambah `tenant_id`, `settings` ke `corporate` (company).
   - Middleware tenant context, trait `BelongsToTenant`, modifikasi base `Sximo` (B.1).
   - Modul Sximo: *Tenant* (Super Admin), *Company* (pakai `CorporateController` yang ada).
   - **Status 2026-11-16: selesai, 29 tes lulus, diuji manual di browser (buat tenant, isolasi Company, tangguhkan/aktifkan).** Implementasi: `App\Support\Tenancy\TenantScope` (pengganti trait `BelongsToTenant`: filter di base `Sximo` + global scope Eloquent + helper global), middleware `SetTenantContext`, `core/tenants` (Superadmin) dengan action `CreateTenant` (tenant + company pertama + Tenant Administrator), menu Tenant & Company. `user_unit` dan `tb_notification` belum diberi `tenant_id` (diakses lewat id pengguna).
2. **IAM**
   - Seed 15 group, perluas `access_data` (B.2), `user_data_scopes`, `login_histories`.
   - MFA (TOTP) untuk HR/Executive/Admin/Auditor/DPO; SSO OIDC via Socialite.
   - Break-glass Super Admin (alasan, batas waktu, notifikasi ke Tenant Admin).
   - **Status 2026-11-16 — gelombang 1 selesai (38 tes lulus, dicek di browser):** 15 peran di `tb_groups` dengan kolom `code`, `data_scope` (all/scoped/own), `requires_mfa` (`App\Support\Security\Roles`); izin `is_approve` & `is_sensitive` di template permission (ekspor tetap memakai `is_excel/is_csv/is_pdf`); `org_unit_closure` + `user_data_scopes` + `App\Support\Security\DataScope`; halaman `core/users/{id}/scopes` (tidak bisa mengubah cakupan sendiri); `login_histories`. **Gelombang 2 selesai (49 tes lulus):** MFA TOTP (`pragmarx/google2fa`, secret terenkripsi, 8 kode pemulihan sekali pakai, anti pemakaian ulang kode, middleware `EnforceMfa` wajib untuk peran `requires_mfa`, reset MFA oleh admin dengan batas hierarki); break-glass Super Administrator (`break_glass_accesses`: alasan ≥ 20 karakter, 15/30/60 menit, notifikasi ke Tenant Administrator, setiap request dicatat, banner hitung mundur); akun platform `tenant_id` kosong hanya bisa membuka data tenant lewat break-glass. **Belum:** SSO OIDC/SAML (menunggu identity provider), notifikasi break-glass via email (saat ini in-app).
3. **Organization & Job Architecture**
   - Perluas `unit` (tenant_id, effective dating), `org_unit_closure` + service rebuild closure, `organization_histories`.
   - Tabel baru: `job_families`, `jobs`, `job_levels`, `job_grades`, `job_descriptions`, `job_requirements`, `positions` (kode, unit, job, grade, status, kritis, lokasi, effective date), `position_relationships` (solid/dotted), `position_histories`.
   - Hubungkan `user_unit.id_position` ke `positions`.
   - Fitur restrukturisasi dengan effective date masa depan (job terjadwal).
   - **Status 2026-11-16 — gelombang 1 selesai (58 tes lulus; belum dilihat di browser karena akun dev menunggu pendaftaran MFA):** tabel `job_families`, `job_levels`, `job_grades`, `jobs_catalog` (nama `jobs` dipakai antrean Laravel), `job_requirements`, `positions`, `position_relationships` (solid/dotted, effective dating), `position_histories`, `organization_histories`; menu sidebar "Organisasi" + modul tipe `core` di `tb_module` (`App\Support\Modules\HcmsModules`, ikut di-seed ulang); CRUD generik `Organization\MasterDataController` (tenant-scoped, soft delete, izin Sximo, cegah hapus bila masih dipakai); validasi unit ∈ company, grade ∈ rentang job, reporting line tanpa siklus. Route modul HCMS di `routes/hcms.php` (bukan `routes/module.php` yang ditulis generator). `user_unit.id_position` masih menunjuk unit, dipindah ke `positions` di langkah Employee. **Gelombang 2 (belum):** restrukturisasi terjadwal, bagan organisasi, import massal.
4. **Employee Management**
   - `employees` (terhubung ke `tb_users.id` bila punya akun), `employee_personal_details`, `employee_identities` (NIK, NPWP, BPJS dengan cast `encrypted`), `employee_families`, `employee_contacts`, `employee_emergency_contacts`, `employee_employments`, `employee_education`, `employee_experiences`, `employee_certifications`, `employee_documents`, `employee_status_histories`.
   - `employee_assignments`: satu primer aktif + rangkap/Plt, nomor SK, effective date; validasi tidak tumpang tindih (di service, karena MySQL tidak punya EXCLUDE constraint).
   - ESS change request (`employee_change_requests`) melalui workflow.
   - Peringatan terjadwal: PKWT jatuh tempo, akhir probation, sertifikasi kedaluwarsa, usia pensiun.
   - Data terminasi minimal (`employee_terminations`: tanggal, jenis, alasan).
   - **Status 2026-11-16 — gelombang 1 selesai (66 tes lulus; belum dilihat di browser karena akun dev menunggu pendaftaran MFA):** menu "Kepegawaian › Pegawai"; daftar & profil dibatasi data scope (`EmployeeScope`: unit penugasan aktif ∈ cakupan, atau data sendiri); identitas negara terenkripsi + HMAC NIK untuk cek duplikat, tampil tersamar, dibuka penuh hanya dengan izin Data Sensitif dan tercatat; data riwayat (keluarga, kontak darurat, pendidikan, pengalaman, sertifikasi); penugasan primer/rangkap/Plt. (`AssignEmployee`: satu primer aktif, mutasi menutup primer lama otomatis, cek headcount & tumpang tindih, posisi harus aktif); riwayat status otomatis; terminasi butuh izin Approve, langsung atau terjadwal (`php artisan hcms:process-terminations`, terjadwal 00:15) — mengakhiri penugasan & menonaktifkan akun. **Gelombang 2 (belum):** ESS change request (menunggu workflow engine), peringatan PKWT/probation/sertifikasi/pensiun (usia pensiun masih keputusan terbuka), unggah foto & dokumen, impor massal, migrasi `user_unit.id_position` ke `positions`.
5. **Workflow & Notification**
   - `workflow_definitions`, `workflow_versions`, `workflow_instances`, `workflow_steps`, `approval_tasks`, `approval_actions`, `approval_delegations`, `sla_policies`, `notification_templates`.
   - Approver ditentukan dari **posisi**; posisi kosong → naik ke atasan berikutnya atau Plt.
   - Eskalasi SLA via scheduler; notifikasi in-app (`tb_notification`) + email (job antrean).
   - Halaman "Tugas Saya" untuk semua portal.
   - **Status 2026-11-16 — gelombang 1 selesai (75 tes lulus; belum dilihat di browser):** `App\Domain\Workflow\WorkflowEngine` + `ApproverResolver` (approver dari posisi: primer/Plt. aktif dengan akun aktif; posisi kosong naik solid line; fallback HR Admin → Tenant Admin; pengaju tidak pernah jadi approver; delegasi bertanggal maks. 90 hari; beberapa kandidat → keputusan pertama menutup langkah); alur berversi per tenant (`workflow_definitions/versions`, halaman Control Panel › Alur Persetujuan); jejak `approval_actions` dengan posisi pemberi keputusan; eskalasi SLA `php artisan hcms:escalate-approvals` tiap jam (maks. 3 tingkat); notifikasi in-app `tb_notification`. Menu "Layanan Mandiri": Profil Saya (ESS change request — subjek workflow pertama, diterapkan beserta riwayat), Tugas Persetujuan, Delegasi. **Belum:** notifikasi email + `notification_templates`, `sla_policies` terpisah (SLA saat ini per langkah), approval paralel "semua harus setuju".
6. **Audit trail & ekspor**
   - `audit_logs` (before/after), `access_logs` untuk akses data sensitif, `data_export_logs` (alasan + persetujuan + watermark di export Excel/PDF Sximo).
   - **Status 2026-11-16 — selesai (85 tes lulus; belum dilihat di browser):** `audit_logs` append-only (trigger MySQL menolak UPDATE/DELETE — butuh hak `TRIGGER`, dan `log_bin_trust_function_creators=1` bila binlog aktif di produksi); `App\Support\Audit\AuditLog` dipasang di base Sximo (insertRow/updateDataRow/deleteDataRow/destroy), master organisasi, pegawai & data riwayat, penugasan, terminasi, identitas, ESS, tenant, cakupan data, MFA, break-glass (`break_glass_id` ditandai), hak akses modul, hapus pengguna; kolom rahasia selalu `[disembunyikan]`; akses identitas tercatat sebagai `sensitive_viewed` (pengganti `access_logs` terpisah). Ekspor (semua modul Sximo + Pegawai) wajib alasan ≥ 10 karakter, > 500 baris butuh izin Approve, watermark berisi pengekspor & nomor log, tercatat di `data_export_logs`. Halaman Control Panel › Audit Trail (Tenant Admin, Auditor, DPO). Partisi bulanan ditunda (MySQL tidak mengizinkan partisi + FK; diganti index `tenant_id, created_at`). Diperbaiki sekaligus: template CSV Sximo yang selalu ParseError (bug master), dan cache hak akses per instance controller yang bisa bocor antar-request di Octane.
7. **Alat migrasi data**
   - Importer CSV/Excel pegawai, organisasi, posisi dengan validasi dan laporan rekonsiliasi.
   - **Status 2026-11-16 — selesai (93 tes lulus; belum dilihat di browser):** Control Panel › Migrasi Data dan `php artisan hcms:import {type} {file} --tenant=KODE [--commit]`. 7 jenis (unit, job family, level, grade, job, posisi, pegawai + penugasan + identitas), template CSV per jenis, pemisah `,`/`;`, tanggal `YYYY-MM-DD` atau `DD/MM/YYYY`. Validasi kering memakai aturan domain sebenarnya di dalam transaksi yang di-rollback; commit menjalankan ulang dan menyimpan semua baris atau tidak sama sekali (data berubah sejak validasi → batch `failed`). Error per nomor baris berkas, rekonsiliasi (baris berkas vs baru/diperbarui vs jumlah di sistem sebelum/sesudah), upsert per kode alami, setiap baris tercatat di audit trail. **Belum:** XLSX langsung (simpan sebagai CSV dari Excel), impor data riwayat (keluarga, pendidikan) dan riwayat penugasan lama.

**Gerbang keluar:** uji isolasi tenant lulus di semua modul Fase 1.
**Uji manual browser 2026-11-16 (tenant DEMO lewat break-glass):** break-glass, master organisasi & job (persyaratan, reporting line), pegawai (form bersyarat, identitas tersamar/dibuka, penugasan, validasi headcount), alur persetujuan (versi 2), tugas & delegasi, audit trail, migrasi data (unggah → validasi → commit, CLI), ekspor CSV, dan isolasi sesudah break-glass berakhir — lulus. 13 temuan, 12 diperbaiki dengan tes regresi bila relevan (95 tes): kategori/tipe unit default untuk tenant baru + menu; checkbox Bootstrap dibajak CSS tema; hitung mundur break-glass berpecahan; pesan penugasan di luar masa berlaku posisi; label form pegawai tidak terkait input; tab profil kembali ke form asal saat error; perubahan persyaratan job & alur persetujuan belum diaudit; catatan identitas menyebut kolom kosong; impor CLI tidak tercatat di riwayat; pesan validasi berbahasa Inggris (kini `resources/lang/id/validation.php`, locale default `id`); ekspor CSV tanpa BOM & status mentah. Editor alur kini bisa mengubah urutan langkah (↑/↓, diuji di browser); kasus tanpa approver sama sekali diuji otomatis (96 tes). **Tersisa:** ESS end-to-end (pegawai mengajukan → HR menyetujui) baru teruji otomatis, belum lewat browser (butuh login sebagai pegawai DEMO).

**Status gerbang 2026-11-16:** isolasi tenant diuji otomatis untuk Sximo base, Company, master organisasi, pegawai, workflow, audit, dan impor (93 tes). Fase 1 selesai di kode; tinggal uji manual di browser dan backlog gelombang 2 per modul.

### Fase 2 — Competency Management (minggu 15–22)

1. Kamus berversi: `competency_framework_versions`, `competency_categories` (Core, Leadership, Functional, Digital), `competencies`, `competency_levels` (1–5), `competency_indicators`.
2. Profil kompetensi job: `job_competencies` (level wajib + bobot kritikalitas 3/2/1), override di `position_competencies`; posisi mewarisi dari job.
3. Asesmen multi-penilai: `competency_assessments`, `assessment_raters`, `assessment_rater_weights` (default 50/20/20/10), `competency_assessment_details`; sembunyikan kelompok peer/subordinate bila < 3 penilai; masa berlaku 24 bulan; hasil assessment center menggantikan metode lain.
4. Kalkulasi (service + job batch): `employee_competencies`, `competency_gaps` (Actual − Required), `job_fit_results` (JFI = Σ min(A/R,1)·w ÷ Σw, band ≥90 / 75–89 / <75).
5. Output: Competency Gap Report (individu/unit/job), Position Readiness, Organizational Competency Heatmap (ECharts heatmap), draf item IDP untuk gap negatif kompetensi kritis.
6. Tes unit rumus JFI memakai contoh blueprint 7.4 (hasil wajib 71,3%).

**Status 2026-11-17 — gelombang 1 selesai (113 tes lulus; diuji di browser di tenant DEMO):** menu "Kompetensi"; kamus berversi (`competency_framework_versions`: draft → berlaku → pensiun; hanya draft yang bisa diubah; draft baru menyalin versi berlaku; terbit butuh izin Approve dan setiap kompetensi aktif wajib punya level 1–5 dengan 2–4 indikator; saat terbit profil job & posisi dipindah ke versi baru berdasarkan kode); kategori Core/Leadership/Functional/Digital otomatis per tenant; profil kompetensi job (level wajib + kritikalitas 3/2/1) dan override posisi (ubah, kecualikan, tambahan khusus posisi, alasan wajib) via `CompetencyProfile`; kalkulator murni `JobFitCalculator` (contoh blueprint = 71,3%, kelebihan dibatasi 100%, band 90/75 dapat dikonfigurasi) dan `RaterAggregator` (bobot 50/20/20/10, rekan/bawahan < 3 penilai gugur dan bobotnya dibagi ulang, assessment center menggantikan). Bug ditemukan tes: pemindahan profil saat terbit diam-diam tidak memindah (pluck dua kolom `id`).

**Status 2026-11-17 — gelombang 2 selesai (117 tes lulus; diuji di browser di tenant DEMO):** layanan `CompetencyAssessment` — HR membuka asesmen per pegawai (kompetensi = profil posisi primer saat ini; satu asesmen berjalan per pegawai); penilai diri sendiri dari akun pegawai, atasan otomatis dari solid line (naik bila kosong), rekan/bawahan/HR dipilih; notifikasi in-app ke penilai. Menu Layanan Mandiri › "Asesmen Saya": daftar tugas penilaian, form skor 1–5 dengan deskripsi level & indikator dari kamus (Alpine.js) + bukti perilaku, profil kompetensi & JFI sendiri. Kompetensi › "Asesmen Kompetensi" (HR/Asesor): rekap per kelompok penilai tanpa skor individual rekan/bawahan, input hasil assessment center, tutup → `employee_competencies` (berlaku 24 bulan, rincian per kelompok), `competency_gaps`, `job_fit_results`, draf IDP (`competency_idp_suggestions`) untuk kompetensi kritis dengan gap negatif; batal dengan alasan. Bobot penilai & batas band dapat diatur per tenant di `tenant_settings` (`competency.rater_weights`, `competency.jfi_bands`). Kompetensi › "Laporan Kompetensi": sebaran kesiapan, gap terbesar, heatmap gap unit × kompetensi, profil individu (radar wajib vs aktual, riwayat) — dibatasi data scope. Grafik memakai ECharts 6.1 via Vite (`resources/js/charts.js`, hanya dimuat di halaman laporan); `echarts-all.js` 2.2.7 bawaan Sximo tidak dipakai. **Belum:** Position Readiness kandidat terhadap posisi selain posisi saat ini (fungsi `fitFor` sudah ada); job batch penghitungan ulang saat profil berubah/skor kedaluwarsa; pengingat tenggat penilai; kalibrasi asesmen; gerbang: satu job family pilot terpetakan penuh (saat ini DEMO baru 1 kompetensi).

**Penyelesaian backlog Fase 2:** Position Readiness menjadi laporan per posisi sasaran yang membandingkan kandidat internal berdasarkan JFI/band, kompetensi kritis yang belum terpenuhi, dan tanggal asesmen; kandidat dibatasi data scope dan laporan menandai skor kedaluwarsa. Tambahkan batch penghitungan ulang JFI/gap setelah perubahan profil atau kedaluwarsa hasil, pengingat tenggat penilai, serta kalibrasi asesmen dengan alasan dan jejak perubahan. Simpan juga riwayat hasil per asesmen (skor, gap, JFI) agar asesmen baru tidak menimpa hasil lama — prasyarat P1 laporan CBHRM. Keluaran ini melengkapi laporan gap yang sudah tersedia dan menjadi masukan terstruktur untuk TNA, IDP, talent review, dan suksesi.

**Status 2026-11-18 — backlog Fase 2 diselesaikan (169 tes lulus):** Kompetensi › **Kesiapan Posisi** (`PositionReadiness`): kandidat internal dalam data scope terhadap posisi sasaran dengan JFI, horizon K4, kompetensi terpenuhi, gap kritis, skor kedaluwarsa, penanda pemangku; filter unit & horizon; akses tercatat di audit; 636 ms pada 10.000 pegawai. Hitung ulang JFI terjadwal (`hcms:competency-recalculate`) sudah ada sejak P4. Profil Kompetensi Job menampilkan **Cakupan pemetaan** per job family (job ≥ 3 kompetensi dengan ≥ 1 kritis; status "Terpetakan penuh") sebagai alat bukti gerbang. **Masih terbuka (opsional MVP):** pengingat tenggat penilai dan kalibrasi asesmen.

**Gerbang keluar:** satu job family pilot terpetakan penuh. **Belum terpenuhi di data:** tenant DEMO baru memiliki 1 kompetensi; HR perlu memetakan job family pilot sampai statusnya "Terpetakan penuh" (dilakukan pada dry run migrasi, `RUNBOOK-PILOT.md` §2.6).

### Fase 3 — Performance Management (minggu 23–32)

1. Kebijakan berversi: `performance_policies`, `rating_scales`, `rating_bands`, `score_conversion_tables`; HR membuka `performance_periods`/`performance_cycles` dan **mengunci versi kebijakan**.
2. Sasaran & cascading: `strategic_objectives`, `kpis`, `objective_alignments` (parent, child, contribution weight); KPI tim dengan bobot kontribusi per anggota.
3. Perencanaan: `performance_plans` (per **assignment**), `performance_objectives`, `performance_targets`; validasi bobot total 100%, per KPI 5–40%, 3–10 KPI; persetujuan atasan & pegawai lewat workflow.
4. Monitoring: `performance_updates`, `performance_evidences` (unggah bukti, storage per tenant), `performance_checkins`, mid-year review wajib, `kpi_change_requests` dengan versi KPI lama.
5. Mesin skor (`app/Domain/Performance/Services/ScoreCalculator`):
   - Normalisasi: higher, lower (`(2 − R/T) × 100`, aman saat R = 0), range, milestone, kualitatif.
   - Cap per KPI (default 120%), What/How sesuai kebijakan, konversi How 1–5 → 50/65/75/85/95.
   - Prorata antar-assignment (`d_j / D`, gabung < 90 hari, status "not rated").
   - Skor > 100 dibatasi untuk rating, nilai mentah disimpan.
6. Penilaian: `performance_assessments` + details (self → manager → one-over-one); 360 feedback informatif.
7. Kalibrasi: `calibration_sessions`, `calibration_adjustments` (alasan wajib, nilai sebelum/sesudah) — layar drag & drop distribusi dengan jQuery UI sortable/gridstack, state & ringkasan distribusi dengan Alpine.js; hitung ulang distribusi lewat endpoint server.
8. Keberatan: `performance_appeals`, jendela 14 hari, diputus pejabat di atas penilai (SoD).
9. Pengesahan: `performance_ratings` + `performance_snapshots` immutable (menyimpan versi kebijakan); koreksi = revisi baru.
10. `performance_improvement_plans`, dashboard kinerja sesuai data scope.

**Gerbang keluar:** simulasi satu siklus penuh dengan data uji.

**Status 2026-11-17 — gelombang 1 selesai (132 tes lulus; diuji di browser di tenant DEMO):** model skor 60/30/10 dengan dimensi ketiga **Kontribusi tambahan** (ADR 0001 no. 8). Mesin skor murni `ScoreCalculator` (higher/lower aman saat R = 0 dan target 0, range dengan penalti per 1% simpangan, milestone dengan faktor keterlambatan, kualitatif via rubrik; cap per KPI; What; konversi 1–5 dengan interpolasi; skor akhir mentah vs dibatasi 100; band; bobot per level jabatan; prorata yang menggabung penugasan < 90 hari dan status not rated). Menu "Kinerja": Kebijakan Kinerja berversi (`performance_policies`, draft → berlaku → pensiun, terbit butuh izin Approve, validasi total bobot/batas KPI/konversi naik/band) dan Periode & Sasaran (`performance_periods`: membuka periode mengunci salinan kebijakan; `strategic_objectives` korporat → direktorat → divisi → tim dengan bobot kontribusi; rekap status rencana pegawai sesuai data scope). Layanan Mandiri › "Rencana Kinerja Saya": rencana per penugasan primer yang beririsan dengan periode (`performance_plans`, `performance_objectives`), editor KPI Alpine.js dengan total bobot langsung, validasi kebijakan di server (3–10 KPI, 5–40%, total 100%, target/rentang/milestone 100%/rubrik 5 level), tautan ke sasaran strategis dan cascade dari KPI atasan yang sudah disetujui; diajukan ke atasan lewat workflow `performance_plan` (ditolak = dikembalikan dengan catatan, bisa ditarik). Diperbaiki: audit kebijakan gagal untuk nilai array (pembaruan kini dalam transaksi).

**Status 2026-11-17 — gelombang 2 (monitoring) selesai (136 tes lulus; diuji di browser di tenant DEMO dengan rina.demo & demo.admin):** halaman Monitoring per rencana (`performance/plans/{id}/monitoring`) dengan peran pemilik / atasan / HR: pegawai mencatat realisasi per KPI (`performance_updates`: angka, status milestone dengan tanggal selesai → faktor keterlambatan, atau skor rubrik), capaian dihitung dengan kebijakan yang dikunci, proyeksi skor What; bukti (`performance_evidences`) disimpan privat di `storage/app/tenants/{tenant}/performance/{rencana}/` dengan nama acak, tipe & ukuran dibatasi, unduhan tercatat di audit. Check-in berkala & mid-year review (`performance_checkins`, satu mid-year per rencana, diselesaikan atasan dengan catatan, notifikasi in-app); periode hanya bisa masuk tahap Penilaian bila semua rencana yang disetujui sudah menyelesaikan mid-year review. Perubahan KPI setelah disetujui lewat `kpi_change_requests` + workflow `kpi_change_request` (atasan): KPI lama disimpan di `performance_plan_versions`, KPI yang dihapus dipensiunkan (`retired_at`, realisasi tetap ada), tipe capaian yang diganti = KPI baru, target yang diubah → capaian realisasi dihitung ulang. Layanan Mandiri › "Kinerja Tim Saya" untuk atasan (bawahan langsung, status rencana, proyeksi, mid-year). Editor KPI dipisah menjadi partial `performance/plans/_kpi_editor` (dipakai rencana & change request). **Catatan:** zona waktu aplikasi masih UTC (bawaan master) sehingga "hari ini" tertinggal 7 jam dari WIB.

**Status 2026-11-17 — gelombang 3 selesai; Fase 3 selesai, gerbang keluar terpenuhi (138 tes lulus; `PerformanceCycleTest` mensimulasikan satu siklus penuh, dan siklus yang sama dijalankan di browser pada tenant DEMO):** kebijakan ditambah item perilaku (How, default 5 nilai inti), rubrik Kontribusi tambahan 1–5, dan jendela keberatan (kebijakan periode lama dilengkapi default). Membuka tahap Penilaian membuat `performance_assessments` untuk setiap rencana yang disetujui: penilaian diri (informatif) → penilaian atasan (skor How & Kontribusi yang dihitung, ringkasan wajib) → review atasan dari atasan (setuju / kembalikan dengan catatan; tanpa atasan dari atasan = selesai otomatis). Skor: What dari realisasi terakhir (tanpa realisasi = 0), How = rata-rata skor atasan → konversi, Kontribusi → konversi, bobot override per level jabatan (grade posisi). Tahap Kalibrasi butuh semua penilaian selesai; `calibration_sessions` per unit (beserta turunannya) dengan papan drag & drop jQuery UI sortable + ringkasan distribusi Alpine; pemindahan band wajib beralasan, skor disesuaikan ke batas band tujuan, tercatat di `calibration_adjustments`. Pengesahan (sesi kalibrasi harus ditutup): `performance_ratings` per pegawai (prorata antar-rencana, not rated bila masa kurang), `performance_snapshots` immutable (trigger MySQL, payload `longText` + SHA-256, memuat kebijakan, KPI & versi lama, realisasi, penilaian, kalibrasi), periode ditutup, notifikasi. Keberatan (`performance_appeals`) sekali per periode dalam jendela kebijakan, diputus atasan dari atasan atau HR — penilai & pegawai sendiri tidak boleh memutus; dikabulkan = revisi rating + snapshot baru. PIP (`performance_improvement_plans`) untuk rating Below/Unsatisfactory dengan atasan sebagai penanggung jawab (selesai/diperpanjang/tidak berhasil). Kinerja › Dasbor Kinerja (progres tahap, sebaran rating, rata-rata per unit, sesuai data scope). Menu Layanan Mandiri: Penilaian Kinerja, Rating Kinerja Saya, Keberatan Rating, Rencana Perbaikan (PIP). **Backlog opsional:** 360 feedback informatif, notifikasi email, dan pengingat tenggat tahap. Ekspor rating CSV sudah tersedia melalui `DataExport` (lihat status Fase 4).

### Fase 4 — Testing & Pilot (minggu 33–40)
- Uji fungsional per kriteria MVP (blueprint bagian 22), uji isolasi tenant, uji SoD.
- Pentest independen, `composer audit`, review konfigurasi Octane (state bocor antar-request).
- Uji beban 10.000 pegawai / 1.000 pengguna bersamaan, p95 < 1 detik — perhatikan query `getRows` Sximo (index `tenant_id`, `company_id`, `employee_id`, kolom effective date).
- 2× dry run migrasi data + rekonsiliasi, UAT, pelatihan pengguna, pilot satu company, stabilisasi.
- Alur hak subjek data minimal (akses & koreksi oleh pegawai).

**Gerbang keluar:** seluruh kriteria MVP terpenuhi.

**Status 2026-11-17 — gelombang 1 selesai (141 tes lulus):** matriks penerimaan di `docs/MVP-ACCEPTANCE.md` — 20 dari 21 kriteria ✅ dengan bukti tes otomatis, 1 🟡 (dasbor dengan peran ber-scope belum punya tes khusus). Celah yang ditutup: (1) **keberatan sebelum pengesahan** — rating kini diumumkan sementara (periode tahap `appeal`, `performance_ratings.status = provisional`), keberatan diputus, lalu **pengesahan final** setelah jendela keberatan berakhir dan semua keberatan diputus; snapshot immutable dibuat saat final (rating lama ber-snapshot dianggap final); (2) tes end-to-end prorata pegawai mutasi (181/184 hari → 85,04) dan masa kerja < 90 hari → not rated; (3) ekspor rating CSV lewat `DataExport` (izin ekspor, alasan, watermark, log, > 500 baris butuh Approve); (4) hak subjek data — Profil Saya › Unduh data saya (JSON berisi data pribadi termasuk identitas yang didekripsi, riwayat, kompetensi, kinerja; tercatat di log ekspor & audit); (5) review Octane — cache statis hanya metadata global, `TenantContext` scoped dan kini dibersihkan middleware saat terminate, tes regresi `RequestStateIsolationTest`; `composer audit` & `npm audit` bersih. **Gelombang 2 (2026-11-17, `docs/LOAD-TEST.md`):** `hcms:load-seed` (10.000 pegawai, 40.000 KPI, 80.000 realisasi, 200.000 audit di database `hcms_load`) + suite benchmark `phpunit.benchmark.xml`; 2 halaman melewati target dan diperbaiki (Laporan Kompetensi 3,4 → 0,7 dtk lewat agregasi SQL, JOIN alih-alih EXISTS+IN, cache berversi; Audit Trail 1,6 → 0,09 dtk lewat paginasi tanpa COUNT, indeks & cache daftar tipe); rekap periode & daftar rating dipaginasi. Semua 20 skenario p95 < 1 dtk (latensi server). Konkurensi 1.000 pengguna: skrip k6 `tests/Load/k6-hcms.js` untuk staging — Apache Laragon dev tanpa OPcache hanya ±17 req/dtk sehingga tidak representatif. **Gelombang 3 (2026-11-18) — dokumen selesai:** `docs/UAT-SKRIP.md` (skenario UAT per kriteria MVP), `docs/PANDUAN-PENGGUNA.md` (materi pelatihan 6 sesi per peran), `docs/RUNBOOK-PILOT.md` (dry run migrasi ×2 dengan rekonsiliasi, go/no-go, rollback, stabilisasi 4 minggu). Kriteria MVP 17 kini teruji otomatis untuk peran ber-scope (`PerformanceDashboardScopeTest`). Pelaksanaan UAT, pelatihan, dry run, uji k6 1.000 pengguna di staging, pentest, dan pilot dilakukan tim HR/TI. Pentest independen oleh pihak ketiga setelah seluruh pengembangan selesai (keputusan 2026-11-17). **Keputusan 2026-11-17:** zona waktu aplikasi & sesi database `Asia/Jakarta` (+07:00); kunci Google Maps, Gemini, dan reCAPTCHA dirotasi pemilik akun.

### Rilis 2 — P1 (+24–30 minggu)
Urutan disarankan: Offboarding & ER lengkap → Learning & Development → Talent → Career → Succession → People Analytics → Platform & Subscription → fitur AI rekomendasi.

#### Learning & Development — TNA dan pengembangan
- Menerima masukan gap kompetensi terverifikasi dari asesmen, hasil kinerja/PIP, kebutuhan organisasi, dan permintaan atasan/pegawai; HR dapat meninjau, menggabungkan, memprioritaskan, dan menyetujui kebutuhan.
- Catat kebutuhan per pegawai, jabatan, unit, dan kompetensi dengan alasan, urgensi, periode, sumber, serta hubungan ke gap/IDP. Bedakan rekomendasi otomatis dari kebutuhan yang telah disetujui.
- Kelola katalog intervensi (pelatihan, coaching, mentoring, penugasan, sertifikasi), penyedia, biaya, prasyarat, kapasitas, jadwal, dan kompetensi/level sasaran.
- Buat rencana pengembangan individu (IDP) dari saran gap yang sudah ada; pegawai dan atasan menyusun sasaran, aktivitas, tenggat, serta bukti. Dukung persetujuan, status pelaksanaan, dan pencatatan sertifikasi.
- Evaluasi efektivitas pelatihan empat tingkat: reaksi, pembelajaran, penerapan perilaku/kompetensi, dan dampak kinerja/organisasi; simpan baseline dan tindak lanjut agar hasil dapat dibandingkan.
- Laporan: TNA menurut sumber/prioritas/unit/kompetensi; gap vs intervensi terencana; biaya dan realisasi pelatihan; partisipasi/sertifikasi; evaluasi efektivitas; kemajuan IDP. Tampilkan kebutuhan belum ditindaklanjuti dan hindari menyamakan saran IDP dengan TNA yang disetujui.

**Status 2026-11-18 — gelombang 1 (TNA) selesai (173 tes lulus):** menu baru **Pengembangan** › Kebutuhan Pelatihan & Laporan TNA, serta Layanan Mandiri › Usulan Pelatihan. Tabel `training_needs` (pegawai × kompetensi/topik × tahun; sumber gap / kinerja-PIP / organisasi / permintaan; status saran otomatis → diajukan → disetujui/ditolak → direncanakan → selesai). Saran otomatis dari gap kompetensi ≤ −1 pada posisi primer saat ini dan dari PIP berjalan (`hcms:tna-suggest`, Minggu 03.00, idempoten; saran usang dihapus, yang sudah ditinjau tidak disentuh); saran tidak dihitung sebagai TNA. Pegawai mengusulkan untuk dirinya, atasan untuk bawahan langsung; HR mengajukan kebutuhan organisasi per pegawai atau seluruh unit. Tinjauan HR (izin Approve, massal, alasan wajib saat menolak; **pengaju tidak dapat memutus pengajuannya sendiri**). Prioritas K5 otomatis bila ada gap (×1,5 bila terkait sasaran strategis), PIP selalu tinggi. Laporan TNA: kebutuhan disetujui menurut sumber, prioritas, kompetensi/topik, dan unit; kebutuhan terbuka & berulang; perkiraan biaya; penyamaran kelompok kecil. Uji beban: 7.010 saran dari 10.000 pegawai dalam 8 dtk; daftar 283 ms, laporan 96 ms. **Berikutnya (gelombang 2):** katalog intervensi & IDP (status `planned` diisi dari IDP), lalu pelaksanaan pelatihan & evaluasi empat tingkat.

**Status 2026-11-18 — gelombang 2 (katalog & IDP) selesai (175 tes lulus):** Pengembangan › **Katalog Intervensi** (pelatihan, e-learning, coaching, mentoring, penugasan, sertifikasi; kompetensi & level sasaran, durasi, biaya per peserta, kapasitas, prasyarat; tidak dapat dihapus bila sudah dipakai IDP) dan **Rekap IDP** (cakupan IDP terhadap pegawai aktif, status, kemajuan aktivitas, lewat tenggat, biaya rencana vs realisasi, gap setelah aktivitas selesai: tertutup / belum / belum diukur). Layanan Mandiri › **IDP Saya**: satu IDP per pegawai per tahun; draft otomatis berisi kebutuhan TNA yang disetujui, saran IDP asesmen yang belum ditindaklanjuti, dan aspirasi karier sebagai sasaran karier; editor aktivitas (pilih dari katalog, kompetensi & level target, tenggat, biaya); diajukan lewat workflow `idp` (atasan langsung, ikut delegasi & eskalasi; dikembalikan dengan catatan); disetujui → kebutuhan TNA terkait `planned`, saran IDP `accepted` (P6: aktivitas menyimpan `training_need_id` & `idp_suggestion_id`). Pelaksanaan: status aktivitas, bukti wajib saat selesai, biaya realisasi, sertifikasi & masa berlaku, catatan pegawai/atasan; kebutuhan TNA `closed` bila semua aktivitasnya selesai; IDP `completed` bila tidak ada aktivitas terbuka. Atasan melihat IDP tim di halaman yang sama. IDP & kebutuhan pelatihan ikut "Unduh data saya". **Berikutnya (gelombang 3):** pelaksanaan pelatihan (jadwal/kelas, peserta, kehadiran) dan evaluasi empat tingkat (laporan 7).

**Status 2026-11-18 — gelombang 3 (pelaksanaan & efektivitas) selesai; Learning & Development lengkap (176 tes lulus):** Pengembangan › **Jadwal Pelatihan**: kelas dari katalog (kode, tanggal, moda, lokasi, pengajar, kapasitas, pre/post-test), kandidat peserta otomatis dari aktivitas IDP yang disetujui & kebutuhan TNA kompetensi yang sama (dalam data scope), pendaftaran dengan batas kapasitas, kehadiran %, pre/post-test, nomor sertifikat, penutupan kelas dengan biaya realisasi (peserta "Terdaftar" harus dicatat lebih dulu). **Lulus** → aktivitas IDP terkait selesai dengan bukti kelulusan otomatis → kebutuhan TNA `closed` → IDP selesai bila tidak ada aktivitas terbuka; peserta tanpa IDP menutup kebutuhan TNA-nya langsung. Layanan Mandiri › **Pelatihan Saya**: riwayat pelatihan & survei reaksi 1–5 (sekali, setelah hadir). Pengembangan › **Efektivitas Pelatihan** per program: penyelesaian, biaya per lulusan, T1 reaksi (bila respons ≥ 50%), T2 post − pre, T3 perubahan skor kompetensi sasaran pada asesmen 3–6 bulan setelah kelas vs sebelum kelas (riwayat P1), T4 perubahan rating final periode berikutnya vs sebelum kelas (K6), dan indikasi lanjutkan/perbaiki/pertimbangkan dihentikan; penyamaran kelompok kecil. Riwayat pelatihan ikut "Unduh data saya". Rantai CBHRM 3 → 5 → 6 → 7 kini tersambung dan dapat ditelusuri (kriteria penerimaan Rilis 2 butir pertama). **Berikutnya:** Talent — penilaian potensi & talent review/Nine Box (laporan 9).

#### Talent — asesmen potensi dan talent pool
- Definisikan kriteria potensi dan instrumen asesmen berversi, terpisah dari rating kinerja dan asesmen kompetensi; catat bukti, assessor, tanggal, dan tingkat keyakinan.
- Jalankan talent review per siklus dengan panel, catatan/justifikasi, kalibrasi, persetujuan, dan riwayat perubahan. Terapkan akses terbatas karena data potensi bersifat sensitif.
- Sediakan Nine Box yang menggabungkan hasil kinerja final dengan potensi tervalidasi; kategori, ambang, dan aturan pemetaan dapat dikonfigurasi per tenant dan periode.
- Talent pool memiliki kriteria masuk/keluar yang transparan, nominasi dan persetujuan, status kesiapan, kompetensi/gap utama, IDP/intervensi, minat karier, serta tanggal tinjau ulang. Keanggotaan tidak otomatis menjadi janji promosi.
- Laporan: distribusi Nine Box per unit/kelompok jabatan, pergerakan antar-siklus, kandidat per pool dan level kesiapan, gap pengembangan, keragaman dan cakupan, serta riwayat keputusan; batasi agregat kecil dan akses baris individu sesuai data scope/izin sensitif.

**Status 2026-11-18 — Talent gelombang 1 (laporan 9) selesai (178 tes lulus):** menu Talenta › **Asesmen Potensi** (instrumen berlaku dari P3; semua kriteria wajib dinilai, bukti perilaku ≥ 20 karakter, tingkat keyakinan, asesor & tanggal tercatat, masa berlaku dari instrumen; dibatalkan dengan alasan; setiap akses daftar tercatat di audit), **Talent Review** (review per periode kinerja tertutup & cakupan unit; panel minimal 2 orang; peserta otomatis dari data scope pembuat dengan usulan kotak K7 dan status kelengkapan data; panel/HR menggeser kotak final dengan justifikasi wajib; **flight risk** dinilai panel, hanya terlihat & dapat diubah dengan izin sensitif, alasan wajib; tindakan pengembangan, penanggung jawab, tanggal tinjau ulang; "Perbarui data" mempertahankan keputusan panel; pengesahan oleh pemegang Approve **selain pembuat review**, setelah sah terkunci; grid Nine Box dengan nama hanya untuk izin sensitif; kotak review sah sebelumnya ditampilkan), dan **Laporan Nine Box** (agregat tanpa nama: sebaran 9 kotak, kelompok talenta kunci/inti/perlu tindak lanjut, kelengkapan data, per unit, pergerakan antar-siklus, keragaman gender talenta kunci, risiko kehilangan talenta kunci hanya untuk izin sensitif; penyamaran < 5 bagi pengguna tanpa akses talent individual — mis. Eksekutif, Department Head, HRBP). Akses modul: HR & Talent Committee penuh; Laporan Nine Box juga Eksekutif, HRBP, Department Head (baca). **Keputusan terbuka:** apakah hasil potensi & posisi Nine Box ikut "Unduh data saya" (hak akses subjek data) — saat ini tidak disertakan. **Berikutnya:** talent pool (laporan 10).

**Status 2026-11-18 — Talent gelombang 2 (talent pool, laporan 10) selesai (179 tes lulus):** Talenta › **Talent Pool**: pool dengan kode, tujuan, sasaran (rumpun jabatan dan/atau posisi), kriteria masuk & keluar, pemilik, siklus tinjau (3–24 bulan). Kandidat otomatis = talenta kunci (kotak 6/8/9) pada talent review sah terakhir tiap pegawai dalam data scope; nominasi manual wajib beralasan. Persetujuan oleh pemegang Approve **selain penominasi**; disetujui wajib menetapkan kesiapan (K4) dan menjadwalkan tinjau ulang; ditolak wajib alasan; anggota aktif dapat ditinjau ulang atau dikeluarkan (alasan wajib). Halaman pool menampilkan kesiapan ditetapkan vs terhitung (bila ada posisi sasaran), gap kritis, keselarasan aspirasi, IDP tahun berjalan, kotak Nine Box & risiko keluar (izin sensitif), tinjau lewat jadwal, dan riwayat keputusan (`talent_pool_events`); setiap pembukaan tercatat di audit. **Laporan Talent Pool** (agregat, penyamaran < 5): pegawai di pool, siap sekarang, masuk/keluar tahun berjalan, tinjau lewat jadwal, cakupan IDP, keselarasan aspirasi, per pool termasuk porsi perempuan. **Berikutnya:** suksesi & bench strength (laporan 11).

#### Career dan Succession — hubungan dengan talent
- Petakan career path dan persyaratan posisi; gunakan Position Readiness dari Fase 2 untuk membandingkan kandidat terhadap posisi sasaran.
- Tandai posisi kritis, rencana suksesi, kandidat utama/cadangan, kesiapan (siap sekarang/siap dalam jangka waktu tertentu), risiko kekosongan, dan tindakan pengembangan. Perubahan kandidat atau keputusan harus memiliki alasan dan jejak audit.
- Laporan: kesiapan kandidat per posisi kritis, cakupan suksesi, bench strength, risiko posisi tanpa suksesor, serta gap yang perlu ditutup. Data talent pool dapat menjadi masukan, tetapi pemilihan suksesor tetap melalui persetujuan.

**Status 2026-11-18 — Talent gelombang 3 (suksesi, laporan 11) selesai; Talent & Succession lengkap (180 tes lulus):** Talenta › **Rencana Suksesi**: daftar posisi kritis dalam cakupan unit dengan dampak, pemangku, risiko kekosongan ≤ 12 bulan (kosong, pensiun — usia dari pengaturan tenant `talent.retirement_age`, bawaan 59 — atau kontrak PKWT berakhir), jumlah suksesor siap sekarang/1–2 tahun, dan status rencana. Satu rencana per posisi kritis; kandidat utama/cadangan dengan kesiapan (K4), sumber (Kesiapan Posisi, talent pool yang menyasar posisi/rumpunnya, manual), alasan ≥ 10 karakter, tindakan pengembangan; usulan otomatis dari Kesiapan Posisi (siap sekarang/1–2 tahun, bukan pemangku) & talent pool; pemangku tidak dapat menjadi suksesornya sendiri; ubah/keluarkan kandidat wajib alasan (jejak tetap); setiap perubahan mengembalikan rencana ke draft; pengesahan oleh pemegang Approve **selain pengubah terakhir**, tinjau ulang 12 bulan; tanda kandidat ganda; akses tercatat di audit. **Laporan Suksesi** (tanpa nama kandidat, penyamaran < 5): cakupan suksesi, bench strength, posisi berisiko, kandidat ganda, rencana disahkan/draft/belum ada — dihitung hanya dari rencana yang disahkan (K8). **Berikutnya:** People Analytics CBHRM lintas modul (laporan 12).

#### People Analytics dan keluaran CBHRM lintas modul
- Satukan laporan gap kompetensi/Position Readiness, TNA dan efektivitas pengembangan, kinerja, Nine Box/talent pool, serta succession ke tampilan analitik dengan definisi metrik dan periode yang konsisten.
- Gunakan snapshot bulanan `analytics_*`, data scope tenant/unit, audit akses dan ekspor, serta penyamaran agregat berisi kurang dari 5 orang.
- Kriteria penerimaan Rilis 2: kebutuhan dari gap dapat ditelusuri hingga intervensi dan evaluasinya; talent pool dapat ditelusuri ke asesmen dan keputusan talent review; laporan posisi kritis menunjukkan kandidat dan gap aktual; seluruh laporan memiliki filter periode/unit dan ekspor sesuai kebijakan akses. Ketertelusuran dibuktikan dengan satu tes end-to-end per pegawai: gap → kebutuhan TNA disetujui → IDP → pelatihan selesai → asesmen ulang → gap tertutup (memakai riwayat hasil per asesmen, prasyarat P1).

#### Urutan laporan CBHRM dan isi minimum
Urutan berikut mengikuti ketergantungan data. Setiap laporan harus mendukung filter periode dan unit yang relevan, menampilkan waktu pembaruan serta definisi metrik, menghormati data scope dan izin sensitif, dan menyediakan ekspor yang tercatat sesuai kebijakan.

**Status per 2026-11-17** (urutan laporan ≠ urutan pembangunan; sebagian sudah tersedia dari Fase 2–3):

| No | Laporan | Status | Yang sudah ada / yang kurang |
|---|---|---|---|
| 1 | Profil kompetensi jabatan | 🟡 sebagian | Profil job + override posisi berversi ada; laporan cakupan pemetaan & posisi tanpa profil belum |
| 2 | Profil & asesmen pegawai | 🟡 sebagian | Laporan Kompetensi › profil individu (radar, riwayat asesmen); riwayat skor per kompetensi belum tersimpan (lihat prasyarat P1) |
| 3 | Competency Gap Analysis | 🟡 sebagian | Sebaran band, gap terbesar, heatmap unit × kompetensi; tren gap belum (P1) |
| 4 | Position Readiness | 🟡 sebagian | Fungsi `fitFor` ada; laporan per posisi sasaran masih backlog Fase 2 |
| 5–7 | TNA, IDP, pelatihan & efektivitas | ✅ ada | TNA (5), IDP (6), dan pelatihan & efektivitas (7) selesai 2026-11-18 — menu Pengembangan & Layanan Mandiri |
| 8 | Performance Management | ✅ ada | Dasbor Kinerja (`executive/analytic/performance`), rating final/sementara, kalibrasi, keberatan, PIP |
| 9–11 | Talent review/Nine Box, talent pool, suksesi | ✅ ada | Talent review & Nine Box (9), talent pool (10), dan suksesi & bench strength (11) selesai 2026-11-18 — menu Talenta |
| 12 | People Analytics | 🟡 kerangka | Dashboard Executive (ringkasan, turnover, mobilitas, DEIB, demografi, pengembangan) menjadi wadahnya |

**Urutan pembangunan yang disarankan:** (a) lengkapi 1–4 dan 8 beserta prasyarat P1–P2 → (b) TNA → IDP → pelatihan (5–7) → (c) talent review → pool → suksesi (9–11) → (d) People Analytics lintas modul (12) di dalam Dashboard Executive.

**Prasyarat sebelum laporan Rilis 2:**
- **P1 — Riwayat hasil kompetensi.** `employee_competencies`, `competency_gaps`, dan `job_fit_results` saat ini unik per pegawai(+posisi)(+kompetensi), sehingga asesmen baru menimpa hasil lama. Simpan hasil per asesmen (gap & JFI berkunci `assessment_id`) dan pertahankan tabel "terkini" sebagai jalan pintas. Tanpa ini riwayat skor (laporan 2), tren gap (3), perbandingan sebelum/sesudah pelatihan (7), dan tren antarperiode (12) tidak dapat dihitung.
  **Status 2026-11-17 — selesai (152 tes lulus):** tabel `employee_competency_history` (skor per asesmen), `job_fit_history` (setiap perhitungan JFI; pemicu `assessment` / `recalculation` / `backfill`), dan `competency_gap_history` (gap per kompetensi per perhitungan) — hanya bertambah, UPDATE ditolak trigger MySQL; DELETE tetap mengikuti cascade pegawai/tenant. `CompetencyHistory` mencatat riwayat saat asesmen ditutup dan saat JFI dihitung ulang; tabel "terkini" tetap ada. Migrasi mengisi riwayat awal dari hasil terkini (idempoten; hasil yang tertimpa sebelum P1 tidak dapat dipulihkan). Pembaca: daftar & detail asesmen kini menampilkan JFI/gap milik asesmen itu sendiri (sebelumnya asesmen lama menampilkan hasil terkini); profil kompetensi individu menampilkan perubahan skor terhadap asesmen sebelumnya dan grafik riwayat JFI; "Unduh data saya" memuat ketiga riwayat. Benchmark: Laporan Kompetensi 454 ms, profil individu 79 ms (p95).
- **P2 — Snapshot `analytics_*` bulanan dimulai sekarang**, bukan di Rilis 2: tren hanya tersedia sejak snapshot pertama.
  **Status 2026-11-17 — selesai (156 tes lulus):** `analytics_snapshots` (tenant × bulan × versi; menjalankan ulang membuat versi baru dan menandai versi lama `superseded_at`, tercatat di audit) dan `analytics_unit_metrics` (format panjang unit × metrik, UPDATE ditolak trigger). Semua metrik **aditif** (jumlah orang atau jumlah nilai, mis. `jfi_sum` + `jfi_n`) sehingga total cakupan = SUM antar-unit dan rata-rata dihitung saat dibaca; unit = unit posisi primer per akhir bulan (hires: per tanggal bergabung; terminasi: per tanggal terminasi). Definisi versi 1 (`MonthlySnapshot::METRICS`): headcount, female, male, hires, terminations, competency_valid, jfi_n/jfi_sum/band, critical_gap_employees (definisi sementara: kritikalitas 3 & gap < 0, sampai P4), performance_rated/score_sum/`performance_band:{band}` dari rating final yang sudah disahkan per akhir bulan. Penjadwal: `hcms:analytics-snapshot` tanggal 1 pukul 01.30 untuk bulan lalu, semua tenant aktif; `--from/--period/--tenant/--force` untuk membangun ulang — snapshot yang dibuat > 7 hari setelah akhir bulan ditandai `reconstructed`. Pembacaan `MonthlySnapshot::series()` menghormati data scope per unit (cakupan "own" tidak mendapat agregat). Uji beban: 12 bulan × 10.000 pegawai ±13 detik. **Belum:** tampilan tren yang membaca snapshot (bagian dari laporan 12) dan penyamaran agregat < 5 orang (P5).
- **P3 — Data yang belum ada di skema:** penanda posisi kritis + tingkat dampak di `positions` (laporan 11); aspirasi/minat karier pegawai lewat Layanan Mandiri (10); instrumen asesmen potensi berversi (9); keputusan apakah risiko kehilangan pegawai dikelola (data sensitif).
  **Status 2026-11-17 — selesai (160 tes lulus):** (1) `positions.is_critical` sudah ada sejak Fase 1; ditambah `critical_impact` (1 sedang / 2 tinggi / 3 sangat tinggi) dan `critical_reason`, wajib bila kritis, dikosongkan otomatis bila status kritis dicabut, tercatat di `position_histories` & audit; daftar posisi menampilkan tingkat dampak. (2) `employee_career_aspirations`: Layanan Mandiri › Aspirasi Karier Saya (rumpun/jabatan dari katalog atau peran bebas, horizon 1–2 / 3–5 / > 5 tahun, kesediaan pindah lokasi, minat, kebutuhan pengembangan); setiap pembaruan = baris baru (`is_current`) + audit; tab "Aspirasi karier" di profil pegawai untuk HR/atasan sesuai data scope; termasuk di "Unduh data saya". (3) Menu baru **Talenta › Instrumen Potensi**: `potential_instruments` + `potential_criteria` berversi (draft → berlaku → pensiun, terbit butuh izin Approve), draft pertama berisi kriteria awal kemampuan & kelincahan belajar 40 / aspirasi 30 / keterikatan 30; syarat terbit 3–8 kriteria, total bobot 100%, ≥ 2 indikator per kriteria, 1 < ambang sedang < ambang tinggi ≤ skala; `PotentialInstruments::evaluate()` menghitung skor tertimbang → rendah/sedang/tinggi untuk talent review. **Keputusan 2026-11-17:** risiko kehilangan pegawai (flight risk) **dikelola di talent review** — dinilai panel, hanya terlihat dengan izin sensitif — dan dibangun bersama laporan 9–10, bukan di P3. Penilaian potensi per pegawai juga dibangun bersama talent review.
- **P4 — Kamus metrik `docs/METRIK-CBHRM.md`** disepakati sebelum membangun laporan 4–12, minimal: definisi gap kritis (mis. kritikalitas 3 dan gap ≤ −1); perlakuan asesmen kedaluwarsa (dikeluarkan atau ditandai); horizon kesiapan (siap sekarang / 1–2 tahun / ≥ 3 tahun) dan aturan penentuannya; pemetaan Nine Box (band rating final → 3 tingkat kinerja, instrumen potensi → 3 tingkat); rumus cakupan suksesi (% posisi kritis dengan ≥ 1 suksesor siap) dan bench strength (rata-rata suksesor siap per posisi kritis); rumus prioritas TNA (mis. kritikalitas × besar gap × jumlah orang × keterkaitan sasaran strategis); waktu ukur evaluasi tingkat 3 (3–6 bulan) dan tingkat 4 (siklus kinerja berikutnya).
  **Status 2026-11-17 — draf tersedia:** [`docs/METRIK-CBHRM.md`](METRIK-CBHRM.md) memuat definisi yang sudah berlaku di kode (✅) dan usulan (🟡) untuk laporan 1–12, plus 9 keputusan terbuka (K1–K9) dengan rekomendasi. Temuan penting: JFI saat ini menghitung kompetensi tanpa skor berlaku sebagai 0 (hanya ditandai "tidak lengkap"), bertentangan dengan prinsip laporan 2 — diusulkan JFI hanya dihitung bila ≥ 80% bobot terisi (K1). **Disetujui 2026-11-17 (163 tes lulus):** seluruh definisi dan K1–K9 ditetapkan. K1–K3 diterapkan di kode — `JobFitCalculator` (JFI butuh ≥ 80% bobot berskor, band "Data belum cukup", kolom `coverage`, gap kosong untuk kompetensi tanpa skor berlaku, gap kritis = kritikalitas 3 & gap ≤ −1), tampilan asesmen/laporan/Asesmen Saya, Laporan Kompetensi, dan snapshot `DEFINITIONS_VERSION` 2 (metrik `jfi_insufficient`; 12 bulan dibangun ulang). Perintah `hcms:competency-recalculate` (terjadwal Minggu 02.00) menghitung ulang JFI dari skor yang masih berlaku — sekaligus menutup backlog Fase 2 "batch penghitungan ulang"; riwayat hanya bertambah bila hasil berubah. K4–K8 dipakai saat modulnya dibangun; K9 = P5.
- **P5 — Aturan privasi seragam:** penyamaran agregat < 5 orang diterapkan juga ke dashboard executive yang sudah ada (DEIB, demografi), bukan hanya laporan baru; data potensi & talent review memakai izin sensitif tersendiri, dan akses baris individu — bukan hanya ekspor — tercatat di audit.
  **Status 2026-11-17 — selesai (166 tes lulus):** keputusan pemilik produk — penyamaran berlaku **hanya bagi pengguna yang tidak berhak membuka data individual** dimensi itu (izin `is_detail` modul sumber baris); pengguna yang dapat membuka barisnya melihat angka asli. Layanan `App\Support\Privacy\SmallGroups` (scoped per request): kelompok 1–4 orang → "< 5", nilai turunan → "—", nilai grafik disaring di server (tidak terkirim ke HTML), nol tetap 0. Diterapkan di Ringkasan Eksekutif & lima laporan analitik (modul `employees`), Dasbor Kinerja (`perfperiods`: sebaran band, rata-rata unit < 5 pegawai tidak ditampilkan), dan Laporan Kompetensi (`competencyreports`: jumlah band, gap terbesar, sel heatmap). Catatan "melindungi privasi" tampil bila penyamaran aktif. Dengan hak akses bawaan, peran yang membuka dashboard ini memang berhak membuka data individual sehingga penyamaran belum aktif; mekanisme dipakai saat peran agregat-saja dibuat dan wajib di laporan talent/People Analytics. **Belum:** belum ada ekspor agregat (aturan: selalu disamarkan, `masks(force: true)`); izin sensitif & audit akses baris untuk data potensi/talent review dibangun bersama laporan 9.
- **P6 — Ketertelusuran IDP:** IDP dibuat dari `competency_idp_suggestions` dengan rujukan ke saran asalnya agar rantai gap → saran → IDP tidak terputus.

1. **Profil kompetensi jabatan dan organisasi** — tersedia setelah standar jabatan/profil kompetensi dipetakan.
   - Jabatan/posisi, unit, versi kerangka kompetensi, tanggal berlaku, dan pemilik profil.
   - Kompetensi wajib, level yang disyaratkan, bobot/tingkat kritikalitas, serta indikator perilaku.
   - Sumber persyaratan (job atau override posisi), perubahan versi, dan jabatan/posisi yang belum memiliki profil lengkap.
   - Ringkasan cakupan pemetaan: jumlah posisi terpetakan dibanding total posisi aktif.

2. **Profil dan asesmen kompetensi pegawai** — menjadi bukti kondisi aktual sebelum menghitung gap.
   - Identitas kerja minimum, jabatan/posisi aktif, unit, tanggal asesmen, status masa berlaku, dan metode/kelompok penilai.
   - Skor aktual per kompetensi beserta level, bukti perilaku, dan status validasi; rincian penilai individual hanya terlihat bagi pihak berwenang.
   - Riwayat asesmen dan perubahan skor agar kemajuan dapat dibandingkan antarperiode.
   - Kompetensi tanpa hasil asesmen atau dengan hasil kedaluwarsa harus ditandai, bukan dianggap bernilai nol.

3. **Competency Gap Analysis** — membandingkan hasil aktual dengan persyaratan jabatan.
   - Level aktual, level yang disyaratkan, selisih gap, bobot/criticality, dan status terpenuhi/belum terpenuhi per kompetensi.
   - Ringkasan per pegawai, jabatan, unit, dan organisasi: jumlah gap, gap kritis, serta kompetensi dengan gap terbanyak/terbesar.
   - JFI/band kesiapan dengan rumus, ambang, versi profil, dan tanggal asesmen yang digunakan.
   - Penanda data tidak lengkap/kedaluwarsa, filter jabatan/unit/periode/band, serta drill-down ke profil pegawai sesuai izin.

4. **Position Readiness** — membandingkan kandidat internal dengan posisi sasaran; jangan menyamakan kesiapan dengan keputusan promosi.
   - Posisi sasaran dan persyaratan kompetensi versus aktual setiap kandidat; JFI/band dan tanggal/sumber data.
   - Kompetensi wajib yang sudah terpenuhi, gap kritis, asesmen kedaluwarsa, serta estimasi/tingkat kesiapan yang definisinya dapat dijelaskan.
   - Daftar kandidat terurut dengan filter unit, posisi sasaran, dan band; akses kandidat mengikuti data scope dan izin sensitif.
   - Peringatan bahwa hasil adalah bahan pertimbangan, bukan rekomendasi otomatis atau keputusan penempatan.

5. **Training Need Analysis (TNA)** — mengubah kebutuhan yang tervalidasi menjadi prioritas pengembangan.
   - Kebutuhan menurut pegawai, kompetensi, jabatan/unit, periode, sumber (gap, kinerja/PIP, prioritas organisasi, atau permintaan), alasan, dan bukti.
   - Prioritas/urgensi, dampak bila tidak ditangani, jumlah orang terdampak, serta status usulan → ditinjau → disetujui/ditolak → direncanakan.
   - Intervensi yang disarankan, kompetensi/level sasaran, perkiraan biaya, tenggat, dan penanggung jawab.
   - Rekap kebutuhan belum ditangani, kebutuhan berulang, distribusi prioritas, dan kemampuan anggaran; pisahkan saran otomatis dari kebutuhan yang disetujui.

6. **Rencana Pengembangan Individu (IDP) dan pelaksanaan** — menelusuri tindak lanjut TNA/gap pada tingkat individu.
   - Sasaran kompetensi/karier, level saat ini dan target, hubungan ke gap/TNA, aktivitas pengembangan, pemilik, tenggat, dan persetujuan.
   - Status tiap aktivitas (direncanakan, berjalan, selesai, tertunda), bukti, sertifikasi, biaya, dan catatan pegawai/atasan.
   - Kemajuan terhadap target, aktivitas lewat tenggat, serta gap yang belum tertangani setelah aktivitas selesai.
   - Riwayat rencana dan perubahan untuk evaluasi lintas periode.

7. **Pelatihan dan efektivitas pengembangan** — mengukur pelaksanaan serta dampaknya, bukan hanya jumlah kehadiran.
   - Katalog/intervensi, peserta sasaran dan aktual, kehadiran/penyelesaian, penyedia, jadwal, biaya, dan kompetensi sasaran.
   - Evaluasi empat tingkat: reaksi, pembelajaran, penerapan perilaku/kompetensi, dan dampak pada kinerja/organisasi; sertakan metode, waktu ukur, respons, dan baseline pembanding.
   - Perubahan skor kompetensi atau indikator kinerja sebelum/sesudah dengan konteks periode dan keterbatasan atribusi.
   - Tingkat penyelesaian, biaya per peserta, hasil evaluasi, dan rekomendasi lanjut/perbaiki/hentikan program.

8. **Performance Management** — memperlihatkan sasaran, proses, hasil, dan tindak lanjut kinerja.
   - Periode dan tahap siklus, kebijakan/versi skala, sasaran/KPI dan bobot, alignment organisasi, target, realisasi, serta bukti.
   - Status rencana, check-in, penilaian diri/atasan/review, What/How/Kontribusi, hasil kalibrasi, rating final/provisional, dan alasan penyesuaian sesuai izin.
   - Distribusi rating per unit/jabatan/periode, tren, progres tahap, karyawan not rated, keberatan, serta status PIP.
   - Tampilkan rating final terpisah dari proyeksi atau rating sementara; ekspor harus mengikuti izin, alasan, watermark, dan audit.

9. **Talent Review dan Nine Box** — menyatukan kinerja final dengan asesmen potensi yang tervalidasi.
   - Periode/siklus, definisi sumbu dan ambang Nine Box, rating kinerja final yang dirujuk, hasil potensi, tanggal, assessor, bukti, dan tingkat keyakinan.
   - Posisi pegawai dalam kotak, catatan/justifikasi panel, kalibrasi, keputusan, serta riwayat perpindahan antar-siklus.
   - Tindak lanjut pengembangan, penanggung jawab, tanggal tinjau ulang, dan status validasi data.
   - Ringkasan distribusi per unit/kelompok jabatan dan cakupan review; jangan tampilkan potensi individu tanpa izin khusus.

10. **Talent Pool** — memantau kelompok talenta dan kesiapan pengembangannya.
    - Nama/tujuan pool, kriteria masuk/keluar, periode, nominasi, status persetujuan, tanggal tinjau ulang, dan pemilik pool.
    - Anggota, sumber keputusan (talent review), posisi/keluarga jabatan sasaran, tingkat kesiapan, minat karier, dan kompetensi/gap utama.
    - IDP/intervensi, kemajuan, risiko kehilangan/retensi bila dikelola kebijakan, serta riwayat perubahan status.
    - Rekap komposisi, kesiapan, gap, pergerakan, dan cakupan; keanggotaan bukan jaminan promosi dan akses baris individu dibatasi.

11. **Succession dan bench strength** — menguji cakupan kandidat untuk posisi kritis.
    - Posisi kritis, tingkat dampak, incumbent, risiko kekosongan, dan status rencana suksesi.
    - Kandidat suksesor utama/cadangan, kesiapan dan horizon waktunya, sumber (termasuk pool/readiness), persetujuan, dan tanggal pembaruan.
    - Gap kompetensi, tindakan pengembangan, dependensi kandidat pada beberapa posisi, dan posisi tanpa kandidat siap.
    - Cakupan suksesi, bench strength, posisi berisiko, dan tindak lanjut; keputusan suksesi tetap memerlukan persetujuan berwenang.

12. **People Analytics CBHRM lintas modul** — ringkasan eksekutif untuk melihat hubungan dan tren tanpa menggantikan laporan rinci.
    - Indikator cakupan profil/asesmen, gap kritis, kebutuhan TNA terbuka, kemajuan IDP, efektivitas pelatihan, hasil kinerja, talent pool, dan cakupan suksesi.
    - Tren antarperiode dan per unit/kelompok jabatan dengan definisi, sumber, waktu snapshot, serta aturan pengecualian yang konsisten.
    - Drill-through ke laporan sumber hanya bagi pengguna yang berhak; sembunyikan agregat kurang dari lima orang dan catat akses/ekspor.
    - Hindari menyimpulkan sebab-akibat hanya dari korelasi antara pelatihan, kompetensi, rating, dan promosi.

Catatan cakupan: rekrutmen/seleksi berbasis kompetensi dan kaitan kompetensi dengan remunerasi berada di Rilis 3; keduanya memakai ulang profil kompetensi jabatan (laporan 1).

Fitur AI rekomendasi (draf IDP, saran pelatihan, ringkasan check-in) memakai `RagService`/Gemini dengan pseudonimisasi dan log versi model + prompt, sebagai bantuan penyusunan rekomendasi yang tetap ditinjau manusia.

### Rilis 3 — P2 (+20–26 minggu)
Recruitment & Onboarding → Attendance & Leave (integrasi mesin absensi via API, cuti, lembur, shift) → Compensation Planning & payroll API (outbound/inbound, idempotency, retry, rekonsiliasi batch) → Workforce Planning → integrasi ERP.

---

## D. Pemetaan tabel master → domain blueprint

| Tabel master | Domain blueprint | Tindakan |
|---|---|---|
| `tb_users` | users | Pakai; tambah `tenant_id`, kolom MFA |
| `tb_groups`, `tb_users_groups` | roles, user_roles | Pakai; seed 15 role |
| `tb_groups_access` | role_permissions | Pakai; tambah kunci approve/export/sensitive |
| `corporate` | companies | Pakai; tambah `tenant_id`, `settings` |
| `unit` | organization_units | Perluas; tambah closure + effective dating |
| `unit_type` | org_unit_types | Pakai |
| `unit_category` | (kategori unit tambahan) | Pakai bila masih relevan |
| `user_unit` | user_data_scopes / unit login | Pakai untuk switch unit; assignment HR di tabel baru |
| `tb_logs` | access_logs | Pakai; audit_logs baru untuk perubahan data |
| `tb_notification` | notifications | Pakai; tambah `tenant_id` |
| `tb_module`, `tb_menu`, `tb_forms` | — (infrastruktur Sximo) | Tetap |
| tabel AI chat & RAG | AI service | Pakai ulang untuk fitur rekomendasi (P1) |

---

## E. Keputusan yang perlu diambil sebelum Fase 1

1. ~~MySQL atau PostgreSQL + RLS?~~ Diputuskan: **MySQL**, isolasi tenant di aplikasi.
2. ~~Blade + Vue island atau Inertia penuh?~~ Diputuskan: Blade + jQuery bawaan master, tanpa Vue, ditambah Alpine.js untuk layar rumit.
3. ~~Model skor default?~~ Diputuskan: **60/30/10** (lihat ADR 0001 no. 8).
4. Pasar awal: swasta saja atau juga BUMN/pemerintah?
5. Region data dan penyedia LLM (Gemini sudah terpasang — perlu penilaian transfer data ke luar negeri sesuai PDP).
6. Usia pensiun dan aturan cuti default per company. *(Sementara: usia pensiun dibaca dari pengaturan tenant `talent.retirement_age`, bawaan 59 tahun, untuk risiko kekosongan suksesi; aturan per company & cuti belum.)*
