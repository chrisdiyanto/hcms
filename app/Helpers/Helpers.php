<?php 


use Illuminate\Support\Facades\Crypt;

if(!function_exists('rupiah')) {
    function rupiah($value, $des = null ,$rp = null, $no = null){ // $des = desimal
        if($value){
            if(!$rp){
                if(!empty($des)){
                    // $formated = str_replace(',', '.', number_format($value, $des));
                    $formated = number_format($value, $des,",",".");
                } else {
                    // $formated = str_replace(',', '.', number_format($value));
                    $formated = number_format($value);
                }

            }elseif($rp == 'rp'){
                if(!empty($des)){
                    // $formated = 'Rp '.str_replace(',', '.', number_format($value, $des,",","."));
                    $formated = 'Rp '.number_format($value, $des,",",".");
                } else {
                    // $formated = 'Rp '.str_replace(',', '.', number_format($value));
                    $formated = 'Rp '.number_format($value);
                }
            
            }elseif($rp == '+62'){
                // $formated = '+62 '.number_format($value,0," ");
                $formated = '+62 '.str_replace(',', ' ', number_format($value));
            }

            return $formated;
        }else{
            return '-';
        }
    }
    
}

if(!function_exists('isEmptyDateTime')) {
    function isEmptyDateTime($value){
           if(empty($value)) return true;
           if($value instanceof \DateTimeInterface) return false;
           $value = trim((string)$value);
           $zeroDates = ['0000-00-00 00:00:00', '0000-00-00', '0000-00-00 00:00:00.000000'];
           if(in_array($value, $zeroDates)) return true;
           if(str_starts_with($value, '0000-00-00')) return true;
           if(is_numeric($value) && intval($value) <= 0) return true;
           $ts = strtotime($value);
           if($ts === false) return true;
           if($ts <= 0) return true;
           return false;
       }
}

if(!function_exists('is_zero_date')) {
    function is_zero_date($value){
        return isEmptyDateTime($value);
    }
}

if(!function_exists('get_user_data')) {
    function get_user_data($id = null, $field = null){
        if(empty($id)){
            $id = get_session('uid');
        }
        $query = DB::table('tb_users')
            ->select(
                'tb_users.*',
                'user_unit.id_unit',
                'user_unit.id_position',
                'user_unit.start_date',
                'user_unit.end_date',
                'unit.unit_code',
                'unit.unit_name',
                'pos_unit.unit_name as position_name'
            )
            ->leftJoin('user_unit', 'user_unit.id_user', '=', 'tb_users.id')
            ->leftJoin('unit', 'unit.id_unit', '=', 'user_unit.id_unit')
            ->leftJoin('unit as pos_unit', 'pos_unit.id_unit', '=', 'user_unit.id_position')
            ->where('tb_users.id', $id);

        if ($id == get_session('uid') && get_session('id_unit')) {
            $activeUnit = get_session('id_unit');
            $query->orderByRaw('CASE WHEN user_unit.id_unit = ? THEN 0 ELSE 1 END', [$activeUnit]);
        }

        $user = $query->first();
        if(!empty($field)){
            return (isset($user->$field) ? $user->$field : '');    
        }else{
            return (isset($user->username) ? $user->username : '');
        }
    
    }
}

if(!function_exists('get_session')) {
    function get_session($field = null){
        if(!empty($field)){
            return Session::get($field);
        }else{
            return Session::get('uid');
        }
    
    }
}

if(!function_exists('devider')) {
    function devider(){
        $id = get_user_data();

		if(!empty($id)){

            $year   = date('Y');
            $month  = date('m');
            $day    = date('d');
            $jml    = $year+$month+$day;
            $key    = hash('sha256','bismillah'.date('F').$year.$month.$day);
            $value  = hash('sha256','alhamdulillah'.date('l').$day.$month.$year);
            

			$data =[
                'a'             => $year * $jml *$day,
                'b'             => ($year - ($month*$day))*$day,
                'c'             => $jml * $day,
            ];
            
            return $data;
		}
    }
}

if(!function_exists('encode')) {
    function encode($string=""){ 
        if(!empty($string)){
            return Crypt::encrypt($string);
        }
    } 
}

if(!function_exists('decode')) {
    function decode($string=""){ 
        if(!empty($string)){
            return Crypt::decrypt($string);
        }
    }
}

if(!function_exists('cleanthis')) {
    function cleanthis($string=""){ 
        if($string != null){
            $string1    = str_replace("'", "", $string); 
            $string2    = str_replace("\\", "", $string1); 
            $string3    = str_replace('"', '', $string2); 
            $bersih     = htmlspecialchars($string3, ENT_QUOTES); 
            return $bersih;
        }else{
            return $string;
        }
    } 
}

