<?php
namespace App\Http\Controllers\Helper;

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class GetDataHelper{
  private $num_data = 0;

  public function __construct($num_data){
    $this->num_data = $num_data;
  }

  public function manage_sort($request, $arr){
    if(!empty($request->order) && is_array($request->order)){
      foreach($request->order as $order)
        $arr = $arr->orderBy($request->columns[$order["column"]]["data"], $order['dir']);
    }
    else if(!empty($request->sort)){
      $arr_sort = json_decode($request->sort, true);
      foreach($arr_sort as $sort){
        if($sort['sort'] != '' && $sort['sort'] != 'none')
          $arr = $arr->orderBy($sort['name'], $sort['sort']);
      }
    }

    return $arr;
  }

  public function manage_search($arr_header, $arr){
    foreach($arr_header as $header){
      $arr_split = explode('.', $header['column']);
      if(!empty($header['search']) && $header['search'] != ""){
        if(empty($header['data_type']) || (!empty($header['data_type']) && $header['data_type'] == 'string')){
          if(count($arr_split) > 1)
            $arr = $arr->where($header['column'], 'like', '%'.str_replace(' ','%',$header['search']).'%');
          else
            $arr = $arr->having($header['column'], 'like', '%'.str_replace(' ','%',$header['search']).'%');
        }
        else if(($header['data_type'] == 'number' || $header['data_type'] == 'currency') && is_numeric($header['search'])){
          if(count($arr_split) > 1)
            $arr = $arr->where($header['column'], '=', $header['search']);
          else
            $arr = $arr->having($header['column'], '=', $header['search']);
        }
      }
    }

    return $arr;
  }

  public function manage_global_search($request, $arr_header, $arr){
    if(!empty($request->q))
      $search = $request->q;
    else if(is_array($request->search))
      $search = $request->search["value"];
    else
      $search = $request->search;

    if(is_array($request->search)){
      if(!empty($search)){
        $arr_where = [];
        $arr_having = [];

        foreach($request->columns as $columns){
          if(!empty($columns["name"])){
            $exp = explode('.', $columns["name"]);
            if(count($exp) > 1)
              array_push($arr_where, $columns["name"]);
            else
              array_push($arr_having, $columns["name"]);
          }
        }
				// dd($request->columns);

        $arr = $arr->where(function($where) use($search, $arr_where){
          foreach($arr_where as $where1)
            $where = $where->orWhere($where1, 'like', '%'.str_replace(' ','%',$search).'%');
        });

				// foreach($arr_having as $having1)
				// 	$arr = $arr->groupBy($having1);
        foreach($arr_having as $having1)
          $arr = $arr->orHaving($having1, 'like', '%'.str_replace(' ','%',$search).'%');

        // $arr = $arr->having(function($having) use($search, $arr_having){
        //   foreach($arr_having as $having1)
        //     $having = $having->orHaving($having1, 'like', '%'.str_replace(' ','%',$search).'%');
        // });
      }
    }
    else{
      $arr = $arr->where(function($where) use($search, $arr_header){
        foreach($arr_header as $header){
          $arr_split = explode('.', $header['column']);
          if(count($arr_split) > 1 && !empty($search) && $search != ""){
            if(empty($header['data_type']) || (!empty($header['data_type']) && $header['data_type'] == 'string'))
              $where = $where->orWhere($header['column'], 'like', '%'.str_replace(' ','%',$search).'%');
            else if(($header['data_type'] == 'number' || $header['data_type'] == 'currency') && is_numeric($header['search']))
              $where = $where->orWhere($header['column'], '=', $search);
          }
        }
      });
    }
    // dd($arr->toSql());

    return $arr;
  }

  public function manage_search_sort($request, $arr_header, $arr){
    $arr = $this->manage_search($arr_header, $arr);
    $arr = $this->manage_sort($request, $arr);
    $arr = $this->manage_global_search($request, $arr_header, $arr);
    // dd($arr->getBindings());

    return $arr;
  }

  public function manage_header($request, $arr_header){
    $arr_sort = json_decode($request->sort, true);
    $arr_specific_search = json_decode($request->specific_search, true);

    foreach($arr_header as $key => $header){
      $arr_header[$key]['sort'] = 'none';
      $arr_header[$key]['search'] = '';

      if(!empty($arr_sort)){
        foreach($arr_sort as $sort){
          if($header['id'] == $sort['name']){
            $arr_header[$key]['sort'] = $sort['sort'];
            break;
          }
        }
      }

      if(!empty($arr_specific_search)){
        foreach($arr_specific_search as $specific_search){
          if($header['id'] == $specific_search['name']){
            $arr_header[$key]['search'] = $specific_search['search'];
            break;
          }
        }
      }
    }

    return $arr_header;
  }

  public function return_data($request, $arr = [], $type = 'view', $view = '', $arr_view = null, $total_data = null){
		foreach($arr as $key => $temp){

		}
    // dd($arr);
    $arr_temp = $arr;
    if(isset($arr["data"])){
      if($arr["data"] instanceof LengthAwarePaginator){
        $arr_temp1 = [];
        foreach($arr["data"] as $data){
          array_push($arr_temp1, $data);
        }
        $arr_temp["data"] = $arr_temp1;
        $arr_temp["recordsTotal"] = !empty($total_data) ? $total_data : $arr["data"]->total();
        $arr_temp["recordsFiltered"] = !empty($total_data) ? $total_data : $arr["data"]->total();

        if(!empty($request->header('Type')) && $request->header('Type') == "web"){
          $arr_temp["current_page"] = $arr["data"]->currentPage();
          $arr_temp["total_page"] = $arr["data"]->lastPage();
        }
      }
      else if(is_array($arr["data"]) || $arr["data"] instanceof Collection){
        $arr_temp["data"] = $arr["data"];
        if(!isset($arr_temp["recordsTotal"]))
          $arr_temp["recordsTotal"] = !empty($total_data) ? $total_data : count($arr["data"]);
        if(!isset($arr_temp["recordsFiltered"]))
          $arr_temp["recordsFiltered"] = !empty($total_data) ? $total_data : count($arr["data"]);
      }


    }
    if(!empty($request->draw))
      $arr_temp["draw"] = $request->draw;


    if($request->expectsJson())
      return response()->json($arr_temp);
    else if(!empty($request->return_type) && $request->return_type == 'array')
      return $arr_temp;
    else{
      if($type == 'view')
        return view($view)->with(!empty($arr_view) ? $arr_view : $arr);
      else if($type == 'redirect')
        return redirect($view)->with(!empty($arr_view) ? $arr_view : $arr);
      else if($type == 'back')
        return back()->with(!empty($arr_view) ? $arr_view : $arr);
    }
  }

  public function manage_get_data($arr, $type = 'index', $request = null){
    if($type == 'index'){
      if(isset($request) && isset($request->start) && isset($request->length) && !isset($request->num_data)){
        $page = $request->start / $request->length;
        $request->merge(['page' => $page + 1, 'num_data' => $request->length]);
      }

			// return $arr->limit(isset($request) && isset($request->num_data) ? $request->num_data : $this->num_data)->get();
      return $arr->paginate(isset($request) && isset($request->num_data) ? $request->num_data : $this->num_data)->withQueryString();
    }
		else if($type == 'get_paginate'){
			if(isset($request) && isset($request->start) && isset($request->length) && !isset($request->num_data)){
				$page = $request->start / $request->length;
				$request->merge(['page' => $page + 1, 'num_data' => $request->length]);
			}

			$num_data = isset($request) && isset($request->num_data) ? $request->num_data : $this->num_data;
			$page = !empty($request->page) ? $request->page : 1;
			$offset = ($page - 1) * $num_data;
			return $arr->offset($offset)->limit($num_data)->get();
		}
    else if($type == 'all'){
      if(isset($request) && isset($request->num_data))
        $arr = $arr->limit($request->num_data);
			else
				$arr = $arr->limit(100);

      return $arr->get();
    }
  }
}
