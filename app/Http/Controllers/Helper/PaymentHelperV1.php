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
use App\Http\Controllers\Helper\StringHelper;

class PaymentHelper{
	private $payment_secret_key = '';
	// private $payment_secret_key = 'Mid-server-JPJ1Ugyb98E0QXjujRcCjdRj';
	// private $payment_secret_key = 'SB-Mid-server-rGNprY78l1b70V0hfymQeBk9';
	private $payment_authorization = '';
	// private $payment_authorization = 'Basic TWlkLXNlcnZlci1KUEoxVWd5Yjk4RTBRWGp1alJjQ2pkUmo6';
	// private $payment_authorization = 'Basic U0ItTWlkLXNlcnZlci1yR05wclk3OGwxYjcwVjBoZnltUWVCazk6';
	private $expired_interval = 1;
	private $host_url = '';
	private $base_host_url = '';
	private $snap_url = '';
	// private $host_url = 'https://api.midtrans.com/v2';
	// private $host_url = 'https://api.sandbox.midtrans.com/v2';

	public $title_prefix = 'Payment order ID ';

	public function __construct(){
		$this->payment_secret_key = env('MIDTRANS_SECRET_KEY', '');
		$this->payment_authorization = env('MIDTRANS_AUTHORIZATION', '');
		$this->host_url = env('MIDTRANS_HOST_URL', '');
		$this->base_host_url = env('MIDTRANS_BASE_HOST_URL', '');
		$this->snap_url = env('MIDTRANS_SNAP_HOST_URL', '');
	}

	public function check_order($order){
		$curl_helper = new CurlHelper();

		$response = $curl_helper->request($this->host_url.'/'.$order->transaction_id.'/status', [
			"Authorization" => $this->payment_authorization,
		], [], 'get');

		return $response;
	}

	public function create_va($order){
		$curl_helper = new CurlHelper();
		$expired_date = Carbon::now()->addDays(1);

		$arr_name = explode(' ', $order->user->name);
		$first_name = '';
		foreach($arr_name as $key => $name){
			if($key < count($arr_name) - 1)
				$first_name .= $name . ' ';
		}
		$last_name = $arr_name[count($arr_name) - 1];

		$response = $curl_helper->request($this->host_url.'/charge', [
			"Authorization" => $this->payment_authorization,
			"Accept" => "application/json",
      "Content-Type" => "application/json",
		], [
			"payment_type" => "bank_transfer",
			"customer_details" => [
				"email" => $order->user->email,
				"first_name" => $first_name,
				"last_name" => $last_name,
				"phone" => str_replace('+62', '0', $order->user->phone),
			],
			"transaction_details" => [
				"order_id" => $this->title_prefix.$order->id,
				"gross_amount" => (int) $order->total_price,
			],
			"bank_transfer" => [
				"bank" => $order->payment_method->code,
			],
		]);

		if(!empty($response["va_numbers"])){
			$order->va_no = $response["va_numbers"][0]["va_number"];
			$order->payment_expired_at = $expired_date;
			$order->transaction_id = $response['transaction_id'];
			$order->save();
		}
		return $response;
	}

	public function create_snap_transaction($order){
		$base = new BaseController();
		$curl_helper = new CurlHelper();
		$expired_date = Carbon::now()->addDays(1);

		$arr_detail = [];
		foreach($order->detail as $detail)
			array_push($arr_detail, [
				"id" => $detail->id,
				"price" => (int) $detail->price,
				"quantity" => (int) $detail->amount,
				"name" => $detail->detail,
			]);
		array_push($arr_detail, [
			"id" => 'service_charge',
			"price" => $order->admin_fee,
			"quantity" => 1,
			"name" => 'Service Charge',
		]);

		$arr_name = explode(' ', $order->user->name);
		$first_name = '';
		foreach($arr_name as $key => $name){
			if($key < count($arr_name) - 1)
				$first_name .= $name . ' ';
		}
		$last_name = $arr_name[count($arr_name) - 1];

		$arr_enabled_payment = [];
		array_push($arr_enabled_payment, $order->payment_method->channel);

		$response = $curl_helper->request($this->snap_url, [
			"Authorization" => $this->payment_authorization,
			"Accept" => "application/json",
      "Content-Type" => "application/json",
		], [
			"payment_type" => "bank_transfer",
			"enabled_payments" => $arr_enabled_payment,
			"customer_details" => [
				"email" => $order->user->email,
				"first_name" => $first_name,
				"last_name" => $last_name,
				"phone" => str_replace('+62', '0', $order->user->phone),
			],
			"item_details" => $arr_detail,
			"transaction_details" => [
				"order_id" => $order->id,
				"gross_amount" => (int) $order->total_price,
			],
			"bank_transfer" => [
				"bank" => $order->payment_method->code,
			],
			// "callbacks" => [
			// 	"finish"  => "http://localhost:8011/registration/success",
			// 	"error" => "http://localhost:8011/registration/success",
			// ],
		]);

		if(!empty($response["redirect_url"])){
			$order->url_payment = $response["redirect_url"];
			$order->payment_expired_at = $expired_date;
			// $order->transaction_id = $response['transaction_id'];
			$order->save();
		}

		$response["type"] = "snap";
		return $response;
	}

