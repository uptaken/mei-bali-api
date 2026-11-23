<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Client;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_search_reaches_itinerary_activities_and_add_ons(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin->value]);
        $client = Client::create(['kode' => 'CLI-95', 'nama' => 'Pencari']);
        $product = Product::create(['product_category_id' => ProductCategory::create(['nama' => 'Makan'])->id, 'nama' => 'Dinner Jimbaran', 'harga_jual' => 0, 'modal' => 50000]);

        $base = ['tipe' => 'tour', 'clientId' => $client->id, 'tanggalMulai' => '2026-11-10'];
        $this->actingAs($admin, 'sanctum')->postJson('/api/orders', $base + [
            'namaOrder' => 'A', 'kodeGroup' => 'G-A',
            'itinerary' => [['hari' => 1, 'tempatPenjemputan' => 'Hotel Lovina', 'aktivitas' => [['aktivitas' => 'Alas Harum Entrance', 'biaya' => 1000, 'supplierTur' => 'Ketut Tour']]]],
            'addOns' => [['produkId' => $product->id, 'qty' => 1, 'modal' => 50000, 'catatan' => 'tanpa seafood']],
        ])->assertCreated();
        $this->actingAs($admin, 'sanctum')->postJson('/api/orders', $base + ['namaOrder' => 'B', 'kodeGroup' => 'G-B'])->assertCreated();

        foreach (['alas harum', 'ketut tour', 'hotel lovina', 'dinner jimbaran', 'seafood'] as $term) {
            $rows = $this->actingAs($admin, 'sanctum')->getJson('/api/orders?q='.urlencode($term))->json('data');
            $this->assertCount(1, $rows, $term);
            $this->assertSame('A', $rows[0]['nama_order'], $term);
        }
        $this->assertCount(2, $this->actingAs($admin, 'sanctum')->getJson('/api/orders')->json('data'));
        $this->assertCount(0, $this->actingAs($admin, 'sanctum')->getJson('/api/orders?q=tidakada')->json('data'));
    }
}
