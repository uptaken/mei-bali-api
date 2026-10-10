<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Client;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class StaleRowsTest extends TestCase
{
    use DatabaseMigrations; // no wrapping transaction, so foreign keys can really be switched off below

    public function test_a_new_order_reusing_a_deleted_orders_id_starts_with_a_clean_riwayat(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin->value]);
        $this->actingAs($admin, 'sanctum');
        $client = Client::create(['kode' => 'CLI-07', 'nama' => 'C']);
        $payload = ['tipe' => 'ticket', 'clientId' => $client->id, 'namaOrder' => 'X', 'kodeGroup' => 'G', 'tanggalPemakaian' => '2026-11-10',
            'ticketRows' => [['namaTiket' => 'Zoo', 'qty' => 1, 'modalSatuan' => 100000]]];

        $first = $this->postJson('/api/orders', $payload)->assertCreated()->json('id');

        // Orders (and their invoices) are truncated but other child tables are forgotten, so the next order gets the same id
        // while its old Riwayat rows are still there.
        Schema::disableForeignKeyConstraints();
        DB::table('invoice_events')->delete();
        DB::table('invoice_lines')->delete();
        DB::table('invoices')->delete();
        DB::table('orders')->delete();
        DB::table('sqlite_sequence')->where('name', 'orders')->delete();
        Schema::enableForeignKeyConstraints();

        $this->assertSame(2, DB::table('order_events')->where('order_id', $first)->count(), 'precondition: the old events were left behind');

        $second = $this->postJson('/api/orders', $payload)->assertCreated();

        $this->assertSame($first, $second->json('id'));
        $this->assertSame(['created', 'confirmed'], collect($second->json('events'))->pluck('kind')->all());
        $this->assertCount(1, $second->json('invoices'));
    }
}
