<?php namespace App\Http\Controllers\core;


use App\Http\Controllers\Controller;
use App\Models\Core\Users;
use App\Models\Core\Groups;
use App\Library\DataHelpers;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator as Paginator;
use Validator, Input, Redirect ; 


class GroupsController extends Controller {

	protected $layout = "layouts.main";
	protected $data = array();	
	public $module = 'groups';
	static $per_page	= '10';

	public function __construct()
	{		
		parent::__construct();
		$this->model = new Groups();	
		$this->info = $this->model->makeInfo( $this->module);	
		$this->data = array(
			'pageTitle'	=> 	$this->info['title'],
			'pageNote'	=>  $this->info['note'],
			'pageModule'=> 'core/groups',
			'return'	=> self::returnUrl()
			
		);
		
	}

	public function index( Request $request )
	{
		// Make Sure users Logged 
		if(!\Auth::check()) 
			return redirect('user/login')->with('status', 'error')->with('message','You are not login');
		$this->grab( $request) ;
		if($this->access['is_view'] ==0) 
			return redirect('dashboard')->with('message', __('core.note_restric'))->with('status','error');				
		// Render into template
		return view( 'core.'. $this->module.'.index',$this->data);
	}

	function create( Request $request ) 
	{
		$this->hook( $request  );

		if($this->access['is_add'] ==0) 
			return redirect('dashboard')->with('message', __('core.note_restric'))->with('status','error');

		$this->data['row'] = $this->model->getColumnTable( $this->info['table']); 
		$this->data['id'] = '';
		return view( 'core.'. $this->module.'.form',$this->data);

	}
	function edit( Request $request , $id ) 
	{
		$this->hook( $request , $id );
		if(!isset($this->data['row']))
			return redirect($this->module)->with('message','Record Not Found !')->with('status','error');

		if($this->access['is_edit'] ==0 )
			return redirect('dashboard')->with('message',__('core.note_restric'))->with('status','error');


		$this->data['row'] = (array) $this->data['row'];
		$this->data['id'] = $id;
		return view( 'core.'. $this->module.'.form',$this->data);
	}	
	function show( Request $request , $id ) 
	{
		/* Handle import , export and view */
		$task =$id ;
		switch( $task)
		{
			case 'search':
				return $this->getSearch();
				break;

			case 'comboselect':
				return $this->getComboselect( $request );
				break;
			case 'import':
				return $this->getImport( $request );
				break;
			case 'export':
				return $this->getExport( $request );
				break;

			default:
				$this->hook( $request , $id );
				if(!isset($this->data['row']))
					return redirect($this->module)->with('message','Record Not Found !')->with('status','error');

				if($this->access['is_detail'] ==0) 
					return redirect('dashboard')->with('message', __('core.note_restric'))->with('status','error');
				return view( 'core.'. $this->module.'.view',$this->data);
				break;		
		}
	}	

	function store( Request $request  )
	{
		$task = $request->input('action_task');
		switch ($task)
		{
			default:
				$rules = $this->validateForm();
				$validator = Validator::make($request->all(), $rules);
				if ($validator->passes()) 
				{
					$data = $this->validatePost( $request );
					$id = $this->model->insertRow($data , $request->input( $this->info['key']));

					/* Insert logs */
					$this->model->logs($request , $id);
					if(!is_null($request->input('apply')))
						return redirect( 'core/'. $this->module .'/'.$id.'/edit?'. $this->returnUrl() )->with('message',__('core.note_success'))->with('status','success');

					return redirect( 'core/'.$this->module .'?'. $this->returnUrl() )->with('message',__('core.note_success'))->with('status','success');
				} 
				else {
					return redirect('core/'.$this->module.'/'. $request->input(  $this->info['key'] ))
							->with('message',__('core.note_error'))->with('status','error')
							->withErrors($validator)->withInput();

				}
				break;
			case 'delete':
				$result = $this->destroy( $request );
				return redirect('core/'.$this->module.'?'.$this->returnUrl())->with($result);
				break;

			case 'import':
				return $this->PostImport( $request );
				break;

			case 'copy':
				$result = $this->copy( $request );
				return redirect('core/'.$this->module.'?'.$this->returnUrl())->with($result);
				break;	
				
			case 'delgroup' :
				return $this->delgroup( $request );
				break;
	

		}	
	
	}

