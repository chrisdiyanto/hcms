<?php

namespace App\Http\Controllers\Talent;

use App\Domain\Talent\PotentialInstruments;
use App\Http\Controllers\Concerns\AuthorizesHcmsModule;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

/** Kamus instrumen asesmen potensi berversi (prasyarat P3 CBHRM). */
class PotentialInstrumentsController extends Controller
{
    use AuthorizesHcmsModule;

    public function __construct(private PotentialInstruments $instruments)
    {
        parent::__construct();
    }

    protected function moduleName(): string
    {
        return 'potentialinstruments';
    }

    public function index(Request $request)
    {
        $access = $this->authorizeTask('is_view');
        $versions = $this->instruments->versions();
        $selected = $request->filled('version')
            ? $versions->firstWhere('id', (int) $request->input('version'))
            : ($versions->firstWhere('status', 'draft') ?? $versions->firstWhere('status', 'published') ?? $versions->first());

        return view('talent.potential-instruments.index', [
            'pageTitle' => 'Instrumen Potensi',
            'pageNote' => $selected ? $selected->name : 'Belum ada versi',
            'versions' => $versions,
            'selected' => $selected,
            'criteria' => $selected ? $this->instruments->criteria((int) $selected->id) : collect(),
            'problems' => $selected && $selected->status === 'draft' ? $this->instruments->publishProblems((int) $selected->id) : [],
            'statuses' => PotentialInstruments::STATUS,
            'levels' => PotentialInstruments::LEVELS,
            'canEdit' => ! empty($access['is_edit']),
            'canApprove' => ! empty($access['is_approve']),
        ]);
    }

    public function createDraft(Request $request)
    {
        $this->authorizeTask('is_edit');
        $id = $this->instruments->createDraft((int) auth()->id());
        $this->logActivity($request, 'Draft instrumen potensi #'.$id.' dibuat');

        return redirect('talent/potential-instruments?version='.$id)->with('message', 'Draft dibuat. Sesuaikan kriteria, bobot, dan ambang lalu terbitkan.')->with('status', 'success');
    }

    public function update(Request $request, int $id)
    {
        $this->authorizeTask('is_edit');
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'scale_max' => ['required', 'integer', 'between:3,10'],
            'threshold_medium' => ['required', 'numeric', 'min:1', 'lt:threshold_high'],
            'threshold_high' => ['required', 'numeric', 'lte:scale_max'],
            'validity_months' => ['required', 'integer', 'between:6,60'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'criteria' => ['required', 'array', 'min:1', 'max:'.PotentialInstruments::MAX_CRITERIA],
            'criteria.*.code' => ['required', 'string', 'max:30', 'regex:/^[A-Za-z0-9_-]+$/', 'distinct:ignore_case'],
            'criteria.*.name' => ['required', 'string', 'max:150'],
            'criteria.*.description' => ['required', 'string', 'max:1000'],
            'criteria.*.indicators' => ['required', 'string', 'max:2000'],
            'criteria.*.weight' => ['required', 'integer', 'between:1,100'],
        ], [], ['criteria.*.code' => 'kode kriteria', 'criteria.*.name' => 'nama kriteria', 'criteria.*.description' => 'deskripsi',
            'criteria.*.indicators' => 'indikator', 'criteria.*.weight' => 'bobot', 'threshold_medium' => 'ambang sedang', 'threshold_high' => 'ambang tinggi']);

        $this->instruments->updateDraft($id, collect($data)->except('criteria')->all(), $data['criteria']);
        $this->logActivity($request, 'Draft instrumen potensi #'.$id.' diubah');

        return redirect('talent/potential-instruments?version='.$id)->with('message', 'Draft tersimpan.')->with('status', 'success');
    }

    /** Terbit = keputusan kebijakan: butuh izin Approve. */
    public function publish(Request $request, int $id)
    {
        $this->authorizeTask('is_approve');
        $this->instruments->publish($id, (int) auth()->id());
        $this->logActivity($request, 'Instrumen potensi #'.$id.' diterbitkan');

        return redirect('talent/potential-instruments?version='.$id)->with('message', 'Instrumen potensi diterbitkan dan menjadi acuan talent review.')->with('status', 'success');
    }

    public function destroy(Request $request, int $id)
    {
        $this->authorizeTask('is_edit');
        $this->instruments->discardDraft($id);
        $this->logActivity($request, 'Draft instrumen potensi #'.$id.' dibuang');

        return redirect('talent/potential-instruments')->with('message', 'Draft dibuang.')->with('status', 'success');
    }
}
