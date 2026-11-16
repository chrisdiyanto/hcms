<?php namespace App\Http\Controllers;

use App\Models\Dashboard;
use App\Http\Controllers;
use Illuminate\Http\Request;
use Carbon\Carbon;
use App\User;
use Validator, Input, Redirect ; 

class DashboardController extends Controller {

	public $module = 'dashboard';
	public function __construct()
	{
		parent::__construct();
		$this->model = new Dashboard();
		
        $this->data = array(
            'pageTitle' =>  $this->config['cnf_appname'],
            'pageNote'  =>  'Welcome to Dashboard',
            
        );			
	}

	public function index( Request $request )
	{
		$last_login = session('ll');
		if(empty($last_login)){
			return redirect('user/changepswd');
		}
		
		return view('dashboard.index', $this->data);
		
	}	
	
	function show( Request $request , $id ) 
	{
		/* Handle import , export and view */
		$task =$id ;
		switch( $task)
		{
			default:
				return view('dashboard.index',$this->data);
				break;		
		}
	}	

	// Additional function


// End of Bracket
}