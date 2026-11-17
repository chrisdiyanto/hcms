# Panduan Pengguna HCMS — Materi Pelatihan Pilot

Materi pelatihan Fase 4 gelombang 3, disusun per peran. Setiap sesi berisi **tujuan**, **menu yang dipakai**, dan **langkah praktik**. Latihan memakai tenant UAT; skenario uji lengkap ada di `UAT-SKRIP.md`.

| Sesi | Peserta | Durasi |
|---|---|---|
| 1. Dasar & Layanan Mandiri | Semua pegawai | 45 menit |
| 2. Atasan: persetujuan, kinerja tim, asesmen | Atasan langsung, Department Head | 90 menit |
| 3. HR: organisasi, pegawai, migrasi data | HR Administrator | 2 × 2 jam |
| 4. HR: kompetensi & kinerja satu siklus | HR Administrator, HRBP | 2 × 2 jam |
| 5. Eksekutif & analitik | Executive, Department Head, HRBP | 45 menit |
| 6. Audit & privasi | Auditor, DPO, Tenant Admin | 60 menit |

---

## Sesi 1 — Dasar & Layanan Mandiri (semua pegawai)

**Masuk dan keamanan**
- Login dengan akun yang diberikan HR. Peran tertentu wajib MFA: pindai kode QR dengan aplikasi autentikator saat pertama login.
- Sesi berakhir setelah 2 jam tanpa aktivitas. Jangan membagikan akun.

**Layanan Mandiri › Profil Saya**
- Lihat data pribadi dan riwayat. Untuk mengubah telepon, email, alamat, status perkawinan/PTKP: ubah kolom, isi alasan, kirim. Perubahan berlaku setelah diverifikasi HR.
- **Unduh data saya**: salinan seluruh data pribadi Anda (hak subjek data UU PDP). Setiap unduhan tercatat.

**Layanan Mandiri › Rencana Kinerja Saya**
- Saat periode dibuka: susun 3–10 KPI, bobot 5–40% per KPI, total 100%. KPI dapat diturunkan dari KPI atasan. Kirim ke atasan; bila dikembalikan, perbaiki sesuai catatan.
- Selama periode: catat realisasi per KPI dan unggah bukti (berkas privat). Ikuti check-in dan **mid-year review** (wajib).

**Layanan Mandiri › Penilaian Kinerja, Rating Kinerja Saya, Keberatan Rating, Rencana Perbaikan (PIP)**
- Isi penilaian diri saat tahap Penilaian (informatif bagi atasan).
- Rating diumumkan **sementara** lebih dulu. Bila tidak setuju, ajukan keberatan **satu kali** dalam jendela keberatan (bawaan 14 hari) dengan alasan jelas. Keberatan diputus atasan dari atasan atau HR, bukan penilai Anda.

**Layanan Mandiri › Asesmen Saya**
- Bila diminta menilai (diri sendiri, rekan, bawahan): beri skor 1–5 per kompetensi dengan bukti perilaku. Penilaian rekan/bawahan bersifat anonim (hanya rata-rata kelompok ≥ 3 penilai yang dipakai).

**Layanan Mandiri › Aspirasi Karier Saya**
- Isi rumpun/jabatan yang diminati, jangka waktu, kesediaan pindah lokasi, dan kebutuhan pengembangan. Ini bahan pembicaraan karier dengan atasan, bukan pengajuan mutasi.

**Layanan Mandiri › Usulan Pelatihan**
- Usulkan kebutuhan pelatihan untuk diri sendiri (atau bawahan langsung bila Anda atasan): kompetensi atau topik, alasan, urgensi. HR meninjau; status dan catatan HR tampil di halaman yang sama.

