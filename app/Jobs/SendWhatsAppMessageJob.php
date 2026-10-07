<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

use App\Services\WhatsAppService;

use App\Http\Controllers\BaseController;
use App\Http\Controllers\Helper\CurlHelper;

class SendWhatsAppMessageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 30;

    public function __construct(
        public readonly string $phone,
        public readonly string $message,
        public readonly ?string $toName = null,
        public readonly ?Model $related = null,
        public readonly ?int $sentByUserId = null,
    ) {
			$base_controller = new BaseController();
			$curl_helper = new CurlHelper();

			$response = $curl_helper->request($base_controller->wa_url."/client/sendMessage/".$base_controller->wa_session_id, [], [
				"chatId" => str_replace('+', '', str_replace(' ', '', $phone))."@c.us",
				"contentType" => "string",
				"content" => $this->message,
			], 'post');
		}

    public function handle(): void
    {


        // $whatsApp->send($this->phone, $this->message, $this->toName, $this->related, $this->sentByUserId);
    }
}
