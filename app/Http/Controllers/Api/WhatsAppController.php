<?php

namespace App\Http\Controllers\Api;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Jobs\SendWhatsAppMessageJob;
use App\Models\Order;
use App\Models\WhatsAppTemplate;
use App\Models\Supplier;
use App\Support\OrderWhatsApp;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

use App\Http\Controllers\BaseController;

/** Ports src/components/WaSupplierDialog.tsx to a real send. */
class WhatsAppController extends BaseController
{
    /** GET /api/orders/{order}/wa-supplier-preview — the exact message that send-wa-supplier would queue. */
    public function previewSupplierConfirmation(Order $order): JsonResponse
    {
        $built = $this->buildMessage($order);
        if ($built instanceof JsonResponse) {
            return $built;
        }

        return response()->json(['template' => $built['template'], 'message' => $built['message']]);
    }

    /** @return array{supplier: Supplier, template: string, message: string}|JsonResponse a 422 when it cannot be built */
    private function buildMessage(Order $order): array|JsonResponse
    {
        $order->load(['assignments.supplier', 'assignments.vehicle']);

        $assignment = $order->assignments->first();
        $supplier = $assignment?->supplier;

        if (! $supplier || ! $supplier->telepon) {
            return response()->json([
                'message' => 'Supplier/Driver belum ditugaskan (atau tidak punya nomor telepon) untuk order ini.',
            ], 422);
        }

        $built = OrderWhatsApp::build($order, $supplier, $assignment->vehicle);
        if ($built['message'] === null) {
            return response()->json(['message' => "Template WA \"{$built['template']}\" belum ada di Master."], 422);
        }

        return ['supplier' => $supplier, ...$built];
    }

    /** POST /api/orders/{order}/send-wa-supplier */
    public function sendSupplierConfirmation(Request $request, Order $order): JsonResponse
    {
        $built = $this->buildMessage($order);
        if ($built instanceof JsonResponse) {
            return $built;
        }
        ['supplier' => $supplier, 'message' => $message] = $built;

        SendWhatsAppMessageJob::dispatch($supplier->telepon, $message, $supplier->nama, $order, $request->user()->id)->afterResponse();

        $order->update(['status' => OrderStatus::InfoTerkirim->value, 'wa_sent_at' => now(), 'updated_by' => $request->user()->id]);
        $order->events()->create([
            'kind' => 'wa',
            'actor_id' => $request->user()->id,
            'at' => now(),
            'detail' => "Dikirim ke {$supplier->nama}",
        ]);

        Log::channel('whatsapp')->info('Konfirmasi supplier di-queue', ['order' => $order->kode, 'to' => $supplier->telepon]);

        return response()->json([
            'message' => 'Pesan WhatsApp sedang dikirim.',
            'preview' => $message,
            'order' => $order->fresh(OrderController::WITH),
        ]);
    }

    /** POST /api/whatsapp/send — free-form send, for anything not covered by a templated flow yet. */
    public function send(Request $request): JsonResponse
    {
        $data = $request->validate([
            'phone' => ['required', 'string'],
            'message' => ['required', 'string'],
            'toName' => ['nullable', 'string'],
        ]);

        SendWhatsAppMessageJob::dispatch($data['phone'], $data['message'], $data['toName'] ?? null, null, $request->user()->id);

        return response()->json(['message' => 'Pesan WhatsApp sedang dikirim.']);
    }
}
