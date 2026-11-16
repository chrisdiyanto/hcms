<?php namespace App\Models\Core;

use App\Models\Sximo;
use Illuminate\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;

class Users extends Sximo  {
	
	protected $table = 'tb_users';
	protected $primaryKey = 'id';

	public function __construct() {
		parent::__construct();
	}

	public static function querySelect(  ){
		
		return " 
			SELECT  tb_users.*,  tb_groups.name, tb_users_groups.group_id, user_unit.id_unit, user_unit.id_position, unit.unit_name 
			FROM tb_users 
			LEFT JOIN tb_users_groups ON tb_users_groups.user_id = tb_users.id
			LEFT JOIN tb_groups ON tb_groups.group_id = tb_users_groups.group_id 
			LEFT JOIN user_unit ON user_unit.id_user = tb_users.id
			LEFT JOIN unit ON unit.id_unit = user_unit.id_unit
		";
	}	

	public static function queryWhere(  ){
		
		return "    WHERE tb_users.id !=''   ";
	}
	
	public static function queryGroup(){
		return "      ";
	}

	public static function getRows( $args , $gid = 0 )
	{
       $table = with(new static)->table;
	   $key = with(new static)->primaryKey;
	   
        extract( array_merge( array(
			'page' 		=> '0' ,
			'limit'  	=> '0' ,
			'sort' 		=> '' ,
			'order' 	=> '' ,
			'params' 	=> '' ,
			'flimit' 	=> '' ,
			'fstart' 	=> '' ,
			'global'	=> 1
        ), $args ));

		list($page, $limit, $sort, $order, $fstart, $flimit) = self::safeListArgs($page, $limit, $sort, $order, $fstart, $flimit);
		$gid = (int) $gid;

		$offset = ($page-1) * $limit ;
		$limitConditional = ($page !=0 && $limit !=0) ? "LIMIT  $offset , $limit" : '';	
		/* Added since version 5.1.7 */
		if($fstart !='' && $flimit != '')
			$limitConditional = "LIMIT  $fstart , $flimit" ;	
		/* End Added since version 5.1.7 */

		$orderConditional = ($sort !='' && $order !='') ?  " ORDER BY {$sort} {$order} " : '';

		// Update permission global / own access new ver 1.1
		$table = with(new static)->table;
		// if($this->access['is_global'] ==0 )
		
		if($global == 0 && \Schema::hasColumn($table, 'entry_by'))
				$params .= " AND {$table}.entry_by ='".$gid."'";
		// End Update permission global / own access new ver 1.1			
        
		$rows = array();
	    $result = \DB::select( self::querySelect() . static::scopedWhere(). " 
				{$params} ". self::queryGroup() ." {$orderConditional}  {$limitConditional} ");
		
		$total = \DB::select( "
				SELECT  count(DISTINCT tb_users.id) as total 
			FROM tb_users 
			LEFT JOIN tb_users_groups ON tb_users_groups.user_id = tb_users.id
			LEFT JOIN tb_groups ON tb_groups.group_id = tb_users_groups.group_id 
			LEFT JOIN user_unit ON user_unit.id_user = tb_users.id
			LEFT JOIN unit ON unit.id_unit = user_unit.id_unit " . static::scopedWhere(). " 
				{$params} ". self::queryGroup() );
		$total = (count($total) != 0 ? $total[0]->total : 0 );

		return $results = array('rows'=> $result , 'total' => $total);	
	}	

	
	public static function level( $id ) {

		$group_id = 0;
		$sql = \DB::table('tb_groups')->where('group_id',$id)->get();
		if(count($sql)){
			$row = $sql[0];
			$group_id = $row->level;
		}
		return $group_id;
			
	}

	function getuserunit($id = null){
		if(!empty($id)){
			$query = \DB::table('user_unit')
				->select('*')
				->where('id_user', $id)
				->first();
	
			return $query;
		}
	}

	function getuserunits($id = null){
		if(!empty($id)){
			return \DB::table('user_unit')
				->leftJoin('unit', 'unit.id_unit', '=', 'user_unit.id_unit')
				->leftJoin('unit as pos_unit', 'pos_unit.id_unit', '=', 'user_unit.id_position')
				->select(
					'user_unit.*',
					'unit.unit_name',
					'unit.unit_code',
					'pos_unit.unit_name as position_name',
					'pos_unit.unit_code as position_code'
				)
				->where('user_unit.id_user', $id)
				->get();
		}
		return collect();
	}

	function saveuserunit($data){
		return \DB::table('user_unit')->insert($data);
	}

	function syncUserUnits($userId, $units, $positions = [], $startDates = [], $endDates = []){
		// Pengguna dan unit/posisi harus milik tenant aktif
		if(!\App\Support\Tenancy\TenantScope::table('tb_users')->where('id', $userId)->exists())
			return 0;

		\DB::table('user_unit')->where('id_user', $userId)->delete();

		$unitsArr = is_array($units) ? $units : [$units];
		$posArr   = is_array($positions) ? $positions : [$positions];
		$startArr = is_array($startDates) ? $startDates : [$startDates];
		$endArr   = is_array($endDates) ? $endDates : [$endDates];

		$ownedUnits = \App\Support\Tenancy\TenantScope::table('unit')
			->whereIn('id_unit', array_filter(array_merge($unitsArr, $posArr)))
			->pluck('id_unit')->map(fn ($v) => (int) $v)->all();
		$owned = fn ($v) => !empty($v) && in_array((int) $v, $ownedUnits, true);

		$inserted = 0;
		foreach ($unitsArr as $idx => $unitId) {
			$posId     = $posArr[$idx] ?? null;
			$unitId    = $owned($unitId) ? $unitId : null;
			$posId     = $owned($posId) ? $posId : null;
			$startDate = !empty($startArr[$idx]) ? $startArr[$idx] : now();
			$endDate   = !empty($endArr[$idx]) ? $endArr[$idx] : null;

			if (!empty($unitId) || !empty($posId)) {
				\DB::table('user_unit')->insert([
					'id_user'     => $userId,
					'id_unit'     => !empty($unitId) ? $unitId : null,
					'id_position' => !empty($posId) ? $posId : null,
					'start_date'  => $startDate,
					'end_date'    => $endDate,
				]);
				$inserted++;
			}
		}

		return $inserted;
	}

	function updateusername($id, $data){
		unset($data['id_unit'], $data['id_position'], $data['start_date'], $data['end_date']);
		if (empty($data)) {
			return [];
		}
		$query = (array)\App\Support\Tenancy\TenantScope::table('tb_users')
		->where('id', $id)
		->update(\App\Support\Tenancy\TenantScope::guardForUpdate('tb_users', $data));

		return $query;
	}

	function updateuserunit($id, $data){
		
		$query = (array)\DB::table('user_unit')
		->where('id_user', $id)
		->update($data);

		return $query;
	}

	// start Users datatables

    var $column_order = array(null, 'unit_name', 'name', 'username', 'first_name', 'last_name', 'email'); 
    var $column_search = array('username', 'first_name', 'last_name', 'email', 'unit.unit_name', 'nik'); 
    var $order = array('tb_users.id', 'asc');

    private function _get_datatables_query($unit = null) {
        $order = request()->input('order');
        if (!empty($order) && isset($order[0]['column'], $order[0]['dir'])) {
            $field  = $this->column_order[$order[0]['column']] ?? $this->order[0];
            $method = $order[0]['dir'];
        } else {
            $field  = $this->order[0];
            $method = $this->order[1];
        }

        $searchValue = request()->input('search.value', '');

        $query = \App\Support\Tenancy\TenantScope::table('tb_users')
            ->select(
                'tb_users.id',
                'tb_users.username',
                'tb_users.first_name',
                'tb_users.last_name',
                'tb_users.email',
                \DB::raw('GROUP_CONCAT(DISTINCT tb_groups.name SEPARATOR ", ") as name'),
                'tb_users.active',
                \DB::raw('GROUP_CONCAT(DISTINCT unit.unit_name SEPARATOR ", ") as unit_name')
            )
            ->leftJoin('tb_users_groups', 'tb_users_groups.user_id', '=', 'tb_users.id')
            ->leftJoin('tb_groups', 'tb_groups.group_id', '=', 'tb_users_groups.group_id')
            ->leftJoin('user_unit', 'user_unit.id_user', '=', 'tb_users.id')
            ->leftJoin('unit', 'unit.id_unit', '=', 'user_unit.id_unit')
            ->where(function ($q) {
                $q->where('tb_users_groups.group_id', '!=', 1)
                  ->orWhereNull('tb_users_groups.group_id');
            })
            ->groupBy(
                'tb_users.id',
                'tb_users.username',
                'tb_users.first_name',
                'tb_users.last_name',
                'tb_users.email',
                'tb_users.active'
            )
            ->orderBy($field, $method)
            ->where(function ($query) use ($searchValue) {
                for ($i = 0; $i <= count($this->column_search) - 1; $i++) {
                    if ($i == 0) {
                        $query->where($this->column_search[$i], 'like', '%' . $searchValue . '%');
                    } else {
                        $query->orWhere($this->column_search[$i], 'like', '%' . $searchValue . '%');
                    }
                }
            });

        if (!empty($unit)) {
            $query->where('user_unit.id_unit', $unit);
        }

        return $query;
    }

    function get_datatables($unit = null) {
        $query = $this->_get_datatables_query($unit);
        $start = intval(request()->input('start', 0));
        $length = intval(request()->input('length', 10));

        if ($length > 0) {
            $query->skip($start)->take($length);
        }

        return $query->get();
    }

    function count_filtered($unit = null) {
        $query = $this->_get_datatables_query($unit);
        return $query->get()->count();
    }

    function count_ajax($unit = null) { 
        $query = \App\Support\Tenancy\TenantScope::table('tb_users')
            ->leftJoin('tb_users_groups', 'tb_users_groups.user_id', '=', 'tb_users.id')
            ->leftJoin('user_unit', 'user_unit.id_user', '=', 'tb_users.id')
            ->where(function ($q) {
                $q->where('tb_users_groups.group_id', '!=', 1)
                  ->orWhereNull('tb_users_groups.group_id');
            });

        if (!empty($unit)) {
            $query->where('user_unit.id_unit', $unit);
        }

        return $query->distinct('tb_users.id')->count('tb_users.id');
    }
    // end Users datatables
	
	function deluser($id){
		// Hanya pengguna milik tenant aktif yang boleh dihapus
		if(!\App\Support\Tenancy\TenantScope::table('tb_users')->where('id', $id)->exists())
			return 0;
		\DB::table('user_unit')->where('id_user', $id)->delete();
		\DB::table('tb_users_groups')->where('user_id', $id)->delete();
		$query = \DB::table('tb_users')
			->where('id',$id)
			->delete();
		return $query;
	}

	function getpositionbycode($code){
		return \App\Support\Tenancy\TenantScope::table('unit')
			->where('unit.unit_code', 'like', '%'.$code.'%')
			->get();
	}

	public function groups()
	{
		return $this->belongsToMany(\App\Models\Core\Groups::class, 'tb_users_groups', 'user_id', 'group_id');
	}

	public function getGroupIdAttribute()
	{
		if (isset($this->attributes['group_id'])) {
			return $this->attributes['group_id'];
		}
		$group = \DB::table('tb_users_groups')
			->where('user_id', $this->id)
			->first();
		return $group ? $group->group_id : null;
	}

	public function getIdUnitAttribute()
	{
		if (isset($this->attributes['id_unit'])) {
			return $this->attributes['id_unit'];
		}
		$unit = \DB::table('user_unit')
			->where('id_user', $this->id)
			->first();
		return $unit ? $unit->id_unit : null;
	}

	public function getIdPositionAttribute()
	{
		if (isset($this->attributes['id_position'])) {
			return $this->attributes['id_position'];
		}
		$unit = \DB::table('user_unit')
			->where('id_user', $this->id)
			->first();
		return $unit ? $unit->id_position : null;
	}

	public function insertRow($data, $id)
	{
		$group_id = null;
		if (isset($data['group_id'])) {
			$group_id = $data['group_id'];
			unset($data['group_id']);
		}

		unset($data['id_unit'], $data['id_position'], $data['start_date'], $data['end_date']);

		$userId = parent::insertRow($data, $id);

		if ($group_id !== null) {
			\DB::table('tb_users_groups')->where('user_id', $userId)->delete();
			if (is_array($group_id)) {
				foreach ($group_id as $gid) {
					if ($gid) {
						\DB::table('tb_users_groups')->insert([
							'user_id' => $userId,
							'group_id' => $gid
						]);
					}
				}
			} elseif ($group_id) {
				\DB::table('tb_users_groups')->insert([
					'user_id' => $userId,
					'group_id' => $group_id
				]);
			}
		}

		return $userId;
	}

	public static function getColumnTable($table)
	{
		$columns = parent::getColumnTable($table);
		$columns['group_id'] = '';
		$columns['id_unit'] = '';
		$columns['id_position'] = '';
		return $columns;
	}

}
