<?php

namespace App\Domain\Performance\Services;

use App\Support\Audit\AuditLog;
use App\Support\Tenancy\TenantScope;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Kebijakan penilaian kinerja berversi (blueprint 8.9 langkah 2).
 * Satu versi `active` per tenant; perubahan dibuat sebagai draft lalu diterbitkan
 * (versi aktif sebelumnya menjadi `retired`). Periode yang sudah dibuka memakai
 * salinan kebijakan yang dikunci, jadi tidak terpengaruh penerbitan berikutnya.
 */
final class PerformancePolicy
{
    /** Model 60/30/10 (ADR 0001 no. 8): Kinerja/KPI, Perilaku (How), Kontribusi tambahan. */
    public const DEFAULTS = [
        'weights' => ['what' => 60, 'how' => 30, 'contribution' => 10],
        'weight_overrides' => [],
        'kpi' => ['min_count' => 3, 'max_count' => 10, 'min_weight' => 5, 'max_weight' => 40],
        'cap' => 120,
        'conversion' => [1 => 50, 2 => 65, 3 => 75, 4 => 85, 5 => 95],
        'bands' => [
            ['code' => 'outstanding', 'label' => 'Outstanding', 'min' => 90],
            ['code' => 'exceeds', 'label' => 'Exceeds Expectation', 'min' => 80],
            ['code' => 'meets', 'label' => 'Meets Expectation', 'min' => 70],
            ['code' => 'below', 'label' => 'Below Expectation', 'min' => 60],
            ['code' => 'unsatisfactory', 'label' => 'Unsatisfactory', 'min' => 0],
        ],
        'prorata' => ['min_segment_days' => 90, 'min_rated_days' => 90],
        'range_penalty' => 1,
        'milestone_late_penalty' => 0.25,
        // Perilaku kerja & nilai inti (How), dinilai 1–5 dengan indikator perilaku (blueprint 8.4)
        'how_items' => [
            ['code' => 'INT', 'label' => 'Integritas', 'description' => 'Jujur, konsisten antara ucapan dan tindakan, mematuhi aturan'],
            ['code' => 'KOL', 'label' => 'Kolaborasi', 'description' => 'Bekerja sama lintas fungsi, berbagi informasi, mendukung rekan'],
            ['code' => 'PEL', 'label' => 'Orientasi pelanggan', 'description' => 'Memahami kebutuhan pelanggan internal/eksternal dan menindaklanjutinya'],
            ['code' => 'INO', 'label' => 'Inovasi', 'description' => 'Mengusulkan dan menerapkan cara kerja yang lebih baik'],
            ['code' => 'AKT', 'label' => 'Akuntabilitas', 'description' => 'Bertanggung jawab atas hasil kerja dan komitmen'],
        ],
        // Dimensi ketiga (ADR 0001 no. 8): inisiatif, proyek lintas unit, tugas di luar KPI
        'contribution_rubric' => [
            1 => 'Tidak ada kontribusi di luar KPI',
            2 => 'Kontribusi kecil dan insidental',
            3 => 'Aktif dalam satu inisiatif/proyek di luar KPI',
            4 => 'Memimpin inisiatif atau berdampak nyata bagi unit lain',
            5 => 'Kontribusi luar biasa yang berdampak luas bagi organisasi',
        ],
        'appeal_days' => 14,
    ];

    public const DIMENSIONS = ['what' => 'Kinerja (KPI)', 'how' => 'Perilaku (How)', 'contribution' => 'Kontribusi tambahan'];

    public function active(): ?object
    {
        return $this->decode(TenantScope::table('performance_policies')->where('status', 'active')->orderByDesc('version')->first());
    }

    public function draft(): ?object
    {
        return $this->decode(TenantScope::table('performance_policies')->where('status', 'draft')->first());
    }

    public function find(int $id): object
    {
        return $this->decode(TenantScope::table('performance_policies')->where('id', $id)->first()) ?? abort(404);
    }

