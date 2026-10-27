<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Tiket;
use Illuminate\Http\Request;

class TiketController extends Controller
{
    public function index(Request $request)
    {
        return $this->visible(Tiket::orderBy('nama')->get(), $request);
    }

    public function store(Request $request)
    {
        return response()->json($this->visible(Tiket::create($this->validated($request))->refresh(), $request), 201);
    }

    public function show(Request $request, Tiket $tiket)
    {
        return $this->visible($tiket, $request);
    }

    public function update(Request $request, Tiket $tiket)
    {
        $tiket->update($this->validated($request, true));
        return $this->visible($tiket, $request);
    }

    /** Harga Jual (referensi) hanya dikirim ke Super Admin. */
    private function visible($tikets, Request $request)
    {
        if (! $request->user()->role->canSeeHargaJual()) {
            collect($tikets instanceof Tiket ? [$tikets] : $tikets)->each->makeHidden('harga_jual');
        }

        return $tikets;
    }

    public function destroy(Tiket $tiket)
    {
        $tiket->delete();
        return response()->json(status: 204);
    }

    private function validated(Request $request, bool $partial = false): array
    {
        $required = $partial ? 'sometimes' : 'required';
        $data = $request->validate([
            'nama' => [$required, 'string', 'max:255'],
            'kategori' => [$required, 'string'],
            'harga_jual' => ['sometimes', 'integer', 'min:0'],
            'modal' => [$required, 'integer', 'min:0'],
            'status' => [$required, 'in:Aktif,Nonaktif'],
            'supplier_nama' => ['nullable', 'string'],
        ]);
        if (! $request->user()->role->canSeeHargaJual()) {
            unset($data['harga_jual']);
        }

        return $data;
    }
}
