<?php

namespace Database\Seeders;

use App\Domain\Competency\Services\CompetencyHistory;
use App\Domain\Performance\Services\PerformancePolicy;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Data demonstrasi HCMS lintas modul. Aman dipanggil berulang kali; data
 * menggunakan kode unik stabil dan hanya mengisi tabel yang sudah dimigrasikan.
 * Jalankan dengan: php artisan db:seed --class=DemoDataSeeder
 */
class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        // Akun demo memakai sandi seragam: jangan pernah dibuat di produksi
        if (app()->environment('production')) {
            $this->command?->error('DemoDataSeeder ditolak di lingkungan production.');

            return;
        }
        if (! Schema::hasTable('tenants')) {
            $this->command?->warn('Migrasi tenant belum dijalankan; data demo dilewati.');

            return;
        }
        foreach (['corporate', 'unit', 'unit_category', 'unit_type', 'tb_users', 'job_families', 'employees'] as $requiredTable) {
            if (! Schema::hasTable($requiredTable)) {
                $this->command?->warn("Tabel {$requiredTable} belum tersedia; data demo dilewati.");

                return;
            }
        }

        $tenantId = (int) DB::table('tenants')->where('code', 'DEFAULT')->value('id');
        if (! $tenantId) {
            $this->command?->warn('Tenant DEFAULT tidak ditemukan; jalankan migrasi terlebih dahulu.');

            return;
        }

        $now = now();
        $companyId = (int) DB::table('corporate')->where('tenant_id', $tenantId)->value('id_corporate');
        $unitId = (int) DB::table('unit')->where('tenant_id', $tenantId)->value('id_unit');
        if (! $companyId || ! $unitId) {
            $this->command?->warn('Data perusahaan/unit awal belum ada; jalankan DatabaseSeeder terlebih dahulu.');

            return;
        }

        $adminId = (int) DB::table('tb_users')->where('tenant_id', $tenantId)->value('id');
        $userIds = [$adminId];
        $profiles = [
            ['andi.pratama', 'Andi', 'Pratama', 'HR Manager', 'HR'],
            ['siti.rahayu', 'Siti', 'Rahayu', 'People Development Lead', 'HR'],
            ['budi.santoso', 'Budi', 'Santoso', 'Software Engineer', 'IT'],
            ['maya.putri', 'Maya', 'Putri', 'Business Analyst', 'IT'],
            ['rudi.hartono', 'Rudi', 'Hartono', 'Finance Analyst', 'FIN'],
            ['dewi.lestari', 'Dewi', 'Lestari', 'Operations Lead', 'OPS'],
            ['tono.wijaya', 'Tono', 'Wijaya', 'Operations Officer', 'OPS'],
            ['nina.kusuma', 'Nina', 'Kusuma', 'Talent Acquisition Specialist', 'HR'],
        ];

        $units = [$unitId];
        foreach ([['HR', 'Human Resources'], ['IT', 'Technology'], ['FIN', 'Finance'], ['OPS', 'Operations']] as [$code, $name]) {
            $unit = DB::table('unit')->where('tenant_id', $tenantId)->where('unit_code', $code)->first();
            if (! $unit) {
                $newId = DB::table('unit')->insertGetId([
                    'slug_unit' => Str::random(20), 'tenant_id' => $tenantId,
                    'parent_id' => $unitId, 'urutan' => count($units) + 1,
                    'unit_name' => $name, 'unit_code' => $code,
                    'id_unit_category' => DB::table('unit_category')->value('id_unit_category'),
                    'id_corporate' => $companyId, 'unit_addres' => '',
                    'unit_explan' => 'Unit contoh untuk demonstrasi HCMS',
                    'id_unit_type' => DB::table('unit_type')->value('id_unit_type'),
                    'status' => 1, 'uuid' => (string) Str::uuid7(),
                ]);
                $unit = DB::table('unit')->where('id_unit', $newId)->first();
            }
            $units[$code] = (int) $unit->id_unit;
        }

        foreach ($profiles as $index => [$username, $first, $last, $title, $unitCode]) {
            $userId = DB::table('tb_users')->where('email', $username.'@example.test')->value('id');
            if (! $userId) {
                $userId = DB::table('tb_users')->insertGetId([
                    'tenant_id' => $tenantId, 'username' => $username,
                    'nik' => 'DEMO'.str_pad((string) ($index + 1), 4, '0', STR_PAD_LEFT),
                    'hp' => '081200000'.str_pad((string) ($index + 1), 3, '0', STR_PAD_LEFT),
                    'password' => bcrypt('password'), 'email' => $username.'@example.test',
                    'first_name' => $first, 'last_name' => $last, 'avatar' => '1.jpg',
                    'active' => 1, 'login_attempt' => 0, 'created_at' => $now, 'updated_at' => $now,
                ]);
            }
            $userIds[] = (int) $userId;
            if (Schema::hasTable('tb_users_groups') && Schema::hasTable('tb_groups')) {
                $employeeGroupId = DB::table('tb_groups')->where('code', 'employee')->value('group_id');
                if ($employeeGroupId && ! DB::table('tb_users_groups')->where('user_id', $userId)->where('group_id', $employeeGroupId)->exists()) {
                    DB::table('tb_users_groups')->insert(['user_id' => $userId, 'group_id' => $employeeGroupId]);
                }
            }
        }

        // Job architecture: family → levels/grades → jobs → positions.
        $familyId = $this->master('job_families', $tenantId, 'OPS', ['name' => 'Operations & Corporate Services', 'description' => 'Fungsi pendukung bisnis']);
        $levels = [];
        foreach ([['L1', 'Staff', 1, false], ['L2', 'Senior / Lead', 2, false], ['L3', 'Manager', 3, true]] as [$code, $name, $rank, $managerial]) {
            $levels[$code] = $this->master('job_levels', $tenantId, $code, ['name' => $name, 'rank' => $rank, 'is_managerial' => $managerial]);
        }
        $grades = [];
        foreach (['L1', 'L2', 'L3'] as $i => $code) {
            $grades[$code] = $this->master('job_grades', $tenantId, 'G'.($i + 1), ['job_level_id' => $levels[$code], 'name' => 'Grade '.($i + 1), 'rank' => $i + 1]);
        }
        $jobData = [
            'HRM' => ['Human Resources Manager', 'L3'], 'PDT' => ['People Development Lead', 'L2'],
            'SWE' => ['Software Engineer', 'L1'], 'BA' => ['Business Analyst', 'L1'],
            'FA' => ['Finance Analyst', 'L1'], 'OL' => ['Operations Lead', 'L2'],
            'OO' => ['Operations Officer', 'L1'], 'TA' => ['Talent Acquisition Specialist', 'L1'],
        ];
        $jobs = [];
        foreach ($jobData as $code => [$title, $grade]) {
            $jobs[$code] = $this->master('jobs_catalog', $tenantId, $code, [
                'job_family_id' => $familyId, 'title' => $title,
                'purpose' => 'Menjalankan fungsi '.$title.' secara efektif dan terukur.',
                'responsibilities' => 'Perencanaan, pelaksanaan, kolaborasi lintas tim, dan pelaporan.',
                'grade_min_id' => $grades['L1'], 'grade_max_id' => $grades[$grade],
            ]);
        }
        $positionIds = [];
        foreach ($profiles as $i => [$username, $first, $last, $title, $unitCode]) {
            $code = array_keys($jobData)[$i];
            $positionIds[$i] = $this->master('positions', $tenantId, 'POS-'.strtoupper($username), [
                'company_id' => $companyId, 'unit_id' => $units[$unitCode], 'job_id' => $jobs[$code],
                'grade_id' => $grades[$jobData[$code][1]], 'title' => $title, 'status' => 'active',
                'is_critical' => in_array($code, ['HRM', 'OL'], true), 'work_location' => 'Jakarta',
                'headcount' => 1, 'effective_from' => '2025-01-01',
            ]);
        }

        $employeeIds = [];
        foreach ($profiles as $i => [$username, $first, $last, $title, $unitCode]) {
            $number = 'DEMO-'.str_pad((string) ($i + 1), 4, '0', STR_PAD_LEFT);
            $employee = DB::table('employees')->where('tenant_id', $tenantId)->where('employee_number', $number)->first();
            if (! $employee) {
                $id = DB::table('employees')->insertGetId([
                    'tenant_id' => $tenantId, 'uuid' => (string) Str::uuid7(), 'company_id' => $companyId,
                    'user_id' => $userIds[$i + 1], 'employee_number' => $number,
                    'full_name' => $first.' '.$last, 'birth_place' => 'Jakarta',
                    'birth_date' => sprintf('19%02d-%02d-%02d', 85 + $i, ($i % 12) + 1, ($i % 27) + 1),
                    'gender' => $i % 2 ? 'F' : 'M', 'marital_status' => $i % 2 ? 'married' : 'single',
                    'ptkp_status' => $i % 2 ? 'K/0' : 'TK/0', 'email' => $username.'@example.test',
                    'phone' => '081200000'.str_pad((string) ($i + 1), 3, '0', STR_PAD_LEFT),
                    'employment_status' => 'pkwtt', 'join_date' => sprintf('20%02d-01-%02d', 18 + ($i % 7), $i + 1),
                    'status' => 'active', 'created_at' => $now, 'updated_at' => $now,
                ]);
                $employee = DB::table('employees')->where('id', $id)->first();
            }
            $employeeIds[$i] = (int) $employee->id;
            $this->upsert('employee_assignments', ['employee_id' => $employee->id, 'type' => 'primary'], [
                'tenant_id' => $tenantId, 'uuid' => (string) Str::uuid7(), 'position_id' => $positionIds[$i],
                'effective_from' => '2025-01-01', 'decree_number' => 'SK/DEMO/'.($i + 1).'/2025',
                'decree_date' => '2025-01-01', 'created_at' => $now, 'updated_at' => $now,
            ]);
            $this->child('employee_education', ['employee_id' => $employee->id], [
                'tenant_id' => $tenantId, 'level' => 's1', 'institution' => 'Universitas Indonesia',
                'major' => 'Manajemen', 'graduation_year' => 2010 + $i, 'created_at' => $now, 'updated_at' => $now,
            ]);
        }

        $this->seedExecutiveTrends($tenantId, $employeeIds, $positionIds, $grades, $units, $companyId, $now);
        $this->seedCompetencies($tenantId, $jobs, $employeeIds, $positionIds, $userIds, $now);
        $this->seedWorkflow($tenantId, $employeeIds, $userIds, $now);
        $this->seedOperations($tenantId, $adminId, $now);
        $this->seedPerformance($tenantId, $employeeIds, $positionIds, $now);

        $this->command?->info('Data demo berhasil disiapkan: 8 pegawai, struktur jabatan, kompetensi, asesmen, workflow, dan data operasional yang tersedia.');
    }

    private function master(string $table, int $tenantId, string $code, array $values): int
    {
        $row = DB::table($table)->where('tenant_id', $tenantId)->where('code', $code)->first();
        $values += ['tenant_id' => $tenantId, 'uuid' => (string) Str::uuid7(), 'created_at' => now(), 'updated_at' => now()];
        if ($row) {
            unset($values['uuid'], $values['created_at']);
            DB::table($table)->where('id', $row->id)->update($values);

            return (int) $row->id;
        }

        return (int) DB::table($table)->insertGetId($values + ['code' => $code]);
    }

    private function upsert(string $table, array $keys, array $values): void
    {
        if (! Schema::hasTable($table)) {
            return;
        }
        DB::table($table)->updateOrInsert($keys, $values);
    }

    private function child(string $table, array $keys, array $values): void
    {
        if (! Schema::hasTable($table)) {
            return;
        }
        if (! DB::table($table)->where($keys)->exists()) {
            DB::table($table)->insert($keys + $values);
        }
    }

    private function seedCompetencies(int $tenantId, array $jobs, array $employees, array $positions, array $users, $now): void
    {
        if (! Schema::hasTable('competency_framework_versions')) {
            return;
        }
        $framework = DB::table('competency_framework_versions')->where('tenant_id', $tenantId)->where('version', 1)->first();
        if (! $framework) {
            $frameworkId = DB::table('competency_framework_versions')->insertGetId([
                'tenant_id' => $tenantId, 'uuid' => (string) Str::uuid7(), 'version' => 1,
                'name' => 'Kamus Kompetensi HCMS 2026', 'status' => 'published',
                'effective_from' => '2026-01-01', 'published_at' => $now, 'published_by' => $users[0] ?: null,
                'notes' => 'Kamus contoh untuk demonstrasi.', 'created_at' => $now, 'updated_at' => $now,
            ]);
        } else {
            $frameworkId = (int) $framework->id;
        }
        $competencySeeds = [
            ['CORE', 'Kompetensi Inti', 'COM', 'Komunikasi Efektif'],
            ['CORE', 'Kompetensi Inti', 'COL', 'Kolaborasi'],
            ['LEAD', 'Kepemimpinan', 'LEA', 'Kepemimpinan Tim'],
            ['DIG', 'Digital', 'ANA', 'Analisis Data'],
        ];
        $competencies = [];
        foreach ($competencySeeds as [$catCode, $catName, $code, $name]) {
            $this->upsert('competency_categories', ['tenant_id' => $tenantId, 'code' => $catCode], ['name' => $catName, 'sort_order' => 1, 'created_at' => $now, 'updated_at' => $now]);
            $categoryId = (int) DB::table('competency_categories')->where('tenant_id', $tenantId)->where('code', $catCode)->value('id');
            $competency = DB::table('competencies')->where('framework_version_id', $frameworkId)->where('code', $code)->first();
            if (! $competency) {
                $id = DB::table('competencies')->insertGetId([
                    'tenant_id' => $tenantId, 'uuid' => (string) Str::uuid7(), 'framework_version_id' => $frameworkId,
                    'category_id' => $categoryId, 'code' => $code, 'name' => $name,
                    'definition' => $name.' dalam konteks kerja sehari-hari.', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now,
                ]);
                $competency = DB::table('competencies')->where('id', $id)->first();
            }
            $competencies[] = $competency;
            for ($level = 1; $level <= 5; $level++) {
                $this->upsert('competency_levels', ['competency_id' => $competency->id, 'level' => $level], [
                    'tenant_id' => $tenantId, 'title' => 'Level '.$level,
                    'description' => 'Menunjukkan '.$name.' pada tingkat '.$level.'.',
                ]);
            }
            foreach ($jobs as $jobId) {
                $this->upsert('job_competencies', ['job_id' => $jobId, 'competency_id' => $competency->id], [
                    'tenant_id' => $tenantId, 'required_level' => 3, 'criticality' => $code === 'LEA' ? 2 : 1,
                    'created_at' => $now, 'updated_at' => $now,
                ]);
            }
        }
        if (! Schema::hasTable('competency_assessments') || ! $employees) {
            return;
        }
        $assessment = DB::table('competency_assessments')->where('tenant_id', $tenantId)->where('title', 'Asesmen Kompetensi Demo 2026')->first();
        if (! $assessment) {
            $assessmentId = DB::table('competency_assessments')->insertGetId([
                'tenant_id' => $tenantId, 'uuid' => (string) Str::uuid7(), 'employee_id' => $employees[2],
                'framework_version_id' => $frameworkId, 'position_id' => $positions[2], 'title' => 'Asesmen Kompetensi Demo 2026',
                'status' => 'closed', 'due_date' => '2026-11-30', 'closed_at' => $now,
                'valid_until' => '2028-11-17', 'created_by' => $users[0] ?: null, 'closed_by' => $users[0] ?: null,
                'created_at' => $now, 'updated_at' => $now,
            ]);
        } else {
            $assessmentId = (int) $assessment->id;
        }
        foreach ([['self', $users[3] ?? $users[0]], ['manager', $users[1] ?? $users[0]], ['peer', $users[4] ?? $users[0]]] as [$type, $raterUser]) {
            if (! $raterUser) {
                continue;
            }
            $this->upsert('assessment_raters', ['assessment_id' => $assessmentId, 'rater_user_id' => $raterUser, 'rater_type' => $type], [
                'tenant_id' => $tenantId, 'status' => 'submitted', 'submitted_at' => $now, 'created_at' => $now, 'updated_at' => $now,
            ]);
            $raterId = (int) DB::table('assessment_raters')->where('assessment_id', $assessmentId)->where('rater_user_id', $raterUser)->where('rater_type', $type)->value('id');
            foreach ($competencies as $i => $competency) {
                DB::table('competency_assessment_details')->updateOrInsert(
                    ['rater_id' => $raterId, 'competency_id' => $competency->id],
                    ['tenant_id' => $tenantId, 'assessment_id' => $assessmentId, 'score' => 3 + (($i + strlen($type)) % 3), 'evidence' => 'Contoh bukti hasil kolaborasi dan penyelesaian pekerjaan.', 'created_at' => $now, 'updated_at' => $now]
                );
            }
        }
        $employeeId = $employees[2];
        foreach ($competencies as $i => $competency) {
            $score = 2 + ($i % 3);
            $this->upsert('employee_competencies', ['employee_id' => $employeeId, 'competency_code' => $competency->code], [
                'tenant_id' => $tenantId, 'competency_id' => $competency->id, 'score' => $score, 'source' => 'raters',
                'assessment_id' => $assessmentId, 'assessed_at' => '2026-11-17', 'valid_until' => '2028-11-17',
                'breakdown' => json_encode(['self' => $score, 'manager' => min(5, $score + 1), 'peer' => $score]),
                'created_at' => $now, 'updated_at' => $now,
            ]);
            $this->upsert('competency_gaps', ['employee_id' => $employeeId, 'position_id' => $positions[2], 'competency_code' => $competency->code], [
                'tenant_id' => $tenantId, 'competency_id' => $competency->id, 'required_level' => 3,
                'criticality' => $competency->code === 'LEA' ? 2 : 1, 'actual' => $score, 'gap' => $score - 3,
                'computed_at' => $now,
            ]);
        }
        if (Schema::hasTable('competency_idp_suggestions') && isset($competencies[0])) {
            $this->upsert('competency_idp_suggestions', ['employee_id' => $employeeId, 'competency_code' => $competencies[0]->code, 'assessment_id' => $assessmentId], [
                'tenant_id' => $tenantId, 'competency_id' => $competencies[0]->id, 'current_level' => 2,
                'target_level' => 3, 'status' => 'accepted', 'created_at' => $now, 'updated_at' => $now,
            ]);
        }
        if (Schema::hasTable('employee_competency_history')) {
            CompetencyHistory::backfill();
        }
    }

    private function seedWorkflow(int $tenantId, array $employees, array $users, $now): void
    {
        if (! Schema::hasTable('workflow_definitions')) {
            return;
        }
        $def = DB::table('workflow_definitions')->where('tenant_id', $tenantId)->where('code', 'DEMO-EMPLOYEE-CHANGE')->first();
        if (! $def) {
            $definitionId = DB::table('workflow_definitions')->insertGetId(['tenant_id' => $tenantId, 'code' => 'DEMO-EMPLOYEE-CHANGE', 'name' => 'Perubahan Data Pegawai', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now]);
            DB::table('workflow_versions')->insert(['tenant_id' => $tenantId, 'definition_id' => $definitionId, 'version' => 1,
                'steps' => json_encode([['name' => 'Persetujuan Atasan'], ['name' => 'Verifikasi HR']]), 'is_current' => true,
                'created_at' => $now, 'updated_at' => $now]);
        } else {
            $definitionId = (int) $def->id;
        }
        $versionId = (int) DB::table('workflow_versions')->where('definition_id', $definitionId)->value('id');
        if (! $versionId || ! $employees || ! $users[0]) {
            return;
        }
        $requestId = (int) DB::table('employee_change_requests')->where('tenant_id', $tenantId)->where('employee_id', $employees[3])->value('id');
        if (! $requestId) {
            $requestId = DB::table('employee_change_requests')->insertGetId(['tenant_id' => $tenantId, 'uuid' => (string) Str::uuid7(), 'employee_id' => $employees[3],
                'requested_by' => $users[4] ?? $users[0], 'changes' => json_encode(['phone' => ['081200000004', '081299900004']]),
                'reason' => 'Pembaruan nomor telepon untuk data kontak aktif.', 'status' => 'pending',
                'created_at' => $now, 'updated_at' => $now]);
            $instanceId = DB::table('workflow_instances')->insertGetId(['tenant_id' => $tenantId, 'uuid' => (string) Str::uuid7(), 'version_id' => $versionId,
                'subject_type' => 'employee_change_request', 'subject_id' => $requestId, 'requester_user_id' => $users[4] ?? $users[0],
                'requester_employee_id' => $employees[3], 'status' => 'pending', 'current_step' => 0,
                'submitted_at' => $now, 'created_at' => $now, 'updated_at' => $now]);
            DB::table('employee_change_requests')->where('id', $requestId)->update(['workflow_instance_id' => $instanceId]);
            DB::table('approval_tasks')->insert(['tenant_id' => $tenantId, 'instance_id' => $instanceId, 'step_index' => 0,
                'step_name' => 'Persetujuan Atasan', 'approver_user_id' => $users[1] ?? $users[0], 'status' => 'pending',
                'due_at' => now()->addDays(3), 'created_at' => $now, 'updated_at' => $now]);
            DB::table('approval_actions')->insert(['tenant_id' => $tenantId, 'instance_id' => $instanceId, 'user_id' => $users[4] ?? $users[0], 'action' => 'submit', 'comment' => 'Mengajukan perubahan data kontak.', 'created_at' => $now]);
        }
    }

    private function seedOperations(int $tenantId, int $userId, $now): void
    {
        if ($userId && Schema::hasTable('import_batches')) {
            $this->upsert('import_batches', ['tenant_id' => $tenantId, 'file_name' => 'pegawai_demo.xlsx'], [
                'uuid' => (string) Str::uuid7(), 'type' => 'employees', 'stored_path' => 'demo/pegawai_demo.xlsx',
                'status' => 'committed', 'total_rows' => 8, 'error_rows' => 0, 'created_rows' => 8, 'updated_rows' => 0,
                'reconciliation' => json_encode(['valid' => 8, 'created' => 8, 'updated' => 0]), 'created_by' => $userId,
                'committed_at' => $now, 'created_at' => $now, 'updated_at' => $now,
            ]);
        }
        if (Schema::hasTable('audit_logs') && $userId && ! DB::table('audit_logs')->where('tenant_id', $tenantId)->where('description', 'Data demo pegawai dimuat')->exists()) {
            DB::table('audit_logs')->insert(['tenant_id' => $tenantId, 'user_id' => $userId, 'event' => 'created',
                'auditable_type' => 'DemoSeeder', 'auditable_id' => 'demo-seed', 'new_values' => json_encode(['employees' => 8]),
                'description' => 'Data demo pegawai dimuat', 'ip_address' => '127.0.0.1',
                'url' => '/demo-seeder', 'created_at' => $now]);
        }
    }

    private function seedPerformance(int $tenantId, array $employees, array $positions, $now): void
    {
        if (! Schema::hasTable('performance_periods') || ! $employees) {
            return;
        }
        $period = DB::table('performance_periods')->where('tenant_id', $tenantId)->where('code', 'DEMO-2026')->first();
        if ($period) {
            $this->seedPerformanceTrend($tenantId, $employees, $now);

            return;
        }
        $policyId = null;
        if (Schema::hasTable('performance_policies')) {
            $policyId = DB::table('performance_policies')->where('tenant_id', $tenantId)->where('version', 1)->value('id');
            if (! $policyId) {
                $policyId = DB::table('performance_policies')->insertGetId(['tenant_id' => $tenantId, 'uuid' => (string) Str::uuid7(), 'version' => 1,
                    'name' => 'Kebijakan Kinerja Demo', 'status' => 'active', 'config' => json_encode(PerformancePolicy::DEFAULTS),
                    'published_at' => $now, 'created_at' => $now, 'updated_at' => $now]);
            }
        }
        $periodId = DB::table('performance_periods')->insertGetId(['tenant_id' => $tenantId, 'uuid' => (string) Str::uuid7(),
            'code' => 'DEMO-2026', 'name' => 'Periode Kinerja Tahun 2026', 'start_date' => '2026-01-01', 'end_date' => '2026-12-31',
            'status' => 'monitoring', 'policy_id' => $policyId, // Salinan kebijakan berstruktur sama dengan PerformancePeriods::open() (termasuk _version)
            'policy_snapshot' => json_encode(PerformancePolicy::DEFAULTS + ['_version' => 1, '_name' => 'Kebijakan Kinerja Demo']),
            'planning_deadline' => '2026-02-15', 'opened_at' => $now, 'created_at' => $now, 'updated_at' => $now]);
        $strategicId = DB::table('strategic_objectives')->insertGetId(['tenant_id' => $tenantId, 'uuid' => (string) Str::uuid7(),
            'period_id' => $periodId, 'level' => 'corporate', 'code' => 'STR-01', 'title' => 'Meningkatkan efektivitas organisasi',
            'description' => 'Membangun organisasi yang produktif dan berorientasi hasil.', 'measure' => 'Produktivitas dan keterlibatan pegawai meningkat',
            'contribution_weight' => 100, 'created_at' => $now, 'updated_at' => $now]);
        if (! Schema::hasTable('employee_assignments')) {
            return;
        }
        foreach ($employees as $i => $employeeId) {
            $assignmentId = DB::table('employee_assignments')->where('employee_id', $employeeId)->where('type', 'primary')->value('id');
            if (! $assignmentId) {
                continue;
            }
            $planId = DB::table('performance_plans')->insertGetId(['tenant_id' => $tenantId, 'uuid' => (string) Str::uuid7(), 'period_id' => $periodId,
                'employee_id' => $employeeId, 'assignment_id' => $assignmentId, 'position_id' => $positions[$i],
                'start_date' => '2026-01-01', 'end_date' => '2026-12-31', 'status' => 'approved', 'approved_at' => $now,
                'created_at' => $now, 'updated_at' => $now]);
            $objectiveId = DB::table('performance_objectives')->insertGetId(['tenant_id' => $tenantId, 'plan_id' => $planId,
                'strategic_objective_id' => $strategicId, 'title' => 'Penyelesaian target kerja utama',
                'description' => 'Memenuhi target kerja sesuai kesepakatan periode.', 'kpi_type' => 'higher', 'unit' => '%',
                'target' => 100, 'weight' => 100, 'sort_order' => 1, 'created_at' => $now, 'updated_at' => $now]);
            if (Schema::hasTable('performance_updates')) {
                DB::table('performance_updates')->insert(['tenant_id' => $tenantId, 'plan_id' => $planId, 'objective_id' => $objectiveId,
                    'as_of' => '2026-10-31', 'realization' => 75 + ($i % 4) * 5, 'achievement' => 75 + ($i % 4) * 5,
                    'note' => 'Pembaruan capaian triwulan untuk demonstrasi.', 'created_by' => DB::table('employees')->where('id', $employeeId)->value('user_id') ?: 1,
                    'created_at' => $now, 'updated_at' => $now]);
            }
        }
        $this->seedPerformanceTrend($tenantId, $employees, $now);
    }

    /** Create six closed demo periods with final ratings for the performance trend chart. */
    private function seedPerformanceTrend(int $tenantId, array $employees, $now): void
    {
        foreach (['performance_periods', 'performance_ratings'] as $table) {
            if (! Schema::hasTable($table)) {
                return;
            }
        }
        $periods = [
            ['2025-Q1', 'Triwulan I 2025', '2025-01-01', '2025-03-31'],
            ['2025-Q2', 'Triwulan II 2025', '2025-04-01', '2025-06-30'],
            ['2025-Q3', 'Triwulan III 2025', '2025-07-01', '2025-09-30'],
            ['2025-Q4', 'Triwulan IV 2025', '2025-10-01', '2025-12-31'],
            ['2026-Q1', 'Triwulan I 2026', '2026-01-01', '2026-03-31'],
            ['2026-Q2', 'Triwulan II 2026', '2026-04-01', '2026-06-30'],
        ];
        foreach ($periods as $periodIndex => [$code, $name, $start, $end]) {
            $period = DB::table('performance_periods')->where('tenant_id', $tenantId)->where('code', 'DEMO-'.$code)->first();
            if (! $period) {
                $id = DB::table('performance_periods')->insertGetId([
                    'tenant_id' => $tenantId, 'uuid' => (string) Str::uuid7(), 'code' => 'DEMO-'.$code,
                    'name' => $name.' (Demo)', 'start_date' => $start, 'end_date' => $end, 'status' => 'closed',
                    'policy_snapshot' => json_encode(PerformancePolicy::DEFAULTS + ['_version' => 1, '_name' => 'Kebijakan Kinerja Demo']),
                    'opened_at' => $now, 'created_at' => $now, 'updated_at' => $now,
                ]);
            } else {
                $id = (int) $period->id;
            }
            foreach ($employees as $employeeIndex => $employeeId) {
                $employee = DB::table('employees')->where('tenant_id', $tenantId)->where('id', $employeeId)->first();
                if (! $employee || $employee->join_date > $end || ($employee->terminated_at && $employee->terminated_at < $start)) {
                    continue;
                }
                $score = 74 + (($periodIndex * 3 + $employeeIndex * 5) % 21);
                $band = $score >= 90 ? 'outstanding' : ($score >= 80 ? 'exceeds' : 'meets');
                $bandLabel = $score >= 90 ? 'Outstanding' : ($score >= 80 ? 'Exceeds Expectation' : 'Meets Expectation');
                $this->upsert('performance_ratings', ['period_id' => $id, 'employee_id' => $employeeId, 'revision' => 1], [
                    'tenant_id' => $tenantId, 'uuid' => (string) Str::uuid7(), 'is_current' => true, 'rated' => true,
                    'raw_score' => $score, 'final_score' => $score, 'band' => $band, 'band_label' => $bandLabel,
                    'published_at' => Carbon::parse($end)->endOfDay(),
                    'appeal_deadline' => Carbon::parse($end)->addDays(14)->toDateString(),
                    'published_by' => null, 'created_at' => $now, 'updated_at' => $now,
                ]);
            }
        }
    }

    /** Seed dated movements so the executive reports show a useful 12-month history. */
    private function seedExecutiveTrends(int $tenantId, array $employees, array $positions, array $grades, array $units, int $companyId, $now): void
    {
        if (! Schema::hasTable('employee_assignments') || ! Schema::hasTable('employees') || count($employees) < 8) {
            return;
        }

        // Four demo employees join at different points in the reporting window.
        $hireDates = ['2025-12-15', '2026-03-02', '2026-06-01', '2026-08-17'];
        foreach ($hireDates as $i => $date) {
            DB::table('employees')->where('tenant_id', $tenantId)->where('id', $employees[$i])->update([
                'join_date' => $date, 'status' => 'active', 'terminated_at' => null,
            ]);
            DB::table('employee_assignments')->where('tenant_id', $tenantId)->where('employee_id', $employees[$i])->where('type', 'primary')
                ->update(['effective_from' => $date, 'effective_to' => null, 'updated_at' => $now]);
        }

        // Three dated grade increases are represented by an ended prior placement and a current placement.
        $promotionDates = ['2026-02-01', '2026-05-01', '2026-09-01'];
        foreach ($promotionDates as $offset => $date) {
            $i = $offset + 4;
            $baseAssignment = DB::table('employee_assignments')->where('tenant_id', $tenantId)->where('employee_id', $employees[$i])
                ->where('type', 'primary')->orderBy('effective_from')->first();
            if (! $baseAssignment) {
                continue;
            }
            $currentPosition = DB::table('positions')->where('id', $positions[$i])->first();
            if (! $currentPosition) {
                continue;
            }
            $currentRank = (int) DB::table('job_grades')->where('id', $currentPosition->grade_id)->value('rank');
            $targetPosition = (int) $positions[$i];
            if ($currentRank <= 1) {
                $targetPosition = $this->master('positions', $tenantId, 'DEMO-PROMOTED-'.$i, [
                    'company_id' => $companyId, 'unit_id' => $currentPosition->unit_id, 'job_id' => $currentPosition->job_id,
                    'grade_id' => $grades['L2'], 'title' => $currentPosition->title, 'status' => 'active',
                    'is_critical' => false, 'work_location' => $currentPosition->work_location ?: 'Jakarta',
                    'headcount' => 1, 'effective_from' => '2025-01-01',
                ]);
            }
            $priorPosition = $this->master('positions', $tenantId, 'DEMO-PREV-'.$i, [
                'company_id' => $companyId, 'unit_id' => $currentPosition->unit_id, 'job_id' => $currentPosition->job_id,
                'grade_id' => $grades['L1'], 'title' => $currentPosition->title, 'status' => 'active',
                'is_critical' => false, 'work_location' => $currentPosition->work_location ?: 'Jakarta',
                'headcount' => 1, 'effective_from' => '2025-01-01',
            ]);
            DB::table('employee_assignments')->where('id', $baseAssignment->id)->update([
                'position_id' => $priorPosition, 'effective_from' => '2025-01-01',
                'effective_to' => Carbon::parse($date)->subDay()->toDateString(),
                'decree_number' => 'SK/DEMO/PROMO/'.$i, 'updated_at' => $now,
            ]);
            $this->upsert('employee_assignments', ['employee_id' => $employees[$i], 'type' => 'primary', 'effective_from' => $date], [
                'tenant_id' => $tenantId, 'uuid' => (string) Str::uuid7(), 'position_id' => $targetPosition, 'effective_to' => null,
                'decree_number' => 'SK/DEMO/PROMO/'.$i, 'decree_date' => $date, 'created_at' => $now, 'updated_at' => $now,
            ]);
        }

        // One processed exit gives turnover and DEIB charts a visible monthly event.
        $leaverId = $employees[7];
        $terminationDate = '2026-07-15';
        DB::table('employees')->where('tenant_id', $tenantId)->where('id', $leaverId)->update([
            'status' => 'terminated', 'terminated_at' => $terminationDate,
        ]);
        DB::table('employee_assignments')->where('tenant_id', $tenantId)->where('employee_id', $leaverId)->where('type', 'primary')
            ->whereNull('effective_to')->update(['effective_to' => $terminationDate, 'updated_at' => $now]);
        if (Schema::hasTable('employee_terminations') && ! DB::table('employee_terminations')->where('tenant_id', $tenantId)->where('employee_id', $leaverId)->where('termination_date', $terminationDate)->exists()) {
            DB::table('employee_terminations')->insert([
                'tenant_id' => $tenantId, 'employee_id' => $leaverId, 'termination_date' => $terminationDate,
                'type' => 'resign', 'reason' => 'Perubahan rencana karier (data demonstrasi).', 'rehire_eligible' => true,
                'state' => 'processed', 'processed_at' => $now, 'created_by' => DB::table('employees')->where('id', $leaverId)->value('user_id'),
                'created_at' => $now, 'updated_at' => $now,
            ]);
        }
    }
}
