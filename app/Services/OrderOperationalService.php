<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Models\Order;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * "Operational & Reservasi" completion — ports TourOperational.tsx / LayananOperational.tsx's
 * persisted-mode save(): fills in whatever Modal Tur / Modal Add-Ons / Modal Transportasi / Supplier
 * assignment was left blank at creation. Never validates or blocks — everything here is optional.
 * Status only advances past "Menunggu Supplier/Driver" for Tour/Layanan once transport is complete,
 * and that never applies to Ticket.
 */
class OrderOperationalService
{
    public function __construct(private readonly OrderService $orderService) {}

    /**
     * Simpan layar Operasional: penugasan kendaraan/supplier, modal add-on, dan biaya aktivitas tur.
     * Lalu hitung ulang total modal, majukan status ke "Siap Kirim WA" bila semua penugasan lengkap,
     * catat riwayat 'assigned', dan sinkronkan baris invoice. Semua dalam satu transaksi.
     */
    public function update(Order $order, array $data, User $actor): Order
    {
        return DB::transaction(function () use ($order, $data, $actor) {
            // Penugasan diganti seluruhnya: layar ini pemegang tunggal datanya setelah dibuka.
            if (array_key_exists('assignments', $data)) {
                $order->assignments()->delete();
                foreach ($data['assignments'] as $a) {
                    $order->assignments()->create([
                        'hari' => $a['hari'] ?? 1,
                        'vehicle_id' => $a['vehicleId'] ?? null,
                        'supplier_id' => $a['supplierId'] ?? null,
                        'biaya_transport_modal' => $a['biayaTransportModal'] ?? 0,
                    ]);
                }
            }

            if (array_key_exists('addOns', $data)) {
                $existing = $order->addOns()->get()->keyBy('id');
                foreach ($data['addOns'] as $a) {
                    if (! empty($a['id']) && $existing->has($a['id'])) {
                        $existing[$a['id']]->update([
                            'modal' => $a['modal'] ?? 0,
                            'supplier_nama' => $a['supplierNama'] ?? null,
                            'skip_biaya' => $a['skipBiaya'] ?? false,
                        ]);
                    } else {
                        $order->addOns()->create([
                            'product_id' => $a['produkId'] ?? $a['productId'] ?? null,
                            'qty' => $a['qty'] ?? 1,
                            'modal' => $a['modal'] ?? 0,
                            'catatan' => $a['catatan'] ?? null,
                            'skip_biaya' => $a['skipBiaya'] ?? false,
                            'supplier_nama' => $a['supplierNama'] ?? null,
                        ]);
                    }
                }
            }

            if ($order->tipe === OrderType::Tour) {
                foreach ($data['itinerary'] ?? [] as $day) {
                    $dayModel = $order->itineraryDays()->where('hari', $day['hari'])->first();
                    if (! $dayModel) {
                        continue;
                    }
                    foreach ($day['aktivitas'] ?? [] as $act) {
                        if (empty($act['id'])) {
                            continue;
                        }
                        $dayModel->activities()->where('id', $act['id'])->update([
                            'biaya' => $act['biaya'] ?? 0,
                            'skip_biaya' => $act['skipBiaya'] ?? false,
                            'supplier_tur' => $act['supplierTur'] ?? null,
                        ]);
                    }
                }
            }

            $order->load(['assignments.vehicle', 'assignments.supplier', 'itineraryDays.activities', 'addOns.product', 'layananDetail']);

            // Lengkap = ada penugasan dan setiap penugasan sudah punya kendaraan + supplier.
            $isComplete = $order->assignments->isNotEmpty()
                && $order->assignments->every(fn ($a) => $a->vehicle_id && $a->supplier_id);

            $totalModalAddOns = $order->addOns->sum(fn ($a) => $a->qty * $a->modal);
            $totalBiayaTransport = $order->assignments->sum('biaya_transport_modal');

            $patch = [
                'vehicle_id' => $order->assignments->first()?->vehicle_id,
                'supplier_id' => $order->assignments->first()?->supplier_id,
                'biaya_transport_modal' => $totalBiayaTransport,
                'updated_by' => $actor->id,
            ];

            // Angka yang sama dengan OrderService saat create/edit: modal inti + add-on + transport.
            $patch['total'] = $order->legModalValue() + $totalModalAddOns + $totalBiayaTransport;

            $canAdvance = in_array($order->tipe, [OrderType::Tour, OrderType::Layanan], true);
            if ($canAdvance && $order->status === OrderStatus::MenungguSupplierDriver && $isComplete) {
                $patch['status'] = OrderStatus::SiapKirimWa->value;
            }

            $order->update($patch);

            // Nama supplier pertama dipakai sebagai keterangan di riwayat order dan invoice.
            $supplierName = $order->assignments->first()?->supplier?->nama
                ?? Supplier::find($order->assignments->first()?->supplier_id)?->nama;

            $order->events()->create([
                'kind' => 'assigned',
                'actor_id' => $actor->id,
                'at' => now(),
                'detail' => $supplierName,
            ]);

            $this->syncInvoice($order, $actor, $supplierName);

            return $order->fresh(['client', 'assignments', 'guests', 'itineraryDays.activities', 'addOns', 'ticketRows', 'layananDetail', 'events', 'invoices.lines']);
        });
    }

