<?php namespace App\Http\Controllers;
use Mail;
use Carbon\Carbon;
use PDF ;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Http\Request;
use Validator, Input, Redirect ;
use Illuminate\Support\Facades\Auth;
use Illuminate\Pagination\LengthAwarePaginator as Paginator;

abstract class Controller extends BaseController {

	use DispatchesJobs, ValidatesRequests;

	protected $user_id ;
	protected $users ;

	public function __construct()
	{
		
		$this->middleware('ipblocked');
		$driver             = config('database.default');
        $database           = config('database.connections');
       
        $this->db           = $database[$driver]['database'];
        $this->dbuser       = $database[$driver]['username'];
        $this->dbpass       = $database[$driver]['password'];
        $this->dbhost       = $database[$driver]['host']; 

        // Load Sximo Config
        $sximo 	= config('sximo');
        $this->config = $sximo ;
        $this->data['sximoconfig'] = $sximo ;  

        $this->middleware(function ($request, $next) {
            if (\Auth::check()) {
                $this->data['userGroups'] = \SiteHelpers::getUserGroups();
                $this->data['currentGid'] = session('gid');
                $this->data['userUnits'] = \SiteHelpers::getUserUnits();
                $this->data['currentUnitId'] = session('id_unit');
            } else {
                $this->data['userGroups'] = collect();
                $this->data['currentGid'] = null;
                $this->data['userUnits'] = collect();
                $this->data['currentUnitId'] = null;
            }
            \View::share('userGroups', $this->data['userGroups']);
            \View::share('currentGid', $this->data['currentGid']);
            \View::share('userUnits', $this->data['userUnits']);
            \View::share('currentUnitId', $this->data['currentUnitId']);
            return $next($request);
        });

        // Guard terpusat untuk task modul (store / show) yang sebelumnya bisa diakses tanpa login
        $this->middleware(function ($request, $next) {
            $denied = $this->guardModuleTask($request);
            if($denied) return $denied;
            return $next($request);
        });
	}

	protected function guardModuleTask( $request )
	{
		if(!isset($this->info['id']) || !$request->route()) return null;

		$method = $request->route()->getActionMethod();
		if($method == 'store')
		{
			$task = (string) $request->input('action_task');
			switch($task)
			{
				case 'public':
					// Form publik (store_public) memang untuk tamu
					return null;
				case 'delete':
					$perm = 'is_remove';
					break;
				case 'import':
				case 'copy':
					$perm = 'is_add';
					break;
				case 'save':
					$perm = $this->savePermission($request);
					break;
				default:
					// Template datatable: default = ambil data grid; native/core: default = simpan form
					$perm = $request->has($this->info['key']) ? $this->savePermission($request) : 'is_view';
					break;
			}
		}
		else if($method == 'show')
		{
			$params = array_values($request->route()->parameters());
			$task = isset($params[0]) ? $params[0] : null;
			$tasks = array(
				'lookup'		=> 'is_view',
				'import'		=> 'is_add',
				'removefiles'	=> 'is_edit',
				'search'		=> null,
				'comboselect'	=> null,
			);
			if(!is_string($task) || !array_key_exists($task, $tasks)) return null;
			$perm = $tasks[$task];
		}
		else if($method == 'update')
		{
			$perm = 'is_edit';
		}
		else if(in_array($method, array('destroy', 'deluser', 'delgroup'), true))
		{
			// deluser / delgroup (Core Users/Groups) sebelumnya tanpa cek login & izin
			$perm = 'is_remove';
		}
		else if($method == 'AjaxData')
		{
			$perm = 'is_view';
		}
		else {
			return null;
		}

		if($this->moduleTaskAllowed($request, $perm)) return null;

		if($request->ajax() || $request->wantsJson())
			return response()->json(array('status'=>'error','message'=>__('core.note_restric')), 403);

		if(!\Auth::check())
			return redirect('user/login')->with('status', 'error')->with('message','You are not login');

		return redirect('dashboard')->with('message',__('core.note_restric'))->with('status','error');
	}

	protected function savePermission( $request )
	{
		return $request->input($this->info['key']) ? 'is_edit' : 'is_add';
	}

	protected function moduleTaskAllowed( $request, $perm )
	{
		// Request API (middleware sximoauth) membawa profile, request web memakai session login
		$profile = $request->attributes->get('profile');
		if(!\Auth::check() && !$profile) return false;
		if($perm === null) return true;

		$gid = $profile ? $profile->group_id : session('gid');
		$access = $this->access($this->info['id'], $gid);
		return is_array($access) && isset($access[$perm]) && $access[$perm] == '1';
	}

	protected function resolveUploadPath( $file )
	{
		// Hanya izinkan file yang benar-benar berada di dalam public/uploads
		$file = (string) $file;
		if($file === '') return null;

		$base = realpath(public_path('uploads'));
		$path = realpath(public_path(ltrim($file, '/\\')));
		if(!$base || !$path || !is_file($path)) return null;
		if(strpos($path, $base.DIRECTORY_SEPARATOR) !== 0) return null;

		return $path;
	}


	function getComboselect( Request $request)
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

	public function getCombotable( Request $request)
	{
		if(Request::ajax() == true && Auth::check() == true)
		{				
			$rows = $this->model->getTableList($this->db);
			$items = array();
			foreach($rows as $row) $items[] = array($row , $row); 	
			return json_encode($items); 	
		} else {
			return json_encode(array('OMG'=>"  Ops .. Cant access the page !"));
		}				
	}		
	
