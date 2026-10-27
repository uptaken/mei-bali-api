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
        $admin = User::factory()->create(['role' => UserRole::SuperAdmin->value]);
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

    public function test_saving_an_order_without_changing_anything_is_not_an_edit(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin->value]);
        $other = User::factory()->create(['role' => UserRole::SuperAdmin->value]);
        $client = Client::create(['kode' => 'CLI-61', 'nama' => 'Tanpa Ubah']);
        $payload = [
            'tipe' => 'tour', 'clientId' => $client->id, 'namaOrder' => 'Sama', 'kodeGroup' => 'G-SAMA', 'tanggalMulai' => '2026-12-03',
            'tamu' => [['nama' => 'A', 'kategori' => 'Dewasa']],
            'itinerary' => [['hari' => 1, 'catatan' => 'x', 'aktivitas' => [['aktivitas' => 'Tur', 'biaya' => 100000, 'supplierTur' => 'V']]]],
        ];

        $id = $this->actingAs($admin, 'sanctum')->postJson('/api/orders', $payload)->assertCreated()->json('id');
        $before = $this->getJson("/api/orders/{$id}")->json();

        // Same data sent again by someone else: nothing is logged and nobody becomes "diperbarui oleh".
        // (the form also sends 0 / [] for fields stored as empty: still not a change)
        $this->actingAs($other, 'sanctum')->patchJson("/api/orders/{$id}", $payload + ['kotaTermasuk' => [], 'bahasaId' => ''])->assertOk();
        $after = $this->getJson("/api/orders/{$id}")->json();

        $this->assertSame(collect($before['events'])->pluck('kind')->all(), collect($after['events'])->pluck('kind')->all());
        $this->assertSame($before['updated_by'], $after['updated_by']);
        $this->assertSame($before['total'], $after['total']);

        // A real change is an edit.
        $this->actingAs($other, 'sanctum')->patchJson("/api/orders/{$id}", [...$payload, 'catatan' => 'baru'])->assertOk();
        $changed = $this->getJson("/api/orders/{$id}")->json();
        $this->assertSame('updated', collect($changed['events'])->last()['kind']);
        $this->assertSame($other->id, $changed['updated_by']);
    }

    public function test_a_layanan_order_can_be_saved_again_after_an_edit_through_the_form(): void
    {
        $this->actingAs(User::factory()->create(['role' => UserRole::Admin->value]), 'sanctum');
        $client = Client::create(['kode' => 'CLI-62', 'nama' => 'Simpan Lagi']);
        $payload = ['tipe' => 'layanan', 'subTipe' => 'check_in', 'clientId' => $client->id, 'namaOrder' => 'L', 'kodeGroup' => 'G-L', 'tanggalMulai' => '2026-12-04',
            'layananDetail' => ['namaTamu' => 'T', 'hotelDropOff' => 'H', 'jumlahPax' => 1]];
        $id = $this->postJson('/api/orders', $payload)->assertCreated()->json('id');

        // The form sends an empty itinerary and a zero duration for Layanan; that must neither store 0 nor be refused.
        $edit = [...$payload, 'itinerary' => [], 'durasiHari' => 0, 'catatan' => 'diubah'];
        $this->patchJson("/api/orders/{$id}", $edit)->assertOk();
        $this->assertNull($this->getJson("/api/orders/{$id}")->json('durasi_hari'));
        $this->patchJson("/api/orders/{$id}", [...$edit, 'catatan' => 'diubah lagi'])->assertOk();
    }
}
