<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Language;
use Illuminate\Http\Request;

class LanguageController extends Controller
{
    public function index(Request $request)
    {
			$arr = Language::query()->orderBy('nama')->get();
			// foreach($arr as $temp)
			// 	$temp->kota = json_decode($temp->kota, true);

      return $arr;
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'kode' => ['required', 'string',],
            'nama' => ['required', 'string'],
            'catatan' => ['nullable',],
        ]);

				$temp = Language::create($data);
				// $temp->kota = json_decode($temp->kota, true);
        return response()->json($temp, 201);
    }

    public function show(Language $language)
    {
        return $language;
    }

    public function update(Request $request, Language $language)
    {
        $data = $request->validate([
            'kode' => ['sometimes', 'string',],
            'nama' => ['sometimes', 'string'],
            'catatan' => ['nullable',],
        ]);

        $language->update($data);
				// $language->kota = json_decode($language->kota, true);
        return $language;
    }

    public function destroy(Language $language)
    {
        $language->delete();

        return response()->json(status: 204);
    }
}
