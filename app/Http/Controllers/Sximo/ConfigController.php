<?php namespace App\Http\Controllers\sximo;

use App\Http\Controllers\Controller;
use App\Models\Core\Groups;
use App\User;
use Illuminate\Http\Request;
use Validator, Input, Redirect; 
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use App\Support\Config\SecretSettings;

class ConfigController extends Controller {

    public function __construct()
    {
    	parent::__construct();
        $this->middleware(function ($request, $next) {           
            if(session('gid') !='1')
                return redirect('dashboard')
                ->with('message','You Dont Have Access to Page !')->with('status','error');            
            return $next($request);
        });
        $this->data['pageTitle'] =  __('core.t_generalsetting');
        $this->data['pageNote']  =   __('core.t_generalsettingsmall');

       
    }

	public function getIndex()
	{	
 		$this->data['pageTitle'] =  __('core.t_generalsetting');
        $this->data['pageNote']  =   __('core.t_generalsettingsmall');	
		$this->data['active'] = '';
		return view('sximo.config.index',$this->data);	
	}


	public function postSave( Request $request )
	{
           
		$rules = array(
			'cnf_appname'=>'required|min:2',
			'cnf_appdesc'=>'required|min:2',
			'cnf_comname'=>'required|min:2',
			'cnf_email'=>'required|email',
		);
		$validator = Validator::make($request->all(), $rules);	
		if (!$validator->fails()) 
		{
			$logo = '';
			if(!is_null( $request->file('logo')))
			{

				$file =  $request->file('logo');
			 	$destinationPath = public_path().'/uploads/images/';
				// Logo wajib gambar; ekstensi diambil dari isi file, bukan dari nama di client
				$extension = \SiteHelpers::safeUploadExtension($file, true);
				if($extension === null)
					return redirect('sximo/config')->with('message','Logo harus berupa gambar (jpg, png, gif, bmp, webp)')->with('status','error');
				$logo = 'backend-logo.'.$extension;
				$uploadSuccess = $file->move($destinationPath, $logo);
			}

            $new_config = [
                'cnf_appname'   => $request->input('cnf_appname'),
                'cnf_appdesc'   => $request->input('cnf_appdesc'),
                'cnf_comname'   => $request->input('cnf_comname'),
                'cnf_email'     => $request->input('cnf_email'),
                'cnf_metakey'   => $request->input('cnf_metakey'),
                'cnf_metadesc'  => $request->input('cnf_metadesc'),
                'cnf_multilang' => !is_null($request->input('cnf_multilang')) ? 1 : 0,
                'cnf_lang'      => $request->input('cnf_lang'),
                'cnf_front'     => !is_null($request->input('cnf_front')) ? 'true' : 'false',
                'cnf_theme'     => $request->input('cnf_theme'),
                'cnf_backend'   => $request->input('cnf_backend'),
                'cnf_mode'      => !is_null($request->input('cnf_mode')) ? 'production' : 'development',
                'cnf_date'      => !is_null($request->input('cnf_date')) ? $request->input('cnf_date') : 'Y-m-d',
            ];
            if ($logo != '') {
                $new_config['cnf_logo'] = $logo;
            }

            if ($error = $this->writeConfig($new_config)) {
                return redirect('sximo/config')->with('message', $error)->with('status', 'error');
            }

			return redirect('sximo/config')->with('message','Setting Has Been Save Successful')->with('status','success');
		} else {
			return redirect('sximo/config')->with('message', 'The following errors occurred')->with('status','success')
			->withErrors($validator)->withInput();
		}			
	
	}




	public function getEmail()
	{
		
		$regEmail = base_path()."/resources/views/user/emails/registration.blade.php";
		$resetEmail = base_path()."/resources/views/user/emails/auth/reminder.blade.php";
		$this->data = array(
			'groups'	=> Groups::all(),
			'pageTitle'	=> __('core.t_emailtemplate'),
			'pageNote'	=> __('core.t_emailtemplatesmall'),
			'regEmail' 	=> file_get_contents($regEmail),
			'resetEmail'	=> 	file_get_contents($resetEmail),
			'active'		=> 'email',
		);	
		return view('sximo.config.email',$this->data);		
	
	}
	
