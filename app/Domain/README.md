# app/Domain — logika bisnis HCMS

Controller hasil generator Sximo (`app/Http/Controllers/*Controller.php`) tetap datar dan tipis.
Semua aturan bisnis, kalkulasi, dan alur approval ditaruh di sini, per modul blueprint:

```
app/Domain/{Modul}/
    Actions/     satu kelas = satu operasi bisnis (mis. AssignEmployeeToPosition)
    Services/    kalkulasi & orkestrasi (mis. JobFitCalculator, ScoreCalculator)
    Events/      event domain (pegawai baru, mutasi, rating disahkan)
    Listeners/
    Jobs/        kalkulasi massal (JFI, skor, snapshot) — selalu membawa tenant_id
    Policies/    otorisasi & Segregation of Duties
```

Modul P0 (MVP): `Tenancy`, `Security`, `Organization`, `Employee`, `Workflow`, `Competency`, `Performance`.

Aturan:
- Antarmodul berkomunikasi lewat Service/Action atau event, tidak memakai model modul lain secara langsung.
- Query data bisnis wajib melewati `App\Support\Tenancy\TenantContext` (lihat `docs/TAHAPAN-PEMBANGUNAN-HCMS.md` bagian B.1).
- Setiap rumus (normalisasi KPI, prorata, JFI) wajib punya tes unit dengan contoh angka dari blueprint.
