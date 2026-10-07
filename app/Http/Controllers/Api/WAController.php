<?php
namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use Carbon\Carbon;
use Auth;
use PDF;

use App\Http\Controllers\BaseController;
use App\Http\Controllers\CommunicationController;
use App\Http\Controllers\Helper\Controller\HomeHelper;
use App\Http\Controllers\Helper\CurlHelper;
use App\Http\Controllers\User\ConsultantController;

use App\Models\User;
use App\Models\Type;
use App\Models\Event;
use App\Models\RegistrationEvent;
use App\Models\RegistrationEventPlayer;
use App\Models\RegistrationEventCoach;
use App\Models\RegistrationEventMatch;
use App\Models\Order;
use App\Models\Setting;

use App\Events\NewWAChatEvent;

class WAController extends BaseController{

  public function wa_socket(Request $request){
    NewWAChatEvent::dispatch($request->all());

    return $this->get_data_helper->return_data($request, [
      'status' => 'success',
    ]);
  }

  public function start_session(Request $request){
    $curl_helper = new CurlHelper();
    $response = $curl_helper->request($this->wa_url.'/session/start/'.$this->wa_session_id, [
      "Content-Type" => "application/json",
		], [], "get");

    return $this->get_data_helper->return_data($request, [
      'status' => 'success',
      'data' => $response,
    ]);
  }

  public function restart_session(Request $request){
    $curl_helper = new CurlHelper();
    $response = $curl_helper->request($this->wa_url.'/session/terminate/'.$this->wa_session_id, [
      "Content-Type" => "application/json",
		], [], "get");

    $response = $curl_helper->request($this->wa_url.'/session/start/'.$this->wa_session_id, [
      "Content-Type" => "application/json",
		], [], "get");

    return $this->get_data_helper->return_data($request, [
      'status' => 'success',
      'data' => $response,
    ]);
  }

  public function terminate_session(Request $request){
    $curl_helper = new CurlHelper();
    $response = $curl_helper->request($this->wa_url.'/session/terminate/'.$this->wa_session_id, [
      "Content-Type" => "application/json",
		], [], "get");

    return $this->get_data_helper->return_data($request, [
      'status' => 'success',
      'data' => $response,
    ]);
  }

  public function qr_session(Request $request){
    $curl_helper = new CurlHelper();
    $response = $curl_helper->request($this->wa_url.'/session/qr/'.$this->wa_session_id, [
      "Content-Type" => "application/json",
		], [], "get");

    return $this->get_data_helper->return_data($request, [
      'status' => 'success',
      'data' => $response,
    ]);
  }

  public function qr_image_session(Request $request){
    $curl_helper = new CurlHelper();
    $response = $curl_helper->request($this->wa_url.'/session/qr/'.$this->wa_session_id.'/image', [
      "Content-Type" => "application/json",
		], [], "get");

    return $this->get_data_helper->return_data($request, [
      'status' => 'success',
      'data' => $response,
    ]);
  }

  public function status_session(Request $request){
    $curl_helper = new CurlHelper();
    $response = $curl_helper->request($this->wa_url.'/session/status/'.$this->wa_session_id, [
      "Content-Type" => "application/json",
		], [], "get");

    return $this->get_data_helper->return_data($request, [
      'status' => 'success',
      'data' => $response,
    ]);
  }

  public function get_state(Request $request){
    $curl_helper = new CurlHelper();
    $response = $curl_helper->request($this->wa_url.'/client/getClassInfo/'.$this->wa_session_id, [
      "Content-Type" => "application/json",
		], [], "get");

    $response_prof_pic = $curl_helper->request($this->wa_url.'/client/getProfilePicUrl/'.$this->wa_session_id, [
      "Content-Type" => "application/json",
		], [
      "contactId" => $response["sessionInfo"]["me"]["_serialized"],
    ]);
    if(!empty($response_prof_pic["result"]))
      $response["profile_picture"] = $response_prof_pic["result"];

    return $this->get_data_helper->return_data($request, [
      'status' => 'success',
      'data' => $response,
    ]);
  }
}
