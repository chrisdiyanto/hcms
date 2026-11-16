<?php

namespace App\Http\Controllers\Core;

use App\Domain\Security\Services\BreakGlass;
use App\Domain\Tenancy\Actions\CreateTenant;
use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Support\Audit\AuditLog;
use App\Support\Security\Roles;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Manajemen tenant (platform). Hanya Superadmin (grup 1).
 * Data company/pengguna di dalam tenant dikelola Tenant Administrator masing-masing.
 */
class TenantsController extends Controller
{
    public function __construct()
    {
        parent::__construct();

        $this->middleware(function ($request, $next) {
            if ((int) session('gid') !== Roles::id(Roles::SUPER_ADMIN)) {
                return redirect('dashboard')->with('message', __('core.note_restric'))->with('status', 'error');
            }

            return $next($request);
        });
    }

    public function index(Request $request)
    {
        $search = trim((string) $request->input('q'));

        $tenants = Tenant::query()
            ->when($search !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('code', 'like', "%{$search}%")
                ->orWhere('name', 'like', "%{$search}%")))
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        $ids = $tenants->pluck('id');
        $companies = DB::table('corporate')->whereIn('tenant_id', $ids)->groupBy('tenant_id')->pluck(DB::raw('count(*)'), 'tenant_id');
        $users = DB::table('tb_users')->whereIn('tenant_id', $ids)->groupBy('tenant_id')->pluck(DB::raw('count(*)'), 'tenant_id');

