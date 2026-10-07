<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Tiket;
use Illuminate\Http\Request;

class TiketController extends Controller
{
    public function index()
    {
        return Tiket::orderBy('nama')->get();
    }

    public function store(Request $request)
    {
        return response()->json(Tiket::create($this->validated($request)), 201);
    }

    public function show(Tiket $tiket)
    {
        return $tiket;
    }

    public function update(Request $request, Tiket $tiket)
    {
        $tiket->update($this->validated($request, true));
        return $tiket;
    }

    public function destroy(Tiket $tiket)
    {
        $tiket->delete();
        return response()->json(status: 204);
    }

    private function validated(Request $request, bool $partial = false): array
    {
        $required = $partial ? 'sometimes' : 'required';
        return $request->validate([
            'nama' => [$required, 'string'],
            'kategori' => [$required, 'string'],
            'harga_jual' => [$required, 'integer', 'min:0'],
            'modal' => [$required, 'integer', 'min:0'],
            'status' => [$required, 'in:Aktif,Nonaktif'],
        ]);
    }
}
