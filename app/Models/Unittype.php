<?php namespace App\Models;

use Illuminate\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;

class unittype extends Sximo  {
	
	protected $table = 'unit_type';
	protected $primaryKey = 'id_unit_type';

	public function __construct() {
		parent::__construct();
		
	}

	public static function querySelect(  ){
		
		return "  SELECT unit_type.* FROM unit_type  ";
	}	

	public static function queryWhere(  ){
		
		return "  WHERE unit_type.id_unit_type IS NOT NULL ";
	}
	
	public static function queryGroup(){
		return "  ";
	}
	

}
