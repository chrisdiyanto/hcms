<?php

namespace App\Console\Commands;

use App\Domain\Competency\Services\CompetencyFramework;
use App\Domain\Competency\Services\CompetencyHistory;
use App\Domain\Organization\Services\OrgUnitClosure;
use App\Domain\Performance\Services\PerformancePolicy;
use App\Domain\Tenancy\Actions\CreateTenant;
use App\Support\Security\Roles;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Data uji beban (Fase 4): satu tenant dengan N pegawai beserta organisasi, posisi,
 * kompetensi, rencana & realisasi kinerja, rating periode lalu, dan audit trail.
 *
 * HANYA untuk database uji beban terpisah, mis.:
 *   DB_DATABASE=hcms_load php artisan migrate --force
 *   DB_DATABASE=hcms_load php artisan db:seed --class=TestCoreSeeder --force
 *   DB_DATABASE=hcms_load php artisan hcms:load-seed --employees=10000 --users=1000
 */
class LoadSeed extends Command
{
    protected $signature = 'hcms:load-seed {--employees=10000} {--users=1000} {--tenant=LOAD} {--audit=200000 : jumlah baris audit_logs}';

    protected $description = 'Isi database uji beban dengan data sintetis (jangan dijalankan di database produksi/dev)';

    private int $tenant;

    private int $company;

    private string $now;

    public function handle(): int
    {
        $db = DB::connection()->getDatabaseName();
        if (! str_contains($db, 'load') && ! $this->confirm("Database aktif \"{$db}\" bukan database uji beban. Lanjutkan?", false)) {
            return self::FAILURE;
        }
        if (DB::table('tenants')->where('code', $this->option('tenant'))->exists()) {
            $this->error('Tenant '.$this->option('tenant').' sudah ada. Gunakan database baru.');

            return self::FAILURE;
        }

        $n = (int) $this->option('employees');
        $this->now = now()->toDateTimeString();
        mt_srand(42);
        DB::disableQueryLog();
        $start = microtime(true);

        $this->step('Tenant & company', fn () => $this->tenantAndCompany());
        [$units, $byLevel] = $this->step('Unit organisasi (~250)', fn () => $this->units());
        $jobs = $this->step('Arsitektur jabatan', fn () => $this->jobs());
        $positions = $this->step("Posisi ($n) & reporting line", fn () => $this->positions($n, $byLevel, $jobs));
        $employees = $this->step("Pegawai ($n) & penugasan", fn () => $this->employees($positions));
        $this->step('Akun pengguna ('.$this->option('users').')', fn () => $this->users($employees, (int) $this->option('users')));
        $this->step('Kompetensi, profil job, skor & JFI', fn () => $this->competencies($jobs, $employees, $positions));
        $this->step('Kinerja: periode, rencana, KPI, realisasi, check-in, rating', fn () => $this->performance($employees, $positions));
        $this->step('Audit trail ('.$this->option('audit').' baris)', fn () => $this->audit((int) $this->option('audit'), $employees));
        $this->step('Closure table unit', fn () => OrgUnitClosure::rebuild($this->tenant));

        $this->info(sprintf('Selesai dalam %.1f detik. Tenant %s (id %d). Akun: load.hr (HR Administrator) dan load.u00001… (sandi seragam, hanya untuk database uji beban).',
            microtime(true) - $start, $this->option('tenant'), $this->tenant));

        return self::SUCCESS;
    }

    private function step(string $label, callable $fn): mixed
    {
        $t = microtime(true);
        $this->output->write(str_pad($label, 62, '.'));
        $result = $fn();
        $this->output->writeln(sprintf(' %6.1f dtk', microtime(true) - $t));

        return $result;
    }

    private function insert(string $table, array $rows, int $chunk = 1000): void
    {
        foreach (array_chunk($rows, $chunk) as $part) {
            DB::table($table)->insert($part);
        }
    }

    private function base(): array
    {
        return ['tenant_id' => $this->tenant, 'created_at' => $this->now];
    }

    // ------------------------------------------------------------ organisasi

