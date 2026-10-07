<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Models\Invoice;
use App\Models\InvoiceEvent;
use App\Models\Order;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Support\Facades\DB;

/**
 * Order creation, ported from the Review & Konfirmasi confirm() flow (src/pages/orders/OrderReview.tsx):
 * one Order → one Invoice, Modal is the only figure ever computed here (Harga Jual is always left
 * blank, filled in manually later on the Invoice), and the status only ever advances past
 * "Menunggu Supplier/Driver" for a Tour or Layanan Order whose transport is already fully assigned.
 */
class OrderService
{
    public function __construct(
        private readonly NumberingService $numbering,
    ) {}

    public function create(array $data, User $creator): Order
    {
        return DB::transaction(function () use ($data, $creator) {
            $tipe = OrderType::from($data['tipe']);
            $hasTransportStep = in_array($tipe, [OrderType::Tour, OrderType::Layanan], true);

            $assignmentsInput = $data['assignments'] ?? [];
            $costedAssignments = collect($assignmentsInput)->filter(
                fn ($a) => ! empty($a['vehicleId']) || ! empty($a['supplierId'])
            )->values();

            $transportComplete = $hasTransportStep
                && $costedAssignments->isNotEmpty()
                && $costedAssignments->every(fn ($a) => ! empty($a['vehicleId']) && ! empty($a['supplierId']));

            $modalLeg = $this->legModal($tipe, $data);
            $modalAddOns = collect($data['addOns'] ?? [])->sum(fn ($a) => ($a['qty'] ?? 1) * ($a['modal'] ?? 0));
            $modalTransport = $costedAssignments->sum(fn ($a) => $a['biayaTransportModal'] ?? 0);
            $totalModal = $modalLeg + $modalAddOns + $modalTransport;

            $status = $hasTransportStep
                ? ($transportComplete ? OrderStatus::SiapKirimWa : OrderStatus::MenungguSupplierDriver)
                : OrderStatus::initial($tipe);

            $order = Order::create([
                'kode' => $this->numbering->nextOrderCode(),
                'tipe' => $tipe->value,
                'sub_tipe' => $data['subTipe'] ?? null,
                'client_id' => $data['clientId'],
                'nama_order' => $data['namaOrder'],
                'kode_group' => $data['kodeGroup'],
                'status' => $status->value,
                'total' => $totalModal,
                'tanggal_mulai' => $data['tanggalMulai'] ?? null,
                'jam_mulai' => $data['jamMulai'] ?? null,
                'durasi_hari' => $data['durasiHari'] ?? (isset($data['itinerary']) ? count($data['itinerary']) : null),
                'destinasi' => $data['destinasi'] ?? null,
                'bahasa_id' => $data['bahasaId'] ?? null,
                'kota' => $data['kota'] ?? null,
                'kota_termasuk' => $data['kotaTermasuk'] ?? null,
                'paket_id' => $data['paketId'] ?? null,
                'dewasa' => $data['dewasa'] ?? 0,
                'anak' => $data['anak'] ?? 0,
                'catatan' => $data['catatan'] ?? null,
                'vehicle_id' => $costedAssignments->first()['vehicleId'] ?? null,
                'supplier_id' => $costedAssignments->first()['supplierId'] ?? null,
                'biaya_transport_modal' => $modalTransport,
                'tanggal_pemakaian' => $data['tanggalPemakaian'] ?? null,
                'created_by' => $creator->id,
                'updated_by' => $creator->id,
            ]);

            foreach ($data['guests'] ?? [] as $g) {
                $order->guests()->create(['nama' => $g['nama'] ?? '', 'kategori' => $g['kategori']]);
            }

            foreach ($assignmentsInput as $a) {
                $order->assignments()->create([
                    'hari' => $a['hari'] ?? 1,
                    'vehicle_id' => $a['vehicleId'] ?? null,
                    'supplier_id' => $a['supplierId'] ?? null,
                    'biaya_transport_modal' => $a['biayaTransportModal'] ?? 0,
                ]);
            }

            foreach ($data['itinerary'] ?? [] as $day) {
                $dayModel = $order->itineraryDays()->create([
                    'hari' => $day['hari'],
                    'waktu_penjemputan' => $day['waktuPenjemputan'] ?? null,
                    'tempat_penjemputan' => $day['tempatPenjemputan'] ?? null,
                    'waktu_drop_akhir' => $day['waktuDropAkhir'] ?? null,
                    'tempat_drop_akhir' => $day['tempatDropAkhir'] ?? null,
                    'catatan' => $day['catatan'] ?? null,
                ]);

                foreach ($day['aktivitas'] ?? [] as $i => $act) {
                    $dayModel->activities()->create([
                        'aktivitas' => $act['aktivitas'] ?? '',
                        'biaya' => $act['biaya'] ?? 0,
                        'skip_biaya' => $act['skipBiaya'] ?? false,
                        'supplier_tur' => $act['supplierTur'] ?? null,
                        'urutan' => $i,
                    ]);
                }
            }

            foreach ($data['addOns'] ?? [] as $a) {
                $order->addOns()->create([
                    'product_id' => $a['produkId'] ?? $a['productId'] ?? null,
                    'qty' => $a['qty'] ?? 1,
                    'modal' => $a['modal'] ?? 0,
                    'catatan' => $a['catatan'] ?? null,
                    'skip_biaya' => $a['skipBiaya'] ?? false,
                    'supplier_nama' => $a['supplierNama'] ?? null,
                ]);
            }

            foreach ($data['ticketRows'] ?? [] as $r) {
                $order->ticketRows()->create([
                    'tiket_id' => $r['tiketId'] ?? null,
                    'nama_tiket' => $r['namaTiket'],
                    'qty' => $r['qty'] ?? 1,
                    'modal_satuan' => $r['modalSatuan'] ?? 0,
                    'harga_jual_ref' => $r['hargaJualRef'] ?? 0,
                    'supplier_nama' => $r['supplierNama'] ?? null,
                ]);
            }

            if ($tipe === OrderType::Layanan && isset($data['layananDetail'])) {
                $ld = $data['layananDetail'];
                $order->layananDetail()->create([
                    'nama_tamu' => $ld['namaTamu'] ?? '',
                    'hotel_drop_off' => $ld['hotelDropOff'] ?? null,
                    'pick_up_point' => $ld['pickUpPoint'] ?? null,
                    'drop_off_point' => $ld['dropOffPoint'] ?? null,
                    'jam_jemput' => $ld['jamJemput'] ?? null,
                    'info_flight' => $ld['infoFlight'] ?? null,
                    'jumlah_pax' => $ld['jumlahPax'] ?? 1,
                    'biaya_modal' => $ld['biayaModal'] ?? 0,
                    'supplier_nama' => $ld['supplierNama'] ?? null,
                ]);
            }

            $order->events()->create(['kind' => 'created', 'actor_id' => $creator->id, 'at' => now()]);
            $order->events()->create(['kind' => 'confirmed', 'actor_id' => $creator->id, 'at' => now()]);

            $assignedSupplierNames = collect($assignmentsInput)
                ->filter(fn ($a) => ! empty($a['supplierId']))
                ->map(fn ($a) => Supplier::find($a['supplierId'])?->nama)
                ->filter()
                ->unique()
                ->implode(', ');

            if ($costedAssignments->isNotEmpty()) {
                $order->events()->create([
                    'kind' => 'assigned',
                    'actor_id' => $creator->id,
                    'at' => now(),
                    'detail' => $assignedSupplierNames ?: null,
                ]);
            }

            $order->load(['itineraryDays.activities', 'ticketRows', 'addOns', 'layananDetail', 'assignments.vehicle', 'assignments.supplier']);

            $invoice = $this->createInvoiceFor($order, $creator, $assignedSupplierNames);

            return $order->fresh(['client', 'assignments', 'guests', 'itineraryDays.activities', 'addOns', 'ticketRows', 'layananDetail', 'events', 'invoices.lines']);
        });
    }

