<?php

namespace App\Http\Controllers\Organization;

use App\Http\Controllers\Controller;
use App\Models\Sximo;
use App\Support\Tenancy\TenantScope;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;
use Illuminate\Validation\Rules\In;

/**
 * CRUD generik untuk master data HCMS yang ditulis tangan.
 *
 * - Data otomatis dibatasi tenant aktif (TenantScope) dan dihapus secara soft delete.
 * - Hak akses memakai baris tb_groups_access modul terdaftar (lihat HcmsModules),
 *   jadi bisa diatur dari halaman Permission Sximo seperti modul lain.
 * - Turunan cukup mendefinisikan tabel, kolom daftar, dan field form.
 */
abstract class MasterDataController extends Controller
{
    /** Nama modul di tb_module (untuk hak akses). */
    abstract protected function module(): string;

    abstract protected function table(): string;

    abstract protected function title(): string;

    abstract protected function baseUrl(): string;

    /**
     * Field form: name => [label, type (text|textarea|number|select|multiselect|checkbox|date),
     * options?, rules?, col?, help?, default?]
     */
    abstract protected function fields(?object $row): array;

    /** Kolom daftar: key => label. */
    abstract protected function columns(): array;

    protected function searchColumns(): array
    {
        return ['code', 'name'];
    }

    protected function defaultOrder(): array
    {
        return ['code', 'asc'];
    }

    /** Query daftar (boleh di-join untuk nama relasi); alias tabel utama = nama tabel. */
    protected function listQuery(): Builder
    {
        return TenantScope::table($this->table())->whereNull($this->table().'.deleted_at')->select($this->table().'.*');
    }

    /** Pesan bila data masih dipakai dan tidak boleh dihapus. */
    protected function blockDelete(int $id): ?string
    {
        return null;
    }

    /** Aturan tambahan lintas field (mis. grade dalam rentang job). */
    protected function extraRules(Request $request, ?object $row): array
    {
        return [];
    }

    /** Dipanggil sesudah simpan, dalam transaksi yang sama. */
    protected function afterSave(Request $request, int $id, ?object $before): void {}

    /** Partial Blade tambahan di form (mis. persyaratan job). */
    protected function formPartial(): ?string
    {
        return null;
    }

    protected function formData(?object $row): array
    {
        return [];
    }

    // ------------------------------------------------------------------ aksi

