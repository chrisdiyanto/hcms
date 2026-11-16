<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Sximo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Hak akses modul HCMS lewat tb_groups_access (izin Sximo) + catatan aktivitas. */
trait AuthorizesHcmsModule
{
    private ?array $moduleAccessCache = null;

    abstract protected function moduleName(): string;

    protected function moduleAccess(): array
    {
        if ($this->moduleAccessCache === null) {
            $model = new Sximo;
            $info = $model->makeInfo($this->moduleName());
            $access = isset($info['id']) ? $model->validAccess($info['id'], session('gid')) : false;
            $this->moduleAccessCache = is_array($access) ? $access : [];
        }

        return $this->moduleAccessCache;
    }

    protected function can(string $task): bool
    {
        return ! empty($this->moduleAccess()[$task]);
    }

    protected function authorizeTask(string $task): array
    {
        if (! $this->can($task)) {
            abort(redirect('dashboard')->with('message', __('core.note_restric'))->with('status', 'error'));
        }

        return $this->moduleAccess();
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