	function postEmail( Request $request)
	{
		
		//print_r($_POST);exit;
		$rules = array(
			'regEmail'		=> 'required|min:10',
			'resetEmail'		=> 'required|min:10',				
		);	
		$validator = Validator::make($request->all(), $rules);	
		if ($validator->passes()) 
		{
			$regEmailFile = base_path()."/resources/views/user/emails/registration.blade.php";
			$resetEmailFile = base_path()."/resources/views/user/emails/auth/reminder.blade.php";			
			
			$fp=fopen($regEmailFile,"w+"); 				
			fwrite($fp,$_POST['regEmail']); 
			fclose($fp);	
			
			$fp=fopen($resetEmailFile,"w+"); 				
			fwrite($fp,$_POST['resetEmail']); 
			fclose($fp);
			
			return redirect('sximo/config/email')->with('message', 'Email Has Been Updated')->with('status','success');	
			
		}	else {

			return redirect('sximo/config/email')->with('message', 'The following errors occurred')->with('status','success')
			->withErrors($validator)->withInput();
		}
	
	}
	
	public function getSecurity()
	{

		$this->data['groups']	= Groups::all();
		$this->data['pageTitle']	= __('core.t_loginsecurity');
		$this->data['pageNote']	= __('core.t_loginsecuritysmall');
		$this->data['active']	= 'security';

		//print_r($this->data) ;exit;
	
		return view('sximo.config.security',$this->data);		
	
	}	
	
		

	
	public function postLogin( Request $request)
	{

		$rules = array(

		);
		$validator = Validator::make($request->all(), $rules);	
		if ($validator->passes()) {


            $new_config = [
                'cnf_group'               => $request->input('CNF_GROUP'),
                'cnf_activation'          => $request->input('CNF_ACTIVATION'),
                'cnf_regist'              => !is_null($request->input('CNF_REGIST')) ? 'true' : 'false',
                'cnf_front'               => !is_null($request->input('CNF_FRONT')) ? 'true' : 'false',
                'cnf_recaptcha'           => !is_null($request->input('cnf_recaptcha')) ? 'true' : 'false',
                'cnf_recaptchapublickey'  => $request->input('cnf_recaptchapublickey'),
                'cnf_recaptchaprivatekey' => $request->input('cnf_recaptchaprivatekey'),
                'cnf_allowip'             => $request->input('CNF_ALLOWIP'),
                'cnf_restrictip'          => $request->input('CNF_RESTRICIP'),
                'cnf_mail'                => !is_null($request->input('CNF_MAIL')) ? $request->input('CNF_MAIL') : 'phpmail',
                'cnf_maps'                => $request->input('CNF_MAPS'),
            ];

            if ($error = $this->writeConfig($new_config)) {
                return redirect('sximo/config/security')->with('message', $error)->with('status', 'error');
            }

			return redirect('sximo/config/security')->with('message','Setting Has Been Save Successful')->with('status','success');
		} else {
			return redirect('sximo/config/security')->with('message', 'The following errors occurred')->with('status','error')
			->withErrors($validator)->withInput();
		}	
	}
	
	public function getLog( $type = null)
	{
	
		
		$this->data = array(
			'pageTitle'	=> __('core.m_clearcache'),
			'pageNote'	=> __('core.dash_clearcache'),
			'active'	=> 'log'
		);	
		return view('sximo.config.log',$this->data);	
	}
		
	
	public function getClearlog()
	{
		
		$dir = base_path()."/storage/logs";	
		foreach(glob($dir . '/*') as $file) {
			if(is_dir($file))
			{
				//removedir($file);
			} else {

				unlink($file);
			}
		}

		$dir = base_path()."/storage/framework/views";	
		foreach(glob($dir . '/*') as $file) {
			if(is_dir($file))
			{
				//removedir($file);
			} else {
				
				unlink($file);
			}
		}		

		return response()->json(array(
			'status'	=>__('core.note_t_success'),
			'message'	=>  __('core.note_success_action')
		));


		//return redirect('sximo/config/log')->with('message','Cache has been cleared !')->with('status','success');	
	}
	
