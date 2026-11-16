<?php namespace App\Models\Core;

use App\Models\Sximo;
use Illuminate\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;

class Groups extends Sximo  {
	
	protected $table = 'tb_groups';
	protected $primaryKey = 'group_id';

	public function __construct() {
		parent::__construct();
		
	}

	public static function querySelect(  ){
		
		
		return " SELECT  
	tb_groups.group_id,
	tb_groups.name,
	tb_groups.description,
	tb_groups.level


FROM tb_groups  ";
	}
	public static function queryWhere(  ){
		
		return "  WHERE tb_groups.group_id IS NOT NULL    ";
	}
	
	public static function queryGroup(){
		return "    ";
	}

	// start Groups datatables

    var $column_order = array(null, 'name', 'description'); 
    var $column_search = array('name', 'description'); 
    var $order = array('tb_groups.group_id', 'asc');

    private function _get_datatables_query() {

        if(!empty($_POST['order'])){
            $field  = $this->column_order[$_POST['order']['0']['column']];
            $method = $_POST['order']['0']['dir'];    
        }else{
            $field  = $this->order[0];
            $method = $this->order[1];
        }

        $query = \DB::table('tb_groups')
            ->select('*')
            ->orderBy($field, $method)
            ->where(function ($query) {
                for ($i = 0; $i <= count($this->column_search)-1; $i++) {
                    if($i == 0){
                        $query->where($this->column_search[$i], 'like', '%'.$_POST['search']['value'].'%');
                    }else{
                        $query->orwhere($this->column_search[$i], 'like', '%'.$_POST['search']['value'].'%');
                    }
                  }
             })
             ->get();

        return $query;

    }

    function get_datatables() {
        $query = $this->_get_datatables_query();
        $start = @$_POST['start'];
        $lenght = @$_POST['length'];
        
        return $query->skip($start)->take($lenght);
		
    }
    function count_filtered() {
        $query = $this->_get_datatables_query();
        
        return $query->count();
    }
    function count_ajax() { 
        $query = \DB::table('tb_groups')
            ->get()
            ->count();
        return $query;
    }
    // end Groups datatables
	
	function delgroup($id){
		$query = \DB::table('tb_groups')
			->where('group_id',$id)
			->delete();
		return $query;
	}

}
