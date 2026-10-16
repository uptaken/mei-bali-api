<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Client;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TourTotalsTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_tour_total_is_the_same_everywhere_it_is_shown(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin->value]);
        $this->actingAs($admin, 'sanctum');
        $client = Client::create(['kode' => 'CLI-09', 'nama' => 'Tour Client']);
        $supplier = Supplier::create(['kode' => 'SUP-09', 'nama' => 'Wayan', 'telepon' => '081200000009']);
        $vehicle = Vehicle::create(['nama' => 'Avanza', 'kapasitas' => 5]);

        $order = $this->postJson('/api/orders', [
            'tipe' => 'tour', 'clientId' => $client->id, 'namaOrder' => 'Totals', 'kodeGroup' => 'G-TOT',
            'tanggalMulai' => '2026-11-10', 'durasiHari' => 2,
            'itinerary' => [
                ['hari' => 1, 'catatan' => 'Tamu vegetarian', 'aktivitas' => [['aktivitas' => 'Snorkeling', 'biaya' => 1000000, 'supplierTur' => 'Bali Dive'], ['aktivitas' => 'Lunch', 'biaya' => 250000, 'supplierTur' => 'Bali Dive'], ['aktivitas' => 'Welcome drink', 'biaya' => 0, 'skipBiaya' => true]]],
                ['hari' => 2, 'aktivitas' => [['aktivitas' => 'Rafting', 'biaya' => 500000, 'supplierTur' => 'Ayung Rafting']]],
            ],
            'assignments' => [
                ['hari' => 1, 'vehicleId' => $vehicle->id, 'supplierId' => $supplier->id, 'biayaTransportModal' => 800000],
                ['hari' => 2, 'vehicleId' => $vehicle->id, 'supplierId' => $supplier->id, 'biayaTransportModal' => 700000],
            ],
        ])->assertCreated();

        // Aktivitas bertanda skip tidak berbiaya. 1.000.000 + 250.000 + 500.000 activities + 800.000 + 700.000 transport
        $expected = 3250000;
        $this->assertSame($expected, $order->json('total'));

        $invoiceModal = collect($order->json('invoices.0.lines'))->sum(fn ($l) => $l['qty'] * $l['modal']);
        $this->assertSame($expected, (int) $invoiceModal, 'invoice lines modal');

        $list = collect($this->getJson('/api/orders')->json('data'))->firstWhere('id', $order->json('id'));
        $this->assertSame($expected, (int) $list['totalItems'], 'order list totalItems');

        $payables = collect($this->getJson('/api/payables')->json('data'))->where('orderId', $order->json('id'));
        $this->assertSame($expected, (int) $payables->sum('modal'), 'payables modal');

        // The list the screens read from carries each day's catatan too.
        $this->assertSame('Tamu vegetarian', $list['itinerary_days'][0]['catatan']);

        // Editing the order keeps the figure.
        $this->patchJson('/api/orders/'.$order->json('id'), ['catatan' => 'x'])->assertOk();
        $this->assertSame($expected, $this->getJson('/api/orders/'.$order->json('id'))->json('total'));
    }

    public function test_riwayat_only_says_supplier_assigned_when_a_supplier_really_was(): void
    {
        $this->actingAs(User::factory()->create(['role' => UserRole::Admin->value]), 'sanctum');
        $client = Client::create(['kode' => 'CLI-60', 'nama' => 'Riwayat']);
        $supplier = Supplier::create(['kode' => 'SUP-60', 'nama' => 'Wayan', 'telepon' => '0812']);
        $vehicle = Vehicle::create(['nama' => 'Avanza', 'kapasitas' => 5]);

        $id = $this->postJson('/api/orders', [
            'tipe' => 'layanan', 'subTipe' => 'check_out', 'clientId' => $client->id, 'namaOrder' => 'R', 'kodeGroup' => 'G-R',
            'tanggalMulai' => '2026-11-20', 'layananDetail' => ['namaTamu' => 'T', 'pickUpPoint' => 'H', 'jamJemput' => '08:00', 'jumlahPax' => 1],
        ])->assertCreated()->json('id');

        $kinds = fn () => collect($this->getJson("/api/orders/{$id}")->json('events'))->pluck('kind')->all();
        $edit = fn (array $assignment) => $this->patchJson("/api/orders/{$id}", ['assignments' => [['hari' => 1, ...$assignment]]])->assertOk();

        $edit(['vehicleId' => $vehicle->id, 'biayaTransportModal' => 500000]);          // vehicle only: nobody was assigned yet
        $edit(['vehicleId' => $vehicle->id, 'supplierId' => $supplier->id, 'biayaTransportModal' => 500000]); // now a supplier is
        $edit(['vehicleId' => $vehicle->id, 'supplierId' => $supplier->id, 'biayaTransportModal' => 650000]); // only the cost changed

        $this->assertSame(['created', 'confirmed', 'updated', 'assigned', 'updated'], $kinds());
    }
}