	function removeDir($dir) {
		foreach(glob($dir . '/*') as $file) {
			if(is_dir($file))
				removedir($file);
			else
				unlink($file);
		}
		rmdir($dir);
	}
	
	public function getTranslation( Request $request, $type = null)
	{
		if(!is_null($request->input('edit')))
		{
			$file = (!is_null($request->input('file')) ? $request->input('file') : 'core.php');
			$langDir  = $this->langFilePath($request->input('edit'));
			$langFile = $this->langFilePath($request->input('edit'), $file);
			if($langDir === null || $langFile === null)
				return redirect('sximo/config/translation')->with('message','Invalid translation file !')->with('status','error');

			$files = scandir($langDir."/");

			//$str = serialize(file_get_contents('./protected/app/lang/'.$request->input('edit').'/core.php'));
			$str = \File::getRequire($langFile);
			
			
			$this->data = array(
				'pageTitle'	=> 'Translation',
				'pageNote'	=> 'Add Multilangues Option',
				'stringLang'	=> $str,
				'lang'			=> $request->input('edit'),
				'files'			=> $files ,
				'file'			=> $file ,
			);	
			$template = 'edit';
		
		} else {

			$this->data = array(
				'pageTitle'	=> 'Translation',
				'pageNote'	=> 'Add Multilangues Option',
			);	
			$template = 'index';		
		
		}

		return view('sximo.config.translation.'.$template,$this->data);	
	}
	
	public function getAddtranslation()
	{
		return view("sximo.config.translation.create");
	} 
	
	public function postAddtranslation( Request $request)
	{
		$rules = array(
			'name'		=> 'required',
			'folder'	=> 'required|alpha',
			'author'	=> 'required',
		);
		$validator = Validator::make($request->all(), $rules);	
		if ($validator->passes()) {

			$template = base_path();

			$folder = $request->input('folder');
			mkdir( $template."/resources/lang/".$folder ,0777 );	
			
			$info = json_encode(array("name"=> $request->input('name'),"folder"=> $folder , "author" => $request->input('author')));
			$fp=fopen(  $template.'/resources/lang/'.$folder.'/info.json',"w+"); 
			fwrite($fp,$info); 
			fclose($fp); 	
					
			$files = scandir( $template .'/resources/lang/en/');
			foreach($files as $f)
			{
				if($f != "." and $f != ".." and $f != 'info.json')
				{
					copy( $template .'/resources/lang/en/'.$f, $template .'/resources/lang/'.$folder.'/'.$f);
				}
			}
			return redirect('sximo/config/translation')->with('messagetect','New Translation has been added !')->with('status','success');	;			
			
		} else {
			return redirect('sximo/config/translation')->with('message','Failed to add translation !' )->with('status','error')->withErrors($validator)->withInput();
		}		
	
	}
	
	public function postSavetranslation( Request $request)
	{
		$lang = $request->input('lang');
		$file	= $request->input('file');
		// Hanya file bahasa yang sudah ada di resources/lang/{lang}/ yang boleh ditimpa
		$filename = $this->langFilePath($lang, $file);
		if($filename === null)
			return redirect('sximo/config/translation')->with('message','Invalid translation file !')->with('status','error');

		$strings = array();
		foreach($request->except(array('_token', 'lang', 'file')) as $key => $val)
		{
			if(!is_array($val))
			{
				$strings[$key] = strip_tags((string) $val);
			} else {
				$strings[$key] = array();
				foreach($val as $k=>$v)
				{
					$strings[$key][$k] = strip_tags(is_array($v) ? '' : (string) $v);
				}
			}
		}
		// var_export menghasilkan literal PHP yang aman (tanpa interpolasi / injeksi kode)
		$form = "<?php \n\nreturn ".var_export($strings, true).";\n";

		$fp=fopen($filename,"w+");
		fwrite($fp,$form); 
		fclose($fp); 	
		return redirect('sximo/config/translation?edit='.$lang.'&file='.$file)
		->with('message','Translation has been saved !')->with('status','success');	
	
	} 	
	
