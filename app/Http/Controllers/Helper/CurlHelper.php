<?php
namespace App\Http\Controllers\Helper;

use Illuminate\Http\Request;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Carbon\CarbonInterval;
use Auth;
use Curl;

use App\Http\Controllers\Helper\IDHelper;

use App\Models\CurlLog;

class CurlHelper{
  public $id_helper;

  public function __construct(){
    // $this->base = new BaseController();
    $this->id_helper = new IDHelper();
  }

  public function request($url, $arr_header, $arr_data = [], $method = "post", $contentType = 'application/json'){
    $response = Curl::to($url)
      ->withContentType($contentType)
      ->withHeaders($arr_header)
      ->withTimeout(3600)
      ->withConnectTimeout(10)
      ->withData($arr_data);

    if($contentType == 'application/json')
      $response = $response->withContentType('application/json')->asJson(true);
    else
      $response = $response->withContentType($contentType);

    if($method == "post")
      $response = $response->post();
    else if($method == "get")
      $response = $response->get();
    else if($method == "put")
      $response = $response->put();

    // $this->insert_log($method, $url, $arr_header, $arr_data, $response);

    return $contentType == 'application/json' ? $response : json_decode($response, true);
  }

  private function insert_log($method, $url, $arr_header, $arr_data, $response){
    $data = new CurlLog();
    try{
      $data->method = $method;
      $data->url = $url;
      $data->header = json_encode($arr_header);
      $data->request = json_encode($arr_data);
      $data->response = json_encode($response);
      $data->save();
    } catch(Exception $e) {
      $this->insert_log($method, $url, $arr_header, $arr_data, $response);
    }
  }
}
