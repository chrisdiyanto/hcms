<?php namespace App\Http\Controllers;

use App\Models\Corporate;
use App\Library\DataHelpers;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator as Paginator;
use Validator, Input, Redirect ; 

class CorporateController extends Controller {

	protected $layout = "layouts.main";
	protected $data = array();	
	public $module = 'corporate';
	static $per_page	= '10';
	
	public function __construct() 
	{
		parent::__construct();
		$this->model = new Corporate();
		
		$this->info = $this->model->makeInfo( $this->module);
		$this->access = $this->model->validAccess($this->info['id']);
	
		$this->data = array(
			'pageTitle'			=> 	$this->info['title'],
			'pageNote'			=>  $this->info['note'],
			'pageModule'		=> 'corporate',
			'pageUrl'			=>  url('corporate'),
			'return' 			=> 	self::returnUrl()	
		);	
			
				
	} 
	public function index()
	{
		if(!\Auth::check()) 
			return redirect('user/login')->with('status', 'error')->with('message','You are not login');

		$this->access = $this->model->validAccess($this->info['id'] , session('gid'));
		if($this->access['is_view'] ==0) 
			return redirect('dashboard')->with('message',__('core.note_restric'))->with('status','error');
		
		$this->data['setting'] 		= $this->info['setting'];
		$this->data['tableForm'] 	= $this->info['config']['forms'];
		$this->data['tableGrid'] 	= $this->info['config']['grid'];
		usort($this->data['tableGrid'], "SiteHelpers::_sort");
			$cols = '{"data":"rowId"},';
			if($this->data['setting']['view-method'] =='expand'){
				$cols .= '{"className": "details-control","orderable": false,"data": null,"defaultContent": ""},';
			}		
				
			foreach($this->data['tableGrid'] as $field)
			{				
				
				if($field['view'] =='1')
				{
					$cols .= '{"data":"'.$field['field'].'"},';
				}						
			}
			$cols .= '{"data":"action"},';
		$this->data['column'] = $cols ;	
		$this->data['access']		= $this->access;	
		$this->data['setting'] 		= $this->info['setting'];
		return view('corporate.index',$this->data);
	}
	function create( Request $request ) 
	{
		$this->hook( $request  );
		if($this->access['is_add'] ==0) 
			return redirect('dashboard')->with('message', __('core.note_restric'))->with('status','error');

		$this->data['row'] = $this->model->getColumnTable( $this->info['table']); 
		
		$this->data['setting'] 		= $this->info['setting'];
		$this->data['id'] = '';
		return view($this->module.'.form',$this->data);
	}
	function edit( Request $request , $id ) 
	{
		$this->hook( $request , $id );
		if(!isset($this->data['row']))
			return redirect($this->module)->with('message','Record Not Found !')->with('status','error');
		if($this->access['is_edit'] ==0 )
			return redirect('dashboard')->with('message',__('core.note_restric'))->with('status','error');
		$this->data['row'] = (array) $this->data['row'];
		
		$this->data['setting'] 		= $this->info['setting'];
		$this->data['id'] = $id;
		return view($this->module.'.form',$this->data);
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
			case 'lookup':
				return $this->getLookup($request );
				break;
			case 'comboselect':
				return $this->getComboselect( $request );
				break;
			case 'import':
				return $this->getImport( $request );
			case 'removefiles':
				return $this->getRemovefiles( $request );	
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

				return view($this->module.'.view',$this->data);	
				break;		
		}
	}	
	public function store( Request $request){

		$task = $request->input('action_task');
		switch ($task)
		{
			default:
				return $this->postData( $request);
				break;
			case 'save':
				$rules = $this->validateForm();
				$currentId = (int) $request->input($this->info['key']);
				// Kolom code & slug unik di database: validasi di sini agar tidak berakhir sebagai error SQL
				$rules['corporate_name'] = 'required|max:255';
				$rules['code'] = 'required|max:20|unique:corporate,code'.($currentId ? ','.$currentId.',id_corporate' : '');
				$validator = Validator::make($request->all(), $rules);
				if ($validator->passes())
				{
					$data = $this->validatePost( $request );
					$data['slug_corporate'] = $this->uniqueSlug($request->input('corporate_name'), $currentId);
					$id = $this->model->insertRow($data , $request->input( $this->info['key']));
					
					/* Insert logs */
					$this->model->logs($request , $id, 'corporate', $data);

					return response()->json(array('status'=>'success','message'=> __('core.note_success')));	
					
				} 
				else {
					$message = $this->validateListError(  $validator->getMessageBag()->toArray() );
					return response()->json(array(
						'message'	=> $message,
						'status'	=> 'error'
					));	
				}
				break;
			case 'delete':
				return $result = $this->destroy( $request );
				break;

			case 'import':
				return $this->PostImport( $request );
				break;

			case 'copy':
				$result = $this->copy( $request );
				return response()->json($result);
				break;		

		}
	}
	// Slug dari nama perusahaan, unik di tabel corporate (mis. pt-abc, pt-abc-2)
	protected function uniqueSlug( $name, $currentId = 0 )
	{
		$base = \Illuminate\Support\Str::limit(\Illuminate\Support\Str::slug((string) $name), 90, '') ?: 'corporate';
		$slug = $base;
		$i = 2;
		while (\DB::table('corporate')->where('slug_corporate', $slug)->where('id_corporate', '!=', (int) $currentId)->exists()) {
			$slug = $base.'-'.$i++;
		}
		return $slug;
	}

	public function postData( $request)
	{ 
		
		$this->access = $this->model->validAccess($this->info['id'] , session('gid'));

		$tables 	= $this->info['config']['grid'];
		$cols = array();
		foreach($tables as $field)
		{				
			//$cols[$this->info['key']]	= array('db'=> $field['alias'] ,$this->info['key'] );
			if($field['view'] =='1')
			{
				$cols[] = array('db'=> $field['alias'],'dt'=> $field['field'] );
			}					
		}
		$conf = DataHelpers::simple($request->all(),$cols);
		$filter =  '';
		if(!is_null($request->input('search')))
		{
			$filter .= $this->liveSearch( $request);
		}

		$params = array(
			'page'		=> $conf['page'] ,
			'limit'		=> $conf['limit'] ,
			'sort'		=> ($conf['order']['sort'] !='' ? $conf['order']['sort'] : $this->info['setting']['orderby']) ,
			'order'		=> ($conf['order']['by'] !='' ? $conf['order']['by'] : $this->info['setting']['ordertype']),
			'params'	=> $filter,
			'global'	=> (isset($this->access['is_global']) ? $this->access['is_global'] : 0 )
		);


		$results = $this->model->getRows($params);
		
		$array = array();
		$no = 1+(($conf['page']-1)*$conf['limit']);
		foreach($results['rows'] as $key=>$row)
		{
			$values = array();
			$values['rowId'] = $row->{$this->info['key']};
			foreach($tables as $field)
			{			
				
				if($field['view'] =='1')
				{
					if($field['field'] == 'id_corporate'){
						
						$values[$field['field']] = 	$no;

					}else{
						$values[$field['field']] = \SiteHelpers::formatRows($row->{$field['field']}, $field , $row);
					}
				}	
			}
			$values['action'] =  \SiteHelpers::buttonAction($this->module,$this->access ,$row->{$this->info['key']} ,$this->info['setting']) ;
			$array[] = $values; 	
			$no++;
		}
		
		$data = array(
			'draw'			=> (!is_null($request->input('draw')) ? intval($request->input('draw') ) : 0), 
			'recordsTotal'	=> $results['total'] ,
			'recordsFiltered'	=> $results['total'],
			'data'				=> $array

		);
		return response()->json($data);
	}

	public function destroy( Request $request)
	{

		if(!\Auth::check()) 
			return response()->json(array(
				'status'=>'error',
				'message'=> 'You are not login'
			));

		$this->access = $this->model->validAccess($this->info['id'] , session('gid'));
		if($this->access['is_remove'] ==0) 
			return response()->json(array('status'=>'error','message'=> __('core.note_restric')));	


		// delete multipe rows 
		$ids = array_filter(array_map('intval', (array) $request->input('ids')));
		if(count($ids) >=1)
		{
			$this->model->destroy($ids);

			// ids berupa array: gabungkan agar bisa dicatat di log
			$id = implode(',', $ids);

			$data = "Data ".$id." dihapus";

			/* Insert logs */
			$this->model->logs($request , $id, 'corporate', $data);	
			
			return response()->json(array(
				'status'=>'success',
				'message'=> __('core.note_success_delete')
			));
		} else {
			return response()->json(array(
				'status'=>'error',
				'message'=> __('core.note_error')
			));

		} 		

	}
}