	public function getRemovetranslation( $folder )
	{
		$dir = $this->langFilePath($folder);
		// Bahasa dasar 'en' dipakai sebagai template bahasa baru, jangan dihapus
		if($dir === null || strtolower($folder) == 'en')
			return redirect('sximo/config/translation')->with('message','Invalid translation !')->with('status','error');

		self::removeDir($dir);
		return redirect('sximo/config/translation')->with('message','Translation has been removed !')->with('status','success');	
		
	}		

	// Path folder/file bahasa yang sudah divalidasi, atau null bila tidak valid / tidak ada
	protected function langFilePath( $lang, $file = null )
	{
		if(!is_string($lang) || !preg_match('/^[A-Za-z]{2,20}$/', $lang))
			return null;

		$dir = base_path('resources/lang/'.$lang);
		if(!is_dir($dir))
			return null;
		if($file === null)
			return $dir;

		if(!is_string($file) || !preg_match('/^[A-Za-z0-9_\-]+\.php$/', $file))
			return null;

		$path = $dir.'/'.$file;
		return is_file($path) ? $path : null;
	}

    private function writeConfig(array $new_config)
    {
        $current = config('sximo');
        $merged = array_merge($current, $new_config);

        // Kunci rahasia ke .env; config/sximo.php (masuk git) hanya berisi env()
        try {
            SecretSettings::writeEnv(collect(SecretSettings::SXIMO)->filter(fn ($env, $key) => array_key_exists($key, $new_config))
                ->mapWithKeys(fn ($env, $key) => [$env => (string) $new_config[$key]])->all());
        } catch (\RuntimeException $e) {
            Log::error($e->getMessage());
            return $e->getMessage();
        }

        $val  = "<?php \n";
        $val .= "return [\n";
        foreach ($merged as $key => $value) {
            // var_export meng-escape kutip sehingga nilai tidak bisa menyisipkan kode PHP
            $val .= var_export((string) $key, true)." => ".SecretSettings::exportValue(SecretSettings::SXIMO, (string) $key, $value).",\n";
        }
        $val .= "];\n";

        $filename = base_path() . '/config/sximo.php';
        if (!is_writable($filename) || @file_put_contents($filename, $val, LOCK_EX) === false) {
            Log::error('Failed to write config file: ' . $filename);
            return 'File config/sximo.php tidak bisa ditulis. Periksa permission file di server.';
        }

        // Production servers often keep the old file in OPcache or in the cached config
        if (function_exists('opcache_invalidate')) {
            @opcache_invalidate($filename, true);
        }
        if (app()->configurationIsCached()) {
            try {
                Artisan::call('config:clear');
            } catch (\Throwable $e) {
                Log::error('Failed to clear config cache: ' . $e->getMessage());
                return 'Setting tersimpan, tetapi config cache gagal dihapus. Jalankan "php artisan config:clear" di server.';
            }
        }
        config(['sximo' => $merged]);

        return null;
    }

    public function getDesign()
    {
        $this->data['pageTitle'] = __('core.t_design') ?? 'Design Settings';
        $this->data['pageNote']  = __('core.t_designsmall') ?? 'Manage Design & Theme Settings';
        $this->data['active']    = 'design';

        return view('sximo.config.design', $this->data);
    }