    public function index(Request $request)
    {
        $access = $this->authorizeTask('is_view');
        $search = trim((string) $request->input('q'));
        [$sort, $direction] = $this->defaultOrder();

        $rows = $this->listQuery()
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($w) use ($search) {
                    foreach ($this->searchColumns() as $column) {
                        $w->orWhere(str_contains($column, '.') ? $column : $this->table().'.'.$column, 'like', '%'.$search.'%');
                    }
                });
            })
            ->orderBy(str_contains($sort, '.') ? $sort : $this->table().'.'.$sort, $direction)
            ->paginate(25)
            ->withQueryString();

        return view('organization.master.index', [
            'pageTitle' => $this->title(),
            'pageNote' => '',
            'baseUrl' => $this->baseUrl(),
            'columns' => $this->columns(),
            'rows' => $rows,
            'search' => $search,
            'access' => $access,
            'formatter' => fn (object $row, string $key) => $this->formatCell($row, $key),
        ]);
    }

    public function create()
    {
        $this->authorizeTask('is_add');

        return $this->form(null);
    }

    public function store(Request $request)
    {
        $this->authorizeTask('is_add');

        return $this->save($request, null);
    }

    public function edit(int $id)
    {
        $this->authorizeTask('is_edit');

        return $this->form($this->findRow($id));
    }

    public function update(Request $request, int $id)
    {
        $this->authorizeTask('is_edit');

        return $this->save($request, $this->findRow($id));
    }

    public function destroy(Request $request, int $id)
    {
        $this->authorizeTask('is_remove');
        $row = $this->findRow($id);

        if ($message = $this->blockDelete($id)) {
            return redirect($this->baseUrl())->with('message', $message)->with('status', 'error');
        }

        TenantScope::table($this->table())->where('id', $id)->update(['deleted_at' => now(), 'updated_by' => auth()->id()]);
        $this->log($request, 'Hapus '.$this->title().' #'.$id.' ('.($row->code ?? '').')');

        return redirect($this->baseUrl())->with('message', __('core.note_success_delete'))->with('status', 'success');
    }

    // ---------------------------------------------------------------- bantuan

    protected function form(?object $row)
    {
        return view('organization.master.form', [
            'pageTitle' => ($row ? 'Ubah ' : 'Tambah ').$this->title(),
            'pageNote' => $row->code ?? '',
            'baseUrl' => $this->baseUrl(),
            'row' => $row,
            'fields' => $this->fields($row),
            'partial' => $this->formPartial(),
            'partialData' => $this->formData($row),
        ]);
    }

    protected function save(Request $request, ?object $row)
    {
        $fields = $this->fields($row);
        $rules = [];
        foreach ($fields as $name => $field) {
            $rules[$name] = $field['rules'] ?? ['nullable'];
        }
        if (isset($fields['code'])) {
            $rules['code'] = array_merge($rules['code'], [
                TenantScope::uniqueRule($this->table(), 'code')->ignore($row?->id),
            ]);
        }
        // Gabung per field: aturan tambahan melengkapi, bukan menggantikan atau diabaikan
        foreach ($this->extraRules($request, $row) as $name => $extra) {
            $rules[$name] = array_merge((array) ($rules[$name] ?? []), (array) $extra);
        }

        $validated = $request->validate($rules);

        $data = [];
        foreach ($fields as $name => $field) {
            if (! empty($field['virtual'])) {
                continue;
            }
            $value = $validated[$name] ?? null;
            if (($field['type'] ?? 'text') === 'checkbox') {
                $value = $request->boolean($name);
            }
            if ($name === 'code' && $value !== null) {
                $value = strtoupper(trim($value));
            }
            $data[$name] = $value === '' ? null : $value;
        }

        $id = DB::transaction(function () use ($data, $row, $request) {
            $data['updated_by'] = auth()->id();
            $data['updated_at'] = now();

            if ($row) {
                TenantScope::table($this->table())->where('id', $row->id)->update(TenantScope::guardForUpdate($this->table(), $data));
                $id = (int) $row->id;
            } else {
                $data['created_by'] = auth()->id();
                $data['created_at'] = now();
                $id = (int) DB::table($this->table())->insertGetId(TenantScope::fillForInsert($this->table(), $data));
            }

            $this->afterSave($request, $id, $row);

            return $id;
        });

        $this->log($request, ($row ? 'Ubah ' : 'Tambah ').$this->title().' #'.$id.' ('.($data['code'] ?? '').')');

        return redirect($this->baseUrl())->with('message', __('core.note_success'))->with('status', 'success');
    }

    protected function findRow(int $id): object
    {
        return TenantScope::table($this->table())->where('id', $id)->whereNull('deleted_at')->first() ?? abort(404);
    }

    /** Pilihan select dari tabel master tenant aktif (yang aktif & belum dihapus). */
    protected function options(string $table, string $label = 'name', ?callable $scope = null): array
    {
        $query = TenantScope::table($table)->whereNull('deleted_at');
        if ($scope) {
            $scope($query);
        }

        return $query->orderBy('code')->get()->mapWithKeys(fn ($r) => [$r->id => $r->code.' — '.$r->{$label}])->all();
    }

    protected function existsIn(string $table, string $column = 'id'): Exists
    {
        $rule = TenantScope::existsRule($table, $column);

        return $column === 'id' ? $rule->whereNull('deleted_at') : $rule;
    }

    protected function formatCell(object $row, string $key): string
    {
        $value = $row->{$key} ?? null;

        if (is_bool($value) || $key === 'is_active' || str_starts_with($key, 'is_')) {
            return $value ? 'Ya' : 'Tidak';
        }

        return e((string) ($value ?? '–'));
    }

    protected function authorizeTask(string $task): array
    {
        $model = new Sximo;
        $info = $model->makeInfo($this->module());
        $access = isset($info['id']) ? $model->validAccess($info['id'], session('gid')) : false;

        if (! is_array($access) || empty($access[$task])) {
            abort(redirect('dashboard')->with('message', __('core.note_restric'))->with('status', 'error'));
        }

        return $access;
    }

    protected function log(Request $request, string $note): void
    {
        DB::table('tb_logs')->insert([
            'ipaddress' => (string) $request->ip(),
            'user_id' => (int) auth()->id(),
            'module' => $this->module(),
            'task' => $request->route()?->getActionMethod(),
            'note' => $note,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    protected function ruleIn(array $options): In
    {
        return Rule::in(array_map('strval', array_keys($options)));
    }
}
