<?php namespace App\Models;

use Illuminate\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;

class acceptance extends Sximo  {
	
	protected $table = 'acceptance';
	protected $primaryKey = 'id';

	public function __construct() {
		parent::__construct();
		
	}

	public static function querySelect(  ){
		
		return "  SELECT acceptance.* FROM acceptance  ";
	}	

	public static function queryWhere(  ){
		
		return "  WHERE acceptance.id IS NOT NULL ";
	}
	
	public static function queryGroup(){
		return "  ";
	}
	

}