	public function getCombotablefield( Request $request)
	{
		if($request->input('table') =='') return json_encode(array());	
		if(Request::ajax() == true && Auth::check() == true)
		{	

				
			$items = array();
			$table = $request->input('table');
			if($table !='')
			{
				$rows = $this->model->getTableField($request->input('table'));			
				foreach($rows as $row) 
					$items[] = array($row , $row); 				 	
			} 
			return json_encode($items);	
		} else {
			return json_encode(array('OMG'=>"  Ops .. Cant access the page !"));
		}					
	}

	function postMultisearch( Request $request)
	{
		$post = $_POST;
		$items ='';
		foreach($post as $item=>$val):
			if($_POST[$item] !='' and $item !='_token' and $item !='md' && $item !='id'):
				$items .= $item.':'.trim($val).'|';
			endif;	
		
		endforeach;
		
		return Redirect::to($this->module.'?search='.substr($items,0,strlen($items)-1).'&md='.$request->input('md'));
	}

	function buildSearch( $map = false)
	{

			$keywords = ''; $fields = '';	$param ='';
			$allowsearch = $this->info['config']['forms'];
			$arr = array();
			foreach($allowsearch as $as) if(self::isSearchableColumn($as)) $arr[$as['field']] = $as ;		
			$mapping = '';
			if($_GET['search'] !='')
			{
				$type = explode("|",$_GET['search'] );
				if(count($type) >= 1)
				{
					foreach($type as $t)
					{
						$keys = explode(":",$t);
						if(in_array($keys[0],array_keys($arr))):
							if($arr[$keys[0]]['type'] == 'select' || $arr[$keys[0]]['type'] == 'radio' )
							{
								$param .= " AND ".$arr[$keys[0]]['alias'].".".$keys[0]." ".self::searchOperation($keys[1])." ".self::quoteValue($keys[2] ?? '')." ";	
								$mapping .= $keys[0].' '.self::searchOperation($keys[1]).' '.e($keys[2] ?? ''). '<br />';

							} else {
								$operate = self::searchOperation($keys[1]);
								if($operate == 'like')
								{
									$param .= " AND ".$arr[$keys[0]]['alias'].".".$keys[0]." LIKE ".self::quoteValue('%'.($keys[2] ?? '').'%')." ";
									$mapping .= $keys[0].' LIKE '.e($keys[2] ?? ''). '<br />';
								} else if( $operate =='is_null') {
									$param .= " AND ".$arr[$keys[0]]['alias'].".".$keys[0]." IS NULL ";
									$mapping .= $keys[0].' IS NULL <br />';

								} else if( $operate =='not_null') {
									$param .= " AND ".$arr[$keys[0]]['alias'].".".$keys[0]." IS NOT NULL ";
									$mapping .= $keys[0].' IS NOT NULL <br />';

								} else if( $operate =='between') {
									$param .= " AND (".$arr[$keys[0]]['alias'].".".$keys[0]." BETWEEN ".self::quoteValue($keys[2] ?? '')." AND ".self::quoteValue($keys[3] ?? '')." ) ";
									$mapping .= $keys[0].' BETWEEN '.e($keys[2] ?? ''). ' - '. e($keys[3] ?? '') .'<br />';
								} else {
									$param .= " AND ".$arr[$keys[0]]['alias'].".".$keys[0]." ".self::searchOperation($keys[1])." ".self::quoteValue($keys[2] ?? '')." ";
									$mapping .= $keys[0].' '.self::searchOperation($keys[1]).' '.e($keys[2] ?? ''). '<br />';	
								}												
							}						
						endif;	
					}
				} 
			}

		if($map == true)
		{
			return $param = array(
					'param'	=> $param,
					'maps'	=> '
					<div class="infobox infobox-info fade in" style="font-size:10px;">
					  <button type="button" class="btn-close float-end" data-bs-dismiss="alert" aria-label="Close"></button>  
					 <b class="text-danger"> Search Result </b> :  <br /> '.$mapping.'
					</div>
					'
				);			

		} else {
			return $param;
		}		
		
	
	}


	// Escape nilai dari request sebelum disisipkan ke potongan SQL mentah
	protected static function quoteValue( $value )
	{
		if(is_array($value)) $value = '';
		return \DB::getPdo()->quote((string) $value);
	}

	function onSearch( $params )
	{
		// Used for extracting URL GET search 
		$psearch = explode('|',$params);
		$currentSearch = array();
		foreach($psearch as $ps)
		{
			$tosearch = explode(':',$ps);
			if(count($tosearch) >=2)
			$currentSearch[$tosearch[0]] = $tosearch[2]; 
		}
		return $currentSearch;		
	}
	
	function searchOperation( $operate)
	{
		$val = '';
		switch ($operate) {
			case 'equal':
				$val = '=' ;
				break;
			case 'bigger_equal':
				$val = '>=' ;
				break;
			case 'smaller_equal':
				$val = '<=' ;
				break;				
			case 'smaller':
				$val = '<' ;
				break;
			case 'bigger':
				$val = '>' ;
				break;
			case 'not_null':
				$val = 'not_null' ;
				break;								

			case 'is_null':
				$val = 'is_null' ;
				break;	

			case 'like':
				$val = 'like' ;
				break;	

			case 'between':
				$val = 'between' ;
				break;					

			default:
				$val = '=' ;
				break;
		}
		return $val;
	}		

