<?php

namespace App\Services;

use App\Enums\PayableStatus;
use App\Models\Order;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Ports src/lib/payables.ts to the backend, line for line: Tagihan rows are *derived*, never
 * authored, and grouped one Tagihan per Order↔Supplier pairing. An Order can owe several different
 * suppliers (tour operator, transport driver, ticket vendor, add-on vendor, the base Layanan cost's
 * own vendor) — each is its own Tagihan even though the Order is the same, and every cost entered
 * against that same supplier name on that Order becomes one itemised row inside it.
 */
class PayablesService
{
    /**
     * @return Collection<int, array> one entry per PayableGroup (Tagihan), see buildGroup() for shape
     */
    public function derive(): Collection
    {
        $orders = Order::with([
            'guests',
            'assignments.vehicle',
            'assignments.supplier',
            'itineraryDays.activities',
            'ticketRows',
            'addOns',
            'layananDetail',
        ])->get();

        $out = collect();

        foreach ($orders as $order) {
            $namaTamu = $order->guests->pluck('nama')->filter()->implode(', ');
            $base = [
                'orderId' => $order->id,
                'cancelled' => $order->status->value === 'Dibatalkan',
                'kode' => $order->kode,
                'namaOrder' => $order->nama_order,
                'namaTamu' => $namaTamu,
                'tanggalOrder' => optional($order->tanggal_mulai)->toDateString(),
            ];

            $out = $out->merge($this->transportGroups($order, $base));
            $out = $out->merge($this->tourGroups($order, $base));
            $out = $out->merge($this->ticketGroups($order, $base));
            $out = $out->merge($this->addOnGroups($order, $base));

            $layananGroup = $this->layananGroup($order, $base);
            if ($layananGroup) {
                $out->push($layananGroup);
            }
        }

        return $out->values();
    }

    /** One Tagihan per Supplier/Driver, itemised one per costed vehicle assignment (a day may carry several). */
    private function transportGroups(Order $order, array $base): Collection
    {
        $bySupplier = collect(); // supplierId => ['nama' => ..., 'items' => Collection]

        // Disambiguate "Transport Hari N #M" only when a day genuinely has more than one vehicle —
        // same convention as transportLineItems() on the frontend.
        $multiDay = $order->assignmentDayCount() > 1;
        $costed = $order->assignments->filter(fn ($a) => $a->biaya_transport_modal > 0 && $a->supplier_id);

        foreach ($costed->groupBy('hari') as $hari => $rowsForDay) {
            foreach ($rowsForDay->values() as $idx => $a) {
                $label = $multiDay ? "Transport Hari {$hari}" : 'Transport';
                $suffix = $rowsForDay->count() > 1 ? ' #'.($idx + 1) : '';
                $vehicleName = $a->vehicle?->nama ?? 'Mobil';

                $item = [
                    'id' => "{$order->id}#t-{$a->id}",
                    'rincian' => "{$label}{$suffix} — {$vehicleName} + Driver",
                    'modal' => (int) $a->biaya_transport_modal,
                    'status' => $a->bayar_status?->value ?? PayableStatus::BelumBayar->value,
                    'tanggalBayar' => optional($a->tanggal_bayar)->toDateString(),
                    'ref' => ['kind' => 'transport', 'assignmentId' => $a->id],
                ];

                $entry = $bySupplier->get($a->supplier_id, ['nama' => $a->supplier?->nama ?? '—', 'items' => collect()]);
                $entry['items']->push($item);
                $bySupplier->put($a->supplier_id, $entry);
            }
        }

        return $bySupplier->map(fn ($entry, $supplierId) => $this->buildGroup(
            $base, 'transportasi', (string) $supplierId, $entry['nama'], $entry['items'],
        ))->values();
    }

    /** One Tagihan per Supplier Tur name, itemised by itinerary activity. */
    private function tourGroups(Order $order, array $base): Collection
    {
        $byNama = collect();

        foreach ($order->itineraryDays as $day) {
            foreach ($day->activities as $act) {
                $nama = trim((string) $act->supplier_tur);
                if ($nama === '' || $act->skip_biaya || $act->biaya <= 0) {
                    continue;
                }
                $key = mb_strtolower($nama);
                $entry = $byNama->get($key, ['nama' => $nama, 'items' => collect()]);
                $entry['items']->push([
                    'id' => "{$order->id}#tour-{$day->id}-{$act->id}",
                    'rincian' => "Hari {$day->hari} — {$act->aktivitas}",
                    'modal' => (int) $act->biaya,
                    'status' => $act->bayar_status?->value ?? PayableStatus::BelumBayar->value,
                    'tanggalBayar' => optional($act->tanggal_bayar)->toDateString(),
                    'ref' => ['kind' => 'tour', 'dayId' => $day->id, 'activityId' => $act->id],
                ]);
                $byNama->put($key, $entry);
            }
        }

        return $byNama->map(fn ($entry) => $this->buildGroup($base, 'tour', null, $entry['nama'], $entry['items']))->values();
    }

    /** One Tagihan per Supplier Tiket name, itemised by ticket cart line. */
    private function ticketGroups(Order $order, array $base): Collection
    {
        $byNama = collect();

        foreach ($order->ticketRows as $row) {
            $nama = trim((string) $row->supplier_nama);
            $modal = $row->qty * $row->modal_satuan;
            if ($nama === '' || $modal <= 0) {
                continue;
            }
            $key = mb_strtolower($nama);
            $entry = $byNama->get($key, ['nama' => $nama, 'items' => collect()]);
            $entry['items']->push([
                'id' => "{$order->id}#ticket-{$row->id}",
                'rincian' => "{$row->nama_tiket} × {$row->qty}",
                'modal' => (int) $modal,
                'status' => $row->bayar_status?->value ?? PayableStatus::BelumBayar->value,
                'tanggalBayar' => optional($row->tanggal_bayar)->toDateString(),
                'ref' => ['kind' => 'ticket', 'ticketRowId' => $row->id],
            ]);
            $byNama->put($key, $entry);
        }

        return $byNama->map(fn ($entry) => $this->buildGroup($base, 'ticket', null, $entry['nama'], $entry['items']))->values();
    }

