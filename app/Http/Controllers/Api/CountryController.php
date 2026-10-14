<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Country;
use Illuminate\Http\Request;

class CountryController extends Controller
{
    public function index(Request $request)
    {
        return Country::query()->orderBy('nama')->get();
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'kode' => ['required', 'string', 'max:255', 'unique:countries,kode'],
            'nama' => ['required', 'string', 'max:255'],
            'kota' => ['nullable',],
        ]);

				$temp = Country::create($data);
        return response()->json($temp, 201);
    }

    public function show(Country $country)
    {
        return $country;
    }

    public function update(Request $request, Country $country)
    {
        $data = $request->validate([
            'kode' => ['sometimes', 'string', 'max:255', 'unique:countries,kode,'.$country->id],
            'nama' => ['sometimes', 'string', 'max:255'],
            'kota' => ['nullable',],
        ]);

        $country->update($data);
        return $country;
    }

    public function destroy(Country $country)
    {
        $country->delete();

        return response()->json(status: 204);
    }
}
