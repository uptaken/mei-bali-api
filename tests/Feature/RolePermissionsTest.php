<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Client;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** The permission matrix of the app, enforced by the API and not only hidden by the screens. */
class RolePermissionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_operator_cannot_reach_pricing_money_or_reports_but_admin_can(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin->value]);
        $operator = User::factory()->create(['role' => UserRole::Operator->value]);

        $client = Client::create(['kode' => 'CLI-71', 'nama' => 'Uang']);
        $this->actingAs($admin, 'sanctum')->postJson('/api/orders', [
            'tipe' => 'ticket', 'clientId' => $client->id, 'namaOrder' => 'Uang', 'kodeGroup' => 'G-U', 'tanggalPemakaian' => '2026-12-06',
            'ticketRows' => [['namaTiket' => 'Zoo', 'qty' => 1, 'modalSatuan' => 100000]],
        ])->assertCreated();
        $invoice = \App\Models\Invoice::query()->value('id');

        foreach (['/api/invoices', '/api/payables', '/api/export/profit-loss/pdf', '/api/export/account-receivable/pdf', '/api/export/account-payable/pdf'] as $path) {
            $this->actingAs($operator, 'sanctum')->getJson($path)->assertForbidden();
        }
        $this->actingAs($operator, 'sanctum')->postJson("/api/invoices/{$invoice}/payments", ['amount' => 1])->assertForbidden();
        $this->actingAs($operator, 'sanctum')->postJson("/api/invoices/{$invoice}/mark-billed")->assertForbidden();
        $this->actingAs($operator, 'sanctum')->postJson('/api/payables/mark-paid-bulk', ['ids' => []])->assertForbidden();
        $this->actingAs($operator, 'sanctum')->postJson('/api/whatsapp/send', ['phone' => '0812', 'message' => 'x'])->assertForbidden();

        $this->actingAs($admin, 'sanctum')->getJson('/api/invoices')->assertOk();
        $this->actingAs($admin, 'sanctum')->getJson('/api/payables')->assertOk();
    }

    public function test_operator_can_assign_a_driver_but_nothing_else_and_never_sees_invoices(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin->value]);
        $operator = User::factory()->create(['role' => UserRole::Operator->value]);
        $client = Client::create(['kode' => 'CLI-70', 'nama' => 'Peran']);
        $supplier = Supplier::create(['kode' => 'SUP-70', 'nama' => 'Wayan', 'telepon' => '0812']);
        $vehicle = Vehicle::create(['nama' => 'Avanza', 'kapasitas' => 5]);

        $id = $this->actingAs($admin, 'sanctum')->postJson('/api/orders', [
            'tipe' => 'layanan', 'subTipe' => 'check_out', 'clientId' => $client->id, 'namaOrder' => 'Peran', 'kodeGroup' => 'G-P', 'tanggalMulai' => '2026-12-06',
            'catatan' => 'asli', 'layananDetail' => ['namaTamu' => 'T', 'pickUpPoint' => 'H', 'jamJemput' => '08:00', 'jumlahPax' => 1],
        ])->assertCreated()->json('id');

        // Operator sends the whole order back (as the screens do) with a changed note and an assignment.
        $this->actingAs($operator, 'sanctum')->patchJson("/api/orders/{$id}", [
            'catatan' => 'diubah operator', 'namaOrder' => 'Dihack',
            'assignments' => [['hari' => 1, 'vehicleId' => $vehicle->id, 'supplierId' => $supplier->id, 'biayaTransportModal' => 0]],
        ])->assertOk()->assertJsonMissingPath('invoices');

        $order = $this->actingAs($admin, 'sanctum')->getJson("/api/orders/{$id}")->json();
        $this->assertSame('asli', $order['catatan']);
        $this->assertSame('Peran', $order['nama_order']);
        $this->assertSame('Siap Kirim WA', $order['status']);
        $this->assertNotEmpty($order['invoices']);

        // The Operator never receives the invoice (Harga Jual, margin), neither from the list nor the detail.
        $this->actingAs($operator, 'sanctum')->getJson("/api/orders/{$id}")->assertOk()->assertJsonMissingPath('invoices');
        $row = collect($this->actingAs($operator, 'sanctum')->getJson('/api/orders')->json('data'))->firstWhere('id', $id);
        $this->assertArrayNotHasKey('invoices', $row);

        // Still no creating or cancelling.
        $this->actingAs($operator, 'sanctum')->postJson('/api/orders', ['tipe' => 'ticket', 'clientId' => $client->id, 'namaOrder' => 'x', 'kodeGroup' => 'x'])->assertForbidden();
        $this->actingAs($operator, 'sanctum')->postJson("/api/orders/{$id}/cancel", ['alasan' => 'x'])->assertForbidden();
    }
}