	public function create_mandiri($order){
		$curl_helper = new CurlHelper();
		$string_helper = new StringHelper();
		$expired_date = Carbon::now()->addDays(1);

		$arr_detail = [];
		$is_team = false;
		$is_match = false;
		foreach($order->detail as $detail){
			if($detail->detail == "Registration")
				$is_team = true;
			else if(preg_match("/match/i", $detail->detail) == 1)
				$is_match = true;

			array_push($arr_detail, [
				"id" => $detail->id,
				"price" => (int) $detail->price,
				"quantity" => (int) $detail->amount,
				"name" => $detail->detail,
			]);
		}

		array_push($arr_detail, [
			"id" => 'service_charge',
			"price" => $order->admin_fee,
			"quantity" => 1,
			"name" => 'Service Charge',
		]);

		$arr_name = explode(' ', $order->user->name);
		$first_name = '';
		foreach($arr_name as $key => $name){
			if($key < count($arr_name) - 1)
				$first_name .= $name . ' ';
		}
		$last_name = $arr_name[count($arr_name) - 1];

		$response = $curl_helper->request($this->host_url.'/charge', [
			"Authorization" => $this->payment_authorization,
		], [
			"payment_type" => "echannel",
			"transaction_details" => [
				"order_id" => $this->title_prefix.$order->id,
				"gross_amount" => (int) $order->total_price,
			],
			"item_details" => $arr_detail,
			"customer_details" => [
				"first_name" => $first_name,
				"last_name" => $last_name,
				"email" => $order->user->email,
				"phone" => str_replace('+62', '0', $order->user->phone),
			],
			"echannel" => [
				"bill_info1" => 'Payment for:',
				"bill_info2" => ($is_team ? 'Team Payment' : '').($is_team && $is_match ? ' and' : '').($is_match ? ' Match Payment' : ''),
				"bill_key" => $string_helper->generateOTP(),
			],
		]);

		if(!empty($response["biller_code"])){
			$order->bill_key = $response["bill_key"];
			$order->biller_code = $response["biller_code"];
			$order->payment_expired_at = $expired_date;
			$order->transaction_id = $response['transaction_id'];
			$order->save();
		}
		return $response;
	}

	public function create_gopay($order){
		$curl_helper = new CurlHelper();
		$expired_date = Carbon::now()->addMinutes(5);

		$arr_detail = [];
		foreach($order->detail as $detail)
			array_push($arr_detail, [
				"id" => $detail->id,
				"price" => (int) $detail->price,
				"quantity" => (int) $detail->amount,
				"name" => $detail->detail,
			]);
		array_push($arr_detail, [
			"id" => 'service_charge',
			"price" => $order->admin_fee,
			"quantity" => 1,
			"name" => 'Service Charge',
		]);

		$arr_name = explode(' ', $order->user->name);
		$first_name = '';
		foreach($arr_name as $key => $name){
			if($key < count($arr_name) - 1)
				$first_name .= $name . ' ';
		}
		$last_name = $arr_name[count($arr_name) - 1];

		$response = $curl_helper->request($this->host_url.'/charge', [
			"Authorization" => $this->payment_authorization,
		], [
			"payment_type" => "gopay",
			"transaction_details" => [
				"order_id" => $this->title_prefix.$order->id,
				"gross_amount" => (int) $order->total_price,
			],
			"item_details" => $arr_detail,
			"customer_details" => [
				"first_name" => $first_name,
				"last_name" => $last_name,
				"email" => $order->user->email,
				"phone" => str_replace('+62', '0', $order->user->phone),
			],
			"gopay" => [
				"enable_callback" => true,
				"callback_url" => url('/payment/callback').'?id='.$order->id,
			],
		]);

		if(!empty($response["actions"])){
			$link = [];
			foreach($response["actions"] as $action){
				if($action['name'] == 'deeplink-redirect'){
					$link = $action;
					break;
				}
			}
			$order->url_payment = $link["url"];
			$order->payment_expired_at = $expired_date;
			$order->transaction_id = $response['transaction_id'];
			$order->save();
		}
		return $response;
	}