    private function tenantAndCompany(): void
    {
        $this->tenant = DB::table('tenants')->insertGetId(['uuid' => (string) Str::uuid7(), 'code' => $this->option('tenant'), 'name' => 'Tenant Uji Beban',
            'status' => 'active', 'license_type' => 'enterprise', 'data_region' => 'ID', 'activated_at' => $this->now, 'created_at' => $this->now, 'updated_at' => $this->now]);
        $this->company = DB::table('corporate')->insertGetId(['tenant_id' => $this->tenant, 'uuid' => (string) Str::uuid7(), 'code' => 'LOAD1',
            'slug_corporate' => 'pt-uji-beban', 'corporate_name' => 'PT Uji Beban', 'created_at' => $this->now, 'updated_at' => $this->now]);
        CreateTenant::seedUnitReferences($this->tenant);
        CompetencyFramework::ensureCategories($this->tenant);
    }

    /** @return array{0: array<int,int>, 1: array<int, int[]>} unit id per kedalaman */
    private function units(): array
    {
        $category = (int) DB::table('unit_category')->where('tenant_id', $this->tenant)->value('id_unit_category');
        $type = (int) DB::table('unit_type')->where('tenant_id', $this->tenant)->value('id_unit_type');
        $make = fn (string $name, ?int $parent) => DB::table('unit')->insertGetId(['tenant_id' => $this->tenant, 'uuid' => (string) Str::uuid7(),
            'slug_unit' => Str::slug($name).'-'.Str::random(5), 'parent_id' => $parent, 'unit_name' => $name, 'unit_code' => strtoupper(Str::random(6)),
            'id_unit_category' => $category, 'id_corporate' => $this->company, 'unit_addres' => '', 'unit_explan' => '', 'id_unit_type' => $type, 'status' => 1]);

        $root = $make('Kantor Pusat', null);
        $byLevel = [0 => [$root], 1 => [], 2 => [], 3 => []];
        foreach (range(1, 8) as $d) {
            $dir = $make("Direktorat $d", $root);
            $byLevel[1][] = $dir;
            foreach (range(1, 5) as $v) {
                $div = $make("Divisi $d.$v", $dir);
                $byLevel[2][] = $div;
                foreach (range(1, 5) as $p) {
                    $byLevel[3][] = $make("Departemen $d.$v.$p", $div);
                }
            }
        }

        return [array_merge(...array_values($byLevel)), $byLevel];
    }

    private function jobs(): array
    {
        $families = [];
        foreach (['FIN' => 'Finance', 'RSK' => 'Risk', 'OPS' => 'Operations', 'IT' => 'Teknologi', 'HR' => 'SDM'] as $code => $name) {
            $families[] = DB::table('job_families')->insertGetId($this->base() + ['uuid' => (string) Str::uuid7(), 'code' => $code, 'name' => $name]);
        }
        $levels = [];
        foreach (['L1' => ['Staff', false], 'L2' => ['Supervisor', true], 'L3' => ['Manager', true], 'L4' => ['General Manager', true], 'L5' => ['Direktur', true]] as $code => [$name, $mgr]) {
            $levels[] = DB::table('job_levels')->insertGetId($this->base() + ['uuid' => (string) Str::uuid7(), 'code' => $code, 'name' => $name, 'rank' => count($levels) + 1, 'is_managerial' => $mgr]);
        }
        $grades = [];
        foreach ($levels as $i => $level) {
            foreach ([1, 2] as $g) {
                $grades[$i][] = DB::table('job_grades')->insertGetId($this->base() + ['uuid' => (string) Str::uuid7(), 'job_level_id' => $level, 'code' => 'G'.($i * 2 + $g), 'name' => 'Grade '.($i * 2 + $g), 'rank' => $i * 2 + $g]);
            }
        }
        $jobs = [];
        foreach ($families as $f => $family) {
            foreach (range(0, 9) as $j) {
                $level = min(4, intdiv($j, 2));
                $jobs[$level][] = DB::table('jobs_catalog')->insertGetId($this->base() + ['uuid' => (string) Str::uuid7(), 'job_family_id' => $family,
                    'code' => "J$f-$j", 'title' => "Jabatan $f-$j", 'grade_min_id' => $grades[$level][0], 'grade_max_id' => $grades[$level][1]]);
            }
        }

        return ['by_level' => $jobs, 'grades' => $grades];
    }

