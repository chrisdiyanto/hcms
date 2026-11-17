# Kamus Metrik CBHRM

Prasyarat **P4** laporan CBHRM (lihat `TAHAPAN-PEMBANGUNAN-HCMS.md` › Urutan laporan CBHRM). Dokumen ini menetapkan definisi yang dipakai semua laporan 1–12, dashboard executive, dan snapshot analitik bulanan, agar angka yang sama selalu dihitung dengan cara yang sama.

**Status setiap definisi**

- ✅ **Berlaku**: sudah diterapkan di kode. Sumbernya disebut.
- 📌 **Ditetapkan**: disetujui pemilik produk (2026-11-17); diterapkan saat modul terkait dibangun.

**Aturan perubahan**

- Definisi yang dipakai snapshot (`MonthlySnapshot::METRICS`) berversi lewat `MonthlySnapshot::DEFINITIONS_VERSION`.
- Bila definisi berubah, versi dinaikkan, perubahan dicatat di bagian *Riwayat* di bawah, dan bulan terkait dibangun ulang dengan `hcms:analytics-snapshot --from=… --force`.
- Versi lama tetap tersimpan sebagai `superseded`.

---

## 1. Prinsip umum

| Aspek | Definisi | Status |
|---|---|---|
| Periode laporan | Bulan kalender (`YYYY-MM`). Posisi data diambil per **akhir bulan** (as-of), dan pergerakan dihitung **selama bulan**. | ✅ Dashboard Executive, snapshot |
| Zona waktu | Asia/Jakarta (WIB); batas hari pukul 00.00 WIB. | ✅ |
| Pegawai aktif per tanggal *d* | `join_date ≤ d` dan (`terminated_at` kosong atau `terminated_at > d`), dan tidak dihapus (soft delete). | ✅ |
| Unit pegawai | Unit posisi **primer** yang berlaku pada tanggal acuan. Pegawai baru memakai unit pada tanggal bergabung; terminasi memakai unit pada tanggal terminasi. Tanpa penempatan = "tanpa unit". | ✅ snapshot |
| Cakupan data | Laporan mengikuti data scope peran (semua / unit / data sendiri). Pegawai yang keluar atau pindah tetap dihitung di unit asalnya selama periode. Cakupan "data sendiri" tidak mendapat agregat. | ✅ |
| Agregasi | Snapshot menyimpan **jumlah** (bukan rata-rata atau persentase). Rata-rata dan persentase dihitung saat dibaca, sehingga total lintas unit konsisten. | ✅ snapshot |
| Hasil dalam riwayat | Skor, JFI, dan gap dibaca dari riwayat per asesmen (P1). Hasil "terkini" hanya jalan pintas. | ✅ |
| Penyamaran kelompok kecil (K9) | Agregat yang mewakili **1–4 orang** ditampilkan "< 5"; nilai turunannya (persen, rata-rata, tingkat turnover) ditampilkan "—" dan nilainya tidak dikirim ke grafik. Nol tetap 0. **Hanya bagi pengguna yang tidak berhak membuka data individual dimensi itu** (izin `is_detail` pada modul sumber baris: `employees` untuk dashboard executive, `perfperiods` untuk Dasbor Kinerja, `competencyreports` untuk Laporan Kompetensi) — pengguna yang dapat membuka barisnya tidak dilindungi apa pun oleh penyamaran (keputusan 2026-11-17). Ekspor agregat selalu disamarkan. Rata-rata kinerja unit < 5 pegawai tidak ditampilkan. | ✅ `SmallGroups` |

---

## 2. Tenaga kerja

