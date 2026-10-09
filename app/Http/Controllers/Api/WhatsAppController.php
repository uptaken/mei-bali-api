<?php

namespace App\Http\Controllers\Api;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Jobs\SendWhatsAppMessageJob;
use App\Models\Order;
use App\Models\WhatsAppTemplate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

use App\Http\Controllers\BaseController;

/** Ports src/components/WaSupplierDialog.tsx to a real send. */
class WhatsAppController extends BaseController
{
    /** POST /api/orders/{order}/send-wa-supplier */
    public function sendSupplierConfirmation(Request $request, Order $order): JsonResponse
    {
        $order->load(['assignments.supplier', 'assignments.vehicle', 'itineraryDays', 'layananDetail']);

        $assignment = $order->assignments->first();
        $supplier = $assignment?->supplier;
        $vehicle = $assignment?->vehicle;

        if (! $supplier || ! $supplier->telepon) {
            return response()->json([
                'message' => 'Supplier/Driver belum ditugaskan (atau tidak punya nomor telepon) untuk order ini.',
            ], 422);
        }

        $template = WhatsAppTemplate::where('nama', 'Konfirmasi Supplier')->first();
        if (! $template) {
            return response()->json(['message' => 'Template WA "Konfirmasi Supplier" belum ada di Master.'], 422);
        }

        $jemput = match (true) {
            $order->tipe->value === 'tour' => $order->itineraryDays->first()?->tempat_penjemputan ?? $order->destinasi ?? '-',
            $order->tipe->value === 'ticket' => $order->kota ?? $order->nama_order ?? '-',
            $order->sub_tipe?->value === 'check_in' => $order->layananDetail?->hotel_drop_off ?? $order->kota ?? '-',
            default => $order->layananDetail?->pick_up_point ?? $order->kota ?? '-',
        };

        $message = $template->render([
            'nama_supplier' => $supplier->nama,
            'kode_order' => $order->kode,
            'tanggal' => optional($order->tanggal_mulai)->translatedFormat('d M Y') ?? '-',
            'jam' => $order->jam_mulai ?? '-',
            'tempat_jemput' => $jemput,
            'jumlah_tamu' => ($order->dewasa + $order->anak).' pax',
            'tipe_mobil' => $vehicle?->nama ?? '(mobil)',
        ]);

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
