<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Client;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderAndPayablesTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_a_tour_order_with_two_vehicles_on_one_day_and_it_produces_two_separate_tagihan(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin->value]);
        $client = Client::create(['kode' => 'CLI-01', 'nama' => 'Test Client']);
        $supplierA = Supplier::create(['kode' => 'SUP-01', 'nama' => 'Wayan', 'telepon' => '081200000001']);
        $supplierB = Supplier::create(['kode' => 'SUP-02', 'nama' => 'Hendra', 'telepon' => '081200000002']);
        $vehicleA = Vehicle::create(['nama' => 'Toyota Avanza', 'kapasitas' => 5]);
        $vehicleB = Vehicle::create(['nama' => 'Isuzu Elf Long', 'kapasitas' => 19]);

        $payload = [
            'tipe' => 'tour',
            'clientId' => $client->id,
            'namaOrder' => 'Test Tour',
            'kodeGroup' => 'GRP-TEST',
            'tanggalMulai' => now()->addDays(5)->toDateString(),
            'itinerary' => [
                ['hari' => 1, 'aktivitas' => [['aktivitas' => 'Snorkeling', 'biaya' => 1000000]]],
            ],
            'assignments' => [
                ['hari' => 1, 'vehicleId' => $vehicleA->id, 'supplierId' => $supplierA->id, 'biayaTransportModal' => 800000],
                ['hari' => 1, 'vehicleId' => $vehicleB->id, 'supplierId' => $supplierB->id, 'biayaTransportModal' => 900000],
            ],
        ];

        $response = $this->actingAs($admin, 'sanctum')->postJson('/api/orders', $payload);

        $response->assertCreated();
        $orderId = $response->json('id');

        // One invoice line per vehicle, disambiguated with "#1"/"#2" since both share Hari 1.
        $lines = $response->json('invoices.0.lines');
        $transportLines = collect($lines)->filter(fn ($l) => str_starts_with($l['deskripsi'], 'Transport'));
        $this->assertCount(2, $transportLines);

        $payables = $this->actingAs($admin, 'sanctum')->getJson('/api/payables')->json('data');
        $transportTagihan = collect($payables)->where('orderId', $orderId)->where('tipeTagihan', 'transportasi');

        // Two different suppliers on the same day → two separate Tagihan, not one merged row.
        $this->assertCount(2, $transportTagihan);
    }
}
