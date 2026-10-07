<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Guest;
use Illuminate\Http\Request;

class GuestController extends Controller
{
    public function index(Request $request)
    {
			$arr = Guest::query()->orderBy('nama')->get();
			// foreach($arr as $temp)
			// 	$temp->kota = json_decode($temp->kota, true);

      return $arr;
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'nama' => ['required', 'string',],
            'kategori' => ['required', 'string',],
            'clientId' => ['nullable', 'string',],
						'negara' => ['nullable', 'string',],
						'bahasa' => ['nullable', 'string',],
						'paspor' => ['nullable', 'string',],
						'catatan' => ['nullable', 'string',],
        ]);

				$temp = Guest::create($data);
				// $temp->kota = json_decode($temp->kota, true);
        return response()->json($temp, 201);
    }

    public function show(Guest $guest)
    {
        return $guest;
    }

    public function update(Request $request, Guest $guest)
    {
        $data = $request->validate([
            'nama' => ['required', 'string',],
						'kategori' => ['required', 'string',],
						'clientId' => ['nullable', 'string',],
						'negara' => ['nullable', 'string',],
						'bahasa' => ['nullable', 'string',],
						'paspor' => ['nullable', 'string',],
						'catatan' => ['nullable', 'string',],
        ]);

        $guest->update($data);
				// $guest->kota = json_decode($guest->kota, true);
        return $guest;
    }

    public function destroy(Guest $guest)
    {
        $guest->delete();

        return response()->json(status: 204);
    }
}
