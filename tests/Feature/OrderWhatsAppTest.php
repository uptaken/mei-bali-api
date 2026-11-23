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

class OrderWhatsAppTest extends TestCase
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

    public function test_tour_message_lists_every_day_with_guests_language_and_notes(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin->value]);
        $client = Client::create(['kode' => 'CLI-97', 'nama' => 'WA Tour']);
        $supplier = Supplier::create(['kode' => 'SUP-97', 'nama' => 'CV Bali', 'nama_driver' => 'Komang', 'telepon' => '081200000097']);
        $vehicle = Vehicle::create(['nama' => 'Hiace', 'kapasitas' => 8]);
        $language = \App\Models\Language::create(['kode' => 'MD', 'nama' => 'Mandarin']);
        $product = \App\Models\Product::create(['product_category_id' => \App\Models\ProductCategory::create(['nama' => 'Makan'])->id, 'nama' => 'Dinner Jimbaran', 'harga_jual' => 0, 'modal' => 0]);
        $fd = \App\Models\Duration::create(['label' => 'FD 10 JAM', 'perkiraanJam' => '10 jam']);

        $id = $this->actingAs($admin, 'sanctum')->postJson('/api/orders', [
            'tipe' => 'tour', 'clientId' => $client->id, 'namaOrder' => 'Paket Bali 3D', 'kodeGroup' => 'G-T', 'tanggalMulai' => '2026-08-17',
            'bahasaId' => (string) $language->id, 'dewasa' => 2, 'anak' => 1, 'catatan' => 'PAKE KAMERA', 'catatanPenugasan' => 'AMBIL KEMBALI KAMERA',
            'tamu' => [['nama' => 'MENG JIAO YANG', 'kategori' => 'Dewasa']],
            'itinerary' => [
                ['hari' => 1, 'waktuPenjemputan' => '08:45', 'tempatPenjemputan' => 'The Kuta Beach Heritage Hotel', 'tempatDropAkhir' => 'The Kuta Beach Heritage Hotel', 'waktuDropAkhir' => '18:30', 'durasiId' => $fd->id,
                    'aktivitas' => [['aktivitas' => 'Pandawa Road (Include Tiket)', 'biaya' => 0], ['aktivitas' => 'Uluwatu', 'biaya' => 0]]],
                ['hari' => 2, 'tempatDropAkhir' => 'Senetan Villas', 'catatan' => 'Bawa jas hujan', 'aktivitas' => [['aktivitas' => 'Tegalalang', 'biaya' => 0]]],
                ['hari' => 3, 'aktivitas' => [['aktivitas' => 'Lovina Dolphin', 'biaya' => 0]]],
            ],
            'addOns' => [['produkId' => $product->id, 'qty' => 2, 'modal' => 0, 'catatan' => 'tanpa seafood']],
            'assignments' => [['hari' => 1, 'vehicleId' => $vehicle->id, 'supplierId' => $supplier->id, 'biayaTransportModal' => 0]],
        ])->assertCreated()->json('id');

        $this->assertSame((string) $fd->id, (string) $this->actingAs($admin, 'sanctum')->getJson("/api/orders/{$id}")->json('itinerary_days.0.durasi_id'));
        $this->artisan('migrate');

        $preview = $this->actingAs($admin, 'sanctum')->getJson("/api/orders/{$id}/wa-supplier-preview")->assertOk();
        $this->assertSame('Info Tour', $preview->json('template'));
        $this->assertSame(
            "Paket Bali 3D\n17 - 19 Agt 2026\n(MANDARIN)\n\n*(PAKE KAMERA)*\n\nNama supir : Komang\nMobil : Hiace\nNama Tamu : MENG JIAO YANG\nTotal : 2 adl + 1 chd\n\nRute :\n"
            ."17 Agt 2026\n*(FD 10 JAM)*\n* Jemput di The Kuta Beach Heritage Hotel\nJam 08.45\n* Pandawa Road (Include Tiket)\n* Uluwatu\n* Drop The Kuta Beach Heritage Hotel\nJam 18.30\n\n"
            ."18 Agt 2026\n* Tegalalang\n* Drop Senetan Villas\nCatatan: Bawa jas hujan\n\n"
            ."19 Agt 2026\n* Lovina Dolphin\n\nAdd-On :\n* Dinner Jimbaran x2 (tanpa seafood)\n\n*(AMBIL KEMBALI KAMERA)*",
            $preview->json('message'),
        );

        // Sending queues exactly what the preview showed.
        Bus::fake();
        $this->actingAs($admin, 'sanctum')->postJson("/api/orders/{$id}/send-wa-supplier")->assertOk();
        Bus::assertDispatched(SendWhatsAppMessageJob::class, fn ($job) => $job->message === $preview->json('message'));
    }
}
