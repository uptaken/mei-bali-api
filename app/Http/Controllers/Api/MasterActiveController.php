<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\Vehicle;
use Illuminate\Http\Request;

/** Aktifkan / nonaktifkan master (client, supplier, mobil, produk) tanpa menghapusnya. */
class MasterActiveController extends Controller
{
    private const MODELS = ['clients' => Client::class, 'suppliers' => Supplier::class, 'vehicles' => Vehicle::class, 'products' => Product::class];

    /** PATCH /api/{master}/{id}/active  body: {aktif: bool} */
    public function update(Request $request, string $master, int $id)
    {
        $data = $request->validate(['aktif' => ['required', 'boolean']]);

        $model = (self::MODELS[$master])::findOrFail($id);
        $model->setActive($data['aktif']);

        return $model->fresh();
    }
}