    public function postDesign(Request $request)
    {
        $new_config = [
            'cnf_header_color'  => $request->input('cnf_header_color'),
            'cnf_logo_color'    => $request->input('cnf_logo_color'),
            'cnf_sidebar_color' => $request->input('cnf_sidebar_color'),
            'cnf_card_color'    => $request->input('cnf_card_color'),
            'cnf_page_color'    => $request->input('cnf_page_color'),
            'cnf_text_color'    => $request->input('cnf_text_color'),
            'cnf_footer_color'  => $request->input('cnf_footer_color'),
            'cnf_theme'         => $request->input('cnf_theme'),
            'cnf_backend'       => $request->input('cnf_backend'),
        ];

        if ($error = $this->writeConfig($new_config)) {
            return redirect('sximo/config/design')->with('message', $error)->with('status', 'error');
        }
 
         return redirect('sximo/config/design')->with('message', 'Design Setting Has Been Saved Successful')->with('status', 'success');
     }
 
    public function getAi()
    {
        $this->data['pageTitle'] = __('core.tab_ai') ?? 'AI Settings';
        $this->data['pageNote']  = 'Configure AI Service Providers and Access Rights';
        $this->data['active']    = 'ai';
        $this->data['groups']    = Groups::all();

        // Read AI configuration values from config/ai.php (with fallback to .env)
        $this->data['ai_provider']           = config('ai.ai_provider') ?? env('AI_PROVIDER', '');
        $this->data['ai_api_key']            = config('ai.ai_api_key') ?? env('AI_API_KEY', '');
        $this->data['ai_api_endpoint']       = config('ai.ai_api_endpoint') ?? env('AI_API_ENDPOINT', '');
        $this->data['ai_model']              = config('ai.ai_model') ?? env('AI_MODEL', '');
        $this->data['ai_name']               = config('ai.ai_name') ?? env('AI_NAME', 'AI Assistant');
        $this->data['ai_avatar']             = config('ai.ai_avatar') ?? env('AI_AVATAR', '');
        $this->data['ai_system_instruction'] = config('ai.ai_system_instruction') ?? env('AI_SYSTEM_INSTRUCTION', '');

        $allowedGroupsStr = config('ai.ai_allowed_groups') ?? env('AI_ALLOWED_GROUPS', '');
        $this->data['allowed_groups']        = $allowedGroupsStr !== '' ? explode(',', $allowedGroupsStr) : [];

        return view('sximo.config.ai', $this->data);
    }

    public function postAi(Request $request)
    {
        $rules = [
            'ai_provider' => 'required',
            'ai_avatar'   => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048', // max 2MB image
        ];
        $validator = Validator::make($request->all(), $rules);
        if ($validator->passes()) {
            $allowedGroups = $request->input('ai_allowed_groups', []);
            $allowedGroupsStr = implode(',', $allowedGroups);

            $aiConfigData = [
                'ai_provider'           => $request->input('ai_provider'),
                'ai_api_key'            => $request->input('ai_api_key') ?? '',
                'ai_api_endpoint'       => $request->input('ai_api_endpoint') ?? '',
                'ai_model'              => $request->input('ai_model') ?? '',
                'ai_allowed_groups'     => $allowedGroupsStr,
                'ai_name'               => $request->input('ai_name') ?? 'AI Assistant',
                'ai_system_instruction' => $request->input('ai_system_instruction') ?? '',
            ];

            // Handle AI Avatar upload
            if ($request->hasFile('ai_avatar')) {
                $file = $request->file('ai_avatar');
                $destinationPath = public_path('uploads/ai');
                if (!file_exists($destinationPath)) {
                    mkdir($destinationPath, 0755, true);
                }

                // Delete old avatar file if it exists
                $oldAvatar = config('ai.ai_avatar') ?? env('AI_AVATAR');
                if ($oldAvatar && file_exists($destinationPath . '/' . $oldAvatar)) {
                    @unlink($destinationPath . '/' . $oldAvatar);
                }

                // Nama acak + ekstensi dari isi file, bukan nama asli dari client
                $avatarName = \SiteHelpers::safeUploadName($file, true);
                if ($avatarName === null) {
                    return redirect('sximo/config/ai')->with('message', 'Avatar harus berupa gambar (jpg, png, gif)')->with('status', 'error');
                }
                $file->move($destinationPath, $avatarName);
                $aiConfigData['ai_avatar'] = $avatarName;
            } else {
                $aiConfigData['ai_avatar'] = config('ai.ai_avatar') ?? env('AI_AVATAR', '');
            }

            $this->writeAiConfig($aiConfigData);

            return redirect('sximo/config/ai')->with('message', 'AI Setting Has Been Saved Successfully')->with('status', 'success');
        } else {
            return redirect('sximo/config/ai')->with('message', 'Failed to save settings')->with('status', 'error')
                ->withErrors($validator)->withInput();
        }
    }

