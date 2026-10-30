<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Jobs\SendWhatsAppMessageJob;
use App\Models\Client;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

class LayananWhatsAppTest extends TestCase
{
    use RefreshDatabase;

    public function test_layanan_orders_send_their_own_template_and_keep_jenis_penerbangan(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin->value]);
        $client = Client::create(['kode' => 'CLI-96', 'nama' => 'WA Layanan']);
        $supplier = Supplier::create(['kode' => 'SUP-96', 'nama' => 'Wayan', 'telepon' => '081200000096']);
        $vehicle = Vehicle::create(['nama' => 'Avanza', 'kapasitas' => 5]);
        $this->artisan('migrate'); // the data migration adds the three templates
        \App\Models\WhatsAppTemplate::firstOrCreate(['nama' => 'Konfirmasi Supplier'], ['kategori' => 'Operasional', 'isi' => 'Halo {{nama_supplier}}', 'variabel' => []]);

        $make = fn (string $sub, array $detail) => $this->actingAs($admin, 'sanctum')->postJson('/api/orders', [
            'tipe' => 'layanan', 'subTipe' => $sub, 'clientId' => $client->id, 'namaOrder' => $sub, 'kodeGroup' => "G-{$sub}", 'tanggalMulai' => '2026-08-25',
            'catatan' => 'Bawa papan nama',
            'layananDetail' => $detail + ['namaTamu' => 'Cao Yang', 'jumlahPax' => 2, 'biayaModal' => 100000],
            'assignments' => [['hari' => 1, 'vehicleId' => $vehicle->id, 'supplierId' => $supplier->id, 'biayaTransportModal' => 0]],
        ])->assertCreated()->json('id');

        $checkIn = $make('check_in', ['hotelDropOff' => 'Cross Vibe Paasha Atelier Bali Kuta', 'infoFlight' => 'ID6334 (20.10)', 'jenisPenerbangan' => 'Domestik']);
        $transfer = $make('transfer', ['pickUpPoint' => 'Bandara', 'dropOffPoint' => 'Ubud', 'jamJemput' => '09:00']);

        $this->assertSame('Domestik', $this->actingAs($admin, 'sanctum')->getJson("/api/orders/{$checkIn}")->json('layanan_detail.jenis_penerbangan'));

        Bus::fake();
        $preview = $this->actingAs($admin, 'sanctum')->postJson("/api/orders/{$checkIn}/send-wa-supplier")->assertOk()->json('preview');
        $this->assertSame("Check In - 25 Agt 2026\n\nNama Tamu : Cao Yang\nHotel : Cross Vibe Paasha Atelier Bali Kuta\nFlight : ID6334 (20.10)\nTotal : 2 pax\n\n*DOMESTIK* - Bawa papan nama", $preview);
        Bus::assertDispatched(SendWhatsAppMessageJob::class, fn ($job) => $job->message === $preview);

        $preview = $this->actingAs($admin, 'sanctum')->postJson("/api/orders/{$transfer}/send-wa-supplier")->assertOk()->json('preview');
        $this->assertStringContainsString("Pick Up : Bandara\nDrop Off : Ubud\nJam Jemput : 09:00", $preview);
    }
}
