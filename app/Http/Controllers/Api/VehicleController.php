<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\RefusesDeleteWhenUsed;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Vehicle;
use Illuminate\Http\Request;

class VehicleController extends Controller
{
    use RefusesDeleteWhenUsed;

    public function index()
    {
        return Vehicle::orderBy('nama')->get();
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'nama' => ['required', 'string'],
            'kapasitas' => ['integer', ],
						'kategori' => ['nullable', 'string'],
						'catatan' => ['nullable', 'string'],
        ]);

        return response()->json(Vehicle::create($data), 201);
    }

    public function update(Request $request, Vehicle $vehicle)
    {
        $data = $request->validate([
            'nama' => ['sometimes', 'string'],
            'kapasitas' => ['integer', ],
						'kategori' => ['nullable', 'string'],
						'catatan' => ['nullable', 'string'],
        ]);

        $vehicle->update($data);

        return $vehicle;
    }

    public function destroy(Vehicle $vehicle)
    {
        $this->refuseIfUsed('Mobil', $vehicle->nama, ['order' => Order::where('vehicle_id', $vehicle->id)->orWhereHas('assignments', fn ($q) => $q->where('vehicle_id', $vehicle->id))->count()], canDeactivate: true);
        $vehicle->delete();

        return response()->json(status: 204);
    }
}
