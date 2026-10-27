<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class AccountAndPaymentGuardsTest extends TestCase
{
    use RefreshDatabase;

    public function test_payment_cannot_exceed_remaining_balance_and_billing_is_idempotent(): void
    {
        $admin = User::factory()->create(['role' => 'Super Admin']);
        $client = Client::create(['kode' => 'CLI-81', 'nama' => 'Bayar']);
        $this->actingAs($admin, 'sanctum')->postJson('/api/orders', [
            'tipe' => 'ticket', 'clientId' => $client->id, 'namaOrder' => 'Bayar', 'kodeGroup' => 'G-B', 'tanggalPemakaian' => '2026-12-06',
            'ticketRows' => [['namaTiket' => 'Zoo', 'qty' => 1, 'modalSatuan' => 100000]],
        ])->assertCreated();
        $invoice = Invoice::firstOrFail();
        $line = $invoice->lines()->first();
        $this->patchJson("/api/invoices/{$invoice->id}/lines", ['lines' => [['id' => $line->id, 'qty' => 1, 'hargaJual' => 150000]]])->assertOk();

        $this->postJson("/api/invoices/{$invoice->id}/payments", ['amount' => 150001])->assertStatus(422);
        $this->postJson("/api/invoices/{$invoice->id}/payments", ['amount' => 50000])->assertOk();
        $this->assertSame(100000, $invoice->fresh()->sisa);

        $this->postJson("/api/invoices/{$invoice->id}/mark-billed")->assertOk();
        $this->postJson("/api/invoices/{$invoice->id}/mark-billed")->assertOk();
        $this->assertSame(1, $invoice->events()->where('kind', 'billed')->count());
    }

    public function test_own_role_and_short_passwords_are_guarded(): void
    {
        Mail::fake();
        $super = User::factory()->create(['role' => 'Super Admin']);
        $this->actingAs($super, 'sanctum')->patchJson("/api/users/{$super->id}", ['role' => 'Operator'])->assertStatus(422);

        $user = User::factory()->create(['role' => 'Admin', 'password' => bcrypt('password1')]);
        $this->actingAs($user, 'sanctum')->patchJson('/api/me/password', [
            'current_password' => 'password1', 'password' => 'abc', 'password_confirmation' => 'abc',
        ])->assertStatus(422);
    }

    public function test_mail_failure_does_not_break_saving(): void
    {
        \Illuminate\Support\Facades\Mail::shouldReceive('to')->andThrow(new \RuntimeException('smtp down'));
        $super = User::factory()->create(['role' => 'Super Admin']);

        $this->actingAs($super, 'sanctum')->postJson('/api/users', [
            'nama' => 'Baru', 'email' => 'baru@example.com', 'role' => 'Operator',
        ])->assertCreated();
        $this->assertDatabaseHas('users', ['email' => 'baru@example.com']);
    }

    public function test_closed_orders_and_billed_invoices_cannot_be_edited_and_empty_ticket_orders_are_refused(): void
    {
        $super = User::factory()->create(['role' => 'Super Admin']);
        $client = Client::create(['kode' => 'CLI-82', 'nama' => 'Tutup']);
        $payload = ['tipe' => 'ticket', 'clientId' => $client->id, 'namaOrder' => 'T', 'kodeGroup' => 'G-T', 'tanggalPemakaian' => '2026-12-06', 'ticketRows' => [['namaTiket' => 'Zoo', 'qty' => 1, 'modalSatuan' => 100000]]];

        $this->actingAs($super, 'sanctum')->postJson('/api/orders', [...$payload, 'ticketRows' => []])->assertStatus(422);

        $id = $this->actingAs($super, 'sanctum')->postJson('/api/orders', $payload)->assertCreated()->json('id');
        $invoice = Invoice::where('order_id', $id)->firstOrFail();
        $line = $invoice->lines()->first();
        $this->patchJson("/api/invoices/{$invoice->id}/lines", ['lines' => [['id' => $line->id, 'qty' => 1, 'hargaJual' => 150000]]])->assertOk();
        $this->postJson("/api/invoices/{$invoice->id}/mark-billed")->assertOk();

        $this->patchJson("/api/invoices/{$invoice->id}/lines", ['lines' => [['id' => $line->id, 'qty' => 1, 'hargaJual' => 1]]])->assertStatus(422);
        $this->assertSame(150000, $invoice->fresh()->total);

        $this->postJson("/api/invoices/{$invoice->id}/payments", ['amount' => 1000])->assertOk();
        $this->postJson("/api/invoices/{$invoice->id}/unmark-billed")->assertStatus(422);

        $this->postJson("/api/orders/{$id}/cancel", ['alasan' => 'batal'])->assertOk();
        $this->patchJson("/api/orders/{$id}", [...$payload, 'namaOrder' => 'Diubah setelah batal'])->assertStatus(422);
        $this->assertSame('T', \App\Models\Order::find($id)->nama_order);
    }
}
