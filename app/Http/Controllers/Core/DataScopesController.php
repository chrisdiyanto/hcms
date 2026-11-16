<?php

namespace App\Http\Controllers\Core;

use App\Domain\Security\Services\Mfa;
use App\Http\Controllers\Controller;
use App\Models\Core\Users;
use App\Support\Security\DataScope;
use App\Support\Security\Roles;
use App\Support\Tenancy\TenantScope;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Cakupan data tambahan per pengguna (user_data_scopes), di luar unit penugasannya.
 * Hak akses mengikuti izin Edit modul Users. Pengguna tidak boleh mengubah
 * cakupan datanya sendiri (Segregation of Duties, blueprint 3.4).
 */
class DataScopesController extends Controller
{
    public function __construct()
    {
        parent::__construct();

        $this->middleware(function ($request, $next) {
            $model = new Users;
            $info = $model->makeInfo('users');
            $access = $model->validAccess($info['id'] ?? 0, session('gid'));
            if (empty($access['is_edit'])) {
                return redirect('dashboard')->with('message', __('core.note_restric'))->with('status', 'error');
            }

            return $next($request);
        });
    }

    public function index(int $user)
    {
        $target = $this->findUser($user);

        $grants = TenantScope::table('user_data_scopes')
            ->join('unit', 'unit.id_unit', '=', 'user_data_scopes.unit_id')
            ->where('user_data_scopes.user_id', $target->id)
            ->orderBy('unit.unit_name')
            ->get(['user_data_scopes.*', 'unit.unit_name', 'unit.unit_code']);

        $assigned = DB::table('user_unit')
            ->join('unit', 'unit.id_unit', '=', 'user_unit.id_unit')
            ->where('user_unit.id_user', $target->id)
            ->get(['unit.unit_name', 'unit.unit_code', 'user_unit.start_date', 'user_unit.end_date']);

        $groups = DB::table('tb_users_groups')
            ->join('tb_groups', 'tb_groups.group_id', '=', 'tb_users_groups.group_id')
            ->where('tb_users_groups.user_id', $target->id)
            ->get(['tb_groups.group_id', 'tb_groups.name', 'tb_groups.data_scope']);

        $effective = $groups->mapWithKeys(fn ($g) => [$g->name => DataScope::for($target->id, (int) $g->group_id)]);

        return view('core.users.scopes', [
            'pageTitle' => 'Cakupan Data',
            'pageNote' => trim($target->first_name.' '.$target->last_name).' ('.$target->username.')',
            'target' => $target,
            'grants' => $grants,
            'assigned' => $assigned,
            'effective' => $effective,
            'units' => TenantScope::table('unit')->orderBy('unit_name')->get(['id_unit', 'unit_name', 'unit_code']),
            'isSelf' => (int) $target->id === (int) auth()->id(),
            'mfaEnabledAt' => $target->mfa_enabled_at ?? null,
            'mfaRequired' => app(Mfa::class)->isRequiredFor((int) $target->id),
            'logins' => TenantScope::table('login_histories')->where('user_id', $target->id)
                ->orderByDesc('created_at')->limit(10)->get(['event', 'ip_address', 'user_agent', 'created_at']),
        ]);
    }

    public function store(Request $request, int $user)
    {
        $target = $this->findUser($user);
        if ($denied = $this->denySelf($target)) {
            return $denied;
        }

        $data = $request->validate([
            'unit_id' => ['required', 'integer', TenantScope::existsRule('unit', 'id_unit')],
            'include_children' => ['nullable', 'boolean'],
            'valid_from' => ['nullable', 'date'],
            'valid_to' => ['nullable', 'date', 'after_or_equal:valid_from'],
            'reason' => ['required', 'string', 'max:255'],
        ]);

        $values = [
            'include_children' => $request->boolean('include_children'),
            'valid_from' => $data['valid_from'] ?? null,
            'valid_to' => $data['valid_to'] ?? null,
            'reason' => $data['reason'],
            'created_by' => auth()->id(),
            'updated_at' => now(),
        ];

        $existing = TenantScope::table('user_data_scopes')->where('user_id', $target->id)->where('unit_id', $data['unit_id']);
        if ($existing->exists()) {
            $existing->update($values);
        } else {
            DB::table('user_data_scopes')->insert(TenantScope::fillForInsert('user_data_scopes', $values + [
                'user_id' => $target->id,
                'unit_id' => (int) $data['unit_id'],
                'created_at' => now(),
            ]));
        }

        $this->audit($request, $target->id, 'Cakupan data unit '.$data['unit_id'].' diberikan: '.$data['reason']);

        return redirect('core/users/'.$target->id.'/scopes')->with('message', __('core.note_success'))->with('status', 'success');
    }

    public function destroy(Request $request, int $user, int $scope)
    {
        $target = $this->findUser($user);
        if ($denied = $this->denySelf($target)) {
            return $denied;
        }

        $deleted = TenantScope::table('user_data_scopes')->where('id', $scope)->where('user_id', $target->id)->delete();
        abort_unless($deleted, 404);

        $this->audit($request, $target->id, 'Cakupan data #'.$scope.' dicabut');

        return redirect('core/users/'.$target->id.'/scopes')->with('message', __('core.note_success'))->with('status', 'success');
    }

    /**
     * Reset MFA pengguna lain (mis. ponsel hilang). Pengguna wajib mendaftar ulang
     * saat login berikutnya. Tidak boleh untuk diri sendiri atau pengguna dengan
     * peran setara/lebih tinggi, kecuali oleh Super Administrator.
     */
    public function resetMfa(Request $request, int $user, Mfa $mfa)
    {
        $target = $this->findUser($user);
        if ($denied = $this->denySelf($target)) {
            return $denied;
        }

        $actorLevel = (int) DB::table('tb_groups')->where('group_id', session('gid'))->value('level');
        $targetLevel = (int) DB::table('tb_users_groups')->join('tb_groups', 'tb_groups.group_id', '=', 'tb_users_groups.group_id')
            ->where('tb_users_groups.user_id', $target->id)->min('tb_groups.level');
        if ((int) session('gid') !== Roles::id(Roles::SUPER_ADMIN) && $targetLevel && $targetLevel <= $actorLevel) {
            return redirect('core/users/'.$target->id.'/scopes')
                ->with('message', 'Anda tidak dapat mereset MFA pengguna dengan peran setara atau lebih tinggi.')->with('status', 'error');
        }

        $mfa->disable($target->id);
        $this->audit($request, $target->id, 'MFA direset');

        return redirect('core/users/'.$target->id.'/scopes')->with('message', 'MFA direset. Pengguna akan diminta mendaftar ulang.')->with('status', 'success');
    }

    private function findUser(int $id): object
    {
        // Kolom rahasia (password, mfa_secret) tidak ikut diambil
        return TenantScope::table('tb_users')->where('id', $id)
            ->first(['id', 'tenant_id', 'username', 'email', 'first_name', 'last_name', 'mfa_enabled_at']) ?? abort(404);
    }

    private function denySelf(object $target)
    {
        if ((int) $target->id === (int) auth()->id()) {
            return redirect('core/users/'.$target->id.'/scopes')
                ->with('message', 'Anda tidak dapat mengubah cakupan data akun sendiri.')->with('status', 'error');
        }

        return null;
    }

    private function audit(Request $request, int $userId, string $note): void
    {
        DB::table('tb_logs')->insert([
            'ipaddress' => (string) $request->ip(),
            'user_id' => (int) auth()->id(),
            'module' => 'datascopes',
            'task' => $request->route()?->getActionMethod(),
            'note' => $note.' (pengguna '.$userId.')',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
