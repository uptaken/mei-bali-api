<?php

namespace App\Http\Controllers\Api;

use App\Enums\OrderStatus;
use App\Enums\PayableStatus;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Endpoint order: daftar, detail, buat, ubah, selesai, dan alur pembatalan.
 * Aturan bisnis (kode, total, status, invoice) ada di OrderService; controller ini hanya
 * memvalidasi input, memeriksa hak akses/status, lalu memanggil service.
 */
class OrderController extends Controller
{
    /** Relasi yang selalu dimuat saat order dikirim ke frontend, agar respons create/update/show seragam. */
    public const WITH = [
        'client', 'assignments.vehicle', 'assignments.supplier', 'guests',
        'itineraryDays.activities', 'addOns.product', 'ticketRows', 'layananDetail',
        'events.actor', 'invoices.lines',
    ];

    public function __construct(private readonly OrderService $orderService) {}

    /**
     * GET /api/orders — daftar order berpaginasi (?per_page=). Filter sama dengan OrderList.tsx:
     * tipe, status, supplier_id, date_from/date_to (tanggal mulai) dan q (kode, kode grup, nama, destinasi).
     * Setiap baris juga diberi `items` (rincian modal yang menjadi dasar tagihan supplier), `totalItems`
     * dan `statusItems` (Bayar/Belum Bayar) supaya layar daftar tidak perlu menghitung ulang.
     */
    public function index(Request $request)
    {
        $q = trim((string) $request->query('q', ''));

        $arrOrder = Order::query()
            ->with(['client', 'supplier', 'vehicle', 'assignments', 'events' => fn ($query) => $query->select('id', 'order_id', 'kind', 'actor_id', 'at', 'detail')->orderBy('id'), 'itineraryDays' => function ($query) {
							$query->select('id', 'order_id', 'hari', 'waktu_penjemputan', 'tempat_penjemputan', 'waktu_drop_akhir', 'tempat_drop_akhir',)
								->with([ 'activities', ])
								->withSum('activities as totalBiaya', 'biaya');
						}, 'addOns', 'ticketRows', 'guests', 'layananDetail', 'invoices' => function ($query) {
							$query->select('id', 'order_id', 'total', 'sisa', 'status', 'catatan',)
								->with([ 'lines' => function ($query) {
									$query->select('id', 'invoice_id', 'qty', 'modal', 'harga_jual', 'supplier_nama', 'deskripsi', 'skip_biaya');
								}, 'events' => function ($query) {
										$query->select('id', 'invoice_id', 'kind', 'actor_id', 'at', 'detail',);
								}, ]);
						},])
            ->when($request->filled('tipe'), fn ($query) => $query->where('tipe', $request->query('tipe')))
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->query('status')))
            ->when($request->filled('supplier_id'), fn ($query) => $query->where('supplier_id', $request->query('supplier_id')))
            ->when($request->filled('date_from'), fn ($query) => $query->whereDate('tanggal_mulai', '>=', $request->query('date_from')))
            ->when($request->filled('date_to'), fn ($query) => $query->whereDate('tanggal_mulai', '<=', $request->query('date_to')))
            ->when($q !== '', fn ($query) => $query->where(fn ($w) => $w
                ->where('kode', 'like', "%{$q}%")
                ->orWhere('kode_group', 'like', "%{$q}%")
                ->orWhere('nama_order', 'like', "%{$q}%")
                ->orWhere('destinasi', 'like', "%{$q}%")))
            ->latest()
            ->paginate($request->integer('per_page', 20));

				foreach($arrOrder as $order){

					// Alias camelCase untuk field yang dibaca frontend (kolom di database memakai snake_case).
					$order->bahasaId = $order->bahasa_id;
					$order->clientId = $order->client_id;
					if(!empty($order->layananDetail)){
						$order->layananDetail->namaTamu = $order->layananDetail->nama_tamu;
						$order->layananDetail->hotelDropOff = $order->layananDetail->hotel_drop_off;
						$order->layananDetail->pickUpPoint = $order->layananDetail->pick_up_point;
						$order->layananDetail->dropOffPoint = $order->layananDetail->drop_off_point;
						$order->layananDetail->jamJemput = $order->layananDetail->jam_jemput;
						$order->layananDetail->infoFlight = $order->layananDetail->info_flight;
						$order->layananDetail->jumlahPax = $order->layananDetail->jumlah_pax;
						$order->layananDetail->biayaModal = $order->layananDetail->biaya_modal;
						$order->layananDetail->supplierNama = $order->layananDetail->supplier_nama;
						$order->layananDetail->bayarStatus = $order->layananDetail->bayar_status;
						$order->layananDetail->tanggalBayar = $order->layananDetail->tanggal_bayar;
						// $order->layananDetail = $order->layanan_detail;
					}

					if(!empty($order->assignments)){
						foreach($order->assignments as $temp)
							$temp->biayaTransportModal = $temp->biaya_transport_modal;
						// $order->layananDetail = $order->layanan_detail;
					}


					// Gabungkan semua sumber modal order (transport, tur, add-on, tiket, layanan) menjadi satu daftar `items`.
					$arrTemp = collect();
					$arrTemp = $arrTemp->merge($order->assignments);
					$arrTemp = $arrTemp->merge($order->itineraryDays);
					$arrTemp = $arrTemp->merge($order->addOns);
					$arrTemp = $arrTemp->merge($order->ticketRows);
					if(!empty($order->layananDetail))
						$arrTemp = $arrTemp->push($order->layananDetail);


					// Total modal semua rincian; status "Belum Bayar" bila ada satu saja rincian yang belum dibayar.
					$totalItems = 0;
					$statusItems = PayableStatus::Bayar;
					foreach($arrTemp as $temp){
						if($temp->bayar_status == PayableStatus::BelumBayar)
							$statusItems = PayableStatus::BelumBayar;


						if(isset($temp->biaya_transport_modal)){
							$totalItems += $temp->biaya_transport_modal;
							$temp->modal = $temp->biaya_transport_modal;
							$temp->detail = 'Transport: '.(!empty($temp->vehicle) && !empty($temp->supplier) ? $temp->vehicle->nama.' - '.$temp->supplier->nama : '-');
						}
						else if(isset($temp->modal)){
							$totalItems += $temp->modal;
							// $temp->modal = $temp->biaya_transport_modal;
							$temp->detail = 'Add Ons: '.(!empty($temp->product) ? $temp->qty.'x '.$temp->product->nama : '-');
						}
						else if(isset($temp->modal_satuan)){
							$totalItems += $temp->qty * $temp->modal_satuan;
							$temp->modal = $temp->qty * $temp->modal_satuan;
							$temp->detail = 'Tiket: '.(!empty($temp->tiket) ? $temp->nama_tiket.' - '.$temp->tiket->nama : '-');
						}
						else if(isset($temp->totalBiaya)){
							$totalItems += $temp->totalBiaya;
							$temp->modal = $temp->totalBiaya;

							$str = '';
							foreach($temp->activities as $key => $activities)
								$str .= ($key > 0 ? ', ' : '').$activities->aktivitas;

							$temp->detail = 'Aktifitas: '.$str;
						}
					}


					$order->items = $arrTemp;
					$order->totalItems = $totalItems;
					$order->statusItems = $statusItems;
				}


				return $arrOrder;
    }

    /** GET /api/orders/{order} — satu order lengkap dengan semua relasinya. */
    public function show(Order $order)
    {
        return $order->load(self::WITH);
    }

    /**
     * Validation rules shared by store() and update().
     *
     * validate() only returns keys that have a rule, so every nested field the frontend sends must be
     * listed here — anything missing is silently dropped before it reaches OrderService.
     * On create the order's identity fields are required; on update they may be omitted.
     */
    private function orderRules(bool $isCreate): array
    {
        $required = $isCreate ? 'required' : 'nullable';

        return [
            'tipe' => [$required, 'in:tour,layanan,ticket'],
            'subTipe' => ['nullable', 'in:check_in,check_out,transfer'],
            'clientId' => [$required, 'exists:clients,id'],
            'namaOrder' => [$required, 'string'],
            'kodeGroup' => [$required, 'string'],
            'tanggalMulai' => ['nullable', 'date'],
            'jamMulai' => ['nullable', 'string'],
            'durasiHari' => ['nullable', 'integer', 'min:1'],
            'destinasi' => ['nullable', 'string'],
            'bahasaId' => ['nullable', 'string'],
            'kota' => ['nullable', 'string'],
            'kotaTermasuk' => ['nullable', 'array'],
            'paketId' => ['nullable', 'string'],
            'dewasa' => ['nullable', 'integer', 'min:0'],
            'anak' => ['nullable', 'integer', 'min:0'],
            'catatan' => ['nullable', 'string'],
            'tanggalPemakaian' => ['nullable', 'date'],

            'tamu' => ['array'],
            'tamu.*.nama' => ['nullable', 'string'],
            'tamu.*.kategori' => ['required_with:tamu', 'in:Dewasa,Anak'],

            'assignments' => ['array'],
            'assignments.*.id' => ['nullable'],
            'assignments.*.hari' => ['nullable', 'integer', 'min:1'],
            'assignments.*.vehicleId' => ['nullable', 'exists:vehicles,id'],
            'assignments.*.supplierId' => ['nullable', 'exists:suppliers,id'],
            'assignments.*.biayaTransportModal' => ['nullable', 'integer', 'min:0'],

            'itinerary' => ['array'],
            'itinerary.*.hari' => ['required_with:itinerary', 'integer', 'min:1'],
            'itinerary.*.waktuPenjemputan' => ['nullable', 'string'],
            'itinerary.*.tempatPenjemputan' => ['nullable', 'string'],
            'itinerary.*.waktuDropAkhir' => ['nullable', 'string'],
            'itinerary.*.tempatDropAkhir' => ['nullable', 'string'],
            'itinerary.*.catatan' => ['nullable', 'string'],
            'itinerary.*.aktivitas' => ['array'],
            'itinerary.*.aktivitas.*.id' => ['nullable'],
            'itinerary.*.aktivitas.*.aktivitas' => ['nullable', 'string'],
            'itinerary.*.aktivitas.*.biaya' => ['nullable', 'integer', 'min:0'],
            'itinerary.*.aktivitas.*.skipBiaya' => ['nullable', 'boolean'],
            'itinerary.*.aktivitas.*.supplierTur' => ['nullable', 'string'],

            'addOns' => ['array'],
            'addOns.*.id' => ['nullable'],
            'addOns.*.produkId' => ['nullable', 'exists:products,id'],
            'addOns.*.qty' => ['nullable', 'integer', 'min:1'],
            'addOns.*.modal' => ['nullable', 'integer', 'min:0'],
            'addOns.*.catatan' => ['nullable', 'string'],
            'addOns.*.skipBiaya' => ['nullable', 'boolean'],
            'addOns.*.supplierNama' => ['nullable', 'string'],

            'ticketRows' => ['array'],
            'ticketRows.*.id' => ['nullable'],
            'ticketRows.*.tiketId' => ['nullable'],
            'ticketRows.*.namaTiket' => ['required_with:ticketRows', 'string'],
            'ticketRows.*.qty' => ['nullable', 'integer', 'min:1'],
            'ticketRows.*.modalSatuan' => ['nullable', 'integer', 'min:0'],
            'ticketRows.*.hargaJualRef' => ['nullable', 'integer', 'min:0'],
            'ticketRows.*.supplierNama' => ['nullable', 'string'],

            'layananDetail' => ['nullable', 'array'],
        ];
    }

    /** POST /api/orders — ports OrderReview.tsx's confirm(). Only Admin/Super Admin (role middleware on the route). */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate($this->orderRules(isCreate: true));

        $order = $this->orderService->create($data, $request->user());

        return response()->json($order->load(self::WITH), 201);
    }

    /** PATCH /api/orders/{order} — menu Ubah: seluruh isi order disinkronkan lewat OrderService. Tipe dan subTipe tidak bisa diganti, sama seperti di frontend. */
    public function update(Request $request, Order $order): Order
    {
        $data = $request->validate($this->orderRules(isCreate: false));

				$order = $this->orderService->update($order, $data, $request->user());

        return $order->load(self::WITH);
    }

		/** PATCH /api/orders/lines/{order} — ubah cepat field induk saja (tanpa tamu, itinerary, add-on, atau invoice). */
		public function updateLines(Request $request, Order $order): Order
		{
				$data = $request->validate([
				    'namaOrder' => ['sometimes', 'string'],
				    'kodeGroup' => ['sometimes', 'string'],
				    'tanggalMulai' => ['nullable', 'date'],
				    'jamMulai' => ['nullable', 'string'],
				    'destinasi' => ['nullable', 'string'],
				    'bahasaId' => ['nullable', 'string'],
				    'kota' => ['nullable', 'string'],
				    'dewasa' => ['nullable', 'integer', 'min:0'],
				    'anak' => ['nullable', 'integer', 'min:0'],
				    'catatan' => ['nullable', 'string'],
				]);

				$map = [
				    'namaOrder' => 'nama_order', 'kodeGroup' => 'kode_group', 'tanggalMulai' => 'tanggal_mulai',
				    'jamMulai' => 'jam_mulai', 'destinasi' => 'destinasi', 'bahasaId' => 'bahasa_id',
				    'kota' => 'kota', 'dewasa' => 'dewasa', 'anak' => 'anak', 'catatan' => 'catatan',
						'tamu' => 'guests', 'subTipe' => 'sub_tipe',
				];
				$patch = collect($data)->mapWithKeys(function ($v, $k) use($map) {
					return !empty($map[$k]) ? [$map[$k] => $v] : [$k => $v];
				})->all();
				$patch['updated_by'] = $request->user()->id;


				$order->update($patch);

				return $order->load(self::WITH);
		}

    /** POST /api/orders/{order}/selesai — hanya untuk order yang sedang Berlangsung; dicatat di Riwayat. */
    public function markSelesai(Request $request, Order $order): Order
    {
        abort_unless($order->status === OrderStatus::Berlangsung, 422, 'Order belum berstatus Berlangsung.');

        $order->update(['status' => OrderStatus::Selesai->value, 'updated_by' => $request->user()->id]);
        $order->events()->create(['kind' => 'selesai', 'actor_id' => $request->user()->id, 'at' => now()]);

        return $order->load(self::WITH);
    }

    /**
     * POST /api/orders/{order}/cancel — Super Admin cancels immediately; anyone else queues a
     * pending request that only a Super Admin can approve (this same call) or reject.
     */
    public function cancel(Request $request, Order $order): Order
    {
        $data = $request->validate(['alasan' => ['nullable', 'string']]);
        $user = $request->user();

        abort_if(
            in_array($order->status, [OrderStatus::Selesai, OrderStatus::Dibatalkan], true),
            422,
            'Order yang sudah selesai atau dibatalkan tidak bisa dibatalkan.',
        );

        if ($user->role->canApproveCancellation()) {
            $order->update([
                'status' => OrderStatus::Dibatalkan->value,
                'pending_cancellation' => false,
                'updated_by' => $user->id,
            ]);
            $order->invoices()->update(['sisa' => 0]);
            $kind = 'cancel_approved';
        } else {
            abort_unless($user->role->canCreateOrder(), 403);
            abort_if($order->pending_cancellation, 422, 'Pembatalan order ini sudah diajukan.');

            $order->update(['pending_cancellation' => true, 'updated_by' => $user->id]);
            $kind = 'cancel_requested';
        }

        $order->events()->create(['kind' => $kind, 'actor_id' => $user->id, 'at' => now(), 'detail' => $data['alasan'] ?? null]);

        return $order->load(self::WITH);
    }

    /** POST /api/orders/{order}/reject-cancellation — Super Admin only; the Order's status was never touched, so only the pending flag clears. */
    public function rejectCancellation(Request $request, Order $order): Order
    {
        $user = $request->user();
        abort_unless($user->role->canApproveCancellation(), 403);
        abort_unless($order->pending_cancellation, 422, 'Order ini tidak punya permintaan pembatalan.');

        $order->update(['pending_cancellation' => false, 'updated_by' => $user->id]);
        $order->events()->create(['kind' => 'cancel_rejected', 'actor_id' => $user->id, 'at' => now()]);

        return $order->load(self::WITH);
    }
}