	function inputLogs(Request $request, $note = NULL)
	{
		$now = date('Y-m-d H:i:s');
		$data = array(
			'module'	=> $request->segment(1),
			'task'		=> $request->segment(2),
			'user_id'	=> \Session::get('uid') ?? \Auth::id() ?? 0,
			'ipaddress'	=> $request->getClientIp() ?? '127.0.0.1',
			'note'		=> $note ?? '',
			'created_at'=> $now,
			'updated_at'=> $now,
		);
		\DB::table( 'tb_logs')->insert($data);
	
	}

	function validateForm( $forms = array() )
	{
		if(count($forms) <= 0)
			$forms = $this->info['config']['forms'];
		
		//print_r($forms);
		$rules = array();
		foreach($forms as $form)
		{
			
			if($form['required'] =='0') {
				$form['required'] = '';
			}
			if($form['required'] != '' )
			{
			//	

				if( $form['type'] != 'file') {			
					$rules[$form['field']] = $form['required'];
				} 
				else {

					if($form['required'] =='required')
					{
						$validation = 'required';
						if($form['option']['upload_type'] =='image')
						{
							$validation .= '|mimes:jpg,jpeg,png,gif,bmp';

						} else {
							$validation .= '|mimes:zip,csv,xls,doc,docx,xlsx,pdf,rtf';						
						}

						if($form['option']['image_multiple'] != '1')
						{
							// IF SINGLE UPLOAD FILE OR IMAGE 	
							$rules[$form['field']] = $validation;

						}  else {
							// IF MULTIPLE UPLOAD FILE OR IMAGE 	
							$FilesArray = [];
							if(isset($_FILES[$form['field']])) {
								if(count($_FILES[$form['field']]) >=1 )
								{
									$nbr = count($_FILES[$form['field']]) - 1;
									foreach(range(0, $nbr) as $index) {
									   // $imagesArray['images.' . $index] = 'required|image';
									    $rules[$form['field'].'.'.$index] = $validation;
									}
								}
							}	
						}
						
					
					} else {
						
						if($form['option']['upload_type'] =='image')
						{
							$validation = 'mimes:jpg,jpeg,png,gif,bmp';
						} 
						else {
							$validation = 'mimes:zip,csv,xls,doc,docx,xlsx';						
						}

						if($form['option']['image_multiple'] != '1')
						{
							// IF SINGLE UPLOAD FILE OR IMAGE 	
							$rules[$form['field']] = $validation;

						}  else {
							// IF MULTIPLE UPLOAD FILE OR IMAGE 	
							$FilesArray = [];
							if(isset($_FILES[$form['field']])) {
								if(count($_FILES[$form['field']]) >=1 )
								{
									$nbr = count($_FILES[$form['field']]) - 1;
									foreach(range(0, $nbr) as $index) {
									   // $imagesArray['images.' . $index] = 'required|image';
									    $rules[$form['field'].'.'.$index] = $validation;
									}
								}
							}	

						}
					} 
				}
			}						
		}	
		
		return $rules ;


	}	

	function validateListError( $rules )
    {
        $errMsg = __('core.note_error') ;
        $errMsg .= '<hr /> <ul>';
        foreach($rules as $key=>$val)
        {
            $errMsg .= '<li>'.$key.' : '.$val[0].'</li>';
        }
        $errMsg .= '</li>'; 
        return $errMsg;
    }

