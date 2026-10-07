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
  private $payment_secret_key = 'xnd_production_brp7IoEv1OaLRE01ynlBpWGqugKdsFTf1ICKZu6AnTEPOW5DqjdI0aa1B2jbub';
  // private $payment_authorization = 'Basic eG5kX2RldmVsb3BtZW50X1BhNGtyYThOejFKcmY4dkRUaHZhTm1kMVhTYzhYaW1ha1M3Smx0RWl1amoyMFFvTzlSVHJPOG1Od3ZVVTU6';
  private $payment_authorization = 'Basic eG5kX3Byb2R1Y3Rpb25fYnJwN0lvRXYxT2FMUkUwMXlubEJwV0dxdWdLZHNGVGYxSUNLWnU2QW5URVBPVzVEcWpkSTBhYTFCMmpidWI6';
  private $expired_interval = 1;
  private $host_url = 'https://api.xendit.co';

  public function create_va($order){
    $curl_helper = new CurlHelper();

    $response = $curl_helper->request($this->host_url.'/callback_virtual_accounts', [
      "Authorization" => $this->payment_authorization,
    ], [
      "external_id" => $order->id,
      "bank_code" => $order->payment_method->code,
      "name" => !empty($order->user) ? $order->user->name : $order->created_user->name,
      "is_closed" => true,
      "expected_amount" => (int) $order->total_price,
    ]);

    if(!empty($response["account_number"])){
      $expired_date = Carbon::parse($response["expiration_date"])->setTimezone('Asia/Jakarta');

      $order->va_no = $response["account_number"];
      $order->payment_expired_at = $expired_date;
      $order->save();
    }
  }

  public function simulate_payment($order){
    $curl_helper = new CurlHelper();
    
    $response = $curl_helper->request($this->host_url.'/callback_virtual_accounts/external_id='.$order->id.'/simulate_payment', [
      "Authorization" => $this->payment_authorization,
    ], [
      "amount" => $order->total_price,
    ]);
  }

  public function create_credit_card($order){
    $payment_controller = new PaymentController();
    $curl_helper = new CurlHelper();

    $response_token = $curl_helper->request($this->host_url.'/credit_card_charges', [
      "Authorization" => $this->payment_authorization,
    ], [
      "token_id" => $order->credit_card_token_id,
      "external_id" => $order->id,
      "amount" => $order->total_price,
    ]);

    if(!empty($response["status"]) && $response["status"] == "CAPTURED")
      $payment_controller->payment_process($temp);
  }

  public function create_ewallet($order){
    $curl_helper = new CurlHelper();

    $arr = [
      "reference_id" => $order->id,
      "currency" => "IDR",
      "amount" => $order->total_price,
      "checkout_method" => "ONE_TIME_PAYMENT",
      "channel_code" => $order->payment_method->channel,
      "channel_properties" => [],
    ];

    if($order->payment_method->channel == "ID_OVO")
      $arr["channel_properties"] = [
        "mobile_number" => $order->user->phone,
      ];
    else
      $arr["channel_properties"] = [
        "success_redirect_url" => url('/payment/xendit/callback'),
      ];

    $response = $curl_helper->request($this->host_url.'/ewallets/charges', [
      "Authorization" => $this->payment_authorization,
    ], $arr);

    if(!empty($response["actions"])){
      $expired_date = Carbon::now()->addMinutes(5);

      $order->url_payment = $response["actions"]["mobile_deeplink_checkout_url"];
      $order->payment_expired_at = $expired_date;
      $order->save();
    }
  }

  public function create_payment_page($order){
    $curl_helper = new CurlHelper();

    $response = $curl_helper->request($this->host_url.'/v2/invoices', [
      "Authorization" => $this->payment_authorization,
    ], [
      "external_id" => $order->id,
      "amount" => $order->total_price,
      "items" => [
        [
          "name" => "Top up Transaction ".$order->id,
          "quantity" => 1,
          "price" => $order->total_price,
        ]
      ]
    ]);

    if(!empty($response["invoice_url"])){
      $expired_date = Carbon::parse($response["expiry_date"])->setTimezone('Asia/Jakarta');

      $order->url_payment = $response["invoice_url"];
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
      else if($temp->payment_method->data == 'credit_card' && !empty($order->credit_card_token_id) && $order->credit_card_token_id != "")
        $this->create_credit_card($temp);
      else if($temp->payment_method->data == 'xendit')
        $this->create_payment_page($temp);
    }
    else{
      $payment_controller->payment_process($temp);
    }

    // return $response;
  }
}
