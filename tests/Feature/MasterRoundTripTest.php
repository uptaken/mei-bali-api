<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Country;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Every field a master form sends must be stored and come back the way the frontend reads it. */
class MasterRoundTripTest extends TestCase
{
    use RefreshDatabase;

    public function test_masters_keep_every_field_the_forms_send(): void
    {
        $this->actingAs(User::factory()->create(['role' => UserRole::Admin->value]), 'sanctum');

        $supplier = $this->postJson('/api/suppliers', ['nama' => 'Wayan', 'telepon' => '0812', 'nama_driver' => 'Komang', 'area' => 'Ubud', 'catatan' => 'Fasih bahasa Inggris'])
            ->assertCreated()->assertJsonPath('nama_driver', 'Komang')->assertJsonPath('area', 'Ubud')->assertJsonPath('catatan', 'Fasih bahasa Inggris');
        $this->patchJson('/api/suppliers/'.$supplier->json('id'), ['area' => 'Kuta'])->assertOk()->assertJsonPath('area', 'Kuta');

        $this->postJson('/api/vehicles', ['nama' => 'Hiace', 'kapasitas' => 12, 'kategori' => 'Minibus', 'catatan' => 'AC'])
            ->assertCreated()->assertJsonPath('kategori', 'Minibus')->assertJsonPath('catatan', 'AC');

        $this->postJson('/api/clients', ['nama' => 'PT Baru', 'kontak' => 'Rani', 'alamat' => 'Jl. Sunset Road 1'])
            ->assertCreated()->assertJsonPath('alamat', 'Jl. Sunset Road 1');

        // Lists arrive as real arrays, whether sent as arrays or as JSON text.
        $this->postJson('/api/countries', ['kode' => 'ID', 'nama' => 'Indonesia', 'kota' => ['Denpasar', 'Ubud']])
            ->assertCreated()->assertJsonPath('kota', ['Denpasar', 'Ubud']);
        $this->postJson('/api/countries', ['kode' => 'MY', 'nama' => 'Malaysia', 'kota' => '["Penang"]'])
            ->assertCreated()->assertJsonPath('kota', ['Penang']);
        Country::query()->where('kode', 'MY')->update(['kota' => 'Penang, Ipoh']); // old plain-text row
        $this->getJson('/api/countries')->assertOk()->assertJsonFragment(['kota' => ['Penang', 'Ipoh']]);

        $this->postJson('/api/packages', ['nama' => 'Bali 2D', 'durasiHari' => 2, 'hargaMulai' => 1000000, 'kotaTermasuk' => ['Ubud', 'Kuta'], 'deskripsi' => 'x', 'hari' => [['hari' => 1, 'judul' => 'Ubud', 'kota' => 'Ubud']]])
            ->assertCreated()->assertJsonPath('kotaTermasuk', ['Ubud', 'Kuta'])->assertJsonPath('hari.0.judul', 'Ubud');
    }

    public function test_bad_master_input_is_refused_with_a_readable_message_not_a_server_error(): void
    {
        $this->actingAs(User::factory()->create(['role' => UserRole::Admin->value]), 'sanctum');

        // A duplicate kode used to hit the database constraint and answer 500.
        $this->postJson('/api/countries', ['kode' => 'ZZ', 'nama' => 'A'])->assertCreated();
        $this->postJson('/api/countries', ['kode' => 'ZZ', 'nama' => 'B'])->assertStatus(422)->assertJsonValidationErrors('kode');
        $this->postJson('/api/languages', ['kode' => 'en', 'nama' => 'English'])->assertCreated();
        $second = $this->postJson('/api/languages', ['kode' => 'id', 'nama' => 'Indonesia'])->assertCreated()->json('id');
        $this->patchJson("/api/languages/{$second}", ['kode' => 'en'])->assertStatus(422)->assertJsonValidationErrors('kode');
        $this->patchJson("/api/languages/{$second}", ['kode' => 'id', 'nama' => 'Bahasa'])->assertOk(); // keeping its own kode is fine

        // Names longer than the column would fail on MySQL with "Data too long".
        $this->postJson('/api/suppliers', ['nama' => str_repeat('x', 300), 'telepon' => '0812'])->assertStatus(422)->assertJsonValidationErrors('nama');
        $this->postJson('/api/vehicles', ['nama' => 'Minus', 'kapasitas' => -5])->assertStatus(422)->assertJsonValidationErrors('kapasitas');
    }
}
