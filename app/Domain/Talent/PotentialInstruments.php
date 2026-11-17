<?php

namespace App\Domain\Talent;

use App\Support\Audit\AuditLog;
use App\Support\Tenancy\TenantScope;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Kamus instrumen asesmen potensi berversi (prasyarat P3 CBHRM; sumbu potensi Nine Box).
 *
 * Pola sama dengan kamus kompetensi: paling banyak satu draft dan satu versi berlaku per tenant;
 * hanya draft yang dapat diubah; draft baru menyalin versi berlaku; terbit butuh izin Approve dan
 * versi lama menjadi pensiun. Potensi terpisah dari rating kinerja dan asesmen kompetensi.
 * Skor potensi = rata-rata tertimbang skor kriteria (1..scale_max) → tingkat rendah/sedang/tinggi
 * berdasarkan ambang versi instrumen. Penilaian pegawai dibangun bersama talent review (laporan 9).
 */
class PotentialInstruments
{
    public const STATUS = ['draft' => 'Draft', 'published' => 'Berlaku', 'retired' => 'Pensiun'];

    public const LEVELS = ['low' => 'Rendah', 'medium' => 'Sedang', 'high' => 'Tinggi'];

    public const MIN_CRITERIA = 3;

    public const MAX_CRITERIA = 8;

    /** Titik awal draft pertama (model ability–aspiration–engagement); dapat diubah HR sebelum terbit. */
    public const DEFAULT_CRITERIA = [
        ['code' => 'ABILITY', 'name' => 'Kemampuan & kelincahan belajar', 'weight' => 40,
            'description' => 'Kapasitas memahami persoalan baru yang lebih kompleks dan belajar cepat dari pengalaman.',
            'indicators' => "Cepat menguasai tugas atau bidang baru\nMenarik pelajaran dari keberhasilan dan kegagalan lalu menerapkannya\nMenyelesaikan masalah yang belum pernah ditangani sebelumnya"],
        ['code' => 'ASPIRATION', 'name' => 'Aspirasi', 'weight' => 30,
            'description' => 'Keinginan untuk berkembang dan memikul peran dengan tanggung jawab lebih besar.',
            'indicators' => "Secara aktif mencari tanggung jawab dan tantangan baru\nMemiliki tujuan karier yang jelas dan selaras dengan organisasi"],
        ['code' => 'ENGAGEMENT', 'name' => 'Keterikatan', 'weight' => 30,
            'description' => 'Komitmen emosional dan rasional terhadap organisasi serta kemauan memberi upaya ekstra.',
            'indicators' => "Menunjukkan inisiatif di luar uraian tugas\nMenjadi panutan nilai organisasi bagi rekan kerja"],
    ];

    public function versions()
    {
        return TenantScope::table('potential_instruments')->orderByDesc('version')->get();
    }

    public function published(): ?object
    {
        return TenantScope::table('potential_instruments')->where('status', 'published')->orderByDesc('version')->first();
    }

    public function draft(): ?object
    {
        return TenantScope::table('potential_instruments')->where('status', 'draft')->first();
    }

    public function criteria(int $instrumentId)
    {
        return TenantScope::table('potential_criteria')->where('instrument_id', $instrumentId)->orderBy('sort_order')->orderBy('id')->get();
    }

    public function createDraft(?int $userId): int
    {
        if ($this->draft()) {
            throw ValidationException::withMessages(['version' => 'Sudah ada draft instrumen potensi yang belum diterbitkan.']);
        }

        return DB::transaction(function () use ($userId) {
            $source = $this->published();
            $next = (int) TenantScope::table('potential_instruments')->max('version') + 1;
            $header = $source ? collect((array) $source)->only(['scale_max', 'threshold_medium', 'threshold_high', 'validity_months', 'notes'])->all() : [];
            $id = DB::table('potential_instruments')->insertGetId(TenantScope::fillForInsert('potential_instruments', $header + [
                'version' => $next, 'name' => 'Instrumen Potensi v'.$next, 'status' => 'draft',
                'created_by' => $userId, 'created_at' => now(), 'updated_at' => now(),
            ]));
            $criteria = $source
                ? $this->criteria((int) $source->id)->map(fn ($c) => collect((array) $c)->only(['code', 'name', 'description', 'indicators', 'weight'])->all())->all()
                : self::DEFAULT_CRITERIA;
            $this->replaceCriteria($id, $criteria);
            AuditLog::created('potential_instruments', $id, ['version' => $next, 'status' => 'draft', 'sumber' => $source ? 'v'.$source->version : 'bawaan'], 'Draft instrumen potensi');

            return $id;
        });
    }

    /**
     * @param  array{name: string, scale_max: int, threshold_medium: float, threshold_high: float, validity_months: int, notes: ?string}  $header
     * @param  array<int, array{code: string, name: string, description: string, indicators: string, weight: int}>  $criteria
     */
    public function updateDraft(int $id, array $header, array $criteria): void
    {
        $instrument = $this->editable($id);
        DB::transaction(function () use ($instrument, $header, $criteria) {
            // Audit membandingkan nilai skalar: bobot kriteria diringkas menjadi teks "KODE:bobot"
            $weights = fn ($rows) => collect($rows)->map(fn ($c) => strtoupper(trim((string) ((array) $c)['code'])).':'.(int) ((array) $c)['weight'])->implode(', ');
            $before = collect((array) $instrument)->only(array_keys($header))->all() + ['bobot_kriteria' => $weights($this->criteria((int) $instrument->id))];
            TenantScope::table('potential_instruments')->where('id', $instrument->id)->update($header + ['updated_at' => now()]);
            $this->replaceCriteria((int) $instrument->id, $criteria);
            AuditLog::updated('potential_instruments', $instrument->id, $before, $header + ['bobot_kriteria' => $weights($criteria)], 'Draft instrumen potensi diubah');
        });
    }