	public function create_shopeepay($order){
		$curl_helper = new CurlHelper();
		$expired_date = Carbon::now()->addMinutes(5);

		$arr_detail = [];
		foreach($order->detail as $detail)
			array_push($arr_detail, [
				"id" => $detail->id,
				"price" => (int) $detail->price,
				"quantity" => (int) $detail->amount,
				"name" => $detail->detail,
			]);
		array_push($arr_detail, [
			"id" => 'service_charge',
			"price" => $order->admin_fee,
			"quantity" => 1,
			"name" => 'Service Charge',
		]);

		$arr_name = explode(' ', $order->user->name);
		$first_name = '';
		foreach($arr_name as $key => $name){
			if($key < count($arr_name) - 1)
				$first_name .= $name . ' ';
		}
		$last_name = $arr_name[count($arr_name) - 1];

		$response = $curl_helper->request($this->host_url.'/charge', [
			"Authorization" => $this->payment_authorization,
		], [
			"payment_type" => "shopeepay",
			"transaction_details" => [
				"order_id" => $this->title_prefix.$order->id,
				"gross_amount" => (int) $order->total_price,
			],
			"item_details" => $arr_detail,
			"customer_details" => [
				"first_name" => $first_name,
				"last_name" => $last_name,
				"email" => $order->user->email,
				"phone" => str_replace('+62', '0', $order->user->phone),
			],
			"shopeepay" => [
				"callback_url" => url('/payment/callback').'?id='.$order->id,
			],
		]);

		if(!empty($response["actions"])){
			$link = [];
			foreach($response["actions"] as $action){
				if($action['name'] == 'deeplink-redirect'){
					$link = $action;
					break;
				}
			}
			$order->url_payment = $link["url"];
			$order->payment_expired_at = $expired_date;
			$order->transaction_id = $response['transaction_id'];
			$order->save();
		}
		return $response;
	}

	public function create_qris($order){
		$curl_helper = new CurlHelper();
		$expired_date = Carbon::now()->addMinutes(5);

		$arr_detail = [];
		foreach($order->detail as $detail)
			array_push($arr_detail, [
				"id" => $detail->id,
				"price" => (int) $detail->price,
				"quantity" => (int) $detail->amount,
				"name" => $detail->detail,
			]);
		array_push($arr_detail, [
			"id" => 'service_charge',
			"price" => $order->admin_fee,
			"quantity" => 1,
			"name" => 'Service Charge',
		]);

		$arr_name = explode(' ', $order->user->name);
		$first_name = '';
		foreach($arr_name as $key => $name){
			if($key < count($arr_name) - 1)
				$first_name .= $name . ' ';
		}
		$last_name = $arr_name[count($arr_name) - 1];

		$response = $curl_helper->request($this->host_url.'/charge', [
			"Authorization" => $this->payment_authorization,
			"Content-Type" => "application/json",
			"Accept" => "application/json",
		], [
			"payment_type" => "qris",
			"transaction_details" => [
				"order_id" => $this->title_prefix.$order->id,
				"gross_amount" => (int) $order->total_price,
			],
			"item_details" => $arr_detail,
			"customer_details" => [
				"first_name" => $first_name != "" ? $first_name : $last_name,
				"last_name" => $last_name,
				"email" => $order->user->email,
				"phone" => str_replace('+62', '0', $order->user->phone),
			],
			"qris" => [
				"acquirer" => "gopay",
			],
		]);

		if(!empty($response["actions"])){
			$link = [];
			foreach($response["actions"] as $action){
				if($action['name'] == 'generate-qr-code'){
					$link = $action;
					break;
				}
			}
			$order->url_payment = $link["url"];
			$order->payment_expired_at = $expired_date;
			$order->transaction_id = $response['transaction_id'];
			$order->save();
		}
		return $response;
	}

	public function request_to_payment($temp, $request = null){
		$base = new BaseController();
		$payment_controller = new PaymentController();
		$response = null;


		if($this->payment_authorization != ""){
			if($temp->payment_method->data == 'e_wallet'){
				if($temp->payment_method->code == 'gopay')
					$response = $this->create_gopay($temp);
				else if($temp->payment_method->code == 'shopeepay')
					$response = $this->create_shopeepay($temp);
			}
			else if($temp->payment_method->data == 'bill_payment')
				// $response = $this->create_mandiri($temp);
				$response = $this->create_snap_transaction($temp);
			else if($temp->payment_method->data == 'va')
				$response = $this->create_snap_transaction($temp);
			else if($temp->payment_method->data == 'qris')
				// $response = $this->create_new_qris($temp);
				// $response = $this->create_qris($temp);
				$response = $this->create_snap_transaction($temp);
		}
		else{
			$payment_controller->payment_process($temp);
			$response = [
				'status' => '200',
			];
		}

		return $response;
	}
}
