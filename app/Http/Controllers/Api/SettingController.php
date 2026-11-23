<?php
namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use Carbon\Carbon;

use App\Http\Controllers\BaseController;

use App\Models\Setting;

class SettingController extends BaseController{
	private $arr_header = [
		[
			"id" => "name",
			"column" => "country.name",
			"name" => "general.name",
			"data_type" => "string",
		],
	];

	public function index(Request $request){
		$arr = $this->manage_where($request);

		return $this->get_data_helper->return_data($request, [
			'status' => 'success',
			'data' => !empty($request->id) || !empty($request->key) ? $arr[0] : $arr,
		]);
	}

	private function manage_where($request, $type = 'index'){
		$this->arr_header = $this->get_data_helper->manage_header($request, $this->arr_header);

		$arr = new Setting();

		if(!empty($request->id))
			$arr = $arr->where('id', '=', $request->id);

		if(!empty($request->key))
			$arr = $arr->where('key', 'like', $request->key);

		if(!empty($request->arr_key)){
			$arr_key = json_decode($request->arr_key, true);
			$arr = $arr->where(function($where) use($arr_key){
				foreach($arr_key as $key)
					$where = $where->orWhere('key', '=', $key);
			});
		}

		$arr = $this->get_data_helper->manage_search_sort($request, $this->arr_header, $arr);

		return $this->get_data_helper->manage_get_data($arr, $type, $request);
	}

	public function get_all(Request $request){
		$arr = $this->manage_where($request, 'all');

		return $this->get_data_helper->return_data($request, [
			'status' => 'success',
			'data' => !empty($request->id) ? $arr[0] : $arr,
		]);
	}
}
