<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Storage;
use Auth;
use Hash;
use Curl;
use Image;
use Mail;
use Carbon\Carbon;
use Illuminate\Support\Facades\Validator;

use App\Http\Controllers\Helper\Controller\BaseHelper;

use App\Http\Controllers\Helper\FileHelper;
use App\Http\Controllers\Helper\IDHelper;
use App\Http\Controllers\Helper\StringHelper;
use App\Http\Controllers\Helper\RelationshipHelper;
use App\Http\Controllers\Helper\PaymentHelper;
use App\Http\Controllers\Helper\GetDataHelper;
use App\Http\Controllers\Helper\CommunicationHelper;
use App\Http\Controllers\Helper\SendSMSHelper;

class BaseController extends Controller{
  public $app_name = '';
  public $app_address = '';
  public $app_version = '0.0.0001';
  public $web_admin_name = '';
  public $locale = 'id';
  public $str_length = 5;
  public $num_data = 20;
  public $reset_password_expired = 7;
  public $job_wait_time = 10;
  public $column_base_category = 'base_category_id';
  public $date_format = '%d-%m-%Y';
  public $url_web = '';
  public $url_user_admin = '';
  public $url_admin = '';
  public $max_player_team_name = 2;

	public $cutoff_str = 'Cut-Off';
	public $cutoff_tournament_str = 'Cut-Off + Tournament';

  protected $url_backup = "http://backup-transaction.quantumtri.com";
  protected $url_city_province_backup = "http://city-province.quantumtri.com";
  protected $backup_from = "student_open";
  // protected $url_admin = "https://admin.student-open.com";
  // protected $url_user_admin = "https://user-admin.student-open.com";

  public $wa_url = "";
  public $wa_session_id = "";

  public $file_helper;
  public $id_helper;
  public $string_helper;
  public $relationship_helper;
  public $payment_helper;
  public $get_data_helper;
  public $communication_helper;
  public $send_sms_helper;
  public $base_helper;

  public function __construct(){
    $this->web_admin_name = __('general.app_name');
    $this->app_name = env('APP_NAME', '');

    $this->url_web = env('URL_WEB', '');
		$this->url_user_admin = env('URL_USER_ADMIN', '');
		$this->url_admin = env('URL_ADMIN', '');

    $this->wa_url = env('WA_URL', '');
    $this->wa_session_id = env('WA_SESSION_ID', '');

    $this->file_helper = new FileHelper();
    $this->id_helper = new IDHelper();
    $this->string_helper = new StringHelper();
    $this->relationship_helper = new RelationshipHelper();

    // $this->payment_helper = new PaymentHelper();
    $this->send_sms_helper = new SendSMSHelper();
    $this->base_helper = new BaseHelper();
    $this->get_data_helper = new GetDataHelper($this->num_data);
    $this->communication_helper = new CommunicationHelper($this->app_name);
  }

  protected function manage_per_page($request){
    return !empty($request->per_page) ? $request->per_page : $this->num_data;
  }

  public function processed_to_backup($data, $method = 'add'){
    $response = $curl_helper->request($this->url_backup.'/transaction', [
      "Content-Type" => "application/json",
    ], [
      'data_id' => $data->id,
      'from' => $this->backup_from,
      'data' => $data,
    ], $method == 'add' ? 'post' : 'put');
  }

  protected function manage_validation($request, $arr_condition){
    $validator = Validator::make($request->all(), $arr_condition);
    if($validator->fails())
      return [
        'status' => 'error',
        'message' => $validator->errors()->first(),
      ];

    return null;
  }
}