if(!function_exists('generatetoken')){
    function generatetoken($action){
        $id = get_user_data();

		if(!empty($id)){
            
			$stamp 	= time();
            $devider	= devider();
			$a			= $devider['a'];
			$b			= $devider['b'];
			$c			= $devider['c'];
            
			$stamp2	= (($stamp*$c)+$a)/$b;
			$date	= date('Ymd');
			$string	= $date.$stamp2;

            $output = encode($string, $action);
            
            return $output;
            // return $user_agent;
		}
    }
}

if(!function_exists('rannum')) {
    function rannum($length = null){
        if(empty($length)){
            $length = 7;
        }
        $karakter= '1234567890';
		$string = '';
		for ($i = 0; $i < $length; $i++) {
				$pos = rand(0, strlen($karakter)-1);
				$string .= $karakter[$pos];
			}
		return $string;
    }
}


if(!function_exists('randthis')) {
    function randthis($jml = null){
        $karakter= 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz1234567890';
		$string = '';
        if(empty($jml)){
            $jml = 7;
        }
		for ($i = 0; $i < $jml+1; $i++) {
				$pos = rand(0, strlen($karakter)-1);
				$string .= $karakter[$pos];
			}
		return $string;
    }
}

if(!function_exists('generateslug')) {
    function generateslug($length = null){
        $id = get_user_data();

		if(!empty($id)){
            $id = rannum(11);
        }

        if(empty($length)){
            $length = 10;
        }

            $rand2  = randthis(2);
            $output = $rand2.str_rot13(hash('crc32b', str_rot13(crypt($id.randthis().date('Ymd').time(), 'rl'))));
            
            if(strlen($output) > $length-1){
                $output = substr($output, 0, $length-1);
            }elseif(strlen($output) < $length){
                $output = $output.randthis($length-1-strlen($output));
            }
            
            return $output;
		
    }
}


if (!function_exists('db_get_all_data')) {
	function db_get_all_data($table_name = null, $where = false)
	{
        if(!empty($table_name)){
            $normalizeWhere = function ($clause) use ($table_name) {
                if ($table_name === 'tb_users' && is_string($clause)) {
                    $clause = preg_replace(
                        '/(?:tb_users\.)?\bgroup_id\s*=\s*(\d+)/i',
                        'EXISTS (SELECT 1 FROM tb_users_groups WHERE tb_users_groups.user_id = tb_users.id AND tb_users_groups.group_id = $1)',
                        $clause
                    );
                    $clause = preg_replace(
                        '/(?:tb_users\.)?\bgroup_id\s*!=\s*(\d+)/i',
                        'NOT EXISTS (SELECT 1 FROM tb_users_groups WHERE tb_users_groups.user_id = tb_users.id AND tb_users_groups.group_id = $1)',
                        $clause
                    );
                    $clause = preg_replace(
                        '/(?:tb_users\.)?\bid_unit\s*=\s*(\d+)/i',
                        'EXISTS (SELECT 1 FROM user_unit WHERE user_unit.id_user = tb_users.id AND user_unit.id_unit = $1)',
                        $clause
                    );
                    $clause = preg_replace(
                        '/(?:tb_users\.)?\bid_position\s*=\s*(\d+)/i',
                        'EXISTS (SELECT 1 FROM user_unit WHERE user_unit.id_user = tb_users.id AND user_unit.id_position = $1)',
                        $clause
                    );
                }
                return $clause;
            };

            if(empty($where)){
                $query = \DB::table($table_name)
                    ->get();
            }else{

                if(is_array($where)){
                    $query = \DB::table($table_name)
                        ->where(function ($query) use ($where, $normalizeWhere) {
                            foreach ($where as $item) {
                                $query->whereRaw($normalizeWhere($item));
                            }
                        })
                        ->get();
                }else{
                    $query = \DB::table($table_name)
                        ->whereRaw($normalizeWhere($where))
                        ->get();
                }
            }
    
            return $query;
        }
	}
}

if (!function_exists('db_get_value')) {
    function db_get_value($table_name = null, $field = null, $where = false)
    {
        if(!empty($table_name) && !empty($field)){
            $normalizeWhere = function ($clause) use ($table_name) {
                if ($table_name === 'tb_users' && is_string($clause)) {
                    $clause = preg_replace(
                        '/(?:tb_users\.)?\bgroup_id\s*=\s*(\d+)/i',
                        'EXISTS (SELECT 1 FROM tb_users_groups WHERE tb_users_groups.user_id = tb_users.id AND tb_users_groups.group_id = $1)',
                        $clause
                    );
                    $clause = preg_replace(
                        '/(?:tb_users\.)?\bid_unit\s*=\s*(\d+)/i',
                        'EXISTS (SELECT 1 FROM user_unit WHERE user_unit.id_user = tb_users.id AND user_unit.id_unit = $1)',
                        $clause
                    );
                    $clause = preg_replace(
                        '/(?:tb_users\.)?\bid_position\s*=\s*(\d+)/i',
                        'EXISTS (SELECT 1 FROM user_unit WHERE user_unit.id_user = tb_users.id AND user_unit.id_position = $1)',
                        $clause
                    );
                }
                return $clause;
            };

            $baseQuery = \DB::table($table_name);
            if ($table_name === 'tb_users' && in_array($field, ['id_unit', 'id_position'])) {
                $baseQuery->leftJoin('user_unit', 'user_unit.id_user', '=', 'tb_users.id')
                          ->select('user_unit.' . $field);
            } else {
                $baseQuery->select($field);
            }

            if(empty($where)){
                $query = $baseQuery->first();
            }else{
                if(is_array($where)){
                    $query = $baseQuery
                        ->where(function ($query) use ($where, $normalizeWhere) {
                            foreach ($where as $item) {
                                $query->whereRaw($normalizeWhere($item));
                            }
                        })
                        ->first();
                }else{
                    $query = $baseQuery
                        ->whereRaw($normalizeWhere($where))
                        ->first();
                }
            }

            if(!empty($query)){
                return $query->$field;
            }
        }
    }
}

