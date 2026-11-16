<?php

namespace App\Http\Middleware;

use App\Domain\Security\Services\BreakGlass;
use App\Support\Security\Roles;
use App\Support\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\View;

/**
 * Mengisi TenantContext dari pengguna yang login.
 *
 * - Tenant diambil dari tb_users.tenant_id (bukan dari sesi atau input), jadi
 *   pengguna tidak bisa berpindah tenant. Company aktif diambil dari sesi `cid`
 *   bila company itu milik tenant yang sama.
 * - Tenant yang tidak aktif membuat pengguna di-logout.
 * - Akun platform (tenant_id kosong) tidak punya tenant context, kecuali
 *   Super Administrator sedang dalam sesi break-glass yang masih berlaku.
 * - Break-glass berlaku juga untuk Super Administrator yang terikat ke tenant
 *   lain; selama aktif, context menunjuk tenant tujuan dan setiap request dicatat.
 */
class SetTenantContext
{
    public function __construct(private TenantContext $context, private BreakGlass $breakGlass) {}

    public function handle(Request $request, Closure $next)
    {
        $user = Auth::user();
        if (! $user) {
            return $next($request);
        }

        if ($access = $this->breakGlassFor($request, (int) $user->id)) {
            $this->context->set((int) $access->tenant_id);
            View::share('breakGlass', $access);
            $this->breakGlass->record($access, $request->method(), $request->path(), $request->ip());

            return $next($request);
        }

        if ($user->tenant_id) {
            $tenant = DB::table('tenants')
                ->whereNull('deleted_at')
                ->where('id', $user->tenant_id)
                ->first(['id', 'status']);

            if (! $tenant || $tenant->status !== 'active') {
                Auth::logout();
                $request->session()->invalidate();

                return redirect('user/login')
                    ->with('status', 'error')
                    ->with('message', 'Akun organisasi Anda tidak aktif. Hubungi administrator.');
            }

            $companyId = null;
            $cid = (int) $request->session()->get('cid');
            if ($cid > 0 && DB::table('corporate')->where('id_corporate', $cid)->where('tenant_id', $tenant->id)->exists()) {
                $companyId = $cid;
            }

            $this->context->set((int) $tenant->id, $companyId);
        }

        return $next($request);
    }

    private function breakGlassFor(Request $request, int $userId): ?object
    {
        $id = (int) $request->session()->get(BreakGlass::SESSION_KEY);
        if (! $id) {
            return null;
        }

        $access = (int) $request->session()->get('gid') === Roles::id(Roles::SUPER_ADMIN)
            ? $this->breakGlass->active($userId, $id)
            : null;

        if (! $access) {
            $request->session()->forget(BreakGlass::SESSION_KEY);
            $request->session()->flash('status', 'error');
            $request->session()->flash('message', 'Sesi break-glass sudah berakhir.');
        }

        return $access;
    }
}