	function validatePost(  $request )
	{	
		$str = $this->info['config']['forms'];
		$data = array();
		foreach($str as $f){
			$field = $f['field'];
			// Update for V5.1.5 issue on Autofilled createOn and updatedOn fields
			if($field == 'createdOn') $data['createdOn'] = date('Y-m-d H:i:s');
            if($field == 'updatedOn') $data['updatedOn'] = date('Y-m-d H:i:s');			
			if($f['view'] ==1) 
			{
				

				if($f['type'] =='textarea_editor' || $f['type'] =='textarea')
				{
					 $data[$field] = $request->input( $field );
				} else {

					if(!is_null( $request->input( $field ) )) $data[$field] = $request->input( $field ) ;

					// if post is file or image		


					if($f['type'] =='file')
					{	
						
						if(!is_dir(public_path().$f['option']['path_to_upload']))
							mkdir( public_path().$f['option']['path_to_upload'],0777 );   

						$files ='';	
						if($f['option']['upload_type'] =='file')
						{

							if(isset($f['option']['image_multiple']) && $f['option']['image_multiple'] ==1)
							{								
								if(isset($_POST['curr'.$field]))
								{
									$curr =  '';
									for($i=0; $i<count($_POST['curr'.$field]);$i++)
									{
										$files .= $_POST['curr'.$field][$i].',';
									}
								}	

								if(!is_null($request->file($field)))
								{

									$destinationPath = '.'. $f['option']['path_to_upload'];
									foreach((array) $request->file($field) as $file ){
										// Tolak ekstensi skrip/HTML; nama file dibuat ulang agar tidak bisa menimpa file lain
										$extension = \SiteHelpers::safeUploadExtension($file);
										if($extension === null)
											continue;
										$baseName = \Illuminate\Support\Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME)) ?: 'file';
										$file_name = time().'-'.\Illuminate\Support\Str::random(6).'-'.$baseName.'.'.$extension;
										$file->move($destinationPath, $file_name);
										$files .= $file_name.',';
									}
									
									if($files !='')	$files = substr($files,0,strlen($files)-1);	
									$data[$field] = $files;
								} else {
									unset($data[$field]);	
								}	
																					

							} else {
							
								// Tolak ekstensi skrip/HTML (php, phtml, html, svg, js, ...)
								$extension = \SiteHelpers::safeUploadExtension($request->file($field));
								if(!is_null($request->file($field)) && $extension !== null)
								{

									// check if folder exists

										$file = $request->file($field);
									 	$destinationPath = '.'. $f['option']['path_to_upload'];
										$rand = rand(1000,100000000);
										$newfilename = strtotime(date('Y-m-d H:i:s')).'-'.$rand.'.'.$extension;
										$uploadSuccess = $file->move($destinationPath, $newfilename);
										if( $uploadSuccess ) {
										   $data[$field] = $newfilename;
										}
								
								}	 
							}	

						} else {

							if(!is_dir(public_path().$f['option']['path_to_upload']))
								mkdir( public_path().$f['option']['path_to_upload'],0777 );   

							if(isset($f['option']['image_multiple']) && $f['option']['image_multiple'] ==1)
							{
								$files = '';
								if(isset($_POST['curr'.$field]))
								{
									$curr =  '';
									for($i=0; $i<count($_POST['curr'.$field]);$i++)
									{
										$files .= $_POST['curr'.$field][$i].',';
									}
								}

								$destinationPath = '.'. $f['option']['path_to_upload']; 
								if(count($request->file($f['field'])) >=1 )
								{
									
									$destinationPath = '.'. $f['option']['path_to_upload']; 
									foreach($_FILES[$field]['tmp_name'] as $key => $tmp_name ){
									 	$file_name = $_FILES[$field]['name'][$key];
										$file_tmp =$_FILES[$field]['tmp_name'][$key];
										if($file_name !='')
										{
											//move_uploaded_file($file_tmp,$destinationPath.'/'.$file_name);
											//echo  $file_name.'<br />';
											$file = $request->file($field)[$key];
											// Field gambar: ekstensi dari isi file dan wajib gambar
											$extension = \SiteHelpers::safeUploadExtension($file, true);
											if($extension === null)
												continue;
											$rand = rand(1000,100000000);
											$newfilename = strtotime(date('Y-m-d H:i:s')).'-'.$rand.'.'.$extension;
											$files .= $newfilename.',';

											$uploadSuccess = $file->move($destinationPath, $newfilename);


											 if($f['option']['resize_width'] != '0' && $f['option']['resize_width'] !='')
											 {
											 	if( $f['option']['resize_height'] ==0 )
												{
													$f['option']['resize_height']	= $f['option']['resize_width'];
												}
											 	$orgFile = $destinationPath.'/'.$newfilename;
												 \SiteHelpers::cropImage($f['option']['resize_width'] , $f['option']['resize_height'] , $orgFile ,  $extension,	 $orgFile)	;						 
											 }
										}										
									}

																	
								} 
								if($files !='')	$files = substr($files,0,strlen($files)-1);	
									$data[$field] = $files;	

								

							} else {

								// Field gambar: ekstensi dari isi file dan wajib gambar
								$extension = \SiteHelpers::safeUploadExtension($request->file($field), true);
								if(!is_null($request->file($field)) && $extension !== null)
								{
									$file = $request->file($field);
								 	$destinationPath = '.'. $f['option']['path_to_upload'];
									$rand = rand(1000,100000000);
									$newfilename = strtotime(date('Y-m-d H:i:s')).'-'.$rand.'.'.$extension;


									$uploadSuccess = $file->move($destinationPath, $newfilename);


									 if($f['option']['resize_width'] != '0' && $f['option']['resize_width'] !='')
									 {
									 	if( $f['option']['resize_height'] ==0 )
										{
											$f['option']['resize_height']	= $f['option']['resize_width'];
										}
									 	$orgFile = $destinationPath.'/'.$newfilename;
										 \SiteHelpers::cropImage($f['option']['resize_width'] , $f['option']['resize_height'] , $orgFile ,  $extension,	 $orgFile)	;						 
									 }
									 
									if( $uploadSuccess ) {
									   $data[$field] = $newfilename;
									}
								}	 
							}		

						}						
					}	

					// Handle Checkbox input 
					if($f['type'] =='checkbox')
					{
						if(!is_null( $request->{$field} ))
						{
							$data[$field] = implode(",", $request->input( $field ));
						} else {
							$data[$field] = '0';	
						}
					}
					// if post is date						
					if($f['type'] =='date')
					{
						$data[$field] = date("Y-m-d",strtotime($request->input($field)));
					}
					
					// if post is seelct multiple						
					if($f['type'] =='select')
					{
						//echo '<pre>'; print_r( $_POST[$field] ); echo '</pre>'; 
						if( isset($f['option']['select_multiple']) &&  $f['option']['select_multiple'] ==1 )
						{
							if(isset($_POST[$field]))
							{
								$multival = implode(",",$request->input( $field )); 
								$data[$field] = $multival;
							}
						} else {
							$data[$field] = $request->input( $field );
						}	
					}									
					
				}	 						

			}	
		}
		$this->access = $this->model->validAccess($this->info['id'] , session('gid'));
		$global	= (isset($this->access['is_global']) ? $this->access['is_global'] : 0 );
		
		if($global == 0 )
			$data['entry_by'] = \Session::get('uid');
		/* Added for Compatibility laravel 5.2 */
		$values = array();
		foreach($data as $key=>$val)
		{
			if($val !='') $values[$key] = $val;
		}			
		return $values;

	}


	function postFilter( Request $request)
	{
		$module = $this->module;
		$sort 	= (!is_null($request->input('sort')) ? $request->input('sort') : '');
		$order 	= (!is_null($request->input('order')) ? $request->input('order') : '');
		$rows 	= (!is_null($request->input('rows')) ? $request->input('rows') : '');
		$md 	= (!is_null($request->input('md')) ? $request->input('md') : '');
		
		$filter = '?';
		if($sort!='') $filter .= '&sort='.$sort; 
		if($order!='') $filter .= '&order='.$order; 
		if($rows!='') $filter .= '&rows='.$rows; 
		if($md !='') $filter .= '&md='.$md;
		 
		 

		return Redirect::to($this->data['pageModule'] . $filter);
	
	}	

	function injectPaginate()
	{

		$sort 	= (isset($_GET['sort']) 	? $_GET['sort'] : '');
		$order 	= (isset($_GET['order']) 	? $_GET['order'] : '');
		$rows 	= (isset($_GET['rows']) 	? $_GET['rows'] : '');
		$search = (isset($_GET['search']) ? $_GET['search'] : '');
		$s 		= (isset($_GET['s']) ? $_GET['s'] : '');

		$appends = array();
		if($sort!='') 	$appends['sort'] = $sort; 
		if($order!='') 	$appends['order'] = $order; 
		if($rows!='') 	$appends['rows'] = $rows; 
		if($search!='') $appends['search'] = $search; 
		if($s!='') $appends['s'] = $s; 
		
		return $appends;
			
	}	

	function returnUrl()
	{
		$pages 	= (isset($_GET['page']) ? $_GET['page'] : '');
		$sort 	= (isset($_GET['sort']) ? $_GET['sort'] : '');
		$order 	= (isset($_GET['order']) ? $_GET['order'] : '');
		$rows 	= (isset($_GET['rows']) ? $_GET['rows'] : '');
		$search 	= (isset($_GET['search']) ? $_GET['search'] : '');
		
		$appends = array();
		if($pages!='') 	$appends['page'] = $pages; 
		if($sort!='') 	$appends['sort'] = $sort; 
		if($order!='') 	$appends['order'] = $order; 
		if($rows!='') 	$appends['rows'] = $rows; 
		if($search!='') $appends['search'] = $search; 
		
		$url = "";
		foreach($appends as $key=>$val)
		{
			$url .= "&$key=$val";
		}
		return $url;
			
	}	

	public function getRemovecurrentfiles( Request $request)
	{
		if(!isset($this->info['id']) || !$this->moduleTaskAllowed($request, 'is_edit'))
			return response()->json(array('status'=>'error'), 403);

		$id 	= $request->input('id');
		$field 	= $request->input('field');
		$path 	= $this->resolveUploadPath($request->input('file'));
		$fields = array_column($this->info['config']['forms'], 'field');
		if($path && in_array($field, $fields, true))
		{
			// Pastikan baris milik tenant aktif sebelum menghapus file
			$owned = \App\Support\Tenancy\TenantScope::table($this->info['table'])->where($this->info['key'],$id);
			if(!$owned->exists())
				return response()->json(array('status'=>'error'), 404);
			if(unlink($path))
			{
				$owned->update(array($field=>''));
			}
			return response()->json(array('status'=>'success'));
		} else {
			return response()->json(array('status'=>'error'));
		}
	}	

	public function getRemovefiles( $request)
	{
		$path = $this->resolveUploadPath($request->input('file'));
		if($path)
		{
			unlink($path);
		}
		return response()->json(array('status'=>'success'));
	}


	public function getSearch( $mode = 'native' )
	{
		if(isset($_GET['type']))
			$mode = $_GET['type'];

		$this->data['tableForm'] 	= $this->info['config']['forms'];	
		$this->data['tableGrid'] 	= $this->info['config']['grid'];
		$this->data['searchMode'] 	= $mode ;
		$this->data['pageUrl']		= url($this->module);
		return view('sximo.module.utility.search',$this->data);
	
	}

	function getImport( Request $request)
	{
		$task = $request->input('template');
		if($task !='')
		{
			$fields =  $this->info['config']['grid'];
			$output = fopen('php://output', 'w');
			$head= array();
			foreach($fields as $f )
			{
				$head[] = $f['field'];
			}

			fputcsv($output, $head);
			header('Content-Type: text/csv; charset=utf-8');
			header('Content-Disposition: attachment; filename='.$this->module.'.csv');	

		} else {
			$this->data['url'] = url($this->module) ;
			$this->data['module'] = $this->module ;
			return view('sximo.module.utility.import', $this->data);
		}	

	}

	function postImport( $request)
	{

		if(!is_null($request->file('fileimport')))
		{
			$file = 	$request->file('fileimport');
			// Jangan simpan file ke folder publik; cukup baca file temporary-nya, dan hanya terima CSV
			$extension = strtolower($file->getClientOriginalExtension());
			if(!$file->isValid() || !in_array($extension, array('csv','txt'), true))
				return response()->json(array('status'	=> 'error','message'=>'Only CSV file is allowed!'));

			$uploadSuccess = $file->getRealPath();
			if( $uploadSuccess ) {
				$csv = array_map('str_getcsv', file($uploadSuccess));
				$table = $this->info['config']['grid'];
				$fields = array();
				foreach($table as $f )
				{
					$fields[] = $f['field'];
				}
				//print_r($fields);
				foreach($csv as $row) {
					$data = array();
					foreach($fields as $key=>$val)
					{
						if($key != 0 )
							$data[$val] = (isset($row[$key]) ? $row[$key] : '' ) ;	
					}
					//print_r($data);
					//echo $row[0];
					$this->model->insertRow($data ,$row[0]);	
											
				}
				
				return response()->json(array('status'	=> 'success','message'=>'Csv Imported Successful !'));			  
			} else {
				return response()->json(array('status'	=> 'error','message'=>'Upload Failed!'));	
			}
		} else {			
			return response()->json(array('status'	=> 'error','message'=>'Please select file to Upload!'));
		}	

	}

	public function getExpotion()
	{
		$this->data['pageUrl'] = url( $this->data['pageModule']);
		return view('sximo.module.utility.export', $this->data);
	}
	public function getExport( Request $request, $t = 'excel')
	{
		if($request->has('filter'))
			return $this->getExpotion();

		$t = $request->input('do');
		$this->access = $this->model->validAccess($this->info['id'] , session('gid'));

		if(isset($this->access['is_'.$t]) ){
			if($this->access['is_'.$t] == 0 )
				return redirect('dashboard')->with('message', __('core.note_restric'))->with('status','error');
		} else {
			return redirect('dashboard')->with('message', __('core.note_restric'))->with('status','error');
		}

		$info 		= $this->model->makeInfo( $this->module);
		$filter = '';	
		if(!is_null($request->input('search')))
		{
			$search = 	$this->buildSearch('maps');
			$filter = $search['param'];
			$this->data['search_map'] = $search['maps'];
		} 

		$params 	= array(
					'params'	=> $filter ,
					'fstart'	=> $request->input('fstart'),
					'flimit'	=> $request->input('flimit')	
		);		

		$results 	= $this->model->getRows( $params );
		$fields		= $info['config']['grid'];
		$rows		= $results['rows'];
		$content 	= array(
						'fields' => $fields,
						'rows' => $rows,
						'title' => $this->data['pageTitle'],
					);
		
		if($t == 'word')
		{			
			 return view('sximo.module.utility.word',$content);
			 
		} else if($t == 'pdf') {	

			$pdf = PDF::loadView('sximo.module.utility.pdf', $content)->setPaper('a4', 'landscape');
			return $pdf->download('invoice.pdf');
			
		} else if($t == 'csv') {		
		 
			return view('sximo.module.utility.csv',$content);
			
		} else if ($t == 'print') {
		
			//return view('sximo.module.utility.print',$content);
			$data['html'] = view('sximo.module.utility.print', $content)->render();	
			return view('layouts.blank',$data);			 
			 				 
		} else  {			
		
			 return view('sximo.module.utility.excel',$content);
		}	
	}		

	function getLookup( $request)
	{
		$id = $request->input('param');
		$args = explode("-",(string) $id);
		// Nama model & kolom hanya boleh identifier; nilai di-quote agar tidak bisa SQL injection
		$valid = count($args) >= 7
			&& preg_match('/^[A-Za-z0-9_]+$/', $args[3])
			&& preg_match('/^[A-Za-z0-9_]+$/', $args[4])
			&& preg_match('/^[A-Za-z0-9_]+$/', $args[5]);
		$model = $valid ? '\\App\\Models\\'.ucwords($args[3]) : null;
		if($valid && class_exists($model) && is_subclass_of($model, \App\Models\Sximo::class))
		{

			$model = new $model();
			$info = $model->makeInfo( $args['3'] );
			$targetAccess = isset($info['id']) ? $model->validAccess($info['id'], session('gid')) : false;
			if(!is_array($targetAccess) || !isset($targetAccess['is_view']) || $targetAccess['is_view'] != '1')
				return 'Invalid Argument';
			$data['pageTitle'] = $info['title'];
			$data['pageNote'] = $info['note'];			
			$params = array(
				'params'	=> " And ".$args['4'].".".$args['5']." = ". \DB::getPdo()->quote($args['6']),
				//'global'	=> (isset($this->access['is_global']) ? $this->access['is_global'] : 0 )
			);
			$results = $model->getRows( $params );	
			$data['access']		= $targetAccess;
			$data['rowData']		= $results['rows'];
			$data['tableGrid'] 	= $info['config']['grid'];
			$data['tableForm'] 	= $info['config']['forms'];	
			$data['colspan']		= \SiteHelpers::viewColSpan($info['config']['grid']);
			$data['nested_subgrid']	= (isset($info['config']['subgrid']) ? $info['config']['subgrid'] : array());
			//print_r($data['nested_subgrid']);exit;
			$data['id'] 		= $args[6];
			$data['key']		= $info['key'];
			//$data['ids']		= 'md'-$info['id'];
			return view('sximo.module.utility.masterdetail',$data);

		} else {
			return 'Invalid Argument';
		}
	}


	function detailview( $model , $detail , $id )
	{
		
		$info = $model->makeInfo( $detail['module'] );
		$params = array(
			'params'	=> " And `".$detail['key']."` =". self::quoteValue($id),
			'global'	=> (isset($this->access['is_global']) ? $this->access['is_global'] : 0 )
		);
		$results = $model->getRows( $params );	
		$data['rowData']		= $results['rows'];
		$data['tableGrid'] 		= $info['config']['grid'];
		$data['tableForm'] 		= $info['config']['forms'];	
		
		return $data;

		
			
	}


