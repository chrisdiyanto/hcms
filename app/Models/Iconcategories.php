<?php namespace App\Models;

use Illuminate\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;

class iconcategories extends Sximo  {
	
	protected $table = 'tb_icons';
	protected $primaryKey = 'id';

	public function __construct() {
		parent::__construct();
		
	}

	public static function querySelect(  ){
		
		return "  SELECT tb_icons.* FROM tb_icons  ";
	}	

	public static function queryWhere(  ){
		
		return "  WHERE tb_icons.id IS NOT NULL ";
	}
	
	public static function queryGroup(){
		return "  ";
	}
	

}
