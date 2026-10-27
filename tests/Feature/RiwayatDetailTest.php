<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RiwayatDetailTest extends TestCase
{
    use RefreshDatabase;

    public function test_riwayat_names_what_an_edit_changed_and_who_did_it(): void
    {
        $admin = User::factory()->create(['role' => 'Admin', 'nama' => 'Admin A']);
        $client = Client::create(['kode' => 'CLI-93', 'nama' => 'Riwayat']);
        $payload = [
            'tipe' => 'tour', 'clientId' => $client->id, 'namaOrder' => 'Ubud', 'kodeGroup' => 'G-R', 'tanggalMulai' => '2026-11-20', 'jamMulai' => '08:00',
            'dewasa' => 2, 'anak' => 0, 'catatan' => 'lama',
            'itinerary' => [
                ['hari' => 1, 'aktivitas' => [['aktivitas' => 'Tegalalang', 'biaya' => 100000]]],
                ['hari' => 2, 'aktivitas' => [['aktivitas' => 'Monkey Forest', 'biaya' => 50000]]],
            ],
        ];
        $id = $this->actingAs($admin, 'sanctum')->postJson('/api/orders', $payload)->assertCreated()->json('id');

        $edit = $payload;
        $edit['namaOrder'] = 'Ubud Plus';
        $edit['tanggalMulai'] = '2026-11-25';
        $edit['catatan'] = 'baru';
        $edit['itinerary'][1]['aktivitas'][0]['biaya'] = 75000;
        $this->actingAs($admin, 'sanctum')->patchJson("/api/orders/{$id}", $edit)->assertOk();

        $events = collect($this->actingAs($admin, 'sanctum')->getJson("/api/orders/{$id}")->json('events'));
        $updated = $events->firstWhere('kind', 'updated');
        $this->assertSame('Admin A', $updated['actor']['nama']);
        $this->assertStringContainsString('Nama Order: Ubud → Ubud Plus', $updated['detail']);
        $this->assertStringContainsString('Tanggal Mulai: 2026-11-20 → 2026-11-25', $updated['detail']);
        $this->assertStringContainsString('Catatan', $updated['detail']);
        $this->assertStringContainsString('Modal Tur Hari 2', $updated['detail']);
        $this->assertStringNotContainsString('Hari 1', $updated['detail']);
        $this->assertStringNotContainsString('75000', $updated['detail']);

        // The list carries the actor's name too, so a role that cannot read /api/users still sees who edited.
        $row = collect($this->actingAs($admin, 'sanctum')->getJson('/api/orders')->json('data'))->firstWhere('id', $id);
        $this->assertSame('Admin A', collect($row['events'])->firstWhere('kind', 'updated')['actor']['nama']);
    }
}
