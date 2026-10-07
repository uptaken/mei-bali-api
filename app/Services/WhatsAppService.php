<?php

namespace App\Services;

use App\Models\WhatsAppMessage;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Sends WhatsApp messages through Fonnte (https://fonnte.com/apidocs.php) — an Indonesian WA
 * gateway: connect a phone number by scanning a QR code in their dashboard, then send messages
 * against a single API token, no Meta Business verification needed.
 *
 * Every attempt (successful or not) is logged to `whatsapp_messages` for an audit trail, and to
 * the `whatsapp` log channel. When FONNTE_ENABLED=false (the default until a real token is
 * configured), messages are logged only — never actually sent — so the app is safe to run in dev
 * without a live WhatsApp account or burning message quota.
 */
class WhatsAppService
{
    public function __construct(
        private readonly ?string $baseUrl,
        private readonly ?string $token,
        private readonly bool $enabled,
    ) {}

    /**
     * @param  Model|null  $related  the Order/Invoice this message is about, for the audit trail
     */
    public function send(string $phone, string $message, ?string $toName = null, ?Model $related = null, ?int $sentByUserId = null): WhatsAppMessage
    {
        $phone = $this->normalisePhone($phone);

        $record = WhatsAppMessage::create([
            'related_type' => $related?->getMorphClass(),
            'related_id' => $related?->getKey(),
            'sent_by' => $sentByUserId,
            'to_phone' => $phone,
            'to_name' => $toName,
            'message' => $message,
            'status' => 'pending',
        ]);

        if (! $this->enabled || ! $this->token) {
            Log::channel('whatsapp')->info('WA (simulasi — FONNTE_ENABLED=false atau token kosong)', [
                'to' => $phone,
                'message' => $message,
            ]);

            $record->update([
                'status' => 'sent',
                'sent_at' => now(),
                'provider_response' => ['simulated' => true],
            ]);

            return $record;
        }

        try {
            $response = Http::withHeaders(['Authorization' => $this->token])
                ->asForm()
                ->timeout(15)
                ->post(rtrim($this->baseUrl, '/').'/send', [
                    'target' => $phone,
                    'message' => $message,
                ]);

            $body = $response->json() ?? ['raw' => $response->body()];

            Log::channel('whatsapp')->info('WA terkirim ke Fonnte', [
                'to' => $phone,
                'status_code' => $response->status(),
                'response' => $body,
            ]);

            $record->update([
                'status' => $response->successful() ? 'sent' : 'failed',
                'sent_at' => now(),
                'provider_response' => $body,
            ]);
        } catch (\Throwable $e) {
            Log::channel('whatsapp')->error('WA gagal dikirim', [
                'to' => $phone,
                'error' => $e->getMessage(),
            ]);

            $record->update([
                'status' => 'failed',
                'provider_response' => ['error' => $e->getMessage()],
            ]);
        }

        return $record;
    }

    /** Fonnte expects a bare international-format number (e.g. 62812xxxxxxx), no "+", spaces or dashes. */
    private function normalisePhone(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        if (str_starts_with($digits, '0')) {
            $digits = '62'.substr($digits, 1);
        }

        return $digits;
    }
}
