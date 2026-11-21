<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Client;
use App\Models\InvoiceLine;
use App\Models\Order;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LayananSubTypeChangeTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Client $client;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => UserRole::Admin->value]);
        $this->client = Client::create(['kode' => 'CLI-1', 'nama' => 'Klien']);
    }

    /** @return array<string, mixed> */
    private function layanan(string $sub, array $detail): array
    {
        return [
            'tipe' => 'layanan', 'subTipe' => $sub, 'clientId' => $this->client->id, 'namaOrder' => $sub, 'kodeGroup' => 'G-1', 'tanggalMulai' => '2026-12-01',
            'layananDetail' => $detail + ['namaTamu' => 'Cao Yang', 'jumlahPax' => 2, 'biayaModal' => 100000],
        ];
    }

    private function create(string $sub = 'check_in'): int
    {
        return $this->actingAs($this->admin, 'sanctum')->postJson('/api/orders', $this->layanan($sub, ['hotelDropOff' => 'Hotel Kuta']))->assertCreated()->json('id');
    }

    public function test_a_wrongly_picked_jenis_can_be_corrected_and_the_invoice_line_follows_it(): void
    {
        $id = $this->create('check_in');
        InvoiceLine::query()->update(['harga_jual' => 750000]);

        $edited = $this->actingAs($this->admin, 'sanctum')->patchJson("/api/orders/{$id}", $this->layanan('transfer', ['pickUpPoint' => 'Bandara', 'dropOffPoint' => 'Ubud', 'jamJemput' => '09:00']))
            ->assertOk();

        $this->assertSame('transfer', $edited->json('sub_tipe'));
        $lines = Order::findOrFail($id)->invoices->first()->lines;
        $this->assertCount(1, $lines, 'the old Check In line is renamed, not left behind next to a new one');
        $this->assertSame('Transfer — Cao Yang', $lines->first()->deskripsi);
        $this->assertSame(750000, $lines->first()->harga_jual);

        $riwayat = Order::findOrFail($id)->events()->orderByDesc('id')->get()->firstWhere('kind', 'updated')->detail;
        $this->assertStringContainsString('Jenis Layanan: Check In → Transfer', $riwayat);
    }

    public function test_the_wa_template_follows_the_corrected_jenis(): void
    {
        $this->artisan('migrate');
        $id = $this->create('check_in');
        $this->actingAs($this->admin, 'sanctum')->patchJson("/api/orders/{$id}", $this->layanan('check_out', ['pickUpPoint' => 'Hotel Kuta', 'jamJemput' => '08:00']))->assertOk();

        $this->assertSame('Info Check Out', \App\Support\OrderWhatsApp::templateName(Order::findOrFail($id)));
    }

    public function test_only_layanan_orders_change_jenis_and_only_by_an_admin(): void
    {
        $tour = $this->actingAs($this->admin, 'sanctum')->postJson('/api/orders', ['tipe' => 'tour', 'clientId' => $this->client->id, 'namaOrder' => 'T', 'kodeGroup' => 'G-T', 'tanggalMulai' => '2026-12-01'])->assertCreated()->json('id');
        $this->actingAs($this->admin, 'sanctum')->patchJson("/api/orders/{$tour}", ['subTipe' => 'transfer'])->assertOk();
        $this->assertNull(Order::findOrFail($tour)->sub_tipe);

        $id = $this->create('check_in');
        $this->actingAs($this->admin, 'sanctum')->patchJson("/api/orders/{$id}", ['subTipe' => null])->assertOk();
        $this->assertSame('check_in', Order::findOrFail($id)->sub_tipe->value, 'a payload without a jenis never blanks it');

        $operator = User::factory()->create(['role' => UserRole::Operator->value]);
        $this->actingAs($operator, 'sanctum')->patchJson("/api/orders/{$id}", ['subTipe' => 'transfer', 'catatanPenugasan' => 'ok'])->assertOk();
        $this->assertSame('check_in', Order::findOrFail($id)->sub_tipe->value);

        $this->actingAs($this->admin, 'sanctum')->patchJson("/api/orders/{$id}", ['subTipe' => 'ticket'])->assertUnprocessable();
    }

    public function test_saving_the_same_jenis_is_still_not_an_edit(): void
    {
        $id = $this->create('transfer');
        $before = Order::findOrFail($id)->events()->count();

        $this->actingAs($this->admin, 'sanctum')->patchJson("/api/orders/{$id}", $this->layanan('transfer', ['hotelDropOff' => 'Hotel Kuta']))->assertOk();

        $this->assertSame($before, Order::findOrFail($id)->events()->count());
    }
}
