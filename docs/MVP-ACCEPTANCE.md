# Matriks Penerimaan MVP (Blueprint §22)

Status per 2026-11-18 — Fase 4 gelombang 3. Bukti utama adalah tes otomatis (`php vendor/bin/phpunit`, 169 tes lulus) yang berjalan di CI pada setiap push, ditambah uji manual di browser pada tenant DEMO. Skrip UAT pengguna bisnis: `UAT-SKRIP.md`.

Legenda: ✅ terpenuhi & teruji otomatis · 🟡 terpenuhi, bukti sebagian/manual · ⏳ belum.

| # | Kriteria MVP | Status | Bukti (tes otomatis / layar) |
|---|---|---|---|
| 1 | Administrator dapat membuat tenant dan company | ✅ | `TenantHttpTest::test_superadmin_membuat_tenant_beserta_company_dan_admin`; Control Panel › Tenant |
| 2 | Struktur organisasi bertingkat dan job architecture dapat dikelola | ✅ | `IamTest::test_closure_table_berisi_seluruh_bawahan`, `JobArchitectureTest` (unit tanpa siklus, job family/level/grade/job, posisi, reporting line solid/dotted) |
| 3 | Master pegawai tersedia, termasuk assignment ganda dan Plt. | ✅ | `EmployeeTest::test_penugasan_primer_mutasi_rangkap_dan_validasi`, `WorkflowTest::test_approver_berdasarkan_posisi_kosong_naik_dan_plt` |
| 4 | Riwayat penempatan dapat ditelusuri dengan effective date | ✅ | `EmployeeTest::test_data_riwayat_dan_riwayat_perubahan`, `JobArchitectureTest::test_reporting_line_solid_dotted_siklus_dan_riwayat`, `test_riwayat_unit_tercatat_saat_disimpan` |
| 5 | Kamus kompetensi berversi tersedia | ✅ | `CompetencyFrameworkTest::test_draft_lengkap_baru_bisa_terbit`, `test_versi_terbit_hanya_baca_dan_draft_baru_menyalin_isinya`, `test_terbit_versi_baru_memindahkan_profil_berdasarkan_kode` |
| 6 | Profil kompetensi job tersedia dan diwarisi posisi | ✅ | `CompetencyFrameworkTest::test_profil_job_validasi_dan_audit`, `test_override_posisi_kecualikan_ubah_dan_tambah` |
| 7 | Profil kompetensi individu dan Job Fit Index tersedia | ✅ | `CompetencyAssessmentTest::test_asesmen_atasan_menghasilkan_jfi_contoh_blueprint_dan_draf_idp` (JFI 71,3% sesuai contoh blueprint), `JobFitCalculatorTest` |
| 8 | HR dapat membuat periode penilaian dan mengunci versi kebijakan | ✅ | `PerformancePlanningTest::test_kebijakan_berversi_dan_periode_mengunci_kebijakan` |
| 9 | KPI dapat dikelola dan di-cascade ke unit dan individu | ✅ | `PerformancePlanningTest::test_cascading_sasaran_strategis_dan_kpi_atasan`, `test_rencana_kpi_diajukan_dikembalikan_lalu_disetujui_atasan` |
| 10 | Pegawai dapat memasukkan realisasi dan mengunggah bukti | ✅ | `PerformanceMonitoringTest::test_realisasi_capaian_dan_bukti_privat` (bukti privat per tenant, unduhan tercatat) |
| 11 | Normalisasi KPI (higher, lower, range, milestone) dan cap teruji, termasuk realisasi = 0 | ✅ | `ScoreCalculatorTest::test_higher_dan_lower_dengan_cap` (realisasi 0 & target 0), `test_range_milestone_dan_kualitatif` |
| 12 | Atasan dapat melakukan penilaian; sistem menghitung skor otomatis | ✅ | `PerformanceCycleTest::test_siklus_penuh_penilaian_kalibrasi_pengesahan_keberatan` (60/30/10 → 92,7 lalu 91,7) |
| 13 | Skor pegawai yang mutasi di tengah periode dihitung prorata dengan benar | ✅ | `PerformanceCycleTest::test_mutasi_tengah_periode_dihitung_prorata_dan_masa_kurang_not_rated` (181/184 hari → 85,04; masa 61 hari → not rated), `ScoreCalculatorTest::test_prorata_menggabung_penugasan_pendek` |
| 14 | HR dapat melakukan kalibrasi dengan alasan dan jejak sebelum/sesudah | ✅ | `PerformanceCycleTest` (alasan wajib, `calibration_adjustments` from/to band & skor); papan drag & drop diuji di browser |
| 15 | Keberatan dapat diajukan dan diputus **sebelum** pengesahan | ✅ | Alur: umumkan rating sementara → jendela keberatan → putusan (pejabat di atas penilai, SoD) → pengesahan final. `PerformanceCycleTest` menolak pengesahan selama jendela terbuka / keberatan belum diputus, dan menolak keberatan setelah final |
| 16 | Hasil dapat disahkan; snapshot historis immutable | ✅ | `PerformanceCycleTest` (snapshot dibuat saat final, SHA-256 cocok, UPDATE ditolak trigger) |
| 17 | Dashboard kinerja menampilkan capaian sesuai data scope | ✅ | `PerformanceDashboardScopeTest::test_hr_melihat_semua_kepala_divisi_hanya_unitnya` (HR 4 pegawai/rata-rata 71,75; Kepala Divisi Kredit hanya 2 pegawai unitnya/rata-rata 85,0, unit lain tidak tampil; pegawai ditolak), `PerformanceCycleTest` (dasbor sementara & final) |
| 18 | Workflow approval tercatat, termasuk eskalasi saat posisi atasan kosong dan delegasi | ✅ | `WorkflowTest::test_approver_berdasarkan_posisi_kosong_naik_dan_plt`, `test_delegasi_mengalihkan_tugas`, `test_eskalasi_sla_ke_atasan_approver`, `test_pengaju_tidak_pernah_menjadi_approver_sendiri` |
| 19 | Uji otomatis membuktikan query tanpa tenant context tidak mengembalikan data (pengganti RLS) | ✅ | `TenantContextTest::test_kosong_secara_default_dan_require_gagal`, `TenantIsolationTest::test_tanpa_tenant_context_akses_data_gagal_keras`, `RequestStateIsolationTest` (state antar-request ala Octane) |
| 20 | Audit trail tersedia | ✅ | `AuditTrailTest::test_audit_logs_append_only_di_database`, `test_jalur_sximo_mencatat_created_updated_deleted_dengan_diff`, `test_kolom_rahasia_tidak_pernah_tersimpan` |
| 21 | Ekspor data sesuai kewenangan dan tercatat | ✅ | `AuditTrailTest::test_ekspor_wajib_alasan_watermark_dan_tercatat`, `test_ekspor_massal_butuh_izin_approve`, `EmployeeTest::test_ekspor_pegawai_tanpa_identitas_dan_dengan_watermark`, ekspor rating di `PerformanceCycleTest` |

