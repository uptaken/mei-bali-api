<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Client;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MasterDeleteGuardTest extends TestCase
{
    use RefreshDatabase;

    public function test_master_data_used_by_an_order_cannot_be_deleted_but_unused_data_can(): void
    {
        $this->actingAs(User::factory()->create(['role' => UserRole::Admin->value]), 'sanctum');

        $client = Client::create(['kode' => 'CLI-90', 'nama' => 'Pemakai']);
        $spare = Client::create(['kode' => 'CLI-91', 'nama' => 'Cadangan']);
        $supplier = Supplier::create(['kode' => 'SUP-90', 'nama' => 'Wayan', 'telepon' => '0812']);
        $vehicle = Vehicle::create(['nama' => 'Avanza', 'kapasitas' => 5]);
        $category = ProductCategory::create(['nama' => 'Makan']);
        $product = Product::create(['product_category_id' => $category->id, 'nama' => 'Lunch', 'harga_jual' => 100000, 'modal' => 50000]);

        $this->postJson('/api/orders', [
            'tipe' => 'tour', 'clientId' => $client->id, 'namaOrder' => 'Dipakai', 'kodeGroup' => 'G-DEL', 'tanggalMulai' => '2026-11-10',
            'itinerary' => [['hari' => 1, 'aktivitas' => [['aktivitas' => 'Tur', 'biaya' => 100000]]]],
            'assignments' => [['hari' => 1, 'vehicleId' => $vehicle->id, 'supplierId' => $supplier->id, 'biayaTransportModal' => 300000]],
            'addOns' => [['produkId' => $product->id, 'qty' => 1, 'modal' => 50000]],
        ])->assertCreated();

        // Used: refused with a readable message, and nothing was deleted.
        foreach ([['clients', $client, 'Client Pemakai'], ['suppliers', $supplier, 'Supplier Wayan'], ['vehicles', $vehicle, 'Mobil Avanza'], ['products', $product, 'Produk Lunch']] as [$path, $model, $label]) {
            $this->deleteJson("/api/{$path}/{$model->id}")
                ->assertStatus(422)
                ->assertJsonPath('message', fn ($m) => str_starts_with($m, "{$label} masih dipakai di 1 order") && str_ends_with($m, 'tidak bisa dihapus.'));
            $this->assertModelExists($model);
        }

        $this->deleteJson('/api/product-categories/'.$category->id)->assertStatus(422);
        $this->assertModelExists($category);

        // Not used by any order: deleting still works.
        $this->deleteJson('/api/clients/'.$spare->id)->assertNoContent();
        $this->assertModelMissing($spare);
    }
}