	public function destroy( $request)
	{
		// Make Sure users Logged 
		if(!\Auth::check()) 
			return redirect('user/login')->with('status', 'error')->with('message','You are not login');

		$this->access = $this->model->validAccess($this->info['id'] , session('gid'));
		if($this->access['is_remove'] ==0) 
			return redirect('dashboard')
				->with('message', __('core.note_restric'))->with('status','error');
		// delete multipe rows 
		if(count($request->input('ids')) >=1)
		{
			$this->model->destroy($request->input('ids'));
			
			\SiteHelpers::auditTrail( $request , "ID : ".implode(",",$request->input('ids'))."  , Has Been Removed Successfull");
			// redirect
        	return ['message'=>__('core.note_success_delete'),'status'=>'success'];	
	
		} else {
			return ['message'=>__('No Item Deleted'),'status'=>'error'];				
		}

	}	
	public function getSearch( $mode = 'native')
	{

		$this->data['tableForm'] 	= $this->info['config']['forms'];	
		$this->data['tableGrid'] 	= $this->info['config']['grid'];
		$this->data['searchMode'] = 'native';
		$this->data['pageUrl']		= url('core/groups');
		return view('sximo.module.utility.search',$this->data);
	
	}	

	public function AjaxData(Request $request){

        $validator = Validator::make($request->all(), [
			
        ]);

        if ($validator->passes()) {

			
			$list 		= $this->model->get_datatables();

			$this->access = $this->model->validAccess($this->info['id'] , session('gid'));

			$data = array();
			$no = @$_POST['start'];
					
					foreach ($list as $item) {
						$no++;
						$row = array();

						$row[] = $no.".";
						$row[] = e($item->name, false);
						$row[] = e($item->description, false);
						$row[] = e($item->level, false);
						$btn = "";
						$url	= url('core/groups/'.$item->group_id.'/edit?return=');
						
						$btn .= "
							<div class='dropdown'>
							  <button class='btn btn-sm dropdown-toggle' type='button' data-bs-toggle='dropdown'> <i class='fas fa-tasks'></i> </button>
							  <ul class='dropdown-menu'>";					
								if($this->access['is_edit'] ==1){
									$btn .= "<li class='nav-item'><a  href='".$url."' class='nav-link tips' title='Edit'> Edit</a></li>";
								}
								if($this->access['is_remove'] ==1):
									$btn .="<li class='nav-item'><a href='javascript:void(0)'  onclick='hapusgroup(\"".$item->group_id."\");' class='nav-link tips' title='Hapus'>
										Remove Selected </a></li>";
									endif;
						$btn .= "</ul>
							</div>";

						$row[] = $btn;
						$data[] = $row;
					}
				
				$output = array(
					"draw" 				=> @$_POST['draw'],
					"recordsTotal" 		=> $this->model->count_ajax(),
					"recordsFiltered" 	=> $this->model->count_filtered(),
					"data" 				=> $data,
				);

				echo json_encode($output);

        }

    }

	public function delgroup(Request $request){
		$validator = Validator::make($request->all(), [
			'group_id'	=> 'required|max:11',
        ]);

        if ($validator->passes()) {

			print_r('masuk');

			$id = cleanthis($request->input('group_id'));
			$this->model->delgroup($id);

        }else{
            $message = $this->validateListError(  $validator->getMessageBag()->toArray() );
            return response()->json(array(
                'message'   => $message,
                'status'    => 'error',
                'success'   => false,
            )); 
        }

        if(!empty($this->data)){
            echo json_encode($this->data);
        }
	}

	// $btn .=  \SiteHelpers::buttonAction($this->module, $this->access ,$item->{$this->info['key']} ,$this->info['setting']) ;

// End of bracket
}