| Metrik | Rumus | Status |
|---|---|---|
| Headcount | Jumlah pegawai aktif per akhir bulan. | ✅ |
| Pegawai baru (hires) | Pegawai dengan `join_date` di dalam bulan. | ✅ |
| Terminasi | `employee_terminations` berstatus `processed` dengan `termination_date` di dalam bulan. Status terjadwal atau dibatalkan tidak dihitung. | ✅ |
| Tingkat turnover bulanan | Terminasi ÷ rata-rata (headcount akhir bulan lalu, headcount akhir bulan ini) × 100. | ✅ |
| Turnover tahunan (12 bulan bergulir) | Σ terminasi 12 bulan ÷ rata-rata headcount 13 titik akhir bulan × 100. | 📌 |
| Retensi pegawai awal | (Pegawai aktif sebelum bulan − di antaranya yang keluar selama bulan) ÷ pegawai aktif sebelum bulan × 100. | ✅ |
| Keterisian posisi | Σ min(kapasitas, terisi) per unit ÷ Σ kapasitas posisi aktif × 100. "Terisi" = penempatan primer yang berlaku. | ✅ |
| Kenaikan grade terindikasi | Penempatan primer baru di bulan itu yang `rank` grade-nya lebih tinggi daripada penempatan primer sebelumnya. Belum sama dengan promosi resmi. | ✅ |

---

## 3. Kompetensi

| Metrik | Definisi | Status |
|---|---|---|
| Skor aktual | Rata-rata tertimbang kelompok penilai: atasan 50, diri 20, rekan 20, bawahan 10, bisa diatur per tenant. Kelompok rekan atau bawahan dengan < 3 penilai gugur dan bobotnya dibagi ulang. Hasil assessment center menggantikan skor penilai. | ✅ `RaterAggregator` |
| Masa berlaku skor | 24 bulan sejak asesmen ditutup. | ✅ `CompetencyAssessment::VALID_MONTHS` |
| Gap | Skor aktual − level wajib (profil job atau override posisi). | ✅ |
| Job Fit Index (JFI) | Σ min(A/R, 1) × w ÷ Σ w. A = aktual, R = wajib, w = kritikalitas 3/2/1. | ✅ `JobFitCalculator` |
| Band JFI | ≥ 90 siap sekarang; 75–89,99 siap 1–2 tahun; < 75 siap > 2 tahun. Bisa diatur per tenant. | ✅ |
| Kompetensi tanpa skor berlaku (K1) | JFI hanya dihitung bila **≥ 80% bobot** profil punya skor berlaku (`coverage`). Bila kurang: JFI kosong, band **"Data belum cukup"** (`insufficient`), tidak masuk rata-rata atau distribusi band siap. Bila ≥ 80%: kompetensi yang belum dinilai tetap bernilai 0 di rumus blueprint dan hasil ditandai "belum lengkap". Gap kompetensi tanpa skor dikosongkan, bukan −wajib. | ✅ `JobFitCalculator::MIN_COVERAGE` |
| Asesmen kedaluwarsa (K2) | Skor kedaluwarsa **dikeluarkan** dari perhitungan (sama dengan tanpa skor) dan **ditandai** di laporan individu beserta tanggal berlakunya. JFI dihitung ulang mingguan (`hcms:competency-recalculate`, Minggu 02.00); riwayat hanya bertambah bila hasilnya berubah. | ✅ |
| Gap kritis (K3) | Kompetensi dengan kritikalitas **3** dan gap **≤ −1** (kurang satu level atau lebih). Dipakai Laporan Kompetensi, label "gap kritis" di laporan individu & asesmen, dan snapshot v2. Draf IDP tetap dibuat untuk setiap gap negatif kompetensi kritis (lebih luas). | ✅ `JobFitCalculator::isCriticalGap` |
| Pegawai dengan gap kritis | Pegawai aktif dengan ≥ 1 gap kritis pada perhitungan JFI terakhir ≤ tanggal acuan. | ✅ (memakai definisi gap kritis yang berlaku) |
| Cakupan asesmen | Pegawai aktif dengan ≥ 1 skor kompetensi berlaku ÷ headcount × 100. | ✅ snapshot `competency_valid` |
| Cakupan profil jabatan | Posisi aktif terpetakan = job-nya punya profil kompetensi atau posisi punya kompetensi tambahan; ÷ posisi aktif × 100. Job terpetakan = profil ≥ 3 kompetensi dengan ≥ 1 kritis. Job family **terpetakan penuh** (gerbang Fase 2) = semua job dan posisi aktifnya terpetakan. | ✅ Profil Kompetensi Job › Cakupan pemetaan |

