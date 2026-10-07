<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Client;
use Illuminate\Http\Request;

class ClientController extends Controller
{
    public function index(Request $request)
    {
        $q = trim((string) $request->query('q', ''));

        return Client::query()
            ->when($q !== '', fn ($query) => $query->where(fn ($w) => $w
                ->where('nama', 'like', "%{$q}%")
                ->orWhere('kode', 'like', "%{$q}%")))
            ->orderBy('nama')
            ->paginate($request->integer('per_page', 20));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'kode' => ['required', 'string', 'unique:clients,kode'],
            'nama' => ['required', 'string'],
            'tipe' => ['nullable', 'string'],
            'kontak' => ['nullable', 'string'],
            'telepon' => ['nullable',],
            'email' => ['nullable', 'email'],
            'kota' => ['nullable', 'string'],
            'negara' => ['nullable', 'string'],
            'catatan' => ['nullable', 'string'],
        ]);

        return response()->json(Client::create($data), 201);
    }

    public function show(Client $client)
    {
        return $client;
    }

    public function update(Request $request, Client $client)
    {
        $data = $request->validate([
            'kode' => ['sometimes', 'string', 'unique:clients,kode,'.$client->id],
            'nama' => ['sometimes', 'string'],
            'tipe' => ['nullable', 'string'],
            'kontak' => ['nullable', 'string'],
            'telepon' => ['nullable',],
            'email' => ['nullable', 'email'],
            'kota' => ['nullable', 'string'],
            'negara' => ['nullable', 'string'],
            'catatan' => ['nullable', 'string'],
        ]);

        $client->update($data);

        return $client;
    }

    public function destroy(Client $client)
    {
        $client->delete();

        return response()->json(status: 204);
    }
}
