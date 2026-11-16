<?php namespace App\Http\Controllers\core;


use App\Http\Controllers\Controller;
use App\Models\Core\Users;
use App\Models\Core\Groups;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator as Paginator;
use Validator, Input, Redirect ; 


class UsersController extends Controller {

	protected $layout = "layouts.main";
	protected $data = array();	
	public $module = 'users';
	static $per_page	= '50';

	public function __construct()
	{		
		parent::__construct();
		$this->model = new Users();	
		$this->info = $this->model->makeInfo( $this->module);
		$this->access = $this->model->validAccess($this->info['id']);
		$this->data = array(
			'pageTitle'	=> 	$this->info['title'],
			'pageNote'	=>  $this->info['note'],
			'pageModule'=> 'core/users',
			'return'	=> self::returnUrl()
			
		);
		
	}

	public function index( Request $request )
	{
		// Make Sure users Logged 
		if(!\Auth::check()) 
			return redirect('user/login')->with('status', 'error')->with('message','You are not login');

		$this->access = $this->model->validAccess($this->info['id']);
		
		$filter = [
			'params' => " AND tb_groups.level > '".Users::level(session('gid'))."'" 
		];
		$this->grab( $request , $filter ) ;
		
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

			$data = $this->model->getColumnTable( $this->info['table']);
			$unit = ['id_unit' => '', 'id_position' => '', 'start_date' => '', 'end_date' => ''];
			$this->data['row'] = array_merge($data, $unit);
			$this->data['id'] = '';
			$this->data['user_groups'] = collect();
			$this->data['selected_groups'] = '';
			$this->data['user_units'] = collect();
		
		return view('core.users.form',$this->data);

	}
	function edit( Request $request , $id ) 
	{
		$this->hook( $request , $id );
		if(!isset($this->data['row']))
			return redirect($this->module)->with('message','Record Not Found !')->with('status','error');

		if($this->access['is_edit'] ==0 )
			return redirect('dashboard')->with('message',__('core.note_restric'))->with('status','error');

			$data = (array) $this->data['row'];
			$unit = (array) $this->model->getuserunit($id);
			$this->data['row'] 	= array_merge($data, $unit);
			$this->data['id'] 	= $id;
			$this->data['user_groups'] = \SiteHelpers::getUserGroups($id);
			$this->data['selected_groups'] = $this->data['user_groups']->pluck('group_id')->implode(',');
			$this->data['user_units'] = \SiteHelpers::getUserUnits($id);

		return view('core.users.form',$this->data);
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
			case 'blast':
				return $this->getBlast( $request);
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

				$this->data['user_units'] = \SiteHelpers::getUserUnits($id);
				$this->data['user_groups'] = \SiteHelpers::getUserGroups($id);

				return view('core.users.view',$this->data);	
				break;		
		}
	}	

	function store( Request $request  )
	{
		$task = $request->input('action_task');
		switch ($task)
		{
			default:
				return $this->postSave( $request );
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
		}	
	
	}

	function postSave( $request, $id =0)
	{
		
		$rules = $this->validateForm();
		if($request->input('id') =='')
		{
			$rules['password'] 				= 'required|between:6,12|confirmed';
			$rules['password_confirmation'] = 'required|between:6,12';
			$rules['email'] 				= 'required|email|unique:tb_users';
			$rules['id_unit'] 				= 'required';
			
		} else {
			if($request->input('password') !='')
			{
				$rules['password'] 				='required|between:6,12|confirmed';
				$rules['password_confirmation'] ='required|between:6,12';			
			}
		}
		if(!is_null($request->file('avatar'))) $rules['avatar'] = 'mimes:jpg,jpeg,png,gif,bmp';

		$validator = Validator::make($request->all(), $rules);	
		if ($validator->passes()) {
			$data = $this->validatePost( $request );
			if($request->input('id') =='')
			{
				$data['password'] = \Hash::make($request->input('password'));
			} else {
				if($request->input('password') !='')
				{
					$data['password'] = \Hash::make($request->get('password'));
				} else {
					unset($data['password']);
				}
			}
					
			unset($data['id_unit'], $data['id_position'], $data['start_date'], $data['end_date']);
			
			$id = $this->model->insertRow($data , $request->input('id'));

			$username = [
				'username' 		=> $request->input('username'),
				'nik' 			=> $request->input('nik'),
				'hp' 			=> $request->input('hp'),
			];

			$this->model->updateusername($id, $username);

			$this->model->syncUserUnits(
				$id,
				$request->input('id_unit', []),
				$request->input('id_position', []),
				$request->input('start_date', []),
				$request->input('end_date', [])
			);

			// Ekstensi avatar diambil dari isi file (rule mimes sudah memastikan gambar)
			$extension = \SiteHelpers::safeUploadExtension($request->file('avatar'), true);
			if(!is_null($request->file('avatar')) && $extension !== null)
			{
				$updates = array();
				$file = $request->file('avatar');
				$destinationPath = './uploads/users/';
				 $newfilename = (int) $id.'.'.$extension;
				$uploadSuccess = $request->file('avatar')->move($destinationPath, $newfilename);				 
				if( $uploadSuccess ) {
				    $updates['avatar'] = $newfilename; 
				} 
				$this->model->insertRow($updates , $id );
			}	

			if(!is_null($request->input('apply')))
			{
				$return = 'core/users/update/'.$id.'?return='.self::returnUrl();
			} else {
				$return = 'core/users?return='.self::returnUrl();
			}
			
			return redirect($return)->with('message',__('core.note_success'))->with('status','success');
			
		} else {

			return redirect()->back()->with('message',__('core.note_error'))->with('status','error')
			->withErrors($validator)->withInput();
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

	function getBlast()
	{
		$this->data = array(
			'groups'	=> Groups::all(),
			'pageTitle'	=> 'Blast Email',
			'pageNote'	=> 'Send email to users'
		);	
		return view('core.users.blast',$this->data);		
	}

	function postDoblast( Request $request)
	{

		$rules = array(
			'subject'		=> 'required',
			'message'		=> 'required|min:10',
			'groups'		=> 'required',				
		);	
		$validator = Validator::make($request->all(), $rules);	
		if ($validator->passes()) 
		{	

			if(!is_null($request->input('groups')))
			{
				$count = 0;
				$groups = $request->input('groups');
				for($i=0; $i<count($groups); $i++)
				{
					if($request->input('uStatus') == 'all')
					{
						$users = \App\Support\Tenancy\TenantScope::table('tb_users')
							->join('tb_users_groups', 'tb_users_groups.user_id', '=', 'tb_users.id')
							->where('tb_users_groups.group_id','=',$groups[$i])
							->select('tb_users.*')
							->get();
					} else {
						$users = \App\Support\Tenancy\TenantScope::table('tb_users')
							->join('tb_users_groups', 'tb_users_groups.user_id', '=', 'tb_users.id')
							->where('tb_users.active','=',$request->input('uStatus'))
							->where('tb_users_groups.group_id','=',$groups[$i])
							->select('tb_users.*')
							->get();
					}
					

					foreach($users as $row)
					{
						$data['note'] 	= $request->input('message');
						$data['row']		= $row;
						$data['to']			= $row->email;
						$data['subject']	= $request->input('subject');
						$data['cnf_appname'] = $this->config['cnf_appname'];
						
						
						if($this->config['cnf_mail'] && $this->config['cnf_mail'] =='swift')
						{ 
							\Mail::send('core.users.email', $data, function ($message) use ($data) {
					    		$message->to($data['to'])->subject($data['subject']);
					    	});


					    } else {
					    	$message = view('core.users.email',$data);
							$headers  = 'MIME-Version: 1.0' . "\r\n";
							$headers .= 'Content-type: text/html; charset=iso-8859-1' . "\r\n";
							$headers .= 'From: '.$this->config['cnf_appname'].' <'.$this->config['cnf_email'].'>' . "\r\n";
								mail($data['to'], $data['subject'], $message, $headers);

					    }							
						
						++$count;					
					} 
					
				}
				return redirect('core/users/blast')->with('message','Total '.$count.' Message has been sent')->with('status','success');

			}
			return redirect('core/users/blast')->with('message','No Message has been sent')->with('status','info');
			

		} else {

			return redirect('core/users/blast')->with('message', 'The following errors occurred')->with('status','error')
			->withErrors($validator)->withInput();

		}	

	}

	public function getSearch( $mode = 'native')
	{

		$this->data['tableForm'] 	= $this->info['config']['forms'];	
		$this->data['tableGrid'] 	= $this->info['config']['grid'];
		$this->data['searchMode'] = 'native';
		$this->data['pageUrl']		= url('core/users');
		return view('sximo.module.utility.search',$this->data);
	
	}

	public function AjaxData(Request $request){

        $validator = Validator::make($request->all(), [
			'id_unit' => 'max:11',
        ]);

        if ($validator->passes()) {

			$unit = cleanthis($request->input('id_unit'));
			$list 		= $this->model->get_datatables($unit);

			$this->access = $this->model->validAccess($this->info['id'] , session('gid'));

			$data = array();
			$no = intval($request->input('start', 0));
					
					foreach ($list as $item) {
						$no++;
						$row = array();

						$row[] = $no.".";
						$row[] = e($item->unit_name ?? "", false);
						$row[] = e($item->name ?? "", false);
						$row[] = e($item->username, false);
						$row[] = e($item->first_name, false);
						$row[] = e($item->last_name, false);
						$row[] = e($item->email, false);
						$row[] = $item->active == 1 ? 'Active' : 'Non Active';
						$btn = "";
						$url	= url('core/users/'.$item->id.'/edit?return=');
						$view	= url("/core/users/$item->id");
						
						$btn .= "
							<div class='dropdown'>
							  <button class='btn btn-sm dropdown-toggle' type='button' data-bs-toggle='dropdown'> <i class='fas fa-tasks'></i> </button>
							  <ul class='dropdown-menu'>";					
								if(($this->access['is_view'] ?? 0) == 1 || ($this->access['is_detail'] ?? 0) == 1){
									$btn .= "<li class='nav-item'><a  href='".$view."' class='nav-link tips' title='View'> View</a></li>";
								}
								if($this->access['is_edit'] ==1){
									$btn .= "<li class='nav-item'><a  href='".$url."' class='nav-link tips' title='Edit'> Edit</a></li>";
									$btn .= "<li class='nav-item'><a  href='".url('core/users/'.$item->id.'/scopes')."' class='nav-link tips' title='Cakupan data'> Cakupan data</a></li>";
								}
								if($this->access['is_remove'] ==1):
									$btn .="<li class='nav-item'><a href='javascript:void(0)'  onclick='hapususer(\"".$item->id."\");' class='nav-link tips' title='Hapus'>
										Remove Selected </a></li>";
									endif;
						$btn .= "</ul>
							</div>";

						$row[] = $btn;
						$data[] = $row;
					}
				
				$output = array(
					"draw" 				=> intval($request->input('draw', 0)),
					"recordsTotal" 		=> $this->model->count_ajax($unit),
					"recordsFiltered" 	=> $this->model->count_filtered($unit),
					"data" 				=> $data,
				);

				echo json_encode($output);

        }

    }

	public function deluser(Request $request){
		$this->access = $this->model->validAccess($this->info['id'] , session('gid'));
		if(empty($this->access['is_remove']))
			return response()->json(array('status' => 'error', 'message' => __('core.note_restric'), 'success' => false), 403);

		$validator = Validator::make($request->all(), [
			'user_id'	=> 'required|max:11',
        ]);

        if ($validator->passes()) {

			$id = cleanthis($request->input('user_id'));
			$this->model->deluser($id);

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

	public function unit(Request $request){
		$validator = Validator::make($request->all(), [
            'id'     	=> 'max:11',
			'id_corporate'   => 'max:11',
        ]);

        if ($validator->passes()) {

			$id			= cleanthis($request->input('id'));
			$cor		= cleanthis($request->input('id_corporate'));
			$selectedUnit = cleanthis($request->input('selected_unit'));
			if ($selectedUnit !== null && $selectedUnit !== '') {
				$unit = $selectedUnit;
			} elseif(!empty($id)){
				$unit = get_user_data($id, 'id_unit');
			}else{
				$unit = "";
			}

			$lists ="<option value=''></option>";
			if(!empty($cor)){
				$where = ["parent_id IS NULL", "id_corporate = ".$cor]; 
				$data = db_get_all_data('unit', $where);
				foreach($data as $row){
					$selected = ($row->id_unit == $unit) ? 'selected' : '';
					$lists .="<option $selected value=$row->id_unit>$row->unit_name</option>";
				}
			} else {
				$where = ["parent_id IS NULL"]; 
				$data = db_get_all_data('unit', $where);
				if (empty($data) || count($data) == 0) {
					$data = db_get_all_data('unit');
				}
				foreach($data as $row){
					$selected = ($row->id_unit == $unit) ? 'selected' : '';
					$lists .="<option $selected value=$row->id_unit>$row->unit_name</option>";
				}
			}
			
			$this->data['callback'] = $lists;

        }else{
            $message = $this->validateListError(  $validator->getMessageBag()->toArray() );
            return response()->json(array(
                'message'   => $message,
                'status'    => 'error',
				'success'	=> false,
            )); 
        }

        if(!empty($this->data)){
            echo json_encode($this->data);
        }
    }

	public function position(Request $request){
		$validator = Validator::make($request->all(), [
            'id'     	=> 'max:11',
			'id_unit'   => 'max:11',
        ]);

        if ($validator->passes()) {

			$id		= cleanthis($request->input('id'));
			$unit	= cleanthis($request->input('id_unit'));
			$selectedPos = cleanthis($request->input('selected_position'));
			if ($selectedPos !== null && $selectedPos !== '') {
				$position = $selectedPos;
			} elseif(!empty($id)){
				$position = get_user_data($id, 'id_position');
			}else{
				$position = "";
			}

			if(!empty($unit)){
				$code = get_unit($unit, 'unit_code');
				$data = $this->model->getpositionbycode($code);

				if(!empty($data)){
					$lists ="<option value=''></option>";
					foreach($data as $row){
						$selected = ($row->id_unit == $position) ? 'selected' : '';
						$lists .="<option $selected value=$row->id_unit>$row->unit_name</option>";
					}
				}else{
					$lists ="<option value=''></option>";
				}
			} else {
				$lists ="<option value=''></option>";
			}
			
			$this->data['callback'] = $lists;

        }else{
            $message = $this->validateListError(  $validator->getMessageBag()->toArray() );
            return response()->json(array(
                'message'   => $message,
                'status'    => 'error',
				'success'	=> false,
            )); 
        }

        if(!empty($this->data)){
            echo json_encode($this->data);
        }
    }

	public function getComboselect( Request $request)
	{
		if($request->ajax() == true && \Auth::check() == true)
		{
			$param = explode(':',$request->input('filter'));
			$parent = (!is_null($request->input('parent')) ? $request->input('parent') : null);
			$limit = (!is_null($request->input('limit')) ? $request->input('limit') : null);
			$rows = $this->model->getComboselect($param,$limit,$parent);
			$items = array();
		
			$fields = explode("|",$param[2]);
			
			foreach($rows as $row) 
			{
				if ($param[0] == 'tb_groups') {
					if (isset($row->group_id) && $row->group_id < \Auth::user()->group_id) {
						continue;
					}
				}

				$value = "";
				foreach($fields as $item=>$val)
				{
					if($val != "") $value .= $row->{$val}." ";
				}
				$items[] = array($row->{$param['1']} , $value); 	
			}
			
			return json_encode($items); 	
		} else {
			return json_encode(array('OMG'=>" Ops .. Cant access the page !"));
		}	
	}

// End of bracket
}