---

## 4. Kesiapan posisi (Position Readiness)

| Metrik | Definisi | Status |
|---|---|---|
| Horizon kesiapan kandidat | **Siap sekarang**: band JFI siap sekarang dan **tanpa** gap kritis. **Siap 1–2 tahun**: band siap 1–2 tahun, atau band siap sekarang tetapi ada ≤ 1 gap kritis. **Siap ≥ 3 tahun**: selain itu. Kandidat dengan JFI "data belum cukup" (K1) tidak diberi horizon. | ✅ `PositionReadiness::horizon` (Kompetensi › Kesiapan Posisi) |
| Data pendukung | Tanggal asesmen terakhir, kompetensi wajib terpenuhi (x/y), gap kritis per kode, jumlah skor kedaluwarsa, penanda pemangku posisi. Kandidat = pegawai aktif dalam data scope dengan ≥ 1 skor berlaku pada profil posisi sasaran. Setiap akses daftar tercatat di audit. | ✅ |

Kesiapan adalah bahan pertimbangan, **bukan** rekomendasi atau keputusan penempatan.

---

## 5. TNA

| Metrik | Definisi | Status |
|---|---|---|
| Kebutuhan pelatihan | Satu kebutuhan = pegawai × kompetensi (atau topik) × periode, dengan sumber: gap, kinerja/PIP, prioritas organisasi, atau permintaan. | ✅ `training_needs` (pegawai × kompetensi/topik × tahun, sumber, dedupe saran) |
| Skor prioritas | Kritikalitas (1–3) × besar gap (|gap|, maks 4) × faktor strategis (1; 1,5 bila terkait sasaran strategis aktif). Kebutuhan dari PIP selalu prioritas **tinggi**. Kelompok: tinggi ≥ 6, sedang 3–5,99, rendah < 3. | ✅ `TrainingNeeds::score/level` |
| Kebutuhan terbuka | Kebutuhan berstatus disetujui yang belum punya intervensi terencana (status `planned` diisi saat IDP/pelatihan dibangun). | ✅ Laporan TNA (status Disetujui) |
| Pemisahan | Saran otomatis (dari gap atau IDP) tidak dihitung sebagai kebutuhan TNA sebelum disetujui HR. | ✅ saran = status `suggested`; Laporan TNA memisahkannya |

---

## 6. IDP

| Metrik | Definisi | Status |
|---|---|---|
| Kemajuan IDP | Aktivitas selesai ÷ aktivitas direncanakan (yang dibatalkan tidak dihitung). | ✅ Rekap IDP (`development/idps`) |
| Aktivitas lewat tenggat | Aktivitas belum selesai dengan tenggat < tanggal acuan. | ✅ Rekap IDP & IDP pegawai (label "lewat tenggat") |
| Gap tertutup | Untuk aktivitas IDP berstatus selesai yang punya kompetensi sasaran: gap terkini kompetensi itu (`competency_gaps`) ≥ 0 = tertutup, < 0 = belum tertutup, tanpa data = belum diukur. Bukan klaim sebab-akibat satu aktivitas. | ✅ Rekap IDP |

---

## 7. Pelatihan dan efektivitas

