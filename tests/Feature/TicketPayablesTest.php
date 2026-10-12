<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Client;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TicketPayablesTest extends TestCase
{
    use RefreshDatabase;

    public function test_ticket_orders_always_show_up_in_tagihan_even_before_a_supplier_is_filled_in(): void
    {
        $this->actingAs(User::factory()->create(['role' => UserRole::Admin->value]), 'sanctum');
        $client = Client::create(['kode' => 'CLI-50', 'nama' => 'Tiket Client']);

        $create = fn (array $row) => $this->postJson('/api/orders', [
            'tipe' => 'ticket', 'clientId' => $client->id, 'namaOrder' => 'Tiket', 'kodeGroup' => 'G-TIK', 'tanggalPemakaian' => '2026-11-10',
            'ticketRows' => [$row],
        ])->assertCreated()->json('id');

        $withVendor = $create(['namaTiket' => 'Zoo', 'qty' => 2, 'modalSatuan' => 100000, 'supplierNama' => 'Bali Zoo Ticketing']);
        $noVendor = $create(['namaTiket' => 'Rafting', 'qty' => 1, 'modalSatuan' => 250000]);

        $tagihan = collect($this->getJson('/api/payables')->json('data'));

        $this->assertSame('Bali Zoo Ticketing', $tagihan->firstWhere('orderId', $withVendor)['supplierNama']);
        $this->assertSame(200000, $tagihan->firstWhere('orderId', $withVendor)['modal']);

        $this->assertSame('Supplier belum diisi', $tagihan->firstWhere('orderId', $noVendor)['supplierNama']);
        $this->assertSame(250000, $tagihan->firstWhere('orderId', $noVendor)['modal']);
        $this->assertSame('ticket', $tagihan->firstWhere('orderId', $noVendor)['tipeTagihan']);

        // A ticket order is dated by its usage date, so the month filter on the Tagihan screen can find it.
        $this->assertSame('2026-11-10', $tagihan->firstWhere('orderId', $noVendor)['tanggalOrder']);
    }
}
