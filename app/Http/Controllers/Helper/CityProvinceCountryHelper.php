<?php
namespace App\Http\Controllers\Helper;

use Illuminate\Http\Request;
use Carbon\Carbon;
use Curl;

use App\Http\Controllers\BaseController;
use App\Http\Controllers\Helper\CurlHelper;

use App\Models\Country;
use App\Models\Province;
use App\Models\City;

class CityProvinceCountryHelper extends BaseController{
  public function import_country(){
    $curl_helper = new CurlHelper();
    $page = 1;
    do{
      $response = $curl_helper->request($this->url_city_province_backup.'/api/country?page='.$page, [
        "Content-Type" => "application/json",
      ], [], 'get');

        // dd($response);
      foreach($response['data']['data'] as $data){
        $country = Country::find($data['id']);
        if(empty($country)){
          $country = new Country();
          $country->id = $data['id'];
        }
        $country->name = $data['name'];
        $country->save();
      }

      $page++;
    }while(count($response['data']['data']) > 0);
  }

  public function import_province(){
    $curl_helper = new CurlHelper();
    $page = 1;
    do{
      $response = $curl_helper->request($this->url_city_province_backup.'/api/province?page='.$page, [
        "Content-Type" => "application/json",
      ], [], 'get');

      foreach($response['data']['data'] as $data){
        $country = Country::where('name', '=', $data["country_name"])->first();

        $province = Province::find($data['id']);
        if(empty($province)){
          $province = new Province();
          $province->id = $data['id'];
        }
        $province->name = $data['name'];
        $province->country_id = $country->id;
        $province->save();
      }

      $page++;
    }while(count($response['data']['data']) > 0);
  }

  public function import_city($province = null){
    $curl_helper = new CurlHelper();
    $page = 1;
    do{
      $response = $curl_helper->request($this->url_city_province_backup.'/api/city?page='.$page, [
        "Content-Type" => "application/json",
      ], [], 'get');

      foreach($response['data']['data'] as $data){
        $province = Province::where('name', '=', $data['province_name'])->first();

        $city = City::find($data['id']);
        if(empty($city)){
          $city = new City();
          $city->id = $data['id'];
        }
        $city->name = $data['name'];
        $city->province_id = $province->id;
        $city->save();
      }

      $page++;
    }while(count($response['data']['data']) > 0);
  }
}
