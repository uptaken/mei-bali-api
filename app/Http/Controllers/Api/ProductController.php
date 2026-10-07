<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function index()
    {
        return Product::with('category')->orderBy('nama')->get();
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'product_category_id' => ['nullable', 'exists:product_categories,id'],
            'nama' => ['required', 'string'],
            'harga_jual' => ['required', 'integer', 'min:0'],
            'modal' => ['required', 'integer', 'min:0'],
            'deskripsi' => ['nullable', 'string'],
        ]);

        return response()->json(Product::create($data), 201);
    }

    public function show(Product $product)
    {
        return $product->load('category');
    }

    public function update(Request $request, Product $product)
    {
        $data = $request->validate([
            'product_category_id' => ['nullable', 'exists:product_categories,id'],
            'nama' => ['sometimes', 'string'],
            'harga_jual' => ['sometimes', 'integer', 'min:0'],
            'modal' => ['sometimes', 'integer', 'min:0'],
            'deskripsi' => ['nullable', 'string'],
        ]);

        $product->update($data);

        return $product;
    }

    public function destroy(Product $product)
    {
        $product->delete();

        return response()->json(status: 204);
    }
}