    public function writeAiConfig(array $new_config)
    {
        $current = config('ai') ?? [];
        $merged = array_merge($current, $new_config);

        // API key ke .env; config/ai.php (masuk git) hanya berisi env()
        SecretSettings::writeEnv(collect(SecretSettings::AI)->filter(fn ($env, $key) => array_key_exists($key, $new_config))
            ->mapWithKeys(fn ($env, $key) => [$env => (string) $new_config[$key]])->all());

        $val  = "<?php\n\n";
        $val .= "return [\n";
        foreach ($merged as $key => $value) {
            $val .= '    '.var_export((string) $key, true).' => '.SecretSettings::exportValue(SecretSettings::AI, (string) $key, $value).",\n";
        }
        $val .= "];\n";

        $filename = base_path('config/ai.php');
        file_put_contents($filename, $val, LOCK_EX);
        if (function_exists('opcache_invalidate')) {
            @opcache_invalidate($filename, true);
        }
        config(['ai' => $merged]);
    }

    public function getRag()
    {
        $this->data['pageTitle'] = __('core.tab_rag') ?? 'RAG Documents';
        $this->data['pageNote']  = 'Manage Reference Documents for AI Knowledge Base';
        $this->data['active']    = 'rag';

        // Fetch documents along with chunk counts
        $this->data['documents'] = \DB::table('tb_rag_documents')
            ->select('tb_rag_documents.*')
            ->selectSub(function($query) {
                $query->selectRaw('count(*)')
                    ->from('tb_rag_chunks')
                    ->whereColumn('tb_rag_chunks.document_id', 'tb_rag_documents.id');
            }, 'chunks_count')
            ->orderBy('created_at', 'desc')
            ->get();

        return view('sximo.config.rag', $this->data);
    }

    public function postRagUpload(Request $request)
    {
        $rules = [
            'reference_file' => 'required|file|mimes:txt,md,json,html,pdf,docx,xlsx,pptx,csv,xml|max:20480', // max 20MB (support Office & PDF)
        ];
        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return redirect('sximo/config/rag')
                ->with('message', 'Upload failed. Supported formats: .txt, .md, .json, .html, .pdf, .docx, .xlsx, .pptx, .csv, .xml (max 20MB).')
                ->with('status', 'error')
                ->withErrors($validator);
        }

        $file = $request->file('reference_file');
        $fileName = $file->getClientOriginalName();
        $extension = strtolower($file->getClientOriginalExtension());
        
        // Save file to storage/app/rag_documents
        $destinationPath = storage_path('app/rag_documents');
        if (!file_exists($destinationPath)) {
            mkdir($destinationPath, 0755, true);
        }
        
        $safeFileName = time() . '_' . str_replace(' ', '_', $fileName);
        $file->move($destinationPath, $safeFileName);
        $filePath = $destinationPath . '/' . $safeFileName;

        // 1. Coba konversi menggunakan Microsoft MarkItDown via Python
        $content = '';
        $pythonBinary = $this->getPythonBinary();
        $pythonScript = base_path('python/convert_to_md.py');

        if (file_exists($pythonScript)) {
            try {
                $process = \Illuminate\Support\Facades\Process::timeout(120)->run([$pythonBinary, $pythonScript, $filePath]);
                if ($process->successful() && trim($process->output()) !== '') {
                    $content = $process->output();
                }
            } catch (\Exception $e) {
                // Lanjut ke fallback jika python process error
            }
        }

