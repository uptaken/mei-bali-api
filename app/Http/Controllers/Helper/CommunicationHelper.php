<?php
namespace App\Http\Controllers\Helper;

use Mail;
use Curl;
use Auth;
use Carbon\Carbon;

use App\Models\FirebaseToken;
use App\Models\User;
use App\Models\Notification;
use App\Models\Todo;

use App\Http\Controllers\BaseController;
use App\Http\Controllers\Helper\IDHelper;

// use App\Events\SendNotificationEvent;

class CommunicationHelper{
  // private $firebase_token = "AAAAS176S-8:APA91bH76gX2ZLMASEvsFFak4WGuw1hZwivAz96P4mxVkIRxDbWIYW3idSbqjNbNShhdNjYNRuRZFxh9Un-Ql_ZYuAD093q_5IjqQgRAizSzezAvNZYKxJqe_guVui7kbqbf81GXZieB";
  private $firebase_token = "AAAAnAE2Krs:APA91bH1VDh35P62F3o-rBFmoEVkosn3PPwpauZKl5nF2wbAdi5pDvkA0Adh-Ftk6-FSGRLdli6o6yFCSEyWPnYP5nOF8KsxOAWg_NZxIX6b9GJwHEURwcBlGqY6FDNH-6ZFjHKP4xvA";
  private $app_name = '';
  private $url_frontend = "";
  private $url_admin = "";

  public function send_push_notif($user, $title, $body, $payload = [], $is_saved_db = true){
    $id_helper = new IDHelper();
    $base = new BaseController();

    if($is_saved_db){
      $notification = new Notification();
      $notification->id = $id_helper->generate_new_id_with_date('NOTIFICATION', new Notification());
      $notification->user_id = $user->id;
      $notification->title = $title;
      $notification->body = $body;
      $notification->data = json_encode($payload);
      $notification->save();
    }

    // SendNotificationEvent::dispatch($title, $body, $user);

    $page = 1;
    do{
      $offset = ($page - 1) * $base->num_data;
      $arr_firebase_token = FirebaseToken::where('user_id', '=', $user->id)
        ->offset($offset)
        ->limit($base->num_data)
        ->get();


      foreach($arr_firebase_token as $firebase_token){
        $data = [
          'to' => $firebase_token->token,
          "priority" => "high",
          'notification' => [
            "title" => $title,
            "body" => strip_tags($body),
            "sound" => "default",
            "priority" => "high",
          ],
          'data' => [
            "title" => $title,
            "body" => strip_tags($body),
            "payload" => $payload,
            "priority" => "high",
          ]
        ];

        $curl_helper = new CurlHelper();
        $response = $curl_helper->request('https://fcm.googleapis.com/fcm/send', [
          'Authorization: key='.$this->firebase_token,
        ], $data);
      }
      $page++;
    }while(count($arr_firebase_token) > 0);
  }
}
