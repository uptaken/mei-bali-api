<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ProductCategory;
use Illuminate\Http\Request;

class ProductCategoryController extends Controller
{
    public function index()
    {
        return ProductCategory::withCount('products')->orderBy('nama')->get();
    }

    public function store(Request $request)
    {
        return response()->json(ProductCategory::create($request->validate([
            'nama' => ['required', 'string'],
            'deskripsi' => ['nullable', 'string'],
        ])), 201);
    }

    public function show(ProductCategory $productCategory)
    {
        return $productCategory->loadCount('products');
    }

    public function update(Request $request, ProductCategory $productCategory)
    {
        $productCategory->update($request->validate([
            'nama' => ['sometimes', 'string'],
            'deskripsi' => ['nullable', 'string'],
        ]));

        return $productCategory->loadCount('products');
    }

    public function destroy(ProductCategory $productCategory)
    {
        $productCategory->delete();
        return response()->json(status: 204);
    }
}
