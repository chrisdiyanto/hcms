<?php namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\User;
use App\Libary\SiteHelpers;
use Socialize;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator as Paginator;
use Validator, Input, Redirect ; 
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class UserController extends Controller {

	
	protected $layout = "layouts.main";

	public function __construct() {
		parent::__construct();
		$this->data = array();

	} 

	public function getRegister() {
        
		if(config('sximo.cnf_regist') =='false') :    
			if(\Auth::check()):
				 return redirect('')->with(['message'=>'Youre already login','status'=>'error']);
			else:
				 return redirect('user/login');
			  endif;			  
		else :
				$this->data['socialize'] =  config('services');
				return view('user.register', $this->data);  
		 endif ;        
	}
	public function postCreate( Request $request) {
	
		$rules = array(
			'username'=>'required|alpha|between:3,12|unique:tb_users',
			'firstname'=>'required|alpha_num|min:2',
			'lastname'=>'required|alpha_num|min:2',
			'email'=>'required|email|unique:tb_users',
			'password'=>'required|between:6,12|confirmed',
			'password_confirmation'=>'required|between:6,12'
		);	

		if($request->has('mobile')) {
			unset($rules['password_confirmation']);
			$rules['password'] = 'required|between:6,12';
		} 
		else {
			if(filter_var(config('sximo.cnf_recaptcha'), FILTER_VALIDATE_BOOLEAN)) 
			{
				$return = $this->reCaptcha($request->all());
				if($return === false || empty($return['success']))
				{
					$message = empty($request->input('g-recaptcha-response'))
						? 'Silakan centang verifikasi reCAPTCHA terlebih dahulu.'
						: 'Verifikasi reCAPTCHA tidak valid, silakan coba lagi.';
					if($request->ajax() == true || $request->expectsJson())
					{
						return response()->json(['status' => 'error', 'message' => $message]);
					}
					return redirect('user/register')->with(['message' => $message, 'status' => 'error'])->withInput();
				}
			}
		}

		$validator = Validator::make($request->all(), $rules);
		if ($validator->passes()) {
			// Kode aktivasi acak (kolom activation varchar 50), tidak bisa ditebak dengan brute-force
			$code = Str::random(40);
			$authen = new User;
			$authen->username = $request->input('username');
			$authen->first_name = $request->input('firstname');
			$authen->last_name = $request->input('lastname');
			$authen->email = trim($request->input('email'));
			$authen->activation = $code;
			$authen->group_id = $this->config['cnf_group'];
			$authen->password = \Hash::make($request->input('password'));
			if($this->config['cnf_activation'] == 'auto') { $authen->active = '1'; } else { $authen->active = '0'; }
			$authen->save();
			
			$data = array(
				'firstname'	=> $request->input('firstname') ,
				'lastname'	=> $request->input('lastname') ,
				'email'		=> $request->input('email') ,
				'password'	=> $request->input('password') ,
				'code'		=> $code ,
				'subject'	=> "[ " .$this->config['cnf_appname']." ] REGISTRATION "				
			);
			if(config('sximo.cnf_activation') == 'confirmation')
			{ 
				$to = $request->input('email');
				$subject = "[ " .$this->config['cnf_appname']." ] REGISTRATION "; 			
				if($this->config['cnf_mail'] =='swift')
				{ 				
					\Mail::send('user.emails.registration', $data, function ($message) use ($data) {
			    		$message->to($data['email'])->subject($data['subject']);
			    	});				    	
				}  else {		
					$message = view('user.emails.registration', $data);
					$headers  = 'MIME-Version: 1.0' . "\r\n";
					$headers .= 'Content-type: text/html; charset=iso-8859-1' . "\r\n";
					$headers .= 'From: '.$this->config['cnf_appname'].' <'.$this->config['cnf_email'].'>' . "\r\n";
						mail($to, $subject, $message, $headers);	
				}

				$message = "Thanks for registering! . Please check your inbox and follow activation link";
								
			} elseif($this->config['cnf_activation']=='manual') {
				$message = "Thanks for registering! . We will validate you account before your account active";
			} else {
   			 	$message = "Thanks for registering! . Your account is active now ";         
			
			}	
			if($request->has('mobile') )
			{
				 return response()->json(['status' => 'success', 'message' => $message ]);
			} 
			else {
				return redirect('user/login')->with(['message' => $message,'status'=>'success']);
			}

		} else {
			if($request->has('mobile') )
			{
				 return response()->json(['status' => 'error', 'message' => $validator->errors() ]);
			} 
			else {
				return redirect('user/register')->with(['message'=>'The following errors occurred','status'=>'success'])
				->withErrors($validator)->withInput();
			}
			
		}
	}
	
	public function getActivation( Request $request  )
	{
		$num = $request->input('code');
		if($num =='')
			return redirect('user/login')->with(['message'=>'Invalid Code Activation!','status'=>'error']);
		
		$user =  User::where('activation','=',$num)->get();
		if (count($user) >=1)
		{
			\DB::table('tb_users')->where('activation', $num )->update(array('active' => 1,'activation'=>''));
			return redirect('user/login')->with(['message'=>'Your account is active now!','status'=>'success']);
			
		} else {
			return redirect('user/login')->with(['message'=>'Invalid Code Activation!','status'=>'error']);
		}
		
		
	
	}

	public function getLogin() {
	
		if(\Auth::check())
		{
			return redirect('')->with(['message'=>'success','Youre already login','status'=>'success']);

		} else {
			$this->data['socialize'] =  config('services');
			return View('user.login',$this->data);
			
		}	
	}

	public function reCaptcha( $request)
	{
		$responseToken = is_array($request) ? ($request['g-recaptcha-response'] ?? null) : $request->input('g-recaptcha-response');
		if(empty($responseToken) || !is_string($responseToken))
		{
			return false;
		}

		try {
			$response = Http::asForm()
				->timeout(10)
				->connectTimeout(5)
				->post('https://www.google.com/recaptcha/api/siteverify', [
					'secret'   => config('sximo.cnf_recaptchaprivatekey'),
					'response' => $responseToken,
					'remoteip' => request()->ip(),
				]);
		} catch (\Throwable $e) {
			Log::error('reCAPTCHA verify request failed: '.$e->getMessage());
			return false;
		}

		if(!$response->successful())
		{
			Log::error('reCAPTCHA verify HTTP '.$response->status(), ['body' => $response->body()]);
			return false;
		}

		$data = $response->json();
		if(!is_array($data))
			return false;

		if(empty($data['success']))
		{
			Log::warning('reCAPTCHA rejected', [
				'errors'   => $data['error-codes'] ?? [],
				'hostname' => $data['hostname'] ?? null,
				'ip'       => request()->ip(),
			]);
		}

		return $data;
	}

	public function postSignin( Request $request) {

		$rules = array(
			'email'=>'required',
			'password'=>'required',
		);		
		if(filter_var(config('sximo.cnf_recaptcha'), FILTER_VALIDATE_BOOLEAN)) {
			$return = $this->reCaptcha($request->all());
			if($return === false || empty($return['success']))
			{
				$message = empty($request->input('g-recaptcha-response'))
					? 'Silakan centang verifikasi reCAPTCHA terlebih dahulu.'
					: 'Verifikasi reCAPTCHA tidak valid, silakan coba lagi.';
				if($request->ajax() == true )
				{
					return response()->json(['status' => 'error', 'message' => $message]);
				}
				return redirect('user/login')
					->with(['status' => 'error', 'message' => $message])
					->withInput();
			}
		}
		$validator = Validator::make($request->all(), $rules);
		if ($validator->passes()) {	

			$remember = $request->boolean('remember');
			
			if (\Auth::attempt(array('email'=>$request->input('email'), 'password'=> $request->input('password') ), $remember )
				or 
				\Auth::attempt(array('username'=>$request->input('email'), 'password'=> $request->input('password') ), $remember )

			) {
				if(\Auth::check())
				{
					$row = User::find(\Auth::user()->id); 	
					if(in_array((string) $row->active, array('0', 'Inactive'), true))
					{
						// inactive
						if($request->ajax() == true )
						{
							\Auth::logout();
							return response()->json(['status' => 'error', 'message' => 'Your Account is not active']);
						} else {
							\Auth::logout();
							return redirect('user/login')->with(['status' => 'error', 'message' => 'Your Account is not active']);
						}
						
					} else if(in_array((string) $row->active, array('2', 'Banned'), true))
					{

						if($request->ajax() == true )
						{
							\Auth::logout();
							return response()->json(['status' => 'error', 'message' => 'Your Account is BLocked']);
						} else {
							// BLocked users
							\Auth::logout();
							return redirect('user/login')->with(['status' => 'error', 'message' => 'Your Account is BLocked']);
						}
					} else {
						\DB::table('tb_users')->where('id', '=',$row->id )->update(array('last_login' => date("Y-m-d H:i:s")));
						$level = 99;
						$sql = \DB::table('tb_groups')->where('group_id' , $row->group_id )->get();
				        if(count($sql))
				        {
				            $l = $sql[0];
				            $level = $l->level ;
				        }

						$session = array(
							'gid' => $row->group_id,
							'uid' => $row->id,
							'eid' => $row->email,
							'id_unit' => $row->id_unit,
							'id_position' => $row->id_position,
							'll' => $row->last_login,
							'fid' =>  $row->first_name.' '. $row->last_name,
							'username' =>  $row->username ,
							'join'	=>  $row->created_at ,
							'level'	=> $level 
						);
						/* Set Lang if available */
						if(!is_null($request->input('language')))
						{
							$session['lang'] = $request->input('language');		
						} else {
							$session['lang'] = config('sximo.cnf_lang');
							
						}
						session($session);
						if($request->ajax() == true )
						{

							if( config('sximo.cnf_front') =='false') :
								return response()->json(['status' => 'success', 'url' => url('dashboard')]);					
							else :
								return response()->json(['status' => 'success', 'url' => url('')]);
							endif;	

						} 
						else {
							
							if( config('sximo.cnf_front') =='false') :
								return redirect('dashboard');						
							else :
								return redirect('');
							endif;	
						}				
					}	
				}		
				
			} 
			else {

				if($request->ajax() == true )
				{
					return response()->json(['status' => 'error', 'message' => 'Your username/password combination was incorrect']);
				} else {

					return redirect('user/login')
						->with(['status' => 'error', 'message' => 'Your username/password combination was incorrect'])
						->withInput();					
				}
			}
		} 
		else {

				if($request->ajax() == true)
				{
					return response()->json(['status' => 'error', 'message' => 'The following  errors occurred']);
				} else {

					return redirect('user/login')
						->with(['status' => 'error', 'message' => 'The following  errors occurred'])
						->withErrors($validator)->withInput();
				}	

		}	
	}

    public function postSigninMobile(  Request $request ) {
        $rules = array(
            'email'                 =>'required|email',
            'password'              =>'required'
        );  
        $validator = Validator::make($request->all(), $rules);
        if ($validator->passes()) {
            $email      =  trim($request->input('email')) ;
            $password   =  trim($request->input('password')) ;
            if (\Auth::attempt(array('email'=> $email, 'password'=> $password ))) {

                $row = User::find(\Auth::user()->id);   
                if($row->status =='0')
                {
                    return response()->json(['status' => 'error', 'message' => ' Your account is active yet ' ]);
                } 
                else if( $row->status == '2') {
                    return response()->json(['status' => 'error', 'message' => ' Your account is blocked / banned ' ]);
                } 
                else {
                   
                    $data = array(
                            'gid' => $row->group_id,
                            'uid' => $row->id,
                            'eid' => $row->email,
                            'll' => $row->last_login,
                            'fid' =>  $row->name,
                            'uname'     => $row->username ,
                            'logged_in' => true ,
                            'join'  =>  $row->created_at                             
                        );
                    if(file_exists('./uploads/users/'.$row->avatar ) && $row->avatar !='') {
                            $data['avatar'] = asset('uploads/users/'.$row->avatar) ;
                        } else {
                            $data['avatar'] =  asset('uploads/users/avatar.png') ;
                        }
                    $data['access'] = [];

                    $token =  hash('sha256',Str::random(60)) ;

                    \DB::table('tb_token')->insert([
                    	'userId'	=> $row->id ,
                    	'token'		=> $token ,
                    	'created'	=> date("Y-m-d H:i:s")
                	]);

                    return response()->json([
                        'status' => 'success', 
                        'message' => 'You are Logged',
                        'token'=> $token,
                        'data'   => $data
                    ]);
                }
            } 
            else {
                return response()->json(['status' => 'error', 'message' => 'Your username/password combination was incorrect']);
            }
        }
        else {
            return response()->json(['status' => 'error', 'message' => $validator->errors() ]);
        }
    }

	public function getProfile() {
		
		if(!\Auth::check()) return redirect('user/login');
		
		
		$info =	User::find(\Auth::user()->id);
		$this->data = array(
			'pageTitle'	=> 'My Profile',
			'pageNote'	=> 'View Detail My Info',
			'info'		=> $info,
			'user_units' => \SiteHelpers::getUserUnits(\Auth::user()->id),
		);
		return view('user.profile',$this->data);
	}

	public function getTheme() {
		
		return view('layouts.palette');
	}	
	public function postSaveprofile( Request $request)
	{
		if(!\Auth::check()) return redirect('user/login');
		$rules = array(
			'first_name'=>'required|alpha_num|min:2',
			'last_name'=>'required|alpha_num|min:2',
			);	
			
		if($request->input('email') != \Session::get('eid'))
		{
			$rules['email'] = 'required|email|unique:tb_users';
		}	

		if(!is_null($request->file('avatar'))) $rules['avatar'] = 'mimes:jpg,jpeg,png,gif,bmp';

				
		$validator = Validator::make($request->all(), $rules);

		if ($validator->passes()) {
			
			
			if(!is_null($request->file('avatar')))
			{
				$file = $request->file('avatar');
				$destinationPath = './uploads/users/';
				// Ekstensi diambil dari isi file, bukan dari nama file di client
				$extension = \SiteHelpers::safeUploadExtension($file, true);
				if($extension === null)
					return redirect('user/profile')->with('message','Avatar harus berupa gambar (jpg, png, gif, bmp, webp)')->with('status','error');
				$newfilename = (int) \Session::get('uid').'.'.$extension;
				$uploadSuccess = $request->file('avatar')->move($destinationPath, $newfilename);				 
				if( $uploadSuccess ) {
				    $data['avatar'] = $newfilename; 
				}
				$orgFile = $destinationPath.'/'.$newfilename; 
				\SiteHelpers::cropImage('80' , '80' , $orgFile ,  $extension,	 $orgFile)	;
				
			}		
			
			$user = User::find(\Session::get('uid'));
			$user->first_name 	= $request->input('first_name');
			$user->last_name 	= $request->input('last_name');
			$user->email 		= $request->input('email');
			if(isset( $data['avatar']))  $user->avatar  = $newfilename; 			
			$user->save();

			$newUser = User::find(\Session::get('uid'));

			\Session::put('fid',$newUser->first_name.' '.$newUser->last_name);

			return redirect('user/profile')->with('message','Profile has been saved!')->with('status','success');
		} else {
			return redirect('user/profile')->with('message','The following errors occurred')->with('status','error')
			->withErrors($validator)->withInput();
		}	
	
	}
	
	public function postSavepassword( Request $request)
	{
		if(!\Auth::check()) return redirect('user/login');
		$rules = array(
			'password'=>'required|between:6,12',
			'password_confirmation'=>'required|between:6,12'
			);		
		$validator = Validator::make($request->all(), $rules);
		if ($validator->passes()) {
			$user = User::find(\Session::get('uid'));
			$user->password = \Hash::make($request->input('password'));
			$user->save();

			return redirect('user/profile')->with(['status' => 'success', 'message' => 'Password has been saved!'] );
		} else {
			return redirect('user/profile')->with(['status' => 'error', 'message' => 'The following errors occurred'])
			->withErrors($validator)->withInput();
		}	
	
	}	
	
	public function getReminder()
	{
	
		return view('user.remind');
	}	

	public function postRequest( Request $request)
	{

		$rules = array(
			'credit_email'=>'required|email'
		);	
		
		$validator = Validator::make($request->all(), $rules);
		if ($validator->passes()) {	
	
			$user =  User::where('email','=',$request->input('credit_email'));
			if($user->count() >=1)
			{
				$user = $user->get();
				$user = $user[0];
				// Token reset harus acak & rahasia, jangan pakai _token (CSRF) milik peminta
				$token = Str::random(64);
				// Simpan hash-nya saja: bila database bocor, token tidak bisa langsung dipakai
				User::where('id', $user->id)->update(array('reminder' => hash('sha256', $token)));
				$data = array('token'=>$token);
				$to = $request->input('credit_email');
				$subject = "[ " .config('sximo.cnf_appname')." ] REQUEST PASSWORD RESET ";
				$data['subject'] =  $subject;
				$data['email'] = $to;

				if(config('sximo.cnf_mail') =='swift')
				{
					try {
						\Mail::send('user.emails.auth.reminder', $data, function ($message) use ($data)  {
				    		$message->to($data['email'])->subject($data['subject']);
				    	});
					} catch (\Throwable $e) {
						// Konfigurasi mail salah tidak boleh berujung halaman error (yang juga membocorkan detail server)
						\Log::error('Password reset email failed: '.$e->getMessage());
						return redirect('user/login?reset')->with(['message' => 'Email reset tidak dapat dikirim. Hubungi administrator.','status'=>'error']);
					}

				}  else {

							
					$message = view('user.emails.auth.reminder', $data);
					$headers  = 'MIME-Version: 1.0' . "\r\n";
					$headers .= 'Content-type: text/html; charset=iso-8859-1' . "\r\n";
					$headers .= 'From: '.config('sximo.cnf_appname').' <'.config('sximo.cnf_email').'>' . "\r\n";
						mail($to, $subject, $message, $headers);	
				}					
			
				
				return redirect('user/login')->with(['message' => 'Please check your email','status'=>'success']);	
				
			} else {
				return redirect('user/login?reset')->with(['message' => 'Cant find email address','status'=>'error']);
			}

		}  else {
			return redirect('user/login?reset')->with(['message' => 'The following errors occurred','status'=>'error'])->withErrors($validator)->withInput();
		}	 
	}	
	
	public function getReset( $token = '')
	{
		if(\Auth::check()) return redirect('dashboard');

		if($this->findUserByResetToken($token))
		{
			$this->data['verCode']= $token;
			return view('user.remind',$this->data);

		} else {
			return redirect('user/login')->with(['message'=>'Cant find your reset code','status'=>'error']);
		}
		
	}	
	
	public function postDoreset( Request $request , $token = '')
	{
		$rules = array(
			'password'=>'required|alpha_num|between:6,12|confirmed',
			'password_confirmation'=>'required|alpha_num|between:6,12'
			);		
		$validator = Validator::make($request->all(), $rules);
		if ($validator->passes()) {
			
			$user = $this->findUserByResetToken($token);
			if(!$user)
			{
				return redirect('user/login')->with(['message'=>'Cant find your reset code','status'=>'error']);
			}

			$user->reminder = null;
			$user->password = \Hash::make($request->input('password'));
			$user->save();

			return redirect('user/login')->with(['message'=>'Password has been saved!','status'=>'success'] );
		} else {
			return redirect('user/reset/'.$token)->with(['message'=>'The following errors occurred','status'=>'error'])->withErrors($validator)->withInput();
		}	
	
	}	

	protected function findUserByResetToken( $token )
	{
		// Tolak token kosong/tidak valid agar user dengan reminder '' atau NULL tidak ikut tercocokkan
		if(!is_string($token) || strlen($token) !== 64) return null;

		// Database hanya menyimpan hash token (lihat postRequest)
		return User::where('reminder', hash('sha256', $token))->first();
	}

	public function getLogout() {
		\Auth::logout();
		\Session::flush();
		return redirect('')->with(['message'=>'Your are now logged out!','status'=>'success']);
	}

	function socialize( $social )
	{
		return Socialize::driver($social)->redirect();
	}

	public function changepassword() {
		return view('user.changepassword',$this->data);
	}

	function autosocialize( $social )
	{
		$user = Socialize::driver($social)->user();		
		$users = \DB::table('tb_users')->where('email',$user->email)->get();

		if(count($users)){
			$row = $users[0];
			return self::autoSignin($row->id);		

		} else {
			return redirect('user/login')
				->with(['message'=>'You have not registered yet ','status'=>'error']);
		}
			
	}

	function autoSignin($id)
	{
		
		if(is_null($id)){
		  return redirect('user/login')
				->with(['message'=>'You have not registered yet ','status'=>'error']);
		} else{

		    \Auth::loginUsingId( $id );
			if(\Auth::check())
			{
				$row = User::find(\Auth::user()->id); 

				if($row->active =='0')
				{
					// inactive 
					\Auth::logout();
					return redirect('user/login')->with(['message'=>'Your Account is not active','status'=>'error']);

				} else if($row->active=='2')
				{
					// BLocked users
					\Auth::logout();
					return redirect('user/login')->with(['message'=>'Your Account is BLocked','status'=>'error']);
				} else {
					$session = array(
						'gid' => $row->group_id,
						'uid' => $row->id,
						'eid' => $row->email,
						'id_unit' => $row->id_unit,
						'id_position' => $row->id_position,
						'll' => $row->last_login,
						'fid' =>  $row->first_name.' '. $row->last_name,
						'username' =>  $row->username ,
						'join'	=>  $row->created_at
					);
					session($session);
					if($this->config['cnf_front'] =='false') :
						return redirect('dashboard');						
					else :
						return redirect('');
					endif;					
					
										
				}
				
				
			}
		}

	}
	
	public function getSwitchGroup( Request $request, $gid = '' )
	{
		if (!\Auth::check()) {
			return redirect('user/login');
		}

		$userId = \Auth::user()->id;
		$check = \DB::table('tb_users_groups')
			->where('user_id', $userId)
			->where('group_id', $gid)
			->first();

		if ($check) {
			$group = \DB::table('tb_groups')->where('group_id', $gid)->first();
			$level = $group ? $group->level : 99;

			session([
				'gid' => $gid,
				'level' => $level
			]);

			return redirect('dashboard')->with([
				'message' => 'Group switched successfully!',
				'status' => 'success'
			]);
		}

		return redirect('dashboard')->with([
			'message' => 'Invalid group selected!',
			'status' => 'error'
		]);
	}

	public function postSwitchGroup( Request $request )
	{
		$gid = $request->input('group_id');
		return $this->getSwitchGroup($request, $gid);
	}

	public function getSwitchUnit( Request $request, $id_unit = '' )
	{
		if (!\Auth::check()) {
			return redirect('user/login');
		}

		$userId = \Auth::user()->id;
		$check = \DB::table('user_unit')
			->where('id_user', $userId)
			->where('id_unit', $id_unit)
			->first();

		if ($check) {
			$cid = $check->id_unit ? get_unit($check->id_unit, 'id_corporate') : session('cid', 1);

			session([
				'id_unit'     => $check->id_unit,
				'id_position' => $check->id_position,
				'cid'         => $cid,
			]);

			return redirect('dashboard')->with([
				'message' => 'Unit switched successfully!',
				'status'  => 'success'
			]);
		}

		return redirect('dashboard')->with([
			'message' => 'Invalid unit selected!',
			'status'  => 'error'
		]);
	}

	public function postSwitchUnit( Request $request )
	{
		$id_unit = $request->input('id_unit');
		return $this->getSwitchUnit($request, $id_unit);
	}
	
}
