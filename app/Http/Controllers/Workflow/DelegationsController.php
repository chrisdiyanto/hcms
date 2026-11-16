<?php

namespace App\Http\Controllers\Workflow;

use App\Http\Controllers\Controller;
use App\Support\Tenancy\TenantScope;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/** Delegasi approval bertanggal (blueprint 3.2), dibuat sendiri oleh pemberi delegasi. */
class DelegationsController extends Controller
{
    public function index()
    {
        $me = (int) auth()->id();

        return view('workflow.delegations', [
            'pageTitle' => 'Delegasi Persetujuan',
            'pageNote' => '',
            'given' => TenantScope::table('approval_delegations')->join('tb_users', 'tb_users.id', '=', 'approval_delegations.delegate_user_id')
                ->where('delegator_user_id', $me)->orderByDesc('approval_delegations.id')->get(['approval_delegations.*', 'tb_users.username', 'tb_users.first_name', 'tb_users.last_name']),
            'received' => TenantScope::table('approval_delegations')->join('tb_users', 'tb_users.id', '=', 'approval_delegations.delegator_user_id')
                ->where('delegate_user_id', $me)->whereNull('revoked_at')->where('ends_on', '>=', now()->toDateString())
                ->get(['approval_delegations.*', 'tb_users.username', 'tb_users.first_name', 'tb_users.last_name']),
            'users' => TenantScope::table('tb_users')->where('active', 1)->where('id', '!=', $me)->orderBy('first_name')
                ->get(['id', 'first_name', 'last_name', 'username'])->mapWithKeys(fn ($u) => [$u->id => trim($u->first_name.' '.$u->last_name).' ('.$u->username.')']),
        ]);
    }

    public function store(Request $request)
    {
        $me = (int) auth()->id();
        $data = $request->validate([
            'delegate_user_id' => ['required', 'integer', Rule::notIn([$me]), TenantScope::existsRule('tb_users', 'id')->where('active', 1)],
            'starts_on' => ['required', 'date', 'after_or_equal:today'],
            'ends_on' => ['required', 'date', 'after_or_equal:starts_on', 'before_or_equal:'.now()->addDays(90)->toDateString()],
            'reason' => ['required', 'string', 'max:255'],
        ]);

        $overlap = TenantScope::table('approval_delegations')->where('delegator_user_id', $me)->whereNull('revoked_at')
            ->where('starts_on', '<=', $data['ends_on'])->where('ends_on', '>=', $data['starts_on'])->exists();
        if ($overlap) {
            return back()->withErrors(['starts_on' => 'Sudah ada delegasi aktif pada periode tersebut.'])->withInput();
        }

        DB::table('approval_delegations')->insert(TenantScope::fillForInsert('approval_delegations', $data + [
            'delegator_user_id' => $me, 'created_at' => now(), 'updated_at' => now(),
        ]));

        return redirect('workflow/delegations')->with('message', 'Delegasi disimpan. Tugas baru dalam periode tersebut dialihkan.')->with('status', 'success');
    }

    public function revoke(int $delegation)
    {
        TenantScope::table('approval_delegations')->where('id', $delegation)->where('delegator_user_id', auth()->id())->whereNull('revoked_at')
            ->update(['revoked_at' => now(), 'updated_at' => now()]) ?: abort(404);

        return redirect('workflow/delegations')->with('message', 'Delegasi dicabut.')->with('status', 'success');
    }
}
