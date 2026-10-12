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
                ->assertJsonPath('message', fn ($m) => str_starts_with($m, "{$label} masih dipakai di 1 order") && str_contains($m, 'tidak bisa dihapus.') && str_contains($m, 'Nonaktifkan saja'));
            $this->assertModelExists($model);
        }

        $this->deleteJson('/api/product-categories/'.$category->id)->assertStatus(422);
        $this->assertModelExists($category);

        // Not used by any order: deleting still works.
        $this->deleteJson('/api/clients/'.$spare->id)->assertNoContent();
        $this->assertModelMissing($spare);
    }

    public function test_a_used_master_can_be_deactivated_and_reactivated_without_touching_its_orders(): void
    {
        $this->actingAs(User::factory()->create(['role' => UserRole::Admin->value]), 'sanctum');

        $client = Client::create(['kode' => 'CLI-92', 'nama' => 'Nonaktif']);
        $order = $this->postJson('/api/orders', [
            'tipe' => 'ticket', 'clientId' => $client->id, 'namaOrder' => 'Lama', 'kodeGroup' => 'G-OFF', 'tanggalPemakaian' => '2026-11-10',
            'ticketRows' => [['namaTiket' => 'Zoo', 'qty' => 1, 'modalSatuan' => 100000]],
        ])->assertCreated()->json('id');

        $this->assertTrue($this->getJson('/api/clients')->json('data.0.aktif') ?? $this->getJson('/api/clients')->json('0.aktif'));

        $this->patchJson("/api/clients/{$client->id}/active", ['aktif' => false])->assertOk()->assertJsonPath('aktif', false);

        // Still listed (so old orders can show its name), flagged inactive, and the order is untouched.
        $row = collect($this->getJson('/api/clients')->json('data') ?? $this->getJson('/api/clients')->json())->firstWhere('id', $client->id);
        $this->assertFalse($row['aktif']);
        $this->getJson("/api/orders/{$order}")->assertOk()->assertJsonPath('client.nama', 'Nonaktif');

        $this->patchJson("/api/clients/{$client->id}/active", ['aktif' => true])->assertOk()->assertJsonPath('aktif', true);
    }

    public function test_a_client_without_a_kode_gets_the_next_one(): void
    {
        $this->actingAs(User::factory()->create(['role' => UserRole::Admin->value]), 'sanctum');
        Client::create(['kode' => 'CLI-07', 'nama' => 'Lama']);

        $this->postJson('/api/clients', ['nama' => 'Baru', 'tipe' => 'Agen', 'kontak' => 'PIC'])->assertCreated()->assertJsonPath('kode', 'CLI-08');
        $this->postJson('/api/clients', ['nama' => 'Baru 2', 'kontak' => 'PIC'])->assertCreated()->assertJsonPath('kode', 'CLI-09');
        $this->postJson('/api/clients', ['kode' => 'CLI-ABC', 'nama' => 'Manual'])->assertCreated()->assertJsonPath('kode', 'CLI-ABC');
    }
}
