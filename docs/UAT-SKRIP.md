# Skrip UAT — MVP HCMS (Fase 4 gelombang 3)

**Tujuan:** pengguna bisnis memastikan setiap kriteria MVP (`MVP-ACCEPTANCE.md`) berjalan pada data dan proses mereka sendiri, sebelum pilot satu company.

**Lingkungan:** staging dengan konfigurasi produksi (`config:cache`, OPcache, antrean berjalan, zona waktu WIB). Data: hasil dry run migrasi ke-2 (`RUNBOOK-PILOT.md`) **atau** tenant UAT yang diisi `DemoDataSeeder`. **Jangan** memakai data produksi asli tanpa persetujuan DPO.

**Peserta & akun:**

| Kode | Peran | Akun UAT |
|---|---|---|
| SA | Super Admin (platform) | 1 akun |
| HR | HR Administrator tenant | 2 akun (untuk uji SoD) |
| EX | Executive | 1 akun |
| DH | Department Head (cakupan unit) | 1 akun per unit uji |
| LM | Atasan langsung (pegawai yang memiliki bawahan) | 2 akun |
| EM | Pegawai | ≥ 3 akun |
| AU | Auditor / DPO | 1 akun |

Setiap peserta login dengan MFA sesuai kebijakan peran.

**Pencatatan hasil:**
- Isi kolom **Hasil** dengan ✅ (sesuai harapan), ❌ (tidak sesuai), atau ⚠️ (sesuai tetapi ada catatan).
- Setiap ❌/⚠️ dicatat sebagai temuan (nomor, langkah, tangkapan layar, waktu).
- Temuan **kritis** menghentikan skenario terkait. Ulangi skenario setelah perbaikan.
- **Kriteria lulus UAT:** semua skenario wajib (W) ✅; skenario opsional (O) boleh ⚠️ dengan rencana tindak lanjut.

---

## A. Tenant, organisasi, dan pegawai

| ID | MVP | Peran | Langkah | Hasil yang diharapkan | W/O | Hasil |
|---|---|---|---|---|---|---|
| A1 | 1 | SA | Control Panel › Tenant › Tambah: isi kode, nama, company pertama, admin tenant. | Tenant aktif; admin tenant menerima akun; company tercipta. | W | |
| A2 | 19 | HR (tenant A), HR (tenant B) | Masing-masing buka Pegawai, Posisi, Audit Trail. | Tidak ada data tenant lain yang terlihat di daftar, pencarian, maupun ekspor. | W | |
| A3 | 2 | HR | Organisasi › Unit Organisasi: buat 3 tingkat unit; coba jadikan unit induk sebagai anak dari bawahannya. | Struktur tersimpan; siklus ditolak dengan pesan jelas. | W | |
| A4 | 2 | HR | Organisasi › Job Family, Job Level, Job Grade, Job: buat job dengan rentang grade dan persyaratan. | Data tersimpan; grade di luar rentang job ditolak saat membuat posisi. | W | |
| A5 | 2 | HR | Organisasi › Posisi: buat posisi dengan atasan solid line dan dotted line; tandai satu posisi **kritis** dengan tingkat dampak dan alasan. | Reporting line tersimpan; posisi kritis tanpa dampak/alasan ditolak; daftar menampilkan "Ya — Sangat tinggi". | W | |
| A6 | 3 | HR | Kepegawaian › Pegawai: buat pegawai, isi data riwayat, tambah penugasan primer; tambah penugasan rangkap dan Plt. | Pegawai tersimpan; penugasan ganda dan Plt. tampil di tab Penugasan. | W | |
| A7 | 4 | HR | Mutasikan pegawai A6 ke posisi lain berlaku tanggal tertentu. | Tab Penugasan/Riwayat menampilkan penempatan lama dengan tanggal berakhir dan penempatan baru. | W | |
| A8 | 20 | AU | Control Panel › Audit Trail: cari perubahan dari A5–A7. | Setiap perubahan tercatat: siapa, kapan, nilai sebelum/sesudah. Identitas sensitif tidak tampil polos. | W | |
| A9 | 21 | HR | Ekspor daftar pegawai tanpa alasan, lalu dengan alasan. | Tanpa alasan ditolak; dengan alasan berkas berisi watermark dan tercatat di log ekspor; identitas tidak ikut. | W | |
| A10 | — | HR | Control Panel › Migrasi Data: unggah CSV pegawai dengan 1 baris salah, validasi, perbaiki, commit. | Error ditunjukkan per nomor baris; commit hanya bila semua baris valid; rekonsiliasi menampilkan jumlah sebelum/sesudah. | W | |

