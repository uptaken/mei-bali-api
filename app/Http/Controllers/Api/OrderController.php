<?php

namespace App\Http\Controllers\Api;

use App\Enums\OrderStatus;
use App\Enums\PayableStatus;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    private const WITH = [
        'client', 'assignments.vehicle', 'assignments.supplier', 'guests',
        'itineraryDays.activities', 'addOns.product', 'ticketRows', 'layananDetail',
        'events.actor', 'invoices.lines',
    ];

    public function __construct(private readonly OrderService $orderService) {}

    /** GET /api/orders — mirrors OrderList.tsx's filter set. */
    public function index(Request $request)
    {
        $q = trim((string) $request->query('q', ''));

        $arrOrder = Order::query()
            ->with(['client', 'supplier', 'vehicle', 'assignments', 'itineraryDays' => function ($query) {
							$query->select('id', 'order_id', 'hari', 'waktu_penjemputan', 'tempat_penjemputan', 'waktu_drop_akhir', 'tempat_drop_akhir',)
								->with([ 'activities', ])
								->withSum('activities as totalBiaya', 'biaya');
						}, 'addOns', 'ticketRows', 'guests', 'layananDetail', 'invoices' => function ($query) {
							$query->select('id', 'order_id', 'total', 'sisa', 'status', 'catatan',)
								->with([ 'lines' => function ($query) {
									$query->select('id', 'invoice_id', 'qty', 'modal', 'harga_jual', 'supplier_nama', 'deskripsi');
								}, 'events' => function ($query) {
										$query->select('id', 'invoice_id', 'kind', 'actor_id as actorId', 'at',);
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


					$arrTemp = collect();
					$arrTemp = $arrTemp->merge($order->assignments);
					$arrTemp = $arrTemp->merge($order->itineraryDays);
					$arrTemp = $arrTemp->merge($order->addOns);
					$arrTemp = $arrTemp->merge($order->ticketRows);
					if(!empty($order->layananDetail))
						$arrTemp = $arrTemp->push($order->layananDetail);


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

    public function show(Order $order)
    {
        return $order->load(self::WITH);
    }

    /** POST /api/orders — ports OrderReview.tsx's confirm(). Only Admin/Super Admin (role middleware on the route). */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'tipe' => ['required', 'in:tour,layanan,ticket'],
            'subTipe' => ['nullable', 'in:check_in,check_out,transfer'],
            'clientId' => ['required', 'exists:clients,id'],
            'namaOrder' => ['required', 'string'],
            'kodeGroup' => ['required', 'string'],
            'tanggalMulai' => ['nullable', 'date'],
            'jamMulai' => ['nullable', 'string'],
            'destinasi' => ['nullable', 'string'],
            'bahasaId' => ['nullable', 'string'],
            'kota' => ['nullable', 'string'],
            'kotaTermasuk' => ['nullable', 'array'],
            'paketId' => ['nullable', 'string'],
            'dewasa' => ['nullable', 'integer', 'min:0'],
            'anak' => ['nullable', 'integer', 'min:0'],
            'catatan' => ['nullable', 'string'],
            'tanggalPemakaian' => ['nullable', 'date'],
            'guests' => ['array'],
            'guests.*.nama' => ['nullable', 'string'],
            'guests.*.kategori' => ['required_with:guests', 'in:Dewasa,Anak'],
            'assignments' => ['array'],
            'assignments.*.hari' => ['nullable', 'integer', 'min:1'],
            'assignments.*.vehicleId' => ['nullable', 'exists:vehicles,id'],
            'assignments.*.supplierId' => ['nullable', 'exists:suppliers,id'],
            'assignments.*.biayaTransportModal' => ['nullable', 'integer', 'min:0'],
            'itinerary' => ['array'],
            'itinerary.*.hari' => ['required_with:itinerary', 'integer', 'min:1'],
            'itinerary.*.aktivitas' => ['array'],
            'addOns' => ['array'],
            'addOns.*.produkId' => ['nullable', 'exists:products,id'],
            'addOns.*.qty' => ['nullable', 'integer', 'min:1'],
            'addOns.*.modal' => ['nullable', 'integer', 'min:0'],
            'ticketRows' => ['array'],
            'ticketRows.*.namaTiket' => ['required_with:ticketRows', 'string'],
            'layananDetail' => ['nullable', 'array'],
        ]);

        $order = $this->orderService->create($data, $request->user());

        return response()->json($order->load(self::WITH), 201);
    }

    /** PATCH /api/orders/{order} — top-level field edits only (Ubah). Locked tipe/subTipe, like the frontend. */
    public function update(Request $request, Order $order): Order
    {
			$data = $request->validate([
				'tipe' => ['nullable', 'in:tour,layanan,ticket'],
				'subTipe' => ['nullable', 'in:check_in,check_out,transfer'],
				'clientId' => ['nullable', 'exists:clients,id'],
				'namaOrder' => ['nullable', 'string'],
				'kodeGroup' => ['nullable', 'string'],
				'tanggalMulai' => ['nullable', 'date'],
				'jamMulai' => ['nullable', 'string'],
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
				'tamu.*.kategori' => ['in:Dewasa,Anak'],
				'assignments' => ['array'],
				'assignments.*.hari' => ['nullable', 'integer', 'min:1'],
				'assignments.*.vehicleId' => ['nullable', 'exists:vehicles,id'],
				'assignments.*.supplierId' => ['nullable', 'exists:suppliers,id'],
				'assignments.*.biayaTransportModal' => ['nullable', 'integer', 'min:0'],
				'itinerary' => ['array'],
				'itinerary.*.hari' => ['nullable', 'integer', 'min:1'],
				'itinerary.*.aktivitas' => ['array'],
				'addOns' => ['array'],
				'addOns.*.produkId' => ['nullable', 'exists:products,id'],
				'addOns.*.qty' => ['nullable', 'integer', 'min:1'],
				'addOns.*.modal' => ['nullable', 'integer', 'min:0'],
				'ticketRows' => ['array'],
				'ticketRows.*.namaTiket' => ['required_with:ticketRows', 'string'],
				'layananDetail' => ['nullable', 'array'],
			]);
        // $data = $request->validate([
        //     'namaOrder' => ['sometimes', 'string'],
        //     'kodeGroup' => ['sometimes', 'string'],
        //     'tanggalMulai' => ['nullable', 'date'],
        //     'jamMulai' => ['nullable', 'string'],
        //     'destinasi' => ['nullable', 'string'],
        //     'bahasaId' => ['nullable', 'string'],
        //     'kota' => ['nullable', 'string'],
        //     'dewasa' => ['nullable', 'integer', 'min:0'],
        //     'anak' => ['nullable', 'integer', 'min:0'],
        //     'catatan' => ['nullable', 'string'],
        // ]);

        // $map = [
        //     'namaOrder' => 'nama_order', 'kodeGroup' => 'kode_group', 'tanggalMulai' => 'tanggal_mulai',
        //     'jamMulai' => 'jam_mulai', 'destinasi' => 'destinasi', 'bahasaId' => 'bahasa_id',
        //     'kota' => 'kota', 'dewasa' => 'dewasa', 'anak' => 'anak', 'catatan' => 'catatan',
				// 		'tamu' => 'guests', 'subTipe' => 'sub_tipe',
        // ];
        // $patch = collect($data)->mapWithKeys(function ($v, $k) use($map) {
				// 	return !empty($map[$k]) ? [$map[$k] => $v] : [$k => $v];
				// })->all();
        // $patch['updated_by'] = $request->user()->id;


				$order = $this->orderService->update($order, $data, $request->user());
        // $order->update($patch);

        return $order->load(self::WITH);
    }

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

    /** POST /api/orders/{order}/selesai */
    public function markSelesai(Request $request, Order $order): Order
    {
        abort_unless($order->status === OrderStatus::Berlangsung, 422, 'Order belum berstatus Berlangsung.');

        $order->update(['status' => OrderStatus::Selesai->value, 'updated_by' => $request->user()->id]);
        $order->events()->create(['kind' => 'selesai', 'actor_id' => $request->user()->id, 'at' => now()]);

        return $order->load(self::WITH);
    }

    /**
     * POST /api/orders/{order}/cancel — Super Admin cancels immediately; anyone else queues a
     * pending request that only a Super Admin can approve/reject (see ApprovalController).
     */
    public function cancel(Request $request, Order $order): Order
    {
        $user = $request->user();

        if ($user->role->canApproveCancellation()) {
            $order->update([
                'status' => OrderStatus::Dibatalkan->value,
                'pending_cancellation' => false,
                'updated_by' => $user->id,
            ]);
            $order->invoices()->update(['sisa' => 0]);
            $order->events()->create(['kind' => 'cancel_approved', 'actor_id' => $user->id, 'at' => now()]);
        } else {
            abort_unless($user->role->canCreateOrder(), 403);
            $order->update(['pending_cancellation' => true, 'updated_by' => $user->id]);
            $order->events()->create(['kind' => 'cancel_requested', 'actor_id' => $user->id, 'at' => now()]);
        }

        return $order->load(self::WITH);
    }
}