| Metrik | Definisi | Status |
|---|---|---|
| Tingkat penyelesaian | Peserta selesai ÷ peserta terdaftar (tidak termasuk yang dibatalkan sebelum mulai). | ✅ `TrainingEffectiveness` (Efektivitas Pelatihan) |
| Biaya per peserta | Biaya realisasi ÷ peserta selesai. | ✅ `TrainingEffectiveness` (Efektivitas Pelatihan) |
| Evaluasi tingkat 1 (reaksi) | Survei di akhir pelatihan, skala 1–5; minimal 50% respons agar dilaporkan. | ✅ `TrainingEffectiveness` (Efektivitas Pelatihan) |
| Evaluasi tingkat 2 (pembelajaran) | Selisih skor pre-test dan post-test. | ✅ `TrainingEffectiveness` (Efektivitas Pelatihan) |
| Evaluasi tingkat 3 (penerapan) | Perubahan skor kompetensi sasaran pada asesmen **3–6 bulan** setelah pelatihan dibanding baseline sebelum pelatihan (dari riwayat P1). | ✅ `TrainingEffectiveness` (Efektivitas Pelatihan) |
| Evaluasi tingkat 4 (dampak) | Perubahan skor kinerja pada **siklus kinerja berikutnya** dibanding siklus sebelumnya. Dilaporkan dengan keterbatasan atribusi dan tanpa klaim sebab-akibat. | ✅ `TrainingEffectiveness` (Efektivitas Pelatihan) |
| Indikasi program | Per intervensi: **pertimbangkan dihentikan** bila reaksi < 3,0 atau rata-rata post − pre ≤ 0 (≥ 3 peserta teruji); **perbaiki** bila reaksi < 3,5 atau penyelesaian < 70%; **data belum cukup** bila belum ada peserta lulus; selain itu **lanjutkan**. Indikasi bantu, keputusan tetap pada pengelola program. Ambang awal — sesuaikan bila pemilik produk menetapkan lain. | ✅ `TrainingEffectiveness::indication` |

---

## 8. Kinerja

| Metrik | Definisi | Status |
|---|---|---|
| Skor akhir | What 60 / How 30 / Kontribusi 10 sesuai kebijakan yang dikunci per periode. Cap per KPI 120%. Skor > 100 dibatasi 100 untuk rating, nilai mentah tetap disimpan. | ✅ `ScoreCalculator`, kebijakan |
| Band rating | Outstanding ≥ 90, Exceeds ≥ 80, Meets ≥ 70, Below ≥ 60, Unsatisfactory < 60 (bawaan; mengikuti kebijakan periode). | ✅ |
| Rating final vs sementara | Laporan hasil memakai rating **final** (setelah masa keberatan). Rating sementara hanya ditampilkan dengan label "sementara". | ✅ |
| Not rated | Masa kerja dalam periode < 90 hari. Tidak dihitung dalam distribusi dan rata-rata. | ✅ |
| Rating per tanggal acuan | Rating final terakhir (periode dengan tanggal akhir paling baru, revisi tertinggi) yang disahkan ≤ tanggal acuan. | ✅ snapshot |

---

## 9. Potensi dan Nine Box

| Metrik | Definisi | Status |
|---|---|---|
| Skor potensi | Rata-rata tertimbang skor kriteria instrumen potensi yang berlaku; tingkat rendah / sedang / tinggi memakai ambang versi instrumen (bawaan 2,50 / 3,75 pada skala 1–5). | ✅ `PotentialInstruments::evaluate` |
| Masa berlaku hasil potensi | Mengikuti instrumen (bawaan 24 bulan). | ✅ |
| Sumbu kinerja Nine Box | rating final terakhir. **Tinggi** = Outstanding/Exceeds, **Sedang** = Meets, **Rendah** = Below/Unsatisfactory. Not rated tidak masuk Nine Box. | 📌 K7 |
| Penomoran kotak | kotak 1 = kinerja rendah & potensi rendah … kotak 9 = kinerja tinggi & potensi tinggi. Nama kotak bisa diatur per tenant. | 📌 K7 |
| Cakupan talent review | Pegawai yang direview ÷ pegawai yang memenuhi syarat review × 100. | 📌 |

---

## 10. Talent pool

| Metrik | Definisi | Status |
|---|---|---|
| Anggota aktif | Nominasi yang disetujui, belum keluar, dan tanggal tinjau ulang belum lewat. | 📌 |
| Kesiapan pool | Distribusi horizon kesiapan anggota terhadap posisi atau rumpun sasaran (definisi K4). | 📌 |
| Keselarasan aspirasi | Anggota yang aspirasi kariernya (P3) cocok dengan rumpun sasaran pool ÷ anggota. | 📌 |