    /** One Tagihan per Supplier name, itemised by add-on product row. */
    private function addOnGroups(Order $order, array $base): Collection
    {
        $byNama = collect();

        foreach ($order->addOns as $row) {
            $nama = trim((string) $row->supplier_nama);
            if ($nama === '' || $row->skip_biaya || $row->modal <= 0) {
                continue;
            }
            $key = mb_strtolower($nama);
            $entry = $byNama->get($key, ['nama' => $nama, 'items' => collect()]);
            $entry['items']->push([
                'id' => "{$order->id}#addon-{$row->id}",
                'rincian' => ($row->product?->nama ?? 'Produk')." × {$row->qty}",
                'modal' => (int) ($row->qty * $row->modal),
                'status' => $row->bayar_status?->value ?? PayableStatus::BelumBayar->value,
                'tanggalBayar' => optional($row->tanggal_bayar)->toDateString(),
                'ref' => ['kind' => 'addon', 'addOnId' => $row->id],
            ]);
            $byNama->put($key, $entry);
        }

        return $byNama->map(fn ($entry) => $this->buildGroup($base, 'addon', null, $entry['nama'], $entry['items']))->values();
    }

    /** The Check In/Check Out/Transfer base Biaya (Modal) itself, billed to its own Supplier. */
    private function layananGroup(Order $order, array $base): ?array
    {
        $ld = $order->layananDetail;
        $nama = trim((string) $ld?->supplier_nama);
        if (! $ld || $nama === '' || $ld->biaya_modal <= 0) {
            return null;
        }

        $item = [
            'id' => "{$order->id}#layanan",
            'rincian' => "Biaya {$order->nama_order}",
            'modal' => (int) $ld->biaya_modal,
            'status' => $ld->bayar_status?->value ?? PayableStatus::BelumBayar->value,
            'tanggalBayar' => optional($ld->tanggal_bayar)->toDateString(),
            'ref' => ['kind' => 'layanan'],
        ];

        return $this->buildGroup($base, 'layanan', null, $nama, collect([$item]));
    }

    private function buildGroup(array $base, string $tipeTagihan, ?string $supplierId, string $supplierNama, Collection $items): array
    {
        $modal = $items->sum('modal');
        $status = $items->every(fn ($i) => $i['status'] === PayableStatus::Bayar->value)
            ? PayableStatus::Bayar->value
            : PayableStatus::BelumBayar->value;

        $tanggalBayar = $status === PayableStatus::Bayar->value
            ? $items->pluck('tanggalBayar')->filter()->sort()->last()
            : null;

        $supplierKey = $supplierId ?? mb_strtolower($supplierNama);

        return [
            ...$base,
            'id' => "{$base['orderId']}~{$tipeTagihan}~{$supplierKey}",
            'tipeTagihan' => $tipeTagihan,
            'supplierId' => $supplierId,
            'supplierNama' => $supplierNama,
            'items' => $items->values()->all(),
            'modal' => $modal,
            'status' => $status,
            'tanggalBayar' => $tanggalBayar,
        ];
    }

    /** Only unpaid Tagihan on live Orders are actionable — a cancelled Order's Tagihan is read-only. */
    public function canMarkPaid(array $group): bool
    {
        return ! $group['cancelled'] && $group['status'] === PayableStatus::BelumBayar->value;
    }

    /**
     * Mark one Tagihan (and every still-unpaid item inside it) paid, writing back to whichever table
     * each item's ref points at.
     */
    public function markPaid(array $group): void
    {
        if (! $this->canMarkPaid($group)) {
            return;
        }

        $order = Order::with(['assignments', 'itineraryDays.activities', 'ticketRows', 'addOns', 'layananDetail'])
            ->findOrFail($group['orderId']);

        foreach ($group['items'] as $item) {
            if ($item['status'] === PayableStatus::Bayar->value) {
                continue;
            }

            $ref = $item['ref'];

            match ($ref['kind']) {
                'transport' => $order->assignments->firstWhere('id', $ref['assignmentId'])
                    ?->update(['bayar_status' => PayableStatus::Bayar, 'tanggal_bayar' => Carbon::today()]),
                'tour' => $order->itineraryDays->firstWhere('id', $ref['dayId'])
                    ?->activities->firstWhere('id', $ref['activityId'])
                    ?->update(['bayar_status' => PayableStatus::Bayar, 'tanggal_bayar' => Carbon::today()]),
                'ticket' => $order->ticketRows->firstWhere('id', $ref['ticketRowId'])
                    ?->update(['bayar_status' => PayableStatus::Bayar, 'tanggal_bayar' => Carbon::today()]),
                'addon' => $order->addOns->firstWhere('id', $ref['addOnId'])
                    ?->update(['bayar_status' => PayableStatus::Bayar, 'tanggal_bayar' => Carbon::today()]),
                'layanan' => $order->layananDetail
                    ?->update(['bayar_status' => PayableStatus::Bayar, 'tanggal_bayar' => Carbon::today()]),
                default => null,
            };
        }
    }

    /** Days an unpaid Tagihan has been outstanding, measured against the real clock. */
    public function daysOutstanding(array $group): int
    {
        if (! $group['tanggalOrder']) {
            return 0;
        }

        return (int) floor((now()->timestamp - Carbon::parse($group['tanggalOrder'])->timestamp) / 86400);
    }
}