        // 2. Fallback jika Python MarkItDown belum terinstall / gagal
        if (empty(trim($content))) {
            if ($extension === 'pdf') {
                try {
                    $parser = new \Smalot\PdfParser\Parser();
                    $pdf = $parser->parseFile($filePath);
                    $content = $pdf->getText();
                } catch (\Exception $e) {
                    @unlink($filePath);
                    return redirect('sximo/config/rag')
                        ->with('message', 'Failed to extract text from PDF file: ' . $e->getMessage())
                        ->with('status', 'error');
                }
            } elseif (in_array($extension, ['docx', 'xlsx', 'pptx'])) {
                @unlink($filePath);
                return redirect('sximo/config/rag')
                    ->with('message', "Format .{$extension} membutuhkan Python 'markitdown'. Silakan jalankan 'pip install markitdown' di server.")
                    ->with('status', 'error');
            } else {
                $content = @file_get_contents($filePath);
            }
        }

        if ($content === false || trim($content) === '') {
            @unlink($filePath);
            return redirect('sximo/config/rag')
                ->with('message', 'Document is empty or could not be converted to readable text/markdown.')
                ->with('status', 'error');
        }

        // Save markdown representation
        $mdFileName = pathinfo($safeFileName, PATHINFO_FILENAME) . '.md';
        $mdFilePath = $destinationPath . '/' . $mdFileName;
        @file_put_contents($mdFilePath, $content);

        // Initialize RagService
        $ragService = new \App\Library\RagService();

        // Check if API settings are configured (needed to generate embeddings)
        $provider = config('ai.ai_provider') ?: env('AI_PROVIDER', '');
        $apiKey = config('ai.ai_api_key') ?: env('AI_API_KEY', '');
        if (!$provider || !$apiKey) {
            @unlink($filePath);
            if (file_exists($mdFilePath)) {
                @unlink($mdFilePath);
            }
            return redirect('sximo/config/rag')
                ->with('message', 'AI Provider settings are incomplete. Please configure AI Settings first.')
                ->with('status', 'error');
        }

        // Chunk text
        $chunks = $ragService->chunkText($content, 1000, 200);
        if (empty($chunks)) {
            @unlink($filePath);
            if (file_exists($mdFilePath)) {
                @unlink($mdFilePath);
            }
            return redirect('sximo/config/rag')
                ->with('message', 'Document contains no readable text segments.')
                ->with('status', 'error');
        }