    /** Draft baru dari kebijakan aktif (atau default). Hanya satu draft per tenant. */
    public function createDraft(?int $userId): int
    {
        if ($this->draft()) {
            throw ValidationException::withMessages(['policy' => 'Masih ada draft kebijakan yang belum diterbitkan.']);
        }
        $base = $this->active();
        $version = (int) TenantScope::table('performance_policies')->max('version') + 1;

        $id = DB::table('performance_policies')->insertGetId(TenantScope::fillForInsert('performance_policies', [
            'uuid' => (string) Str::uuid7(), 'version' => $version,
            'name' => 'Kebijakan Kinerja v'.$version, 'status' => 'draft',
            'config' => json_encode($base->config ?? self::DEFAULTS),
            'created_by' => $userId, 'created_at' => now(), 'updated_at' => now(),
        ]));
        AuditLog::created('performance_policies', $id, ['versi' => $version], 'Draft kebijakan kinerja v'.$version);

        return $id;
    }

    public function updateDraft(int $id, string $name, array $config, ?string $notes): void
    {
        $policy = $this->find($id);
        if ($policy->status !== 'draft') {
            throw ValidationException::withMessages(['policy' => 'Hanya draft yang dapat diubah.']);
        }
        $config = $this->normalize($config);
        if ($problems = $this->problems($config)) {
            throw ValidationException::withMessages(['config' => $problems]);
        }

        DB::transaction(function () use ($id, $policy, $name, $config, $notes) {
            DB::table('performance_policies')->where('id', $id)->update([
                'name' => $name, 'config' => json_encode($config), 'notes' => $notes, 'updated_at' => now(),
            ]);
            // AuditLog membandingkan nilai skalar: simpan konfigurasi sebagai JSON
            AuditLog::updated('performance_policies', $id,
                ['nama' => $policy->name, 'config' => json_encode($policy->config)],
                ['nama' => $name, 'config' => json_encode($config)], 'Draft kebijakan kinerja diubah');
        });
    }

    public function publish(int $id, ?int $userId): void
    {
        $policy = $this->find($id);
        if ($policy->status !== 'draft') {
            throw ValidationException::withMessages(['policy' => 'Hanya draft yang dapat diterbitkan.']);
        }
        if ($problems = $this->problems($policy->config)) {
            throw ValidationException::withMessages(['config' => $problems]);
        }

        DB::transaction(function () use ($policy, $userId) {
            TenantScope::table('performance_policies')->where('status', 'active')->update(['status' => 'retired', 'updated_at' => now()]);
            DB::table('performance_policies')->where('id', $policy->id)->update([
                'status' => 'active', 'published_at' => now(), 'published_by' => $userId, 'updated_at' => now(),
            ]);
            AuditLog::updated('performance_policies', $policy->id, ['status' => 'draft'], ['status' => 'active'], 'Kebijakan kinerja v'.$policy->version.' diterbitkan');
        });
    }

    /** Daftar masalah konfigurasi (kosong = valid). */
    public function problems(array $c): array
    {
        $p = [];
        $sum = fn (array $w) => round((float) $w['what'] + (float) $w['how'] + (float) $w['contribution'], 2);
        if ($sum($c['weights']) != 100.0) {
            $p[] = 'Total bobot Kinerja + Perilaku + Kontribusi harus 100%.';
        }
        foreach ($c['weight_overrides'] as $o) {
            if ($sum($o) != 100.0) {
                $p[] = 'Total bobot untuk level jabatan #'.$o['job_level_id'].' harus 100%.';
            }
        }
        $k = $c['kpi'];
        if ($k['min_count'] < 1 || $k['min_count'] > $k['max_count']) {
            $p[] = 'Jumlah KPI minimum harus ≥ 1 dan tidak melebihi maksimum.';
        }
        if ($k['min_weight'] <= 0 || $k['min_weight'] > $k['max_weight'] || $k['max_weight'] > 100) {
            $p[] = 'Rentang bobot per KPI tidak valid.';
        }
        if ($k['min_count'] * $k['min_weight'] > 100 || $k['max_count'] * $k['max_weight'] < 100) {
            $p[] = 'Batas jumlah & bobot KPI membuat total 100% mustahil tercapai.';
        }
        if ($c['cap'] < 100 || $c['cap'] > 200) {
            $p[] = 'Cap capaian KPI harus antara 100% dan 200%.';
        }
        $conv = array_values($c['conversion']);
        for ($i = 1; $i < count($conv); $i++) {
            if ($conv[$i] <= $conv[$i - 1]) {
                $p[] = 'Tabel konversi 1–5 harus naik.';
                break;
            }
        }
        $mins = array_column($c['bands'], 'min');
        if (count($mins) !== count(array_unique($mins)) || ! in_array(0.0, array_map('floatval', $mins), true)) {
            $p[] = 'Batas band rating harus unik dan salah satunya 0.';
        }
        $howItems = $c['how_items'] ?? [];
        if ((float) $c['weights']['how'] > 0 && $howItems === []) {
            $p[] = 'Bobot Perilaku > 0 tetapi belum ada item perilaku (How).';
        }
        if (count($howItems) !== count(array_unique(array_column($howItems, 'code')))) {
            $p[] = 'Kode item perilaku harus unik.';
        }
        if (($c['appeal_days'] ?? 14) < 1 || ($c['appeal_days'] ?? 14) > 60) {
            $p[] = 'Jendela keberatan harus 1–60 hari.';
        }

        return $p;
    }

