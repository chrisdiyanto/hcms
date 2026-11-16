<?php namespace App\Models;

use Illuminate\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;

class corporate extends Sximo  {
	
	protected $table = 'corporate';
	protected $primaryKey = 'id_corporate';

	public function __construct() {
		parent::__construct();
		
	}

	public static function querySelect(  ){
		
		return "  SELECT corporate.* FROM corporate  ";
	}	

	public static function queryWhere(  ){
		
		return "  WHERE corporate.id_corporate IS NOT NULL ";
	}
	
	public static function queryGroup(){
		return "  ";
	}
	

}