## Butir Fase 4 lainnya

| Butir | Status | Catatan |
|---|---|---|
| Uji isolasi tenant & SoD | ✅ | Tes isolasi di setiap modul; SoD: pengaju ≠ approver, penilai/pegawai tidak memutus keberatan, tidak bisa mengubah cakupan data sendiri |
| `composer audit` / `npm audit` | ✅ | 2026-11-17: tidak ada kerentanan (juga dijalankan di CI) |
| Review konfigurasi Octane (state bocor antar-request) | ✅ | Cache statis hanya metadata skema & kode peran global; `TenantContext` di-bind `scoped` dan dibersihkan middleware saat terminate; hak akses modul tidak di-cache per instance controller. Setelah deploy migrasi jalankan `php artisan octane:reload` |
| Hak subjek data (akses & koreksi) | ✅ | Akses: Profil Saya › Unduh data saya (JSON, tercatat) — `DataSubjectAccessTest`. Koreksi: ESS change request — `WorkflowTest::test_ess_disetujui_hr_dan_perubahan_diterapkan_dengan_jejak` |
| Skrip UAT, materi pelatihan, runbook dry run ×2 & pilot | ✅ | `UAT-SKRIP.md` (skenario per kriteria MVP, peran, hasil, tanda tangan), `PANDUAN-PENGGUNA.md` (6 sesi per peran), `RUNBOOK-PILOT.md` (dry run, rekonsiliasi, go/no-go, rollback, stabilisasi). Pelaksanaannya oleh tim HR/TI di staging |
| Pentest independen | ⏳ | Diputuskan 2026-11-17: dilakukan pihak ketiga setelah seluruh pengembangan Fase 4 selesai, sebelum pilot |
| Uji beban 10.000 pegawai / 1.000 pengguna, p95 < 1 detik | 🟡 | Latensi server: 20/20 halaman p95 < 1 dtk pada 10.000 pegawai (`docs/LOAD-TEST.md`). 1.000 pengguna bersamaan: skrip k6 siap, dijalankan di staging (Octane + OPcache) |
| 2× dry run migrasi + rekonsiliasi | ⏳ | `hcms:import` siap (validasi kering, rekonsiliasi); dijalankan dengan data klien pilot |
| UAT, pelatihan, pilot satu company, stabilisasi | ⏳ | Gelombang 3 (skrip UAT & materi pelatihan) |

## Catatan

- Zona waktu: diputuskan 2026-11-17 `Asia/Jakarta` (`APP_TIMEZONE`, sesi database `DB_TIMEZONE=+07:00`). Data dev yang dibuat sebelum perubahan tersimpan dalam jam UTC (tertinggal 7 jam); tidak ada data produksi yang terdampak.
- Rotasi kunci (diputuskan 2026-11-17): Google Maps (`MAPS_API_KEY`), Gemini (`AI_API_KEY`), dan reCAPTCHA (`RECAPTCHA_PRIVATE_KEY`) dirotasi oleh pemilik akun di konsol Google; nilai baru hanya disimpan di `.env` (atau lewat Control Panel › Config) dan kunci lama dinonaktifkan.