## B. Layanan Mandiri dan alur persetujuan

| ID | MVP | Peran | Langkah | Hasil yang diharapkan | W/O | Hasil |
|---|---|---|---|---|---|---|
| B1 | 18 | EM → HR | Layanan Mandiri › Profil Saya: ubah nomor telepon & alamat, kirim. HR menyetujui di Tugas Persetujuan. | Pengajuan menunggu; setelah disetujui data pegawai berubah dan tercatat di audit. | W | |
| B2 | 18 | EM → HR | Ajukan perubahan lain; HR **menolak** dengan catatan. | Data tidak berubah; pegawai melihat status ditolak beserta catatan. | W | |
| B3 | 18 | LM | Layanan Mandiri › Delegasi: delegasikan persetujuan ke rekan selama 3 hari. | Tugas baru masuk ke penerima delegasi selama periode delegasi. | W | |
| B4 | 18 | HR | Kosongkan posisi atasan langsung; ajukan rencana kinerja dari bawahannya. | Tugas naik ke atasan berikutnya (eskalasi posisi kosong). | W | |
| B5 | — | EM | Profil Saya › Unduh data saya. | Berkas JSON berisi data pribadi, riwayat, kompetensi, kinerja, aspirasi karier; tercatat di log ekspor. | W | |
| B6 | — | EM | Layanan Mandiri › Aspirasi Karier Saya: isi lalu perbarui. | Riwayat menampilkan 2 entri, yang terbaru bertanda "Terkini"; HR melihatnya di tab Aspirasi karier profil pegawai. | O | |

## C. Kompetensi

| ID | MVP | Peran | Langkah | Hasil yang diharapkan | W/O | Hasil |
|---|---|---|---|---|---|---|
| C1 | 5 | HR (edit) → HR (approve) | Kompetensi › Kamus Kompetensi: buat draft, tambah kompetensi dengan level 1–5 dan indikator, terbitkan. | Draft yang belum lengkap tidak dapat diterbitkan; setelah terbit versi berstatus Berlaku dan tidak dapat diubah. | W | |
| C2 | 6 | HR | Profil Kompetensi Job: atur level wajib dan kritikalitas untuk job pilot; override satu posisi. | Posisi mewarisi profil job; override tampil sebagai sumber "override". Tabel Cakupan pemetaan menandai job family pilot **Terpetakan penuh**. | W | |
| C3 | 7 | HR → LM → EM | Asesmen Kompetensi: buka asesmen untuk satu pegawai; atasan dan pegawai mengisi di Asesmen Saya; HR menutup. | JFI dan band tampil; gap per kompetensi dengan label "gap kritis" untuk kritikalitas 3 & gap ≤ −1; draf IDP untuk gap kritis. | W | |
| C4 | 7 | HR | Laporan Kompetensi: lihat sebaran kesiapan, gap terbesar, heatmap; buka profil individu. | Angka sesuai hasil C3; profil individu menampilkan perubahan skor dan riwayat JFI. | W | |
| C5 | — | HR | Kompetensi › Kesiapan Posisi: pilih posisi kritis dari A5. | Kandidat berurutan Siap sekarang → Siap 1–2 tahun → Siap ≥ 3 tahun; peringatan "bukan keputusan penempatan" tampil; akses tercatat di audit. | W | |

## D. Kinerja (satu siklus penuh)

