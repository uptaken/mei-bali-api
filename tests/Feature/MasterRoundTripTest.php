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

        $supplier = $this->postJson('/api/suppliers', ['nama' => 'Wayan', 'telepon' => '0812', 'tipe_mobil' => 'Avanza', 'area' => 'Ubud', 'catatan' => 'Fasih bahasa Inggris'])
            ->assertCreated()->assertJsonPath('area', 'Ubud')->assertJsonPath('catatan', 'Fasih bahasa Inggris');
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
}