    /** @return string[] alasan draft belum dapat diterbitkan */
    public function publishProblems(int $id): array
    {
        $instrument = TenantScope::table('potential_instruments')->where('id', $id)->first();
        if (! $instrument) {
            return ['Instrumen tidak ditemukan.'];
        }
        $criteria = $this->criteria($id);
        $problems = [];
        if ($criteria->count() < self::MIN_CRITERIA || $criteria->count() > self::MAX_CRITERIA) {
            $problems[] = 'Jumlah kriteria harus '.self::MIN_CRITERIA.'–'.self::MAX_CRITERIA.' (saat ini '.$criteria->count().').';
        }
        if ((int) $criteria->sum('weight') !== 100) {
            $problems[] = 'Total bobot kriteria harus 100% (saat ini '.(int) $criteria->sum('weight').'%).';
        }
        foreach ($criteria as $c) {
            if (count(self::indicatorLines($c->indicators)) < 2) {
                $problems[] = "Kriteria {$c->code} membutuhkan minimal 2 indikator perilaku.";
            }
        }
        $medium = (float) $instrument->threshold_medium;
        $high = (float) $instrument->threshold_high;
        if (! ($medium > 1 && $medium < $high && $high <= (float) $instrument->scale_max)) {
            $problems[] = 'Ambang harus memenuhi 1 < sedang < tinggi ≤ skala maksimum.';
        }

        return $problems;
    }

    public function publish(int $id, int $userId): void
    {
        $instrument = $this->editable($id);
        if ($problems = $this->publishProblems($id)) {
            throw ValidationException::withMessages(['publish' => $problems]);
        }
        DB::transaction(function () use ($instrument, $userId) {
            $previous = $this->published();
            if ($previous) {
                TenantScope::table('potential_instruments')->where('id', $previous->id)->update(['status' => 'retired', 'updated_at' => now()]);
            }
            TenantScope::table('potential_instruments')->where('id', $instrument->id)
                ->update(['status' => 'published', 'published_at' => now(), 'published_by' => $userId, 'updated_at' => now()]);
            AuditLog::updated('potential_instruments', $instrument->id, ['status' => 'draft'],
                ['status' => 'published', 'menggantikan' => $previous ? 'v'.$previous->version : null], 'Instrumen potensi diterbitkan');
        });
    }

    public function discardDraft(int $id): void
    {
        $instrument = $this->editable($id);
        TenantScope::table('potential_instruments')->where('id', $instrument->id)->delete();
        AuditLog::deleted('potential_instruments', $instrument->id, ['version' => $instrument->version], 'Draft instrumen potensi dibuang');
    }

    // ------------------------------------------------------------ rumus

    /**
     * Skor potensi tertimbang dan tingkatnya (dipakai talent review).
     *
     * @param  array<string, float>  $scores  kode kriteria => skor 1..scale_max
     * @return array{score: ?float, level: ?string, complete: bool}
     */
    public static function evaluate(object $instrument, iterable $criteria, array $scores): array
    {
        $sum = 0.0;
        $weight = 0;
        $complete = true;
        foreach ($criteria as $c) {
            if (! isset($scores[$c->code])) {
                $complete = false;

                continue;
            }
            $sum += (float) $scores[$c->code] * (int) $c->weight;
            $weight += (int) $c->weight;
        }
        if ($weight === 0) {
            return ['score' => null, 'level' => null, 'complete' => false];
        }
        $score = round($sum / $weight, 2);
        $level = $score >= (float) $instrument->threshold_high ? 'high' : ($score >= (float) $instrument->threshold_medium ? 'medium' : 'low');

        return ['score' => $score, 'level' => $level, 'complete' => $complete];
    }

    /** @return string[] */
    public static function indicatorLines(?string $text): array
    {
        return array_values(array_filter(array_map('trim', preg_split('/\R/', (string) $text)), fn ($l) => $l !== ''));
    }

    // ---------------------------------------------------------------- bantuan

    private function editable(int $id): object
    {
        $instrument = TenantScope::table('potential_instruments')->where('id', $id)->first() ?? abort(404);
        if ($instrument->status !== 'draft') {
            throw ValidationException::withMessages(['status' => 'Hanya draft yang dapat diubah. Buat draft baru untuk merevisi instrumen.']);
        }

        return $instrument;
    }

    private function replaceCriteria(int $instrumentId, array $criteria): void
    {
        TenantScope::table('potential_criteria')->where('instrument_id', $instrumentId)->delete();
        foreach (array_values($criteria) as $i => $c) {
            DB::table('potential_criteria')->insert(TenantScope::fillForInsert('potential_criteria', [
                'instrument_id' => $instrumentId, 'code' => strtoupper(trim($c['code'])), 'name' => trim($c['name']),
                'description' => trim($c['description']), 'indicators' => implode("\n", self::indicatorLines($c['indicators'])),
                'weight' => (int) $c['weight'], 'sort_order' => $i + 1, 'created_at' => now(), 'updated_at' => now(),
            ]));
        }
    }
}
