<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\OrderOperationalService;
use Illuminate\Http\Request;

/** Ports TourOperational.tsx / LayananOperational.tsx's persisted-mode save(). */
class OrderOperationalController extends Controller
{
    public function __construct(private readonly OrderOperationalService $service) {}

    /** PATCH /api/orders/{order}/operational */
    public function update(Request $request, Order $order): Order
    {
        $data = $request->validate([
            'assignments' => ['sometimes', 'array'],
            'assignments.*.hari' => ['nullable', 'integer', 'min:1'],
            'assignments.*.vehicleId' => ['nullable', 'exists:vehicles,id'],
            'assignments.*.supplierId' => ['nullable', 'exists:suppliers,id'],
            'assignments.*.biayaTransportModal' => ['nullable', 'integer', 'min:0'],
            'addOns' => ['sometimes', 'array'],
            'addOns.*.id' => ['nullable', 'integer', 'exists:order_add_ons,id'],
            'addOns.*.produkId' => ['nullable', 'exists:products,id'],
            'addOns.*.qty' => ['nullable', 'integer', 'min:1'],
            'addOns.*.modal' => ['nullable', 'integer', 'min:0'],
            'addOns.*.supplierNama' => ['nullable', 'string'],
            'addOns.*.skipBiaya' => ['nullable', 'boolean'],
            'itinerary' => ['sometimes', 'array'],
            'itinerary.*.hari' => ['required_with:itinerary', 'integer', 'min:1'],
            'itinerary.*.aktivitas' => ['array'],
            'itinerary.*.aktivitas.*.id' => ['required', 'integer', 'exists:order_itinerary_activities,id'],
            'itinerary.*.aktivitas.*.biaya' => ['nullable', 'integer', 'min:0'],
            'itinerary.*.aktivitas.*.skipBiaya' => ['nullable', 'boolean'],
            'itinerary.*.aktivitas.*.supplierTur' => ['nullable', 'string'],
        ]);

        return $this->service->update($order, $data, $request->user());
    }
}