    /** Pastikan tipe angka & struktur lengkap (input form datang sebagai string). */
    public function normalize(array $c): array
    {
        $input = $c;
        $c = array_replace_recursive(self::DEFAULTS, array_intersect_key($c, self::DEFAULTS));
        // Daftar diganti utuh (bukan digabung per indeks dengan default)
        foreach (['bands', 'weight_overrides', 'how_items'] as $list) {
            if (array_key_exists($list, $input)) {
                $c[$list] = (array) $input[$list];
            }
        }
        $num = fn ($v) => (float) $v;
        $c['weights'] = array_map($num, array_intersect_key($c['weights'], self::DIMENSIONS));
        $c['weight_overrides'] = array_values(array_map(fn ($o) => [
            'job_level_id' => (int) $o['job_level_id'], 'what' => $num($o['what']), 'how' => $num($o['how']), 'contribution' => $num($o['contribution']),
        ], array_filter($c['weight_overrides'] ?? [], fn ($o) => ! empty($o['job_level_id']))));
        $c['kpi'] = array_map('intval', $c['kpi']);
        $c['cap'] = $num($c['cap']);
        $c['conversion'] = array_combine([1, 2, 3, 4, 5], array_map($num, array_slice(array_values($c['conversion']), 0, 5)));
        $c['bands'] = array_values(array_map(fn ($b) => ['code' => Str::slug(($b['code'] ?? '') ?: $b['label'], '_'), 'label' => trim($b['label']), 'min' => $num($b['min'])],
            array_filter($c['bands'], fn ($b) => trim((string) ($b['label'] ?? '')) !== '')));
        usort($c['bands'], fn ($a, $b) => $b['min'] <=> $a['min']);
        $c['prorata'] = array_map('intval', $c['prorata']);
        $c['range_penalty'] = $num($c['range_penalty']);
        $c['milestone_late_penalty'] = min(max($num($c['milestone_late_penalty']), 0), 1);
        $c['how_items'] = array_values(array_map(fn ($h) => [
            'code' => Str::upper(Str::slug(($h['code'] ?? '') ?: $h['label'], '_')), 'label' => trim($h['label']), 'description' => trim((string) ($h['description'] ?? '')),
        ], array_filter($c['how_items'], fn ($h) => trim((string) ($h['label'] ?? '')) !== '')));
        $c['contribution_rubric'] = array_combine([1, 2, 3, 4, 5], array_map(fn ($v) => trim((string) $v), array_slice(array_values($c['contribution_rubric']), 0, 5)));
        $c['appeal_days'] = (int) $c['appeal_days'];

        return $c;
    }

    public static function bandLabels(array $config): array
    {
        return array_column($config['bands'], 'label', 'code');
    }

    /** Konfigurasi periode lama dilengkapi kunci yang ditambahkan di versi berikutnya. */
    public static function withDefaults(array $config): array
    {
        return $config + array_diff_key(self::DEFAULTS, $config);
    }

    private function decode(?object $row): ?object
    {
        if ($row) {
            $row->config = self::withDefaults(json_decode((string) $row->config, true));
        }

        return $row;
    }
}
