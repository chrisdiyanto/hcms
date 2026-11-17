<?php

namespace App\Http\Controllers;

use App\Models\Dashboard;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public $module = 'dashboard';

    public function __construct()
    {
        parent::__construct();
        $this->model = new Dashboard();

        $this->data = [
            'pageTitle' => $this->config['cnf_appname'] ?? 'Dashboard',
            'pageNote' => 'Welcome to Dashboard',
        ];
    }

    public function index(Request $request)
    {
        if (session()->has('ll') && empty(session('ll'))) {
            return redirect('user/changepswd');
        }

        $period = $this->model->resolvePeriod(
            $request->query('period'),
            $request->query('start_date'),
            $request->query('end_date')
        );

        $dashboardData = $this->model->getDashboardData(
            $period['start_date'],
            $period['end_date']
        );

        $this->data = array_merge($this->data, $dashboardData, [
            'period' => $period,
            'periodOptions' => $period['options'],
            'selectedPeriod' => $period['selected_period'],
            'startDate' => $period['start_date'],
            'endDate' => $period['end_date'],
            'periodLabel' => $period['label'],
        ]);

        return view('dashboard.index', $this->data);
    }

    public function show(Request $request, $id)
    {
        return $this->index($request);
    }
}
