<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Sximo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Hak akses modul HCMS lewat tb_groups_access (izin Sximo) + catatan aktivitas.
 *
 * Jangan menyimpan hasil hak akses di properti controller: Laravel memakai ulang
 * instance controller yang sama antar-request (Route menyimpan instance-nya,
 * dan di Octane worker hidup lama), sehingga hak akses pengguna sebelumnya
 * bisa terbawa ke pengguna berikutnya.
 */
trait AuthorizesHcmsModule
{
    abstract protected function moduleName(): string;

    protected function moduleAccess(): array
    {
        $model = new Sximo;
        $info = $model->makeInfo($this->moduleName());
        $access = isset($info['id']) ? $model->validAccess($info['id'], session('gid')) : false;

        return is_array($access) ? $access : [];
    }

    protected function can(string $task): bool
    {
        return ! empty($this->moduleAccess()[$task]);
    }

    protected function authorizeTask(string $task): array
    {
        $access = $this->moduleAccess();
        if (empty($access[$task])) {
            abort(redirect('dashboard')->with('message', __('core.note_restric'))->with('status', 'error'));
        }

        return $access;
    }

    protected function logActivity(Request $request, string $note, ?string $module = null): void
    {
        DB::table('tb_logs')->insert([
            'ipaddress' => (string) $request->ip(),
            'user_id' => (int) auth()->id(),
            'module' => $module ?? $this->moduleName(),
            'task' => $request->route()?->getActionMethod(),
            'note' => mb_substr($note, 0, 1000),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
