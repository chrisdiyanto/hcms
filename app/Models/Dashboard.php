<?php namespace App\Models;

use Illuminate\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;

class dashboard extends Sximo  {
	
	public function __construct() {
		parent::__construct();
		
	}

	public static function querySelect(  ){
		
		return "  SELECT risk_name.*, risk_category
		FROM risk_name  
		LEFT JOIN risk_category ON risk_category.id_risk_category = risk_name.id_risk_category
		";
	}	

	public static function queryWhere(  ){
		
		return "  WHERE risk_name.id_risk_name IS NOT NULL ";
	}
	
	public static function queryGroup(){
		return "  ";
	}
	
	// start risk agregat

	var $column_order = array(null, 'risk_name_agregat', 'risk_name_code'); 
	var $column_search = array('risk_name_agregat', 'risk_name_code'); 
	var $order = array('risk_agregat.id_risk_agregat', 'asc');

	private function _get_datatables_query($th) {

		if(!empty($_POST['order'])){
			$field  = $this->column_order[$_POST['order']['0']['column']];
			$method = $_POST['order']['0']['dir'];    
		}else{
			$field  = $this->order[0];
			$method = $this->order[1];
		}

		$query = \DB::table('risk_agregat')
			->select('risk_agregat.*', 'risk_name_code', 'risk_name_agregat')
			->join('risk_name_agregat', 'risk_name_agregat.id_risk_name_agregat', '=', 'risk_agregat.id_risk_name_agregat', 'LEFT')
			->where('tahun', $th)
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

	function get_datatables($th) {
		$query = $this->_get_datatables_query($th);
		$start = @$_POST['start'];
		$lenght = @$_POST['length'];
		
		return $query->skip($start)->take($lenght);

	}

	function count_filtered($th) {
		$query = $this->_get_datatables_query($th);
		
		return $query->count();
	}

	function count_ajax($th) { 
		$query = \DB::table('risk_agregat')
			->select('risk_agregat.*')
			->where('tahun', $th)
			->get()
			->count();
		return $query;
	}
	// end risk agregat

	// start risk unit

	var $column_orderim = array(null, 'risk_name', 'risk_name_code'); 
	var $column_searchim = array('risk_name', 'risk_name_code'); 
	var $orderim = array('risk_unit.id_risk_name', 'asc');

	private function _get_unitdatatables_query($id, $th) {

		if(!empty($_POST['order'])){
			$field  = $this->column_orderim[$_POST['order']['0']['column']];
			$method = $_POST['order']['0']['dir'];    
		}else{
			$field  = $this->orderim[0];
			$method = $this->orderim[1];
		}

		$query = \DB::table('risk_unit_period')
			->select('*', 'risk_name', 'risk_name_code', 'risk_likelihood', 'risk_impact', 'risk_value', 'unit_name')
			->where('risk_unit_period.period', $th)
			->join('risk_unit', 'risk_unit.id_risk_unit', '=', 'risk_unit_period.id_risk_unit', 'LEFT')
			->where('risk_unit.id_unit', $id)
			->join('risk_analysis', 'risk_analysis.id_risk_unit_period', '=', 'risk_unit_period.id_risk_unit_period', 'LEFT')
			->join('risk_name', 'risk_name.id_risk_name', '=', 'risk_unit.id_risk_name', 'LEFT')
			->join('unit', 'unit.id_unit', '=', 'risk_unit.id_unit', 'LEFT')
			->orderBy($field, $method)
			->where(function ($query) {
				for ($i = 0; $i <= count($this->column_searchim)-1; $i++) {
					if($i == 0){
						$query->where($this->column_searchim[$i], 'like', '%'.$_POST['search']['value'].'%');
					}else{
						$query->orwhere($this->column_searchim[$i], 'like', '%'.$_POST['search']['value'].'%');
					}
				}
			})
			->get();

		return $query;

	}

	function get_unitdatatables($id, $th) {
		$query = $this->_get_unitdatatables_query($id, $th);
		$start = @$_POST['start'];
		$lenght = @$_POST['length'];
		
		return $query->skip($start)->take($lenght);

	}

	function count_unitfiltered($id, $th) {
		$query = $this->_get_unitdatatables_query($id, $th);
		
		return $query->count();
	}

	function count_unitajax($id, $th) { 
		$query = \DB::table('risk_unit_period')
			->where('period', $th)
			->join('risk_unit', 'risk_unit.id_risk_unit', '=', 'risk_unit_period.id_risk_unit', 'LEFT')
			->where('risk_unit.id_unit', $id)
			->get()
			->count();
		return $query;
	}
	// end risk unit

	function total_impact($id) { 
		$query = \DB::table('impact')
			->select('impact.*')
			->where('id_risk_name', $id)
			->get()
			->count();
		return $query;
	}

	function update_impact($a, $b, $data){
		$query = \DB::table('impact')
				->where('id_risk_name',$a)
				->where('impact',$b)
				->update($data);
			return $query;
	}

	function currentagregat($id, $bl){
		$query = \DB::table('monitoring_agregat')
				->select('*')
				->where('id_risk_agregat', $id)
				->where('bulan', $bl)
				->first();
		return $query;
	}

	function lastagregat($id){
		$query = \DB::table('monitoring_agregat')
				->select('*')
				->where('id_risk_agregat', $id)
				->orderBy('bulan', 'desc')
				->first();
		return $query;
	}

	function lastriskunit($id){
		$query = \DB::table('monitoring_progress')
				->select('*', 'p_risk_impact as risk_impact', 'p_risk_likelihood as risk_likelihood', 'p_risk_level as risk_level')
				->where('id_risk_unit_period', $id)
				->orderBy('monitoring_period', 'desc')
				->first();
		return $query;
	}

	function currentriskunit($id, $bl){
		$query = \DB::table('monitoring_progress')
				->select('*', 'p_risk_impact as risk_impact', 'p_risk_likelihood as risk_likelihood', 'p_risk_level as risk_level')
				->where('id_risk_unit_period', $id)
				->where('monitoring_period', $bl)
				->first();
		return $query;
	}

	function getpicagregat($id){
		$query = \DB::table('unit_pic_agregat')
				->select('*')
				->where('id_risk_agregat', $id)
				->join('unit', 'unit.id_unit', '=', 'unit_pic_agregat.id_unit', 'LEFT')
				->get()
				->toArray();
		return $query;
	}
	
    function getlastperiodagregat($th){
		$query = \DB::table('risk_agregat')
			->select('bulan')
			->where('monitoring_agregat.tahun', $th)
			->join('monitoring_agregat', 'monitoring_agregat.id_risk_agregat', '=', 'risk_agregat.id_risk_agregat', 'LEFT')
			->orderBy('bulan', 'DESC')
			->first();
		if(!empty($query)){
			return $query->bulan;
		}
    }

	function getlastperiodanalysis($id, $th){
		$query = \DB::table('risk_unit_period')
				->select('monitoring_period')
				->where('risk_unit_period.period', $th)
				->join('risk_unit', 'risk_unit.id_risk_unit', '=', 'risk_unit_period.id_risk_unit', 'LEFT')
				->where('risk_unit.id_unit', $id)
				->join('monitoring_progress', 'monitoring_progress.id_risk_unit_period', '=', 'risk_unit_period.id_risk_unit_period', 'LEFT')
				->orderBy('monitoring_period', 'desc')
				->first();
		if(!empty($query)){
			return $query->monitoring_period;
		}
	}

	function getallrisk($id, $th){
		$query = \DB::table('risk_unit')
				->selectRaw('risk_unit_period.id_risk_unit_period, risk_name_code')
				->where('id_unit', $id)
				->join('risk_unit_period', 'risk_unit_period.id_risk_unit', '=', 'risk_unit.id_risk_unit', 'LEFT')
				->join('risk_name', 'risk_name.id_risk_name', '=', 'risk_unit.id_risk_name', 'LEFT')
				->join('risk_analysis', 'risk_analysis.id_risk_unit_period', '=', 'risk_unit_period.id_risk_unit_period', 'LEFT')
				->where('risk_unit_period.period', $th)
				->get();
		return $query;
	}

	function getriskpromit($id, $th, $bl){
		$query = \DB::table('monitoring_progress')
				->selectRaw('id_risk_unit_period, promit')
				->where('id_risk_unit_period', $id)
				->where('monitoring_period', $bl)
				->first();
		return $query;
	}

	function getallriskprogress($id, $th, $bl, $field = null){
		$query = \DB::table('risk_unit')
				->selectRaw('risk_unit_period.id_risk_unit_period, p_risk_level, count(p_risk_level) AS jml')
				->where('id_unit', $id)
				->join('risk_unit_period', 'risk_unit_period.id_risk_unit', '=', 'risk_unit.id_risk_unit', 'LEFT')
				->where('risk_unit_period.period', $th)
				->join('monitoring_progress', 'monitoring_progress.id_risk_unit_period', '=', 'risk_unit_period.id_risk_unit_period', 'LEFT')
				->where('monitoring_period', $bl)
				->groupBy('p_risk_level')
				->get()->toArray();
		return $query;
	}

	function getallagregatprogress($th, $bl, $field = null){
		$query = \DB::table('monitoring_agregat')
				->selectRaw('monitoring_agregat.id_risk_agregat, risk_level, count(risk_level) AS jml')
				->where('monitoring_agregat.tahun', $th)
				->where('monitoring_agregat.bulan', $bl)
				->groupBy('risk_level')
				->get()->toArray();
		return $query;
	}

// End of Bracket
}