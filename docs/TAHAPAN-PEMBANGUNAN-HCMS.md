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
- Kunci kebijakan penilaian: bobot skor 60/30/10 (sudah diputuskan; tinggal definisi dimensi ketiga), cap, band rating, tabel konversi, aturan prorata.
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

**Gerbang keluar:** uji isolasi tenant lulus di semua modul Fase 1.

### Fase 2 — Competency Management (minggu 15–22)

1. Kamus berversi: `competency_framework_versions`, `competency_categories` (Core, Leadership, Functional, Digital), `competencies`, `competency_levels` (1–5), `competency_indicators`.
2. Profil kompetensi job: `job_competencies` (level wajib + bobot kritikalitas 3/2/1), override di `position_competencies`; posisi mewarisi dari job.
3. Asesmen multi-penilai: `competency_assessments`, `assessment_raters`, `assessment_rater_weights` (default 50/20/20/10), `competency_assessment_details`; sembunyikan kelompok peer/subordinate bila < 3 penilai; masa berlaku 24 bulan; hasil assessment center menggantikan metode lain.
4. Kalkulasi (service + job batch): `employee_competencies`, `competency_gaps` (Actual − Required), `job_fit_results` (JFI = Σ min(A/R,1)·w ÷ Σw, band ≥90 / 75–89 / <75).
5. Output: Competency Gap Report (individu/unit/job), Position Readiness, Organizational Competency Heatmap (ECharts heatmap), draf item IDP untuk gap negatif kompetensi kritis.
6. Tes unit rumus JFI memakai contoh blueprint 7.4 (hasil wajib 71,3%).

**Gerbang keluar:** satu job family pilot terpetakan penuh.

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

### Fase 4 — Testing & Pilot (minggu 33–40)
- Uji fungsional per kriteria MVP (blueprint bagian 22), uji isolasi tenant, uji SoD.
- Pentest independen, `composer audit`, review konfigurasi Octane (state bocor antar-request).
- Uji beban 10.000 pegawai / 1.000 pengguna bersamaan, p95 < 1 detik — perhatikan query `getRows` Sximo (index `tenant_id`, `company_id`, `employee_id`, kolom effective date).
- 2× dry run migrasi data + rekonsiliasi, UAT, pelatihan pengguna, pilot satu company, stabilisasi.
- Alur hak subjek data minimal (akses & koreksi oleh pegawai).

**Gerbang keluar:** seluruh kriteria MVP terpenuhi.

### Rilis 2 — P1 (+24–30 minggu)
Urutan disarankan: Offboarding & ER lengkap → Learning & Development (TNA, IDP, katalog, sertifikasi, evaluasi 4 tingkat) → Talent (potential assessment, talent review, Nine Box, talent pool) → Career (career path, promosi, mutasi) → Succession (posisi kritis, suksesor, bench strength) → People Analytics (tabel `analytics_*` snapshot bulanan, dashboard per portal, sembunyikan agregat < 5 orang) → Platform & Subscription (paket, kuota, tagihan) → fitur AI rekomendasi (draf IDP, saran pelatihan, ringkasan check-in) memakai `RagService`/Gemini dengan pseudonimisasi dan log versi model + prompt.

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
6. Usia pensiun dan aturan cuti default per company.