        try {
            // Insert document metadata
            $documentId = \DB::table('tb_rag_documents')->insertGetId([
                'file_name'  => $fileName,
                'file_path'  => 'rag_documents/' . $safeFileName,
                'created_at' => now(),
                'updated_at' => now()
            ]);

            // Dispatch asynchronous Queue Job to generate embeddings in the background
            \App\Jobs\ProcessRagEmbeddingsJob::dispatch($documentId, $chunks);

            return redirect('sximo/config/rag')
                ->with('message', "Dokumen '" . e($fileName) . "' berhasil dikonversi ke Markdown (" . count($chunks) . " chunks). Proses embedding dijalankan melalui background Queue Job.")
                ->with('status', 'success');

        } catch (\Exception $e) {
            @unlink($filePath);
            if (isset($mdFilePath) && file_exists($mdFilePath)) {
                @unlink($mdFilePath);
            }
            return redirect('sximo/config/rag')
                ->with('message', 'Processing failed: ' . $e->getMessage())
                ->with('status', 'error');
        }
    }

    private function getPythonBinary()
    {
        $custom = env('PYTHON_BINARY');
        if ($custom && file_exists($custom)) {
            return $custom;
        }

        $venv = base_path('python/venv/Scripts/python.exe');
        if (file_exists($venv)) {
            return $venv;
        }

        $user = get_current_user();
        $candidates = [
            'python',
            'py',
            "C:\\Users\\{$user}\\AppData\\Local\\Programs\\Python\\Python311\\python.exe",
            "C:\\Users\\{$user}\\AppData\\Local\\Programs\\Python\\Python312\\python.exe",
            "C:\\Users\\{$user}\\AppData\\Local\\Programs\\Python\\Python310\\python.exe",
            'C:\\Program Files\\Python311\\python.exe',
            'C:\\Program Files\\Python312\\python.exe',
            'C:\\Program Files\\Python310\\python.exe',
            'C:\\laragon\\bin\\python\\python.exe',
        ];

        foreach ($candidates as $bin) {
            if ($bin === 'python' || $bin === 'py') {
                return $bin;
            }
            if (file_exists($bin)) {
                return $bin;
            }
        }

        return 'python';
    }

    public function getRagDownloadMd($id)
    {
        $doc = \DB::table('tb_rag_documents')->where('id', $id)->first();
        if (!$doc) {
            return redirect('sximo/config/rag')->with('message', 'Document not found.')->with('status', 'error');
        }

        $mdFileNameOnDisk = pathinfo($doc->file_path, PATHINFO_FILENAME) . '.md';
        $mdFilePath = storage_path('app/rag_documents/' . $mdFileNameOnDisk);
        $originalFilePath = storage_path('app/' . $doc->file_path);

        $content = '';

        if (file_exists($mdFilePath)) {
            $content = @file_get_contents($mdFilePath);
        } elseif (file_exists($originalFilePath)) {
            $extension = strtolower(pathinfo($originalFilePath, PATHINFO_EXTENSION));
            if ($extension === 'md' || $extension === 'txt') {
                $content = @file_get_contents($originalFilePath);
            } else {
                $pythonBinary = $this->getPythonBinary();
                $pythonScript = base_path('python/convert_to_md.py');
                if (file_exists($pythonScript)) {
                    try {
                        $process = \Illuminate\Support\Facades\Process::timeout(120)->run([$pythonBinary, $pythonScript, $originalFilePath]);
                        if ($process->successful() && trim($process->output()) !== '') {
                            $content = $process->output();
                            @file_put_contents($mdFilePath, $content);
                        }
                    } catch (\Exception $e) {
                        // Fallback
                    }
                }
            }
        }

        // Fallback: Reconstruct content from saved vector chunks if original/md file is missing or empty
        if (empty(trim($content))) {
            $chunks = \DB::table('tb_rag_chunks')->where('document_id', $id)->orderBy('id', 'asc')->pluck('content')->toArray();
            if (!empty($chunks)) {
                $content = implode("\n\n", $chunks);
            }
        }

        if (empty(trim($content))) {
            return redirect('sximo/config/rag')->with('message', 'Markdown content could not be generated or found.')->with('status', 'error');
        }

        $downloadName = pathinfo($doc->file_name, PATHINFO_FILENAME) . '.md';

        return response($content, 200, [
            'Content-Type' => 'text/markdown; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . addslashes($downloadName) . '"',
        ]);
    }

    public function getRagDelete($id)
    {
        $doc = \DB::table('tb_rag_documents')->where('id', $id)->first();
        if ($doc) {
            $filePath = storage_path('app/' . $doc->file_path);
            if (file_exists($filePath)) {
                @unlink($filePath);
            }

            $mdFileName = pathinfo($doc->file_path, PATHINFO_FILENAME) . '.md';
            $mdFilePath = storage_path('app/rag_documents/' . $mdFileName);
            if (file_exists($mdFilePath)) {
                @unlink($mdFilePath);
            }

            \DB::table('tb_rag_chunks')->where('document_id', $id)->delete();
            \DB::table('tb_rag_documents')->where('id', $id)->delete();

            return redirect('sximo/config/rag')
                ->with('message', 'Document and its corresponding vector chunks successfully deleted.')
                ->with('status', 'success');
        }

        return redirect('sximo/config/rag')
            ->with('message', 'Document not found.')
            ->with('status', 'error');
    }

}