		public function update(Order $order, array $data, User $creator): Order
		{
				return DB::transaction(function () use ($order, $creator, $data) {
						$tipe = OrderType::from($data['tipe']);
						$hasTransportStep = in_array($tipe, [OrderType::Tour, OrderType::Layanan], true);

						$assignmentsInput = $data['assignments'] ?? [];
						$costedAssignments = collect($assignmentsInput)->filter(
								fn ($a) => ! empty($a['vehicleId']) || ! empty($a['supplierId'])
						)->values();

						$transportComplete = $hasTransportStep
								&& $costedAssignments->isNotEmpty()
								&& $costedAssignments->every(fn ($a) => ! empty($a['vehicleId']) && ! empty($a['supplierId']));

						$modalLeg = $this->legModal($tipe, $data);
						$modalAddOns = collect($data['addOns'] ?? [])->sum(fn ($a) => ($a['qty'] ?? 1) * ($a['modal'] ?? 0));
						$modalTransport = $costedAssignments->sum(fn ($a) => $a['biayaTransportModal'] ?? 0);
						$totalModal = $modalLeg + $modalAddOns + $modalTransport;

						$status = $hasTransportStep
								? ($transportComplete ? OrderStatus::SiapKirimWa : OrderStatus::MenungguSupplierDriver)
								: OrderStatus::initial($tipe);

						$order->update([
								'tipe' => $tipe->value,
								'sub_tipe' => $data['subTipe'] ?? null,
								'client_id' => $data['clientId'],
								'nama_order' => $data['namaOrder'],
								'kode_group' => $data['kodeGroup'],
								'status' => $status->value,
								'total' => $totalModal,
								'tanggal_mulai' => $data['tanggalMulai'] ?? null,
								'jam_mulai' => $data['jamMulai'] ?? null,
								'durasi_hari' => $data['durasiHari'] ?? (isset($data['itinerary']) ? count($data['itinerary']) : null),
								'destinasi' => $data['destinasi'] ?? null,
								'bahasa_id' => $data['bahasaId'] ?? null,
								'kota' => $data['kota'] ?? null,
								'kota_termasuk' => $data['kotaTermasuk'] ?? null,
								'paket_id' => $data['paketId'] ?? null,
								'dewasa' => $data['dewasa'] ?? 0,
								'anak' => $data['anak'] ?? 0,
								'catatan' => $data['catatan'] ?? null,
								'vehicle_id' => $costedAssignments->first()['vehicleId'] ?? null,
								'supplier_id' => $costedAssignments->first()['supplierId'] ?? null,
								'biaya_transport_modal' => $modalTransport,
								'tanggal_pemakaian' => $data['tanggalPemakaian'] ?? null,
								'created_by' => $creator->id,
								'updated_by' => $creator->id,
						]);

						if (array_key_exists('tamu', $data)) {
							$order->guests()->delete();
							foreach ($data['tamu'] ?? [] as $g) {
									$order->guests()->create(['nama' => $g['nama'] ?? '', 'kategori' => $g['kategori']]);
							}
						}

						if (array_key_exists('assignments', $data)) {
							$order->assignments()->delete();
							foreach ($assignmentsInput as $a) {
									$order->assignments()->create([
											'hari' => $a['hari'] ?? 1,
											'vehicle_id' => $a['vehicleId'] ?? null,
											'supplier_id' => $a['supplierId'] ?? null,
											'biaya_transport_modal' => $a['biayaTransportModal'] ?? 0,
									]);
							}
						}

						if (array_key_exists('itinerary', $data)) {
							$order->itineraryDays()->delete();
							foreach ($data['itinerary'] ?? [] as $day) {
								$dayModel = $order->itineraryDays()->create([
										'hari' => $day['hari'],
										'waktu_penjemputan' => $day['waktuPenjemputan'] ?? null,
										'tempat_penjemputan' => $day['tempatPenjemputan'] ?? null,
										'waktu_drop_akhir' => $day['waktuDropAkhir'] ?? null,
										'tempat_drop_akhir' => $day['tempatDropAkhir'] ?? null,
										'catatan' => $day['catatan'] ?? null,
								]);

								foreach ($day['aktivitas'] ?? [] as $i => $act) {
										$dayModel->activities()->create([
												'aktivitas' => $act['aktivitas'] ?? '',
												'biaya' => $act['biaya'] ?? 0,
												'skip_biaya' => $act['skipBiaya'] ?? false,
												'supplier_tur' => $act['supplierTur'] ?? null,
												'urutan' => $i,
										]);
								}
							}
						}

						if (array_key_exists('addOns', $data)) {
							$order->addOns()->delete();
							foreach ($data['addOns'] ?? [] as $a) {
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

						if (array_key_exists('ticketRows', $data)) {
							$order->ticketRows()->delete();
							foreach ($data['ticketRows'] ?? [] as $r) {
								$order->ticketRows()->create([
										'tiket_id' => $r['tiketId'] ?? null,
										'nama_tiket' => $r['namaTiket'],
										'qty' => $r['qty'] ?? 1,
										'modal_satuan' => $r['modalSatuan'] ?? 0,
										'harga_jual_ref' => $r['hargaJualRef'] ?? 0,
										'supplier_nama' => $r['supplierNama'] ?? null,
								]);
							}
						}

						if ($tipe === OrderType::Layanan && isset($data['layananDetail'])) {
							$order->layananDetail()->delete();
								$ld = $data['layananDetail'];
								$order->layananDetail()->create([
										'nama_tamu' => $ld['namaTamu'] ?? '',
										'hotel_drop_off' => $ld['hotelDropOff'] ?? null,
										'pick_up_point' => $ld['pickUpPoint'] ?? null,
										'drop_off_point' => $ld['dropOffPoint'] ?? null,
										'jam_jemput' => $ld['jamJemput'] ?? null,
										'info_flight' => $ld['infoFlight'] ?? null,
										'jumlah_pax' => $ld['jumlahPax'] ?? 1,
										'biaya_modal' => $ld['biayaModal'] ?? 0,
										'supplier_nama' => $ld['supplierNama'] ?? null,
								]);
						}

						$order->events()->create(['kind' => 'created', 'actor_id' => $creator->id, 'at' => now()]);
						$order->events()->create(['kind' => 'confirmed', 'actor_id' => $creator->id, 'at' => now()]);

						$assignedSupplierNames = collect($assignmentsInput)
								->filter(fn ($a) => ! empty($a['supplierId']))
								->map(fn ($a) => Supplier::find($a['supplierId'])?->nama)
								->filter()
								->unique()
								->implode(', ');

						// if ($costedAssignments->isNotEmpty()) {
						// 		$order->events()->create([
						// 				'kind' => 'assigned',
						// 				'actor_id' => $creator->id,
						// 				'at' => now(),
						// 				'detail' => $assignedSupplierNames ?: null,
						// 		]);
						// }

						$order->load(['itineraryDays.activities', 'ticketRows', 'addOns', 'layananDetail', 'assignments.vehicle', 'assignments.supplier']);

						$invoice = Invoice::where('order_id', $order->id)->first();
						$invoice = $this->createInvoiceFor($order, $creator, $assignedSupplierNames, $invoice);

						return $order->fresh(['client', 'assignments', 'guests', 'itineraryDays.activities', 'addOns', 'ticketRows', 'layananDetail', 'events', 'invoices.lines']);
				});
		}

    /** Mirrors legModal() from src/lib/orderDraft.ts. */
    private function legModal(OrderType $tipe, array $data): int
    {
        return match ($tipe) {
            OrderType::Tour => collect($data['itinerary'] ?? [])
                ->flatMap(fn ($d) => $d['aktivitas'] ?? [])
                ->sum(fn ($a) => $a['biaya'] ?? 0),
            OrderType::Ticket => collect($data['ticketRows'] ?? [])
                ->sum(fn ($r) => ($r['qty'] ?? 1) * ($r['modalSatuan'] ?? 0)),
            OrderType::Layanan => $data['layananDetail']['biayaModal'] ?? 0,
        };
    }

    /** Mirrors buildOrderLines() + the Add-On/Transport line builders in OrderReview.tsx's confirm(). */
    private function createInvoiceFor(Order $order, User $creator, string $assignedSupplierNames, Invoice $invoice = null): Invoice
    {
        $lines = [];

        $lines = [...$lines, ...match ($order->tipe) {
            OrderType::Ticket => $order->ticketRows->map(fn ($r) => [
                'deskripsi' => $r->nama_tiket,
                'qty' => $r->qty,
                'modal' => $r->modal_satuan,
                'harga_jual' => 0,
                'supplier_nama' => $r->supplier_nama,
            ])->all(),
            OrderType::Tour => $this->tourActivityLines($order),
            OrderType::Layanan => [[
                'deskripsi' => $this->mainLineLabel($order),
                'qty' => 1,
                'modal' => $order->layananDetail?->biaya_modal ?? 0,
                'harga_jual' => 0,
                'supplier_nama' => $order->layananDetail?->supplier_nama,
            ]],
        }];

        foreach ($order->addOns as $a) {
            $lines[] = [
                'deskripsi' => 'Add-On: '.($a->product?->nama ?? 'Produk'),
                'qty' => $a->qty,
                'modal' => $a->modal,
                'harga_jual' => 0,
                'supplier_nama' => $a->supplier_nama,
                'skip_biaya' => $a->skip_biaya,
            ];
        }

        foreach ($this->transportInvoiceLines($order) as $line) {
            $lines[] = $line;
        }

				if(empty($invoice))
        	$invoice = Invoice::create([
            'nomor' => $this->numbering->nextInvoiceNomor($order),
            'order_id' => $order->id,
            'client_id' => $order->client_id,
            'total' => 0,
            'sisa' => 0,
            'tanggal_dibuat' => now()->toDateString(),
            'status' => 'Belum Ditagihkan',
            'catatan' => 'Dibuat otomatis dari Order Review & Konfirmasi — isi Harga Jual sebelum download Excel.',
        	]);

        foreach ($lines as $line) {
					$invoice->lines()->delete();
          $invoice->lines()->create($line);
        }

				$invoice->events()->delete();
        $invoice->events()->create(['kind' => 'created', 'actor_id' => $creator->id, 'at' => now()]);
        if ($assignedSupplierNames) {
            $invoice->events()->create(['kind' => 'assigned', 'actor_id' => $creator->id, 'at' => now(), 'detail' => $assignedSupplierNames]);
        }

        return $invoice;
    }

    private function mainLineLabel(Order $order): string
    {
        if ($order->tipe === OrderType::Tour) {
            return "Paket Tur — {$order->nama_order}";
        }

        $label = match ($order->sub_tipe?->value) {
            'check_in' => 'Check In',
            'check_out' => 'Check Out',
            'transfer' => 'Transfer',
            default => 'Layanan',
        };

        return "{$label} — ".($order->layananDetail?->nama_tamu ?: '—');
    }

    /** One line per itinerary activity — never one lumped "Paket Tur" line, so Modal/Supplier Tur/Harga Jual all stay traceable. */
    private function tourActivityLines(Order $order): array
    {
        $multiDay = $order->itineraryDays->count() > 1;
        $lines = [];

        foreach ($order->itineraryDays as $day) {
            foreach ($day->activities as $act) {
                $lines[] = [
                    'deskripsi' => $multiDay ? "Hari {$day->hari} — {$act->aktivitas}" : ($act->aktivitas ?: 'Aktivitas'),
                    'qty' => 1,
                    'modal' => $act->biaya,
                    'harga_jual' => 0,
                    'supplier_nama' => $act->supplier_tur,
                    'skip_biaya' => $act->skip_biaya,
                ];
            }
        }

        return $lines;
    }

    /** One line per costed vehicle assignment, "#N" suffix only when a day carries more than one. */
    public function transportInvoiceLines(Order $order): array
    {
        $costed = $order->assignments->filter(fn ($a) => $a->biaya_transport_modal > 0);
        $multiDay = $order->assignmentDayCount() > 1;
        $lines = [];

        foreach ($costed->groupBy('hari') as $hari => $rowsForDay) {
            foreach ($rowsForDay->values() as $idx => $a) {
                $label = $multiDay ? "Transport Hari {$hari}" : 'Transport';
                $suffix = $rowsForDay->count() > 1 ? ' #'.($idx + 1) : '';
                $vehicleName = $a->vehicle?->nama ?? Vehicle::find($a->vehicle_id)?->nama ?? 'Mobil';
                $supplierName = $a->supplier?->nama ?? Supplier::find($a->supplier_id)?->nama;

                $lines[] = [
                    'deskripsi' => "{$label}{$suffix} — {$vehicleName} + Driver",
                    'qty' => 1,
                    'modal' => $a->biaya_transport_modal,
                    'harga_jual' => 0,
                    'supplier_nama' => $supplierName,
                ];
            }
        }

        return $lines;
    }
}
