<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Package;
use Illuminate\Http\Request;

class PackageController extends Controller
{
    public function index(Request $request)
    {
			$arr = Package::query()->orderBy('nama')->get();
			foreach($arr as $temp){
				$temp->kotaTermasuk = json_decode($temp->kotaTermasuk, true);
				$temp->hari = json_decode($temp->hari, true);
			}

      return $arr;
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            // 'kode' => ['required', 'string',],
            'nama' => ['required', 'string'],
						'durasiHari' => ['integer', ],
						'hargaMulai' => ['integer', ],
						'kotaTermasuk' => ['nullable', ],
						'deskripsi' => ['nullable', ],
            'hari' => ['nullable',],
        ]);

				$temp = Package::create($data);
				$temp->kotaTermasuk = json_decode($temp->kotaTermasuk, true);
				$temp->hari = json_decode($temp->hari, true);
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
            'nama' => ['required', 'string'],
						'durasiHari' => ['integer', ],
						'hargaMulai' => ['integer', ],
						'kotaTermasuk' => ['nullable', ],
						'deskripsi' => ['nullable', ],
						'hari' => ['nullable',],
        ]);

        $package->update($data);
				$package->kotaTermasuk = json_decode($package->kotaTermasuk, true);
				$package->hari = json_decode($package->hari, true);

        return $package;
    }

    public function destroy(Package $package)
    {
        $package->delete();

        return response()->json(status: 204);
    }
}
