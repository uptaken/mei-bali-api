<?php
namespace App\Http\Controllers\Helper;

use Illuminate\Http\Request;
use Carbon\Carbon;

class LocaleHelper{
  private $arr = [
    "en" => "English", "zh" => "Chinese",
  ];

  public function get_arr_locale(){

    return $this->arr;
  }
}
