<?php
namespace App\Http\Controllers\Helper;

use Illuminate\Http\Request;
use Storage;
use Auth;
use Hash;
use Curl;
use Image;
use Mail;
use Carbon\Carbon;

use App\Http\Controllers\PaymentController;
use App\Http\Controllers\BaseController;

class PaymentHelper{
  // private $payment_secret_key = 'xnd_development_Pa4kra8Nz1Jrf8vDThvaNmd1XSc8XimakS7JltEiujj20QoO9RTrO8mNwvUU5';
  private $payment_secret_key = 'JDJ5JDEzJFUvbm1IUW1Cb2xUckcvdm5WMnh1NC55cHVCclJFdmphRTEvTTVKUHAzV0gxLlkwMkxROUgu';
  // private $payment_authorization = 'Basic eG5kX2RldmVsb3BtZW50X1BhNGtyYThOejFKcmY4dkRUaHZhTm1kMVhTYzhYaW1ha1M3Smx0RWl1amoyMFFvTzlSVHJPOG1Od3ZVVTU6';
  private $payment_authorization = 'Basic SkRKNUpERXpKRlV2Ym0xSVVXMUNiMnhVY2tjdmRtNVdNbmgxTkM1NWNIVkNjbEpGZG1waFJURXZUVFZLVUhBelYwZ3hMbGt3TWt4Uk9VZ3U6';
  private $expired_interval = 1;
  // private $host_url = 'https://bigflip.id/api/v2/pwf';
  private $host_url = 'https://bigflip.id/big_sandbox_api/v2/pwf';

  public $title_prefix = 'Payment order ID ';

  public function create_va($order){
    $curl_helper = new CurlHelper();
    $expired_date = Carbon::now()->addDays(1);

    $response = $curl_helper->request($this->host_url.'/bill', [
      "Authorization" => $this->payment_authorization,
    ], [
      "title" => $this->title_prefix.$order->id,
      "amount" => (int) $order->total_price,
      "type" => "SINGLE",
      "expired_date" => $expired_date->isoFormat('YYYY-MM-DD HH:mm'),
      "redirect_url" => url('/payment/callback'),
      "step" => 3,
      "sender_name" => $order->user->name,
      "sender_email" => $order->user->email,
      "sender_phone_number" => $order->user->phone,
      "sender_address" => $order->user->name,
      "sender_bank" => $order->payment_method->code,
      "sender_bank_type" => 'virtual_account',
    ], 'post', 'application/x-www-form-urlencoded');

    if(!empty($response["bill_payment"])){
      $order->va_no = $response["bill_payment"]["receiver_bank_account"]["account_number"];
      $order->url_payment = $response["payment_url"];
      $order->payment_expired_at = $expired_date;
      $order->save();
    }
  }

  public function create_ewallet($order){
    $curl_helper = new CurlHelper();
    $expired_date = Carbon::now()->addMinutes(5);

    $response = $curl_helper->request($this->host_url.'/bill', [
      "Authorization" => $this->payment_authorization,
      "Content-Type" => 'application/x-www-form-urlencoded',
    ], [
      "title" => $this->title_prefix.$order->id,
      "amount" => (int) $order->total_price,
      "type" => "SINGLE",
      "expired_date" => $expired_date->isoFormat('YYYY-MM-DD HH:mm'),
      "redirect_url" => url('/payment/callback'),
      "step" => 3,
      "sender_name" => $order->user->name,
      "sender_email" => $order->user->email,
      "sender_phone_number" => $order->user->phone,
      "sender_address" => $order->user->name,
      "sender_bank" => $order->payment_method->code,
      "sender_bank_type" => 'wallet_account',
    ], 'post', 'application/x-www-form-urlencoded');

    if(!empty($response["bill_payment"])){
      $order->url_payment = $response["payment_url"];
      $order->payment_expired_at = $expired_date;
      $order->save();
    }
  }

  public function create_qris($order){
    $curl_helper = new CurlHelper();
    $expired_date = Carbon::now()->addMinutes(10);

    $response = $curl_helper->request($this->host_url.'/bill', [
      "Authorization" => $this->payment_authorization,
      "Content-Type" => 'application/x-www-form-urlencoded',
    ], [
      "title" => $this->title_prefix.$order->id,
      "amount" => (int) $order->total_price,
      "type" => "SINGLE",
      "expired_date" => $expired_date->isoFormat('YYYY-MM-DD HH:mm'),
      "redirect_url" => url('/payment/callback'),
      "step" => 3,
      "sender_name" => $order->user->name,
      "sender_email" => $order->user->email,
      "sender_phone_number" => $order->user->phone,
      "sender_address" => $order->user->name,
      "sender_bank" => $order->payment_method->code,
      "sender_bank_type" => 'wallet_account',
    ], 'post', 'application/x-www-form-urlencoded');

    if(!empty($response["bill_payment"])){
      $order->qr_string = $response["bill_payment"]["receiver_bank_account"]["qr_code_data"];
      $order->url_payment = $response["payment_url"];
      $order->payment_expired_at = $expired_date;
      $order->save();
    }
  }

  public function request_to_payment($temp){
    $base = new BaseController();
    $payment_controller = new PaymentController();
    $response = null;

    if($this->payment_authorization != ""){
      if($temp->payment_method->data == 'e_wallet')
        $this->create_ewallet($temp);
      else if($temp->payment_method->data == 'va')
        $this->create_va($temp);
      else if($temp->payment_method->data == 'qris')
        $this->create_qris($temp);
    }
    else{
      $payment_controller->payment_process($temp);
    }

    // return $response;
  }
}
