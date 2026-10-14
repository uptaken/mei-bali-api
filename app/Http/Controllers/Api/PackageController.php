<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\RefusesDeleteWhenUsed;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Package;
use Illuminate\Http\Request;

class PackageController extends Controller
{
    use RefusesDeleteWhenUsed;

    public function index(Request $request)
    {
        return Package::query()->orderBy('nama')->get();
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            // 'kode' => ['required', 'string',],
            'nama' => ['required', 'string', 'max:255'],
						'durasiHari' => ['integer', ],
						'hargaMulai' => ['integer', ],
						'kotaTermasuk' => ['nullable', ],
						'deskripsi' => ['nullable', ],
            'hari' => ['nullable',],
        ]);

				$temp = Package::create($data);
        return response()->json($temp, 201);
    }

    public function show(Package $country)
    {
        return $country;
    }

    public function update(Request $request, Package $package)
    {
        $data = $request->validate([
            // 'kode' => ['sometimes', 'string',],
            'nama' => ['required', 'string', 'max:255'],
						'durasiHari' => ['integer', ],
						'hargaMulai' => ['integer', ],
						'kotaTermasuk' => ['nullable', ],
						'deskripsi' => ['nullable', ],
						'hari' => ['nullable',],
        ]);

        $package->update($data);

        return $package;
    }

    public function destroy(Package $package)
    {
        $this->refuseIfUsed('Package', $package->nama, ['order' => Order::where('paket_id', (string) $package->id)->count()]);
        $package->delete();

        return response()->json(status: 204);
    }
}