    /** Posisi pimpinan per unit + posisi staf di departemen; reporting line solid. */
    private function positions(int $n, array $byLevel, array $jobs): array
    {
        $rows = [];
        $meta = [];
        $code = 0;
        $add = function (int $unit, int $level, ?int $parentIdx) use (&$rows, &$meta, &$code, $jobs) {
            $code++;
            $job = $jobs['by_level'][$level][array_rand($jobs['by_level'][$level])];
            $rows[] = $this->base() + ['uuid' => (string) Str::uuid7(), 'company_id' => $this->company, 'unit_id' => $unit, 'job_id' => $job,
                'grade_id' => $jobs['grades'][$level][mt_rand(0, 1)], 'code' => sprintf('P%06d', $code), 'title' => ['Staf', 'Supervisor', 'Manajer', 'Kepala Divisi', 'Direktur'][$level].' '.$code,
                'status' => 'active', 'headcount' => 1, 'effective_from' => '2015-01-01'];
            $meta[] = ['parent' => $parentIdx, 'unit' => $unit, 'job' => $job];

            return count($rows) - 1;
        };
        $ceo = $add($byLevel[0][0], 4, null);
        $dirIdx = $divIdx = $deptIdx = [];
        foreach ($byLevel[1] as $u) {
            $dirIdx[$u] = $add($u, 4, $ceo);
        }
        $divParents = DB::table('unit')->whereIn('id_unit', $byLevel[2])->pluck('parent_id', 'id_unit');
        foreach ($byLevel[2] as $u) {
            $divIdx[$u] = $add($u, 3, $dirIdx[$divParents[$u]]);
        }
        $deptParents = DB::table('unit')->whereIn('id_unit', $byLevel[3])->pluck('parent_id', 'id_unit');
        foreach ($byLevel[3] as $u) {
            $deptIdx[$u] = $add($u, 2, $divIdx[$deptParents[$u]]);
        }
        $depts = $byLevel[3];
        for ($i = count($rows); $i < $n; $i++) {
            $u = $depts[$i % count($depts)];
            $add($u, $i % 10 === 0 ? 1 : 0, $deptIdx[$u]);
        }

        $this->insert('positions', $rows);
        $ids = DB::table('positions')->where('tenant_id', $this->tenant)->orderBy('id')->pluck('id')->all();
        $rel = [];
        foreach ($meta as $i => $m) {
            if ($m['parent'] !== null) {
                $rel[] = ['tenant_id' => $this->tenant, 'position_id' => $ids[$i], 'reports_to_id' => $ids[$m['parent']], 'type' => 'solid', 'effective_from' => '2015-01-01', 'created_at' => $this->now];
            }
        }
        $this->insert('position_relationships', $rel);

        return array_map(fn ($id, $m) => ['id' => $id] + $m, $ids, $meta);
    }

    private function employees(array $positions): array
    {
        $first = ['Andi', 'Budi', 'Citra', 'Dewi', 'Eko', 'Fitri', 'Gita', 'Hadi', 'Indah', 'Joko', 'Kartika', 'Lukman', 'Maya', 'Nanda', 'Oki', 'Putri', 'Rina', 'Sari', 'Teguh', 'Wulan'];
        $last = ['Pratama', 'Santoso', 'Wijaya', 'Lestari', 'Saputra', 'Kusuma', 'Hidayat', 'Nugroho', 'Permata', 'Siregar', 'Harahap', 'Simanjuntak'];
        $rows = [];
        foreach ($positions as $i => $p) {
            $rows[] = $this->base() + ['uuid' => (string) Str::uuid7(), 'company_id' => $this->company, 'employee_number' => sprintf('LD%06d', $i + 1),
                'full_name' => $first[mt_rand(0, 19)].' '.$last[mt_rand(0, 11)], 'gender' => mt_rand(0, 1) ? 'M' : 'F', 'birth_date' => sprintf('19%02d-%02d-%02d', mt_rand(65, 99), mt_rand(1, 12), mt_rand(1, 28)),
                'employment_status' => ['pkwtt', 'pkwtt', 'pkwtt', 'pkwt', 'probation'][mt_rand(0, 4)], 'join_date' => sprintf('20%02d-%02d-01', mt_rand(15, 25), mt_rand(1, 12)), 'status' => 'active'];
        }
        $this->insert('employees', $rows);
        $ids = DB::table('employees')->where('tenant_id', $this->tenant)->orderBy('id')->pluck('id')->all();

        $assign = [];
        foreach ($positions as $i => $p) {
            $assign[] = $this->base() + ['uuid' => (string) Str::uuid7(), 'employee_id' => $ids[$i], 'position_id' => $p['id'], 'type' => 'primary', 'effective_from' => $rows[$i]['join_date']];
        }
        $this->insert('employee_assignments', $assign);

        return $ids;
    }

