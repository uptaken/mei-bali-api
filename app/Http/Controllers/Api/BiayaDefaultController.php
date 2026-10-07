<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BiayaDefault;
use Illuminate\Http\Request;

class BiayaDefaultController extends Controller
{
    public function index(Request $request)
    {
			$arr = BiayaDefault::query()->orderBy('tipe')->get();
			// foreach($arr as $temp)
			// 	$temp->kota = json_decode($temp->kota, true);

      return $arr;
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'tipe' => ['required', 'string',],
            'ruteKategori' => ['required', 'string',],
            'biayaDefault' => ['nullable', 'integer',],
        ]);

				$temp = BiayaDefault::create($data);
				// $temp->kota = json_decode($temp->kota, true);
        return response()->json($temp, 201);
    }

    public function show(BiayaDefault $biaya_default)
    {
        return $biaya_default;
    }

    public function update(Request $request, BiayaDefault $biaya_default)
    {
        $data = $request->validate([
            'tipe' => ['required', 'string',],
						'ruteKategori' => ['required', 'string',],
						'biayaDefault' => ['nullable', 'integer',],
        ]);

        $biaya_default->update($data);
				// $biaya_default->kota = json_decode($biaya_default->kota, true);
        return $biaya_default;
    }

    public function destroy(BiayaDefault $biaya_default)
    {
        $biaya_default->delete();

        return response()->json(status: 204);
    }
}