function detailviewsave( $model ,$request , $detail , $id )

	{
		//\DB::table($detail['table'])->where($detail['key'],$request[$detail['key']])->delete();

		$info = $model->makeInfo( $detail['module'] );
		$relation_key = $info['key'];
		$access = $model->validAccess($info['id'] , session('gid'));

		if($access['is_add'] == '1' && $access['is_edit'] == '1' )
		{
			$str = $info['config']['forms'];
			$global	= (isset($access['is_global']) ? $access['is_global'] : 0 );
			$total = count($request['counter']);
			$mkeys = array();
			$getAllCurrentData = \App\Support\Tenancy\TenantScope::table($detail['table'])->where($detail['master_key'] , $id  )->get();

			$pkeys = array();
			for($i=0; $i<$total;$i++) 
					$pkeys[] = $request['bulk_'.$relation_key][$i];

			foreach ($getAllCurrentData as $keys) {	
				if(!in_array($keys->{$relation_key} , $pkeys))		
				{	
					// Remove If items is not resubmited
					\App\Support\Tenancy\TenantScope::table($detail['table'])->where($relation_key,$keys->{$relation_key})->delete();
				}
			}

			for($i=0; $i<$total;$i++)
			{
				$data =array();
				foreach($str as $f){
					$field = $f['field'];
					if($f['view'] ==1)
					{
						if(isset($request['bulk_'.$field][$i]) && $request['bulk_'.$field][$i] !='')

						{
							$data[$f['field']] = $request['bulk_'.$field][$i];
						}
					}			
				}

				$rules = self::validateForm($str);
				$validator = Validator::make($data, $rules);
				if($validator->passes()) {	
					$data[$detail['key']] =  $id ;
					if($global == 0 )
						$data['entry_by'] = \Session::get('uid');
					
					// Check if data currentry exist
					$check = \App\Support\Tenancy\TenantScope::table($detail['table'])->where($relation_key , $request['bulk_'.$relation_key][$i]  )->get();
					if(count($check) >=1)
					{
						\App\Support\Tenancy\TenantScope::table($detail['table'])->where($relation_key ,  $request['bulk_'.$relation_key][$i] )->update(\App\Support\Tenancy\TenantScope::guardForUpdate($detail['table'], $data));
					} else {
						unset($data[$relation_key]);
						\DB::table($detail['table'])->insert(\App\Support\Tenancy\TenantScope::fillForInsert($detail['table'], $data));	
					}
				}			

			}	
		}	

		

	}

	// Field config modul hanya boleh masuk ke WHERE bila kolomnya benar-benar ada.
	// Alias SELECT (mis. "created_at AS logdate") tidak bisa dipakai di WHERE dan
	// memicu SQLSTATE[42S22] Unknown column. Alias yang bukan nama tabel fisik
	// (alias JOIN) tidak bisa dicek, jadi dibiarkan lolos.
	protected static function isSearchableColumn( $form )
	{
		static $cache = array();
		$table = $form['alias'] ?? '';
		$field = $form['field'] ?? '';
		if($table == '' || $field == '') return false;
		$key = $table.'.'.$field;
		if(!array_key_exists($key, $cache)) {
			try {
				$cache[$key] = !\Schema::hasTable($table) || \Schema::hasColumn($table, $field);
			} catch (\Throwable $e) {
				$cache[$key] = true;
			}
		}
		return $cache[$key];
	}

	public function liveSearch( $request,  $forms = '')
	{
		$query = '';
		if($forms == '') $forms = $this->info['config']['forms'];

		$keyword = trim($request->input('s'));
		if(!is_null($request->input('search')))
			$keyword = trim($request->input('search')['value']);
		
		$like = self::quoteValue('%'.$keyword.'%');
		$i = 0;
		foreach($forms as $form)
		{
			if($form['search'] == '1' && self::isSearchableColumn($form)){
				if($i == 0){
					$query .= " AND ( ".$form['alias'].".".$form['field']." LIKE ".$like."  ";
				}
				else {
					$query .= " OR  ".$form['alias'].".".$form['field']." LIKE ".$like."  ";
				}
				$i++;
			}

		}
		if($query !='' ) {
			$query .= ' ) ';
		}	
		return $query ;
	}

	public function grab( $request , $args = array())
	{
		
		if(  isset( $request->get('profile')->group_id)) {
			$this->access = $this->access($this->info['id'] , $request->get('profile')->group_id );
		} else {
			$this->access = $this->access($this->info['id']  );
		}
		

		$sort = (!is_null($request->input('sort')) ? $request->input('sort') : $this->info['setting']['orderby']); 
		$order = (!is_null($request->input('order')) ? $request->input('order') :  $this->info['setting']['ordertype']);
		// End Filter sort and order for query 
		// Filter Search for query		
		$filter ='';	
		$filter .= (isset($args['params']) ? $args['params'] : '');
		if(!is_null($request->input('search')))
		{
			$search = 	$this->buildSearch('maps');
			$filter = $search['param'];
			$this->data['search_map'] = $search['maps'];
		} 
		if(!is_null($request->input('s')))
		{
			$filter .= $this->liveSearch( $request);
		}

		
		$page = $request->input('page', 1);
		$params = array(
			'page'		=> $page ,
			'limit'		=> (!is_null($request->input('rows')) ? filter_var($request->input('rows'),FILTER_VALIDATE_INT) : static::$per_page ) ,
			'sort'		=> $sort ,
			'order'		=> $order,
			'params'	=> $filter,
			'global'	=> (isset($this->access['is_global']) ? $this->access['is_global'] : 0 )
		);
		// Get Query 
		$results = $this->model->getRows( $params , session('uid') );				
		
		// Build pagination setting
		$pagination = new Paginator($results['rows'], $results['total'], $params['limit']);	
		if($this->info['type'] =='ajax'){
			$pagination->setPath( $this->module.'/data' );
		} else {
			$pagination->setPath( $this->module );	
		}
		$this->data['param']		= $params;
		$this->data['rowData']		= $results['rows'];
		// Build Pagination 
		$this->data['pagination']	= $pagination;
		// Build pager number and append current param GET
		$this->data['pager'] 		= $this->injectPaginate();	
		// Row grid Number 
		$this->data['i']			= ($page * $params['limit'])- $params['limit']; 
		// Grid Configuration 
		usort($this->info['config']['grid'], "SiteHelpers::_sort");
		$this->data['tableGrid'] 	= $this->info['config']['grid'];
		$this->data['tableForm'] 	= $this->info['config']['forms'];
		$this->data['colspan'] 		= \SiteHelpers::viewColSpan($this->info['config']['grid']);		
		// Group users permission
		$this->data['access']		= $this->access;
		// Detail from master if any
		$this->data['fields'] =  \SiteHelpers::fieldLang($this->info['config']['grid']);
		// Master detail link if any 
		$this->data['subgrid']	= (isset($this->info['config']['subgrid']) ? $this->info['config']['subgrid'] : array());
		$this->data['setting'] 		= $this->info['setting']; 

		$this->data['insort']	= $sort ;
		$this->data['inorder']	= $order ;
				
		return  $this->data;
	}
	public function grabApi( $request , $args = array())
	{
		
		if(  isset( $request->get('profile')->group_id)) {
			$this->access = $this->access($this->info['id'] , $request->get('profile')->group_id );
		} else {
			$this->access = $this->access($this->info['id']  );
		}
		

		$sort = (!is_null($request->input('sort')) ? $request->input('sort') : $this->info['setting']['orderby']); 
		$order = (!is_null($request->input('order')) ? $request->input('order') :  $this->info['setting']['ordertype']);
		// End Filter sort and order for query 
		// Filter Search for query		
		$filter ='';	
		$filter .= (isset($args['params']) ? $args['params'] : '');
		if(!is_null($request->input('search')))
		{
			$search = 	$this->buildSearch('maps');
			$filter = $search['param'];
			$this->data['search_map'] = $search['maps'];
		} 
		if(!is_null($request->input('s')))
		{
			$filter .= $this->liveSearch( $request);
		}

		
		$page = $request->input('page', 1);
		$params = array(
			'page'		=> $page ,
			'limit'		=> (!is_null($request->input('rows')) ? filter_var($request->input('rows'),FILTER_VALIDATE_INT) : static::$per_page ) ,
			'sort'		=> $sort ,
			'order'		=> $order,
			'params'	=> $filter,
			'global'	=> (isset($this->access['is_global']) ? $this->access['is_global'] : 0 )
		);
		// Get Query 
		$results = $this->model->getRows( $params , session('uid') );				
		// Build pagination setting
		$pagination = new Paginator($results['rows'], $results['total'], $params['limit']);	
		$this->data['pagination']	= $pagination;
		
		$this->data['rowData']		= $results['rows'];
				
		return  $this->data;
	}

	public function hook( $request , $id = null  ) 
	{
		$this->access = $this->access($this->info['id']);
		$row = $this->model->getRow($id);
		if($row)
		{
			$this->data['row'] =  $row;
			$this->data['fields'] 		=  \SiteHelpers::fieldLang($this->info['config']['grid']);
			$this->data['id'] = $id;
			$this->data['access']		= $this->access;
			$this->data['subgrid']	= (isset($this->info['config']['subgrid']) ? $this->info['config']['subgrid'] : array()); 
			$this->data['fields'] =  \SiteHelpers::fieldLang($this->info['config']['grid']);
			$this->data['prevnext'] = $this->model->prevNext($id);
			$this->data['setting'] 		= $this->info['setting']; 
			
		}  
		return $this->data;
	}

	function copy( $request)
	{
	    foreach(\DB::select("SHOW COLUMNS FROM ".  $this->info['table'] ) as $column)
        {
			if( $column->Field != $this->info['key'])
				$columns[] = $column->Field;
        }
		
		$ids = array_values(array_filter((array) $request->input('ids'), 'is_scalar'));
		if(count($ids) >=1)
		{
			// Salin per baris: hanya baris milik tenant aktif, tenant_id & uuid baru diisi ulang
			$table = $this->info['table'];
			$rows = \App\Support\Tenancy\TenantScope::table($table)->whereIn($this->info['key'], $ids)->get($columns);
			foreach($rows as $row)
			{
				$data = (array) $row;
				unset($data['uuid']);
				\DB::table($table)->insert(\App\Support\Tenancy\TenantScope::fillForInsert($table, $data));
			}
			return ['message'=>__('core.note_success'),'status'=>'success'];
		} else {
			return ['message'=>__('Please select row to copy'),'status'=>'error'];
		}	
		
	}

	function access( $id , $gid = null )
	{
		if($gid == null) 
			$gid = session('gid');

		$row = \DB::table('tb_groups_access')->where('module_id', $id)->where('group_id', $gid )->get();
		
		if(count($row) >= 1)
		{
			$row = $row[0];
			if($row->access_data !='')
			{
				$data = json_decode($row->access_data,true);
			} else {
				$data = array();
			}	

			
			return $data;		
			
		} else {
			return false;
		}			
	
	}		

}