    private function users(array $employees, int $count): void
    {
        $hash = Hash::make('LoadTest#2026');
        $rows = [['tenant_id' => $this->tenant, 'username' => 'load.hr', 'email' => 'load.hr@example.test', 'first_name' => 'HR', 'last_name' => 'Uji Beban',
            'password' => $hash, 'avatar' => '', 'active' => 1, 'created_at' => $this->now]];
        foreach (array_slice($employees, 0, $count) as $i => $emp) {
            $rows[] = ['tenant_id' => $this->tenant, 'username' => sprintf('load.u%05d', $i + 1), 'email' => sprintf('load.u%05d@example.test', $i + 1),
                'first_name' => 'Pegawai', 'last_name' => (string) ($i + 1), 'password' => $hash, 'avatar' => '', 'active' => 1, 'created_at' => $this->now];
        }
        $this->insert('tb_users', $rows);
        $ids = DB::table('tb_users')->where('tenant_id', $this->tenant)->orderBy('id')->pluck('id', 'username');
        $groups = [['user_id' => $ids['load.hr'], 'group_id' => Roles::id(Roles::HR_ADMIN)]];
        foreach (array_slice($employees, 0, $count) as $i => $emp) {
            $uid = $ids[sprintf('load.u%05d', $i + 1)];
            DB::table('employees')->where('id', $emp)->update(['user_id' => $uid]);
            // 249 posisi pertama = pimpinan
            $groups[] = ['user_id' => $uid, 'group_id' => Roles::id($i < 249 ? Roles::LINE_MANAGER : Roles::EMPLOYEE)];
        }
        $this->insert('tb_users_groups', $groups);
    }

    // ------------------------------------------------------------ kompetensi

