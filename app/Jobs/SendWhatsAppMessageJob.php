<?php

namespace App\Jobs;

use App\Http\Controllers\BaseController;
use App\Http\Controllers\Helper\CurlHelper;
use App\Models\WhatsAppMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use RuntimeException;
use Throwable;

/**
 * Sends one WhatsApp message through the WhatsApp gateway (WA_URL / WA_SESSION_ID) and keeps an audit
 * row in `whatsapp_messages`. The request happens in handle(), so a failing gateway is retried by the
 * queue instead of breaking the request that dispatched the job.
 */
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
    ) {}

    /** WhatsApp wants the number in international form: digits only, with Indonesia's 62 in place of a leading 0. */
    public static function chatId(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone);

        if (str_starts_with($digits, '0')) {
            $digits = '62'.substr($digits, 1);
        }

        return $digits.'@c.us';
    }

    public function handle(CurlHelper $curl): void
    {
        $gateway = new BaseController();

        $response = $curl->request("{$gateway->wa_url}/client/sendMessage/{$gateway->wa_session_id}", [], [
            'chatId' => self::chatId($this->phone),
            'contentType' => 'string',
            'content' => $this->message,
        ], 'post');

        if (! is_array($response) || empty($response['success'])) {
            throw new RuntimeException('Gateway WhatsApp tidak menerima pesan: '.json_encode($response));
        }

        $this->record('sent', $response);
    }

    /** Called by the queue once every retry has failed. */
    public function failed(Throwable $exception): void
    {
        $this->record('failed', ['error' => $exception->getMessage()]);
    }

    private function record(string $status, array $providerResponse): void
    {
        WhatsAppMessage::create([
            'related_type' => $this->related?->getMorphClass(),
            'related_id' => $this->related?->getKey(),
            'sent_by' => $this->sentByUserId,
            'to_phone' => $this->phone,
            'to_name' => $this->toName,
            'message' => $this->message,
            'status' => $status,
            'provider_response' => $providerResponse,
            'sent_at' => $status === 'sent' ? now() : null,
        ]);
    }
}
