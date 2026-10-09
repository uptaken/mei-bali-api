<?php
namespace App\Http\Controllers\Helper;

use Mail;
use Curl;

use App\Http\Controllers\Helper\CurlHelper;

use App\Models\FirebaseToken;
use App\Models\User;

class SendSMSHelper{
  // private $url_citcall = "http://104.199.196.122/gateway/v3";
  // private $api_key = "01c65793b71d14e1fa6609810af1a8b4";

  private $url_vonage = "https://rest.nexmo.com";
  private $organization_vonage = "Master";
  private $api_key = 'd517057c';
  private $api_secret = 'QJjzsevZLVhm0t5D';

  public function send_otp($user, $body, $token){
    $curl_helper = new CurlHelper();

    $response = $curl_helper->request($this->url_vonage.'/sms/json', [
      "Content-Type" => "application/json",
    ], [
      'from' => $this->organization_vonage,
      'to' => $user->phone[0] == '0' ? "+62".substr($user->phone,1) : $user->phone,
      'text' => $body,
      'api_key' => $this->api_key,
      'api_secret' => $this->api_secret,
    ], 'post');

    $user->otp_code = $token;
    // if(!empty($response) && !empty($response['trxid']))
    //   $user->trxid = $response['trxid'];
    $user->save();
  }
}
