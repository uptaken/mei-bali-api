<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\RefusesDeleteWhenUsed;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    use RefusesDeleteWhenUsed;

    public function index(Request $request)
    {
        return $this->withoutHargaJual(Product::with('category')->orderBy('nama')->get(), $request);
    }

    /** Harga Jual hanya dikirim ke Super Admin. */
    private function withoutHargaJual($products, Request $request)
    {
        if (! $request->user()->role->canSeeHargaJual()) {
            collect($products instanceof Product ? [$products] : $products)->each->makeHidden('harga_jual');
        }

        return $products;
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'product_category_id' => ['nullable', 'exists:product_categories,id'],
            'nama' => ['required', 'string', 'max:255'],
            'harga_jual' => ['sometimes', 'integer', 'min:0'],
            'modal' => ['required', 'integer', 'min:0'],
            'deskripsi' => ['nullable', 'string'],
        ]);
        if (! $request->user()->role->canSeeHargaJual()) {
            unset($data['harga_jual']);
        }

        return response()->json($this->withoutHargaJual(Product::create($data)->refresh(), $request), 201);
    }

    public function show(Request $request, Product $product)
    {
        return $this->withoutHargaJual($product->load('category'), $request);
    }

    public function update(Request $request, Product $product)
    {
        $data = $request->validate([
            'product_category_id' => ['nullable', 'exists:product_categories,id'],
            'nama' => ['sometimes', 'string', 'max:255'],
            'harga_jual' => ['sometimes', 'integer', 'min:0'],
            'modal' => ['sometimes', 'integer', 'min:0'],
            'deskripsi' => ['nullable', 'string'],
        ]);

        if (! $request->user()->role->canSeeHargaJual()) {
            unset($data['harga_jual']);
        }
        $product->update($data);

        return $this->withoutHargaJual($product, $request);
    }

    public function destroy(Product $product)
    {
        $this->refuseIfUsed('Produk', $product->nama, ['order' => Order::whereHas('addOns', fn ($q) => $q->where('product_id', $product->id))->count()], canDeactivate: true);
        $product->delete();

        return response()->json(status: 204);
    }
}