| ID | MVP | Peran | Langkah | Hasil yang diharapkan | W/O | Hasil |
|---|---|---|---|---|---|---|
| D1 | 8 | HR | Kinerja › Kebijakan Kinerja: terbitkan kebijakan. Periode & Sasaran: buat periode lalu buka. | Periode mengunci salinan kebijakan; perubahan kebijakan sesudahnya tidak memengaruhi periode. | W | |
| D2 | 9 | HR → LM → EM | Buat sasaran strategis; atasan menyusun KPI dan disetujui; pegawai cascade dari KPI atasan. | Total bobot 100%, 3–10 KPI, 5–40% per KPI divalidasi; rencana dikembalikan/ disetujui lewat Tugas Persetujuan. | W | |
| D3 | 10, 11 | EM | Monitoring: isi realisasi termasuk **realisasi 0** pada KPI lower; unggah bukti. | Capaian dihitung tanpa error; bukti hanya bisa diunduh pemilik/atasan/HR dan unduhan tercatat. | W | |
| D4 | — | EM → LM | Mid-year review: pegawai mengisi, atasan menyelesaikan. | Periode baru bisa masuk tahap Penilaian setelah semua mid-year selesai. | W | |
| D5 | 12 | EM → LM → atasan LM | Penilaian diri, penilaian atasan (How & Kontribusi), review atasan dari atasan. | Skor 60/30/10 dihitung otomatis dan sama dengan perhitungan manual tim HR. | W | |
| D6 | 13 | HR | Pegawai yang mutasi di tengah periode (dari A7). | Skor prorata sesuai lama penugasan; masa < 90 hari = not rated. | W | |
| D7 | 14 | HR | Kalibrasi: pindahkan satu pegawai antar-band tanpa alasan, lalu dengan alasan. | Tanpa alasan ditolak; dengan alasan tercatat nilai sebelum/sesudah. | W | |
| D8 | 15 | HR → EM → atasan LM | Umumkan rating sementara; pegawai mengajukan keberatan; diputus atasan dari atasan. Penilai mencoba memutus. | Penilai dan pegawai sendiri tidak dapat memutus (SoD); keberatan dikabulkan = revisi rating. | W | |
| D9 | 16 | HR | Sahkan rating final setelah jendela keberatan. | Rating final dan snapshot tersimpan; snapshot tidak dapat diubah. | W | |
| D10 | 17 | HR, DH, LM | Buka Dasbor Kinerja. | HR melihat seluruh unit; Department Head hanya unitnya; angka sama dengan rekap periode. | W | |
| D11 | — | LM | Rating Below: buat PIP, selesaikan atau perpanjang. | PIP tercatat dan terlihat oleh pegawai di Rencana Perbaikan (PIP). | O | |

## E. Dashboard executive dan privasi

| ID | MVP | Peran | Langkah | Hasil yang diharapkan | W/O | Hasil |
|---|---|---|---|---|---|---|
| E1 | — | EX, HR | Kepegawaian › Ringkasan Eksekutif dan lima laporan analitik. | Angka headcount, pegawai baru, keluar, dan turnover sama dengan data sumber (cocokkan dengan ekspor pegawai). | W | |
| E2 | — | DH | Buka Ringkasan Eksekutif. | Hanya unit dalam cakupan; catatan cakupan tampil. | W | |
| E3 | — | AU | Buat peran uji tanpa izin detail modul Pegawai, beri akses Ringkasan Eksekutif. | Kelompok 1–4 orang tampil "< 5" dan catatan privasi muncul. | O | |
| E4 | — | AU | Audit Trail: filter akses data sensitif (profil kompetensi, kesiapan posisi, unduh data saya). | Setiap akses tercatat dengan pengguna dan waktu. | W | |

## F. Non-fungsional

| ID | Butir | Langkah | Hasil yang diharapkan | W/O | Hasil |
|---|---|---|---|---|---|
| F1 | Kinerja halaman | Buka 10 halaman utama dengan data pilot. | Setiap halaman < 1 detik (lihat juga uji k6 di staging). | W | |
| F2 | Sesi & MFA | Login peran wajib MFA; biarkan sesi menganggur melewati batas. | MFA diminta; sesi berakhir dan meminta login ulang. | W | |
| F3 | Peramban | Ulangi B1, C3, D3 di Chrome, Edge, dan ponsel. | Tampilan dan fungsi tidak rusak. | O | |
| F4 | Mode gelap | Ulangi E1 di mode gelap. | Grafik dan teks terbaca. | O | |

---

**Tanda tangan penerimaan**

| Peran | Nama | Tanggal | Keputusan (Diterima / Diterima bersyarat / Ditolak) |
|---|---|---|---|
| Pemilik produk HR | | | |
| Perwakilan pengguna atasan | | | |
| TI / keamanan | | | |
| DPO | | | |
