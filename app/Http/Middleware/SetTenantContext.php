<?php

namespace App\Http\Middleware;

use App\Support\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Mengisi TenantContext dari pengguna yang login.
 *
 * Tenant diambil dari tb_users.tenant_id (bukan dari sesi atau input), jadi
 * pengguna tidak bisa berpindah tenant. Company aktif diambil dari sesi `cid`
 * (diisi saat pindah unit) dan hanya dipakai bila company itu milik tenant
 * yang sama. Tenant yang tidak aktif membuat pengguna di-logout.
 */
class SetTenantContext
{
    public function __construct(private TenantContext $context) {}

    public function handle(Request $request, Closure $next)
    {
        $user = Auth::user();

        if ($user && $user->tenant_id) {
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
}