    private function competencies(array $jobs, array $employees, array $positions): void
    {
        $version = DB::table('competency_framework_versions')->insertGetId($this->base() + ['uuid' => (string) Str::uuid7(), 'version' => 1, 'name' => 'Kamus v1', 'status' => 'published', 'published_at' => $this->now]);
        $categories = DB::table('competency_categories')->where('tenant_id', $this->tenant)->pluck('id')->all();
        $comps = [];
        foreach (range(1, 20) as $c) {
            $comps[] = DB::table('competencies')->insertGetId($this->base() + ['uuid' => (string) Str::uuid7(), 'framework_version_id' => $version,
                'category_id' => $categories[$c % count($categories)], 'code' => 'K'.str_pad((string) $c, 2, '0', STR_PAD_LEFT), 'name' => "Kompetensi $c"]);
        }
        $profile = [];
        foreach (array_merge(...array_values($jobs['by_level'])) as $job) {
            foreach ((array) array_rand(array_flip($comps), 5) as $k => $comp) {
                $profile[] = $this->base() + ['job_id' => $job, 'competency_id' => $comp, 'required_level' => mt_rand(2, 4), 'criticality' => [3, 3, 2, 2, 1][$k]];
            }
        }
        $this->insert('job_competencies', $profile);
        $profileByJob = collect($profile)->groupBy('job_id');
        $codes = DB::table('competencies')->whereIn('id', $comps)->pluck('code', 'id');

        $scores = $gaps = $fits = [];
        $bands = ['ready_now', 'ready_1_2', 'ready_later'];
        foreach (array_slice($employees, 0, intdiv(count($employees), 2)) as $i => $emp) {
            $pos = $positions[$i];
            foreach ($profileByJob[$pos['job']] as $req) {
                $actual = mt_rand(10, 50) / 10;
                $scores[] = $this->base() + ['employee_id' => $emp, 'competency_code' => $codes[$req['competency_id']], 'competency_id' => $req['competency_id'], 'score' => $actual,
                    'source' => 'raters', 'assessment_id' => null, 'assessed_at' => '2026-03-01', 'valid_until' => '2028-03-01'];
                $gaps[] = ['tenant_id' => $this->tenant, 'employee_id' => $emp, 'position_id' => $pos['id'], 'competency_id' => $req['competency_id'], 'competency_code' => $codes[$req['competency_id']],
                    'required_level' => $req['required_level'], 'criticality' => $req['criticality'], 'actual' => $actual, 'gap' => $actual - $req['required_level'], 'computed_at' => $this->now];
            }
            $fits[] = ['tenant_id' => $this->tenant, 'employee_id' => $emp, 'position_id' => $pos['id'], 'jfi' => mt_rand(500, 1000) / 10, 'band' => $bands[mt_rand(0, 2)], 'complete' => 1, 'computed_at' => $this->now];
        }
        // employee_competencies.assessment_id wajib; buat satu asesmen sintetis per tenant
        $assessment = DB::table('competency_assessments')->insertGetId($this->base() + ['uuid' => (string) Str::uuid7(), 'employee_id' => $employees[0],
            'framework_version_id' => $version, 'position_id' => $positions[0]['id'], 'title' => 'Asesmen massal (sintetis)', 'status' => 'closed', 'closed_at' => $this->now]);
        $this->insert('employee_competencies', array_map(fn ($r) => ['assessment_id' => $assessment] + $r, $scores));
        $this->insert('competency_gaps', $gaps);
        $this->insert('job_fit_results', $fits);
        // Hasil sintetis menjadi titik awal riwayat kompetensi (prasyarat P1 CBHRM)
        CompetencyHistory::backfill();
    }

    // ------------------------------------------------------------ kinerja

