<?php
namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use Carbon\Carbon;
use Auth;
use Illuminate\Support\Facades\Http;
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

/**
 * Perantara ke gateway WhatsApp self-hosted (WA_URL + WA_SESSION_ID di .env). Browser tidak pernah
 * bicara langsung ke gateway: semua lewat sini agar alamat/sesi gateway tersembunyi dan butuh login.
 * Dipakai halaman "Template WhatsApp" (scan QR) dan pemeriksaan status sebelum kirim pesan ke supplier.
 */
class WAController extends BaseController{

  /** Webhook dari gateway: meneruskan event (qr/ready/disconnected) ke channel realtime. Belum dipasang di route; halaman memakai polling. */
  public function wa_socket(Request $request){
    NewWAChatEvent::dispatch($request->all());

    return $this->get_data_helper->return_data($request, [
      'status' => 'success',
    ]);
  }

  /** Mulai sesi WhatsApp di gateway (menghasilkan QR bila belum login). */
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

  /** Putuskan lalu mulai ulang sesi — dipakai untuk berganti nomor/akun WhatsApp. */
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

  /** Putuskan sesi WhatsApp tanpa memulai ulang. */
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

  /** QR login dalam bentuk data dari gateway (teks). Untuk gambar gunakan qr_image_session. */
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

  /** The QR as a PNG, proxied so the browser needs neither the gateway address nor its session id. */
  public function qr_image_session(Request $request){
    if(empty($this->wa_url))
      return response()->json(['status' => 'error', 'message' => 'Gateway WhatsApp belum dikonfigurasi'], 503);

    $response = Http::timeout(10)->get($this->wa_url.'/session/qr/'.$this->wa_session_id.'/image');

    if(!$response->successful())
      return response()->json(['status' => 'error', 'message' => 'QR belum tersedia'], 404);

    return response($response->body(), 200, ['Content-Type' => 'image/png', 'Cache-Control' => 'no-store']);
  }

  /** Status sesi; `data.success` bernilai true bila WhatsApp sudah terhubung dan siap kirim. */
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

  /** Info akun WhatsApp yang terhubung (nama, nomor, foto profil). Tanpa error bila sesi belum login. */
  public function get_state(Request $request){
    $curl_helper = new CurlHelper();
    $response = $curl_helper->request($this->wa_url.'/client/getClassInfo/'.$this->wa_session_id, [
      "Content-Type" => "application/json",
		], [], "get");

    $contactId = $response["sessionInfo"]["me"]["_serialized"] ?? null;
    if($contactId){
      $response_prof_pic = $curl_helper->request($this->wa_url.'/client/getProfilePicUrl/'.$this->wa_session_id, [
        "Content-Type" => "application/json",
      ], [
        "contactId" => $contactId,
      ]);
      if(!empty($response_prof_pic["result"]))
        $response["profile_picture"] = $response_prof_pic["result"];
    }

    return $this->get_data_helper->return_data($request, [
      'status' => 'success',
      'data' => $response,
    ]);
  }
}
