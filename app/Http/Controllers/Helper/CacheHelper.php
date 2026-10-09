<?php
namespace App\Http\Controllers\Helper;

use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Cache;
use Carbon\Carbon;

use App\Http\Controllers\BaseController;
use App\Http\Controllers\Helper\RelationshipHelper;

class CacheHelper{
	private $relationship_helper = null;

	public function __construct(){
		$this->relationship_helper = new RelationshipHelper();
	}

	public function clean_query($arrTemp){
		// $arrTemp1 = clone $arrTemp;
		if(isset($arrTemp['page']) && isset($arrTemp['num_data']) && isset($arrTemp['start']) && isset($arrTemp['length'])){
			unset($arrTemp['page']);
			unset($arrTemp['num_data']);
		}

		if(!isset($arrTemp['page']) && !isset($arrTemp['num_data']) && isset($arrTemp['start']) && isset($arrTemp['length'])){
			$page = $arrTemp['start'] / $arrTemp['length'];
			$arrTemp['page'] = $page + 1;
			$arrTemp['num_data'] = $arrTemp['length'];
		}

		unset($arrTemp['rnd']);
		unset($arrTemp['_']);
		unset($arrTemp['columns']);
		// unset($arrTemp['search']);
		unset($arrTemp['order']);
		unset($arrTemp['rel_type2']);

		return $arrTemp;
	}

	public function get_query($tag, $request, $group = []){
		$arrTemp = null;
		$arrQuery = $request instanceof Request ? $request->all() : $request;
		$arrQuery = $this->clean_query($arrQuery);

		// if($tag == 'event_category_sport_category_venue')
		// 	dd($tag.'-'.json_encode($arrQuery));
		Cache::flush();
		// dd($tag.'-'.json_encode($arrQuery));
		if(Cache::tags($group)->has($tag.'-'.json_encode($arrQuery)))
			$arrTemp = Cache::tags($group)->get($tag.'-'.json_encode($arrQuery));

		return $arrTemp;
	}

	public function save_query($arr, $tag, $request, $group = [], $custom_time = null){
		$arrQuery = $request instanceof Request ? $request->all() : $request;
		// $arrQuery = [];
		$arrQuery = $this->clean_query($arrQuery);
		// dd($tag.'-'.json_encode($arrQuery));

		// if($tag == 'event_category_sport_category_venue')
		// 	dd($tag.'-'.json_encode($arrQuery));

		if(isset($custom_time) && $custom_time == 0)
			Cache::forever($tag.'-'.json_encode($arrQuery), $arr);
		else
			Cache::tags($group)->put(
				$tag.'-'.json_encode($arrQuery),
				$arr,
				Carbon::now()->addSeconds(
					$custom_time ? $custom_time : env('CACHE_TIME', 10)
				)
			);


	}

	public function get_key($data, $func_name, $request = null, $tag = null, $custom_time = null, $groupTag = []){
		$temp = $data;
		$tempTag = !empty($tag) ? $tag : $data->id;
		$cacheKey = $func_name.'-'.$tempTag;

		$cache = Cache::tags($groupTag);


		if(!$cache->has($cacheKey)){
			if(!is_array($data) && !($data instanceof Collection) && !empty($data->id)){
				// if(!empty($func_name))
				// 	$this->relationship_helper->{$func_name}($data, $request);
				$data->rel_type = $request->rel_type;
			}
			if($custom_time == 0)
				$cache->forever($cacheKey, $data);
			else
				$cache->put(
					$cacheKey, $data,
					Carbon::now()->addSeconds(
						$custom_time ? $custom_time : env('CACHE_TIME', 10)
					)
				);
		}
		else{
			// $temp = !empty($cache->get($cacheKey)) ? $cache->get($cacheKey) : $temp;

			if((!is_array($data) && !($data instanceof Collection) && !empty($data->id) && $temp->rel_type == $this->relationship_helper->request_simple_str) || is_array($data)){
				if(!is_array($data) && !($data instanceof Collection) && !empty($data->id)){
					// $this->relationship_helper->{$func_name}($data, $request);
					$data->rel_type = $request->rel_type;
				}

				if($custom_time == 0)
					$cache->forever($cacheKey, $data);
				else
					$cache->put(
						$cacheKey, $data,
						Carbon::now()->addSeconds(
							$custom_time ? $custom_time : env('CACHE_TIME', 10)
						)
					);

				// $temp = $cache->get($cacheKey);
			}
		}

		return $temp;
	}

	public function update_key($data, $func_name, $request = null){
		$this->relationship_helper->{$func_name}($data, $request);
		Cache::put($func_name.'-'.$data->id, $data, Carbon::now()->addSeconds(env('CACHE_TIME', 10)));
	}

	public function reset_key($groupTag = []){
		Cache::tags($groupTag)->flush();
	}

}