    private function performance(array $employees, array $positions): void
    {
        $policy = DB::table('performance_policies')->insertGetId($this->base() + ['uuid' => (string) Str::uuid7(), 'version' => 1, 'name' => 'Kebijakan v1', 'status' => 'active',
            'config' => json_encode(PerformancePolicy::DEFAULTS), 'published_at' => $this->now]);
        $snapshot = json_encode(PerformancePolicy::DEFAULTS + ['_version' => 1, '_name' => 'Kebijakan v1']);
        $prev = DB::table('performance_periods')->insertGetId($this->base() + ['uuid' => (string) Str::uuid7(), 'code' => 'KIN-2025', 'name' => 'Kinerja 2025',
            'start_date' => '2025-01-01', 'end_date' => '2025-12-31', 'status' => 'closed', 'policy_id' => $policy, 'policy_snapshot' => $snapshot, 'opened_at' => '2025-01-05']);
        $period = DB::table('performance_periods')->insertGetId($this->base() + ['uuid' => (string) Str::uuid7(), 'code' => 'KIN-2026', 'name' => 'Kinerja 2026',
            'start_date' => '2026-01-01', 'end_date' => '2026-12-31', 'status' => 'monitoring', 'policy_id' => $policy, 'policy_snapshot' => $snapshot, 'opened_at' => '2026-01-05']);
        $assignments = DB::table('employee_assignments')->where('tenant_id', $this->tenant)->pluck('id', 'employee_id');

        $plans = [];
        foreach ($employees as $i => $emp) {
            $plans[] = $this->base() + ['uuid' => (string) Str::uuid7(), 'period_id' => $period, 'employee_id' => $emp, 'assignment_id' => $assignments[$emp],
                'position_id' => $positions[$i]['id'], 'start_date' => '2026-01-01', 'end_date' => '2026-12-31', 'status' => 'approved', 'revision' => 1, 'approved_at' => '2026-02-01'];
        }
        $this->insert('performance_plans', $plans);
        $planIds = DB::table('performance_plans')->where('period_id', $period)->orderBy('id')->pluck('id')->all();

        $objectives = [];
        foreach ($planIds as $plan) {
            foreach ([['Pendapatan', 'higher', 30], ['Biaya', 'lower', 25], ['Kualitas', 'higher', 25], ['Proyek', 'higher', 20]] as $k => [$title, $type, $weight]) {
                $objectives[] = ['tenant_id' => $this->tenant, 'plan_id' => $plan, 'title' => "$title $k", 'kpi_type' => $type, 'target' => 100, 'weight' => $weight, 'sort_order' => $k, 'created_at' => $this->now];
            }
        }
        $this->insert('performance_objectives', $objectives);

        $updates = [];
        $users = DB::table('performance_plans')->join('employees', 'employees.id', '=', 'performance_plans.employee_id')->where('period_id', $period)->pluck('employees.user_id', 'performance_plans.id');
        foreach (DB::table('performance_objectives')->whereIn('plan_id', $planIds)->get(['id', 'plan_id'])->all() as $o) {
            foreach (['2026-04-30', '2026-09-30'] as $date) {
                $updates[] = ['tenant_id' => $this->tenant, 'plan_id' => $o->plan_id, 'objective_id' => $o->id, 'as_of' => $date, 'realization' => mt_rand(60, 120),
                    'achievement' => mt_rand(60, 120), 'created_by' => $users[$o->plan_id] ?? 1, 'created_at' => $this->now];
            }
        }
        $this->insert('performance_updates', $updates, 2000);

        $checkins = [];
        foreach (array_slice($planIds, 0, intdiv(count($planIds), 2)) as $plan) {
            $checkins[] = ['tenant_id' => $this->tenant, 'plan_id' => $plan, 'type' => 'midyear', 'checkin_date' => '2026-07-01', 'employee_notes' => 'Progres sesuai rencana',
                'manager_notes' => 'Lanjutkan', 'status' => 'completed', 'created_by' => 1, 'created_at' => $this->now];
        }
        $this->insert('performance_checkins', $checkins);

        $ratings = [];
        $bands = [[90, 'outstanding', 'Outstanding'], [80, 'exceeds', 'Exceeds Expectation'], [70, 'meets', 'Meets Expectation'], [60, 'below', 'Below Expectation'], [0, 'unsatisfactory', 'Unsatisfactory']];
        foreach ($employees as $emp) {
            $score = mt_rand(5000, 10000) / 100;
            $band = collect($bands)->first(fn ($b) => $score >= $b[0]);
            $ratings[] = ['tenant_id' => $this->tenant, 'uuid' => (string) Str::uuid7(), 'period_id' => $prev, 'employee_id' => $emp, 'revision' => 1, 'is_current' => 1, 'status' => 'final',
                'rated' => 1, 'raw_score' => $score, 'final_score' => $score, 'band' => $band[1], 'band_label' => $band[2], 'published_at' => '2026-01-20', 'appeal_deadline' => '2026-02-03',
                'finalized_at' => '2026-02-10', 'created_at' => $this->now];
        }
        $this->insert('performance_ratings', $ratings);
    }

    private function audit(int $count, array $employees): void
    {
        $rows = [];
        for ($i = 0; $i < $count; $i++) {
            $rows[] = ['tenant_id' => $this->tenant, 'user_id' => null, 'event' => ['created', 'updated', 'sensitive_viewed'][$i % 3], 'auditable_type' => 'employees',
                'auditable_id' => (string) $employees[$i % count($employees)], 'description' => 'Data sintetis uji beban', 'created_at' => now()->subMinutes($count - $i)->toDateTimeString()];
            if (count($rows) === 5000) {
                DB::table('audit_logs')->insert($rows);
                $rows = [];
            }
        }
        if ($rows) {
            DB::table('audit_logs')->insert($rows);
        }
    }
}
