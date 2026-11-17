<?php

namespace App\Http\Controllers\Talent;

use App\Domain\Talent\Succession;
use App\Http\Controllers\Concerns\AuthorizesHcmsModule;
use App\Http\Controllers\Controller;
use App\Support\Privacy\SmallGroups;

/**
 * Laporan Suksesi (laporan CBHRM 11, agregat): cakupan suksesi, bench strength, posisi berisiko,
 * rencana belum ada/belum disahkan, kandidat ganda. Tanpa nama kandidat; posisi tetap ditampilkan.
 */
class SuccessionReportController extends Controller
{
    use AuthorizesHcmsModule;

    public function __construct(private Succession $succession)
    {
        parent::__construct();
    }

    protected function moduleName(): string
    {
        return 'successionreport';
    }

    public function index()
    {
        $this->authorizeTask('is_view');
        $positions = $this->succession->criticalPositions();

        return view('talent.succession.report', [
            'pageTitle' => 'Laporan Suksesi', 'positions' => $positions,
            'metrics' => Succession::metrics($positions, $this->succession->primaryCounts()),
            'mask' => app(SmallGroups::class)->masks('succession'), 'risks' => Succession::RISKS,
            'retirementAge' => $this->succession->retirementAge(),
        ]);
    }
}