        return view('core.tenants.index', [
            'pageTitle' => 'Tenant',
            'pageNote' => 'Organisasi pelanggan HCMS',
            'tenants' => $tenants,
            'companies' => $companies,
            'users' => $users,
            'search' => $search,
        ]);
    }

    public function create()
    {
        return view('core.tenants.form', [
            'pageTitle' => 'Tenant Baru',
            'pageNote' => 'Tenant, company pertama, dan Tenant Administrator',
            'tenant' => new Tenant(['license_type' => 'enterprise']),
        ]);
    }

    public function store(Request $request, CreateTenant $action)
    {
        $data = $request->validate($this->tenantRules() + [
            'company_code' => ['required', 'max:20'],
            'company_name' => ['required', 'max:255'],
            'company_address' => ['nullable', 'max:1000'],
            'admin_first_name' => ['required', 'max:50'],
            'admin_last_name' => ['required', 'max:50'],
            'admin_username' => ['required', 'max:100', 'regex:/^[A-Za-z0-9._-]+$/', Rule::unique('tb_users', 'username')],
            'admin_email' => ['required', 'email', 'max:100', Rule::unique('tb_users', 'email')],
            'admin_password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $tenant = $action->handle(
            $this->tenantAttributes($data),
            ['code' => $data['company_code'], 'name' => $data['company_name'], 'address' => $data['company_address'] ?? null],
            [
                'first_name' => $data['admin_first_name'],
                'last_name' => $data['admin_last_name'],
                'username' => $data['admin_username'],
                'email' => $data['admin_email'],
                'password' => $data['admin_password'],
            ],
            (int) session('uid') ?: null,
        );

        $this->logActivity($request, $tenant, 'Tenant '.$tenant->code.' dibuat');

        return redirect('core/tenants')->with('message', 'Tenant '.$tenant->name.' berhasil dibuat.')->with('status', 'success');
    }

    public function edit(Tenant $tenant)
    {
        return view('core.tenants.form', [
            'pageTitle' => 'Ubah Tenant',
            'pageNote' => $tenant->code,
            'tenant' => $tenant,
        ]);
    }

    public function update(Request $request, Tenant $tenant)
    {
        $data = $request->validate($this->tenantRules($tenant));

        $tenant->fill($this->tenantAttributes($data) + ['updated_by' => (int) session('uid') ?: null])->save();

        $this->logActivity($request, $tenant, 'Tenant '.$tenant->code.' diubah');

        return redirect('core/tenants')->with('message', __('core.note_success'))->with('status', 'success');
    }

    public function status(Request $request, Tenant $tenant)
    {
        $data = $request->validate(['status' => ['required', Rule::in(Tenant::STATUSES)]]);

        if ((int) auth()->user()->tenant_id === $tenant->id && $data['status'] !== 'active') {
            return redirect('core/tenants')->with('message', 'Tenant milik akun Anda sendiri tidak dapat dinonaktifkan.')->with('status', 'error');
        }

        $tenant->forceFill([
            'status' => $data['status'],
            'activated_at' => $data['status'] === 'active' ? ($tenant->activated_at ?? now()) : $tenant->activated_at,
            'updated_by' => (int) session('uid') ?: null,
        ])->save();

        $this->logActivity($request, $tenant, 'Status tenant '.$tenant->code.' menjadi '.$data['status']);

        return redirect('core/tenants')->with('message', __('core.note_success'))->with('status', 'success');
    }

    public function breakGlassForm(Tenant $tenant)
    {
        if ($this->isOwnTenant($tenant)) {
            return redirect('core/tenants')->with('message', 'Break-glass tidak diperlukan untuk tenant akun Anda sendiri.')->with('status', 'error');
        }

        return view('core.tenants.break-glass', [
            'pageTitle' => 'Break-glass',
            'pageNote' => 'Akses darurat ke data '.$tenant->name,
            'tenant' => $tenant,
            'durations' => BreakGlass::DURATIONS,
        ]);
    }

    public function breakGlassStart(Request $request, Tenant $tenant, BreakGlass $breakGlass)
    {
        if ($this->isOwnTenant($tenant)) {
            return redirect('core/tenants')->with('message', 'Break-glass tidak diperlukan untuk tenant akun Anda sendiri.')->with('status', 'error');
        }

        $data = $request->validate([
            'reason' => ['required', 'string', 'min:20', 'max:1000'],
            'minutes' => ['required', 'integer', Rule::in(BreakGlass::DURATIONS)],
            'confirm' => ['accepted'],
        ]);

        $id = $breakGlass->start((int) auth()->id(), $tenant->id, $data['reason'], (int) $data['minutes'], $request->ip());
        $request->session()->put(BreakGlass::SESSION_KEY, $id);

        $this->logActivity($request, $tenant, 'Break-glass dimulai ('.$data['minutes'].' menit): '.$data['reason']);

        return redirect('dashboard')->with('message', 'Break-glass aktif untuk '.$tenant->name.'. Semua aktivitas Anda dicatat.')->with('status', 'success');
    }

    public function breakGlassEnd(Request $request, BreakGlass $breakGlass)
    {
        $bgId = (int) $request->session()->pull(BreakGlass::SESSION_KEY);
        $breakGlass->end((int) auth()->id(), $bgId);
        AuditLog::security('break_glass_accesses', $bgId, 'Break-glass diakhiri');

        return redirect('core/tenants')->with('message', 'Break-glass diakhiri.')->with('status', 'success');
    }

    private function isOwnTenant(Tenant $tenant): bool
    {
        return (int) auth()->user()->tenant_id === (int) $tenant->id;
    }

    private function tenantRules(?Tenant $tenant = null): array
    {
        return [
            'code' => ['required', 'max:30', 'alpha_dash', Rule::unique('tenants', 'code')->ignore($tenant?->id)],
            'name' => ['required', 'max:150'],
            'legal_name' => ['nullable', 'max:255'],
            'license_type' => ['required', Rule::in(Tenant::LICENSE_TYPES)],
            'max_employees' => ['nullable', 'integer', 'min:1'],
            'contact_name' => ['nullable', 'max:150'],
            'contact_email' => ['nullable', 'email', 'max:150'],
            'contact_phone' => ['nullable', 'max:30'],
        ];
    }

    private function tenantAttributes(array $data): array
    {
        return [
            'code' => strtoupper($data['code']),
            'name' => $data['name'],
            'legal_name' => $data['legal_name'] ?? null,
            'license_type' => $data['license_type'],
            'max_employees' => $data['max_employees'] ?? null,
            'contact_name' => $data['contact_name'] ?? null,
            'contact_email' => $data['contact_email'] ?? null,
            'contact_phone' => $data['contact_phone'] ?? null,
        ];
    }

    private function logActivity(Request $request, Tenant $tenant, string $note): void
    {
        AuditLog::security('tenants', $tenant->id, $note);
        DB::table('tb_logs')->insert([
            'ipaddress' => (string) $request->ip(),
            'user_id' => (int) session('uid'),
            'module' => 'tenants',
            'task' => $request->route()?->getActionMethod(),
            'note' => $note.' (id '.$tenant->id.')',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