function logout() {
    Auth::logout();
    return redirect('/login');
  }

  if(!function_exists('checkmodule')) {
    function checkmodule($modul = null){
        
            $query = \DB::table('tb_module')
                    ->select('*')
                    ->where('module_name', $modul)
                    ->first();

            if(!empty($field)){
                return $query->$field;
            }else{
                return $query;
            }

    }
}

  if(!function_exists('is_allowed')) {
    function is_allowed($modul = null){
        
        $id = session('gid');
        $check = checkmodule($modul);

        if(!empty($check)){
            $modul = $check->module_id;
        }
        
        if(!empty($id)){

            $null = (array)[
                'is_global' =>  0,
                'is_detail' =>  0,
                'is_add'    =>  0,
                'is_edit'   =>  0,
                'is_remove' =>  0,
                'is_excel'  =>  0,
                'is_csv'    =>  0,
                'is_pdf'    =>  0,
                'is_print'  =>  0,
            ];

            if($id == 1){
                $data = (array)[
                    'is_global' =>  1,
                    'is_detail' =>  1,
                    'is_add'    =>  1,
                    'is_edit'   =>  1,
                    'is_remove' =>  1,
                    'is_excel'  =>  1,
                    'is_csv'    =>  1,
                    'is_pdf'    =>  1,
                    'is_print'  =>  1,
                ];

            }else{
                if(!empty($modul)){
                    $query = \DB::table('tb_groups_access')
                            ->select('*')
                            ->where('group_id', $id)
                            ->where('module_id', $modul)
                            ->first();
                }

                if(!empty($query))
                {
                    $data = json_decode($query->access_data,true);
                } else {
                    $data = (array)[
                        'is_global' =>  0,
                        'is_detail' =>  0,
                        'is_add'    =>  0,
                        'is_edit'   =>  0,
                        'is_remove' =>  0,
                        'is_excel'  =>  0,
                        'is_csv'    =>  0,
                        'is_pdf'    =>  0,
                        'is_print'  =>  0,
                    ];
                }
            }

            return $data;
            
        }

    }
}  

if(!function_exists('getcorporate')) {
    function getcorporate($field = null){
        
            $query = \DB::table('corporate')
                    ->select('*')
                    ->orderBy('id_corporate', 'asc')
                    ->first();

            if(!empty($field)){
                return $query->$field;
            }else{
                return $query;
            }

    }
}

if(!function_exists('get_unit')) {
    function get_unit($id = null, $field = null){
        
        if(empty($id)){
            $id = session('id_unit');
        }

        if(!empty($id)){
            $query = \DB::table('unit')
                    ->select('unit.*')
                    ->where('id_unit', $id)
                    ->first();

            if(!empty($field)){
                return $query->$field;
            }else{
                return $query;
            }
        }

    }
}

if(!function_exists('get_unitbyslug')) {
    function get_unitbyslug($slug = null, $field = null){
        
        if(!empty($slug)){
            $query = \DB::table('unit')
                    ->select('unit.*')
                    ->where('slug_unit', $slug)
                    ->first();

            if(!empty($field)){
                return $query->$field;
            }else{
                return $query;
            }
        }

    }
}

if(!function_exists('getusername')) {
    function getusername($id = null){
        if(empty($id)){
            $id = session('uid');
        }

            $query = \DB::table('tb_users')
                    ->where('id', $id)
                    ->first();
        if(!empty($query)){
            return $query->username;
        }
    }
}

if(!function_exists('groupaccess')) {
    function groupaccess($id, $gid = null){
        if(empty($gid)){
            $gid = get_session('gid');
        }
        $query = \DB::table('tb_users')
            ->join('tb_users_groups', 'tb_users_groups.user_id', '=', 'tb_users.id')
            ->select('tb_users.email')
            ->where('tb_users_groups.group_id', 3)
            ->first();
        return $query;

    }
}