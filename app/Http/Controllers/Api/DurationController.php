<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Duration;
use Illuminate\Http\Request;

class DurationController extends Controller
{
    public function index(Request $request)
    {
			$arr = Duration::query()->orderBy('label')->get();
			// foreach($arr as $temp)
			// 	$temp->kota = json_decode($temp->kota, true);

      return $arr;
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'label' => ['required', 'string',],
            'perkiraanJam' => ['required', 'string'],
            'catatan' => ['nullable',],
        ]);

				$temp = Duration::create($data);
				// $temp->kota = json_decode($temp->kota, true);
        return response()->json($temp, 201);
    }

    public function show(Duration $duration)
    {
        return $duration;
    }

    public function update(Request $request, Duration $duration)
    {
        $data = $request->validate([
            'label' => ['sometimes', 'string',],
            'perkiraanJam' => ['sometimes', 'string'],
            'catatan' => ['nullable',],
        ]);

        $duration->update($data);
				// $duration->kota = json_decode($duration->kota, true);
        return $duration;
    }

    public function destroy(Duration $duration)
    {
        $duration->delete();

        return response()->json(status: 204);
    }
}