    /**
     * Keep the Order's one invoice in step: each Add-On line's Modal + Supplier (matched
     * positionally — this screen never adds/removes Add-On rows client-side beyond what's posted),
     * itinerary activity lines for Tour, and the Transport line(s) — all upserted by description,
     * same convention as order creation.
     */
    private function syncInvoice(Order $order, User $actor, ?string $supplierName): void
    {
        $invoice = $order->invoices()->latest()->first();
        if (! $invoice) {
            return;
        }

        $isAddOn = fn ($l) => str_starts_with($l->deskripsi, 'Add-On:');
        $isTransport = fn ($l) => str_starts_with($l->deskripsi, 'Transport');

        $existing = $invoice->lines;

        if ($order->tipe === OrderType::Tour) {
            $multiDay = $order->itineraryDays->count() > 1;
            foreach ($order->itineraryDays as $day) {
                foreach ($day->activities as $act) {
                    $desk = $multiDay ? "Hari {$day->hari} — {$act->aktivitas}" : ($act->aktivitas ?: 'Aktivitas');
                    $line = $existing->first(fn ($l) => $l->deskripsi === $desk);
                    if ($line) {
                        $line->update(['modal' => $act->biaya, 'supplier_nama' => $act->supplier_tur, 'skip_biaya' => $act->skip_biaya]);
                    } else {
                        $invoice->lines()->create([
                            'deskripsi' => $desk, 'qty' => 1, 'modal' => $act->biaya, 'harga_jual' => 0,
                            'supplier_nama' => $act->supplier_tur, 'skip_biaya' => $act->skip_biaya,
                        ]);
                    }
                }
            }
        }

        $existingAddOnLines = $existing->filter($isAddOn)->values();
        if ($existingAddOnLines->count() === $order->addOns->count()) {
            foreach ($order->addOns as $i => $a) {
                $existingAddOnLines[$i]->update([
                    'modal' => $a->modal,
                    'supplier_nama' => $a->supplier_nama,
                    'skip_biaya' => $a->skip_biaya,
                ]);
            }
        }

        $wantedTransport = $this->orderService->transportInvoiceLines($order);
        $existingTransport = $existing->filter($isTransport);
        foreach ($wantedTransport as $w) {
            $line = $existingTransport->first(fn ($l) => $l->deskripsi === $w['deskripsi']);
            if ($line) {
                $line->update(['modal' => $w['modal'], 'supplier_nama' => $w['supplier_nama']]);
            } else {
                $invoice->lines()->create([...$w, 'harga_jual' => 0]);
            }
        }

        // Drop transport lines for assignments that no longer carry a cost (removed vehicle rows).
        $wantedDescriptions = collect($wantedTransport)->pluck('deskripsi');
        $existingTransport->reject(fn ($l) => $wantedDescriptions->contains($l->deskripsi))->each->delete();

        $invoice->events()->create(['kind' => 'assigned', 'actor_id' => $actor->id, 'at' => now(), 'detail' => $supplierName]);
    }
}
