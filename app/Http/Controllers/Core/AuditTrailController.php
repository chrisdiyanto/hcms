<?php

namespace App\Http\Controllers\Core;

use App\Http\Controllers\Concerns\AuthorizesHcmsModule;
use App\Http\Controllers\Controller;
use App\Support\Tenancy\TenantScope;
use Illuminate\Http\Request;

/** Penelusuran audit trail & log ekspor (baca saja; tabel append-only). */
class AuditTrailController extends Controller
{
    use AuthorizesHcmsModule;

    public const EVENTS = ['created' => 'Dibuat', 'updated' => 'Diubah', 'deleted' => 'Dihapus', 'sensitive_viewed' => 'Data sensitif dibuka', 'exported' => 'Ekspor', 'security' => 'Keamanan'];

    protected function moduleName(): string
    {
        return 'audittrail';
    }

    public function index(Request $request)
    {
        $this->authorizeTask('is_view');
        $filters = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'event' => ['nullable', 'in:'.implode(',', array_keys(self::EVENTS))],
            'type' => ['nullable', 'string', 'max:60'],
            'user' => ['nullable', 'integer'],
            'q' => ['nullable', 'string', 'max:100'],
        ]);
        $from = $filters['from'] ?? now()->subDays(30)->toDateString();
        $to = $filters['to'] ?? now()->toDateString();

        $logs = TenantScope::table('audit_logs')
            ->leftJoin('tb_users', 'tb_users.id', '=', 'audit_logs.user_id')
            ->whereBetween('audit_logs.created_at', [$from.' 00:00:00', $to.' 23:59:59'])
            ->when($filters['event'] ?? null, fn ($q, $v) => $q->where('audit_logs.event', $v))
            ->when($filters['type'] ?? null, fn ($q, $v) => $q->where('audit_logs.auditable_type', $v))
            ->when($filters['user'] ?? null, fn ($q, $v) => $q->where('audit_logs.user_id', $v))
            ->when($filters['q'] ?? null, fn ($q, $v) => $q->where(fn ($w) => $w->where('audit_logs.description', 'like', '%'.$v.'%')->orWhere('audit_logs.auditable_id', $v)))
            ->orderByDesc('audit_logs.id')
            ->select('audit_logs.*', 'tb_users.username')
            ->paginate(50)->withQueryString();

        return view('core.audit.index', [
            'pageTitle' => 'Audit Trail',
            'pageNote' => 'Catatan tidak dapat diubah atau dihapus',
            'logs' => $logs,
            'filters' => $filters + ['from' => $from, 'to' => $to],
            'events' => self::EVENTS,
            'types' => TenantScope::table('audit_logs')->distinct()->orderBy('auditable_type')->pluck('auditable_type'),
            'users' => TenantScope::table('tb_users')->orderBy('username')->pluck('username', 'id'),
            'exports' => TenantScope::table('data_export_logs')->leftJoin('tb_users', 'tb_users.id', '=', 'data_export_logs.user_id')
                ->orderByDesc('data_export_logs.id')->limit(20)->get(['data_export_logs.*', 'tb_users.username']),
        ]);
    }
}
