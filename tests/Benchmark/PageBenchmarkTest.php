<?php

namespace Tests\Benchmark;

use App\Support\Security\Roles;
use App\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * Latensi server per halaman pada data 10.000 pegawai (hcms:load-seed), diukur di dalam proses
 * (tanpa jaringan): pemanasan 2× lalu N iterasi; p50/p95/maks dan jumlah query per request.
 * Target blueprint: p95 < 1 detik. Konkurensi diuji terpisah (ab / k6) di server sungguhan.
 */
class PageBenchmarkTest extends TestCase
{
    private const TARGET_MS = 1000;

    private static array $results = [];

    private static string $database = '';

    private static string $output = '';

    private static int $iterations = 0;

    protected function setUp(): void
    {
        parent::setUp();
        if (DB::connection()->getDatabaseName() !== 'hcms_load' || ! DB::table('tenants')->where('code', 'LOAD')->exists()) {
            $this->markTestSkipped('Butuh database hcms_load berisi tenant LOAD (php artisan hcms:load-seed).');
        }
        Roles::flushCache();
        self::$database = DB::connection()->getDatabaseName();
        self::$output = storage_path('app/benchmark');
    }

    public static function tearDownAfterClass(): void
    {
        if (! self::$results) {
            return;
        }
        $lines = ['# Benchmark halaman — '.now()->format('Y-m-d H:i').' WIB', '',
            'Data: tenant LOAD (10.000 pegawai, 40.000 KPI, 80.000 realisasi, 200.000 audit). Database `'.self::$database.'`, PHP '.PHP_VERSION.', '.self::$iterations.' iterasi setelah 2× pemanasan, di dalam proses (tanpa jaringan).', '',
            '| Halaman | Peran | cold (ms) | p50 (ms) | p95 (ms) | maks (ms) | query/request | Status |', '|---|---|---:|---:|---:|---:|---:|---|'];
        foreach (self::$results as $r) {
            $lines[] = sprintf('| `%s` | %s | %.0f | %.0f | %.0f | %.0f | %d | %s |', $r['uri'], $r['role'], $r['cold'], $r['p50'], $r['p95'], $r['max'], $r['queries'], $r['p95'] < self::TARGET_MS ? '✅' : '❌');
        }
        File::ensureDirectoryExists(self::$output);
        File::put(self::$output.'/latest.md', implode("\n", $lines)."\n");
        fwrite(STDOUT, "\n".implode("\n", $lines)."\n");
    }

    private function user(string $username): User
    {
        return User::where('username', $username)->firstOrFail();
    }

    /** @param  array<int, array{0:string,1:string}>  $pages  [uri, label peran] */
    private function measure(User $user, int $group, string $role, array $pages): void
    {
        $iterations = (int) (getenv('BENCH_ITERATIONS') ?: 15);
        self::$iterations = $iterations;
        foreach ($pages as $uri) {
            $session = ['mfa_passed' => true, 'gid' => $group, 'uid' => $user->id, 'eid' => $user->email,
                'fid' => $user->first_name.' '.$user->last_name, 'username' => $user->username, 'level' => $group, 'lang' => 'en'];
            $times = [];
            $queries = 0;
            $cold = 0.0;
            for ($i = 0; $i < $iterations + 2; $i++) {
                $count = 0;
                $slow = [];
                DB::listen(function ($q) use (&$count, &$slow) {
                    $count++;
                    $slow[] = [$q->time, $q->sql];
                });
                $t = hrtime(true);
                $response = $this->actingAs($user)->withSession($session)->get($uri);
                $ms = (hrtime(true) - $t) / 1e6;
                $this->assertContains($response->getStatusCode(), [200], "$uri ($role) status ".$response->getStatusCode().' '.($response->exception?->getMessage() ?? ''));
                if ($i === 0) {
                    $cold = $ms;
                }
                if ($i >= 2) {
                    $times[] = $ms;
                    $queries = $count;
                }
                // BENCH_TRACE=<potongan URI>: cetak 5 query terlambat pada iterasi terakhir
                if ($i === $iterations + 1 && ($trace = getenv('BENCH_TRACE')) && str_contains($uri, $trace)) {
                    usort($slow, fn ($a, $b) => $b[0] <=> $a[0]);
                    foreach (array_slice($slow, 0, 5) as [$t, $sql]) {
                        fwrite(STDOUT, sprintf("\n[%s] %7.1f ms  %s", $uri, $t, mb_substr(preg_replace('/\s+/', ' ', $sql), 0, 400)));
                    }
                }
                DB::getEventDispatcher()->forget('Illuminate\Database\Events\QueryExecuted');
                $this->app->forgetScopedInstances();
            }
            sort($times);
            $pick = fn (float $p) => $times[(int) min(count($times) - 1, ceil($p * count($times)) - 1)];
            self::$results[] = ['uri' => $uri, 'role' => $role, 'p50' => $pick(0.5), 'p95' => $pick(0.95), 'max' => end($times), 'queries' => $queries, 'cold' => $cold];
        }
    }

    public function test_halaman_hr(): void
    {
        $hr = $this->user('load.hr');
        $employee = (int) DB::table('employees')->where('employee_number', 'LD005000')->value('id');
        $plan = (int) DB::table('performance_plans')->where('employee_id', $employee)->value('id');
        $periods = DB::table('performance_periods')->pluck('id', 'code');
        $this->measure($hr, Roles::id(Roles::TENANT_ADMIN), 'HR (semua data)', [
            '/employees',
            '/employees?q=Rina',
            '/employees/'.$employee,
            '/competency/reports',
            '/competency/reports/employees/'.$employee,
            '/performance/periods/'.$periods['KIN-2026'],
            '/performance/plans/'.$plan.'/monitoring',
            '/executive/analytic/performance?period='.$periods['KIN-2026'],
            '/executive/analytic/performance?period='.$periods['KIN-2025'],
            '/performance/periods/'.$periods['KIN-2025'].'/ratings',
            '/core/audit',
            '/executive/dashboard',
            '/executive/analytics/turnover',
            '/executive/analytics/promotions',
            '/executive/analytics/deib',
            '/executive/analytics/workforce',
            '/executive/analytics/development',
        ]);
        $this->assertNotEmpty(self::$results);
    }

    public function test_halaman_atasan(): void
    {
        // load.u00050 = kepala departemen pertama (± 39 bawahan langsung)
        $this->measure($this->user('load.u00050'), Roles::id(Roles::LINE_MANAGER), 'Atasan (scoped)', [
            '/performance/team',
            '/employees',
            '/competency/reports',
            '/executive/analytic/performance',
        ]);
        $this->assertNotEmpty(self::$results);
    }

    public function test_halaman_pegawai(): void
    {
        $user = $this->user('load.u00500');
        $plan = (int) DB::table('performance_plans')->join('employees', 'employees.id', '=', 'performance_plans.employee_id')
            ->where('employees.user_id', $user->id)->value('performance_plans.id');
        $this->measure($user, Roles::id(Roles::EMPLOYEE), 'Pegawai (own)', [
            '/ess/profile',
            '/performance/my-plans',
            '/performance/plans/'.$plan.'/monitoring',
            '/performance/my-ratings',
            '/competency/my-assessments',
        ]);
        $this->assertNotEmpty(self::$results);
    }
}
