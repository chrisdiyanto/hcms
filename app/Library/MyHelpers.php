<?php 
/*

	This is general helpers for your applications 
	any library or helper should be write here

*/

use Illuminate\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;

class MyHelpers {

	public static function documentation( $id ) {
	//	return $id ;
		$param = explode('-',$id );
		$id 	= $param[0];
		$type 	= isset($param[1]) ? $param[1] : 'default';
		return  \App::call('App\\Http\\Controllers\DocsController@documentation',['id' => $id ,'type' => $type ]);
		return  $param;
	}
	public static function knowledgebase( $id ) {
		return  \App::call('App\\Http\\Controllers\DocsController@knowledgebase');
		return  $param;
	}


	public static function get_pejabat($id, $field = null){
		$result = \DB::table('position')
             ->select(\DB::raw('*'))
             ->where('position.id_unit', $id)
			 ->where('position.level', 3)
			 ->leftJoin('user_position', 'user_position.id_position', '=', 'position.id_position')
			 ->leftJoin('tb_users', 'tb_users.id', '=', 'user_position.id_user')
             ->first();

		if(!empty($result)){
			if(!empty($field)){
				return $result->$field;
			}
			return $result->username;
		}
	}

	public static function sendemailnotif($data){
		

		\Mail::send('user.emails.approve', $data, function ($message) use ($data) {
			$message->to($data['email'])->subject($data['subject']);
		});
		$message = "email terkirim";
		return response()->json(['status' => 'success', 'message' => $message ]);
	}
	
}