**Layanan Mandiri › IDP Saya**
- Buat IDP tahun berjalan: draft otomatis berisi kebutuhan pelatihan Anda yang disetujui HR dan saran dari asesmen kompetensi. Tambah/ubah aktivitas (bisa memilih dari katalog), isi sasaran yang diukur dan tenggat, lalu **ajukan ke atasan**. Bila dikembalikan, perbaiki sesuai catatan dan ajukan ulang.
- Setelah disetujui: perbarui status setiap aktivitas. Aktivitas **selesai wajib disertai bukti** (nomor sertifikat, laporan, catatan mentor). IDP selesai otomatis bila semua aktivitas selesai atau dibatalkan.

**Layanan Mandiri › Tugas Persetujuan & Delegasi**
- Persetujuan yang menunggu Anda muncul di Tugas Persetujuan. Saat cuti, delegasikan persetujuan ke rekan untuk periode tertentu.

## Sesi 2 — Atasan

- **Tugas Persetujuan:** setujui atau kembalikan rencana KPI bawahan dengan catatan; persetujuan perubahan KPI (change request).
- **Kinerja Tim Saya:** status rencana bawahan langsung, proyeksi skor, mid-year yang belum selesai.
- **Monitoring:** selesaikan check-in dan mid-year review dengan catatan.
- **Penilaian:** nilai What (dari realisasi), How (nilai inti 1–5), dan Kontribusi tambahan; ringkasan wajib. Sebagai atasan dari atasan: setujui atau kembalikan penilaian.
- **Keberatan:** sebagai atasan dari atasan, putuskan keberatan bawahan tidak langsung dengan alasan.
- **PIP:** buat rencana perbaikan untuk rating Below/Unsatisfactory; tandai selesai, diperpanjang, atau tidak berhasil.
- **Asesmen kompetensi:** nilai bawahan pada Asesmen Saya.
- **IDP tim:** setujui atau kembalikan IDP bawahan di Tugas Persetujuan; pantau dan beri catatan di IDP Saya › IDP tim saya.
- **Department Head:** Dasbor Kinerja, Laporan Kompetensi, Kesiapan Posisi, dan Ringkasan Eksekutif hanya menampilkan unit dalam cakupan Anda.

## Sesi 3 — HR: organisasi, pegawai, migrasi data

1. **Organisasi:** Unit Organisasi (bertingkat), Job Family → Job Level → Job Grade → Job (rentang grade & persyaratan) → Posisi (company × unit × job, atasan solid/dotted, kapasitas). Tandai posisi kritis beserta tingkat dampak dan alasan.
2. **Pegawai:** buat pegawai, isi identitas (terenkripsi; tampil tersamar kecuali izin sensitif), data riwayat, penugasan (primer, rangkap, Plt.). Mutasi = akhiri penugasan lama dan buat yang baru dengan tanggal efektif. Terminasi dapat dijadwalkan; diproses otomatis pukul 00.15.
3. **Migrasi Data (Control Panel):** unduh template CSV per jenis → isi → unggah → **validasi kering** → perbaiki error per nomor baris → commit. Urutan impor: unit → job family → job level → job grade → job → posisi → pegawai. Berkas besar: `php artisan hcms:import {jenis} {berkas} --tenant=KODE` (tanpa `--commit` = validasi saja).
4. **Alur Persetujuan:** atur langkah persetujuan per proses (urutan dapat diubah).
5. **Ekspor:** setiap ekspor wajib alasan, diberi watermark, dan tercatat; > 500 baris butuh izin Approve.

## Sesi 4 — HR: kompetensi & kinerja satu siklus

**Kompetensi**
1. Kamus Kompetensi: buat draft (menyalin versi berlaku), lengkapi level 1–5 dan 2–4 indikator per level, terbitkan (butuh izin Approve).
2. Profil Kompetensi Job: level wajib + kritikalitas 3/2/1 per job; override di posisi bila perlu. Pantau tabel **Cakupan pemetaan** sampai job family pilot "Terpetakan penuh".
3. Asesmen Kompetensi: buka per pegawai (penilai diri, atasan otomatis, rekan/bawahan dipilih), pantau pengisian, input hasil assessment center bila ada, tutup.
4. Membaca hasil: JFI ≥ 90% siap sekarang, 75–89% siap 1–2 tahun, < 75% siap > 2 tahun. Bila skor berlaku < 80% bobot profil, JFI "Data belum cukup". Skor berlaku 24 bulan; JFI dihitung ulang otomatis setiap Minggu.
5. Kesiapan Posisi: pilih posisi sasaran untuk membandingkan kandidat internal. Hasilnya bahan pertimbangan, bukan keputusan penempatan.

