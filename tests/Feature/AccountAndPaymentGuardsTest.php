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
}
