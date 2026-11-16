<?php namespace App\Models;

use Illuminate\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;

class unitcategory extends Sximo  {
	
	protected $table = 'unit_category';
	protected $primaryKey = 'id_unit_category';

	public function __construct() {
		parent::__construct();
		
	}

	public static function querySelect(  ){
		
		return "  SELECT unit_category.* FROM unit_category  ";
	}	

	public static function queryWhere(  ){
		
		return "  WHERE unit_category.id_unit_category IS NOT NULL ";
	}
	
	public static function queryGroup(){
		return "  ";
	}
	

}
