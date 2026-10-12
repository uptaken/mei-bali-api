<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\RefusesDeleteWhenUsed;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Supplier;
use Illuminate\Http\Request;

class SupplierController extends Controller
{
    use RefusesDeleteWhenUsed;

    public function index(Request $request)
    {
        return Supplier::query()->orderBy('nama')->get();
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            // 'kode' => ['required', 'string', 'unique:suppliers,kode'],
            'nama' => ['required', 'string'],
            'telepon' => ['nullable',],
            'tipe_mobil' => ['nullable', 'string'],
						'area' => ['nullable', 'string'],
        ]);

        return response()->json(Supplier::create($data), 201);
    }

    public function show(Supplier $supplier)
    {
        return $supplier;
    }

    public function update(Request $request, Supplier $supplier)
    {
        $data = $request->validate([
            // 'kode' => ['sometimes', 'string', 'unique:suppliers,kode,'.$supplier->id],
            'nama' => ['sometimes', 'string'],
            'telepon' => ['nullable',],
            'tipe_mobil' => ['nullable', 'string'],
						'area' => ['nullable', 'string'],
        ]);

        $supplier->update($data);

        return $supplier;
    }

    public function destroy(Supplier $supplier)
    {
        $this->refuseIfUsed('Supplier', $supplier->nama, ['order' => Order::where('supplier_id', $supplier->id)->orWhereHas('assignments', fn ($q) => $q->where('supplier_id', $supplier->id))->count()], canDeactivate: true);
        $supplier->delete();

        return response()->json(status: 204);
    }
}