---

## 11. Suksesi

| Metrik | Definisi | Status |
|---|---|---|
| Cakupan suksesi | Posisi kritis dengan ≥ 1 suksesor **siap sekarang** ÷ posisi kritis aktif × 100. | 📌 K8 |
| Bench strength | Rata-rata jumlah suksesor siap sekarang atau siap 1–2 tahun per posisi kritis. | 📌 K8 |
| Posisi berisiko | Posisi kritis berdampak **sangat tinggi** tanpa suksesor siap sekarang, atau yang pemangkunya akan pensiun atau habis kontraknya ≤ 12 bulan. | 📌 K8 |
| Kandidat ganda | Kandidat yang tercatat sebagai suksesor utama di > 1 posisi kritis. | 📌 |

---

## 12. Keputusan

Semua keputusan **disetujui pemilik produk pada 2026-11-17** sesuai rekomendasi. K1–K3 dan K9 diterapkan di kode (lihat status di tiap bagian); K4–K8 dipakai saat modul terkait dibangun.

| No | Keputusan | Ketetapan |
|---|---|---|
| K1 | Kompetensi tanpa skor berlaku di JFI | JFI hanya bila ≥ 80% bobot terisi; selain itu "data belum cukup" |
| K2 | Asesmen kedaluwarsa | Dikeluarkan dari perhitungan dan ditandai |
| K3 | Gap kritis | Kritikalitas 3 dan gap ≤ −1 |
| K4 | Horizon kesiapan | JFI band + jumlah gap kritis (bagian 4) |
| K5 | Prioritas TNA | Kritikalitas × besar gap × faktor strategis; PIP selalu tinggi |
| K6 | Waktu ukur evaluasi pelatihan | Tingkat 3 pada 3–6 bulan, tingkat 4 pada siklus kinerja berikutnya |
| K7 | Pemetaan Nine Box | Outstanding/Exceeds = tinggi, Meets = sedang, Below/Unsatisfactory = rendah |
| K8 | Cakupan suksesi, bench strength, posisi berisiko | Seperti bagian 11 |
| K9 | Penyamaran agregat | < 5 orang di laporan agregat dan ekspor, bagi pengguna tanpa hak membuka data individual (perincian 2026-11-17, P5) |


## Riwayat

| Tanggal | Perubahan |
|---|---|
| 2026-11-17 | Versi awal: definisi yang sudah berlaku di kode dan usulan K1–K9. |
| 2026-11-17 | K1–K9 dan seluruh usulan disetujui. K1–K3 diterapkan: JFI & gap boleh kosong, kolom `coverage`, band `insufficient`, gap kritis ≤ −1; snapshot `DEFINITIONS_VERSION` = 2 (metrik baru `jfi_insufficient`), snapshot 2025-11 s.d. 2026-10 dibangun ulang; JFI dihitung ulang dengan `hcms:competency-recalculate`. |
| 2026-11-17 | K9 diperinci: penyamaran hanya bagi pengguna tanpa hak membuka data individual; diterapkan di dashboard executive, Dasbor Kinerja, dan Laporan Kompetensi. |
| 2026-11-18 | K4 diterapkan di Kesiapan Posisi; definisi cakupan profil jabatan & job family terpetakan penuh ditetapkan. |
| 2026-11-18 | §5 TNA diterapkan (Rilis 2 gelombang 1): saran otomatis dari gap ≤ −1 dan PIP; tanpa gap kompetensi, prioritas ditetapkan pengaju/peninjau. |
| 2026-11-18 | §6 IDP diterapkan (Rilis 2 gelombang 2): kemajuan, lewat tenggat, gap tertutup dari gap terkini. |
| 2026-11-18 | §7 pelatihan & efektivitas diterapkan (Rilis 2 gelombang 3); aturan indikasi program ditambahkan dengan ambang awal. |