**Pengembangan › Kebutuhan Pelatihan (TNA)**
1. Tab *Saran otomatis*: saran dari gap kompetensi ≤ −1 dan PIP (diperbarui tiap Minggu atau tombol *Perbarui saran*). Setujui atau tolak (alasan wajib) — saran belum dihitung sebagai TNA.
2. Tab *Diajukan*: usulan pegawai/atasan dan kebutuhan organisasi. Anda tidak dapat memutus kebutuhan yang Anda ajukan sendiri.
3. *Rincian*: kaitkan dengan sasaran strategis (skor prioritas ×1,5), isi intervensi, biaya, tenggat, penanggung jawab.
5. **Katalog Intervensi**: daftar pelatihan, coaching, mentoring, penugasan, dan sertifikasi beserta kompetensi sasaran dan biaya; dipakai pegawai saat menyusun IDP.
6. **Rekap IDP**: cakupan IDP, kemajuan, aktivitas lewat tenggat, biaya rencana vs realisasi, dan gap yang belum tertutup setelah aktivitas selesai.
4. *Laporan TNA*: kebutuhan disetujui per sumber, prioritas, kompetensi, unit; kebutuhan terbuka & berulang; perkiraan biaya.

**Kinerja (urutan tahap)**
Kebijakan berversi → buka periode (kebijakan terkunci) → sasaran strategis → perencanaan → monitoring (mid-year wajib) → penilaian → kalibrasi per unit (alasan wajib) → umumkan rating sementara → keberatan → pengesahan final (snapshot tidak dapat diubah) → PIP. Ekspor rating dari halaman periode.

## Sesi 5 — Eksekutif & analitik

- **Ringkasan Eksekutif:** pegawai aktif, perubahan vs bulan lalu, bergabung/keluar, turnover, keterisian posisi, tren 12 bulan, komposisi, unit terbesar, posisi terbuka.
- **Analitik:** Turnover & retensi, Mobilitas (kenaikan grade terindikasi, bukan promosi resmi), DEIB (gender), Demografi, Pengembangan, Kinerja.
- Angka dihitung per akhir bulan yang dipilih; pegawai yang pindah/keluar tetap dihitung di unit asal selama periode. Definisi resmi setiap metrik ada di `METRIK-CBHRM.md`.
- Bagi peran tanpa akses data individual, kelompok 1–4 orang tampil "< 5".

## Sesi 6 — Audit & privasi

- **Audit Trail:** setiap perubahan data (sebelum/sesudah), akses data sensitif, ekspor, break-glass. Log tidak dapat diubah.
- **Break-glass** (Super Admin): akses darurat ke data tenant dengan alasan dan batas waktu; seluruh tindakan tercatat.
- **Hak subjek data:** akses (Unduh data saya) dan koreksi (pengajuan perubahan di Profil Saya).
- **Snapshot & riwayat:** rating final, riwayat skor/JFI kompetensi, dan snapshot analitik bulanan tidak dapat diubah; koreksi selalu berupa revisi/versi baru.

---

**Lampiran — jadwal otomatis yang perlu diketahui pengguna**

| Waktu (WIB) | Proses |
|---|---|
| Setiap hari 00.15 | Terminasi terjadwal yang jatuh tempo diproses |
| Setiap jam | Eskalasi tugas persetujuan yang melewati SLA |
| Minggu 02.00 | Hitung ulang JFI dari skor kompetensi yang masih berlaku |
| Minggu 03.00 | Perbarui saran kebutuhan pelatihan (TNA) |
| Tanggal 1 pukul 01.30 | Snapshot analitik bulan sebelumnya |
