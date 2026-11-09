<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Client;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ActivityBookerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $operator;

    private Client $client;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => UserRole::Admin->value, 'nama' => 'Ayu Admin']);
        $this->operator = User::factory()->create(['role' => UserRole::Operator->value, 'nama' => 'Made Operator']);
        $this->client = Client::create(['kode' => 'CLI-1', 'nama' => 'Klien']);
    }

    /** @return array<string, mixed> */
    private function payload(?int $bookedBy): array
    {
        return [
            'tipe' => 'tour', 'clientId' => $this->client->id, 'namaOrder' => 'Tur', 'kodeGroup' => 'G-1', 'tanggalMulai' => '2026-12-01',
            'itinerary' => [['hari' => 1, 'aktivitas' => [
                ['aktivitas' => 'Ijen Trekking', 'biaya' => 1000, 'bookedBy' => $bookedBy],
                ['aktivitas' => 'Snorkeling', 'biaya' => 2000],
            ]]],
        ];
    }

    public function test_the_booker_is_saved_and_shown_with_its_name_to_every_role(): void
    {
        $created = $this->actingAs($this->admin, 'sanctum')->postJson('/api/orders', $this->payload($this->operator->id))->assertCreated();

        $activities = $created->json('itinerary_days.0.activities');
        $this->assertSame($this->operator->id, $activities[0]['booked_by']);
        $this->assertSame('Made Operator', $activities[0]['booker']['nama']);
        $this->assertNull($activities[1]['booked_by']);

        // The Operator cannot list users, but still sees who booked from the order itself.
        $shown = $this->actingAs($this->operator, 'sanctum')->getJson('/api/orders/'.$created->json('id'))->assertOk();
        $this->assertSame('Made Operator', $shown->json('itinerary_days.0.activities.0.booker.nama'));
        $listed = $this->actingAs($this->operator, 'sanctum')->getJson('/api/orders')->assertOk();
        $this->assertSame('Made Operator', $listed->json('data.0.itinerary_days.0.activities.0.booker.nama'));
    }

    public function test_only_an_admin_or_operator_can_be_the_booker(): void
    {
        $superAdmin = User::factory()->create(['role' => UserRole::SuperAdmin->value]);

        $this->actingAs($this->admin, 'sanctum')->postJson('/api/orders', $this->payload($superAdmin->id))->assertUnprocessable();
        $this->actingAs($this->admin, 'sanctum')->postJson('/api/orders', $this->payload(999999))->assertUnprocessable();
        $this->actingAs($this->admin, 'sanctum')->postJson('/api/orders', $this->payload($this->admin->id))->assertCreated();
    }

    public function test_editing_keeps_the_booker_and_can_change_or_clear_it(): void
    {
        $created = $this->actingAs($this->admin, 'sanctum')->postJson('/api/orders', $this->payload($this->operator->id))->assertCreated();
        $id = $created->json('id');
        $activityId = $created->json('itinerary_days.0.activities.0.id');

        $edit = fn (array $activity) => $this->actingAs($this->admin, 'sanctum')->patchJson("/api/orders/{$id}", [
            'itinerary' => [['hari' => 1, 'aktivitas' => [['id' => $activityId, 'aktivitas' => 'Ijen Trekking', 'biaya' => 1000, ...$activity]]]],
        ])->assertOk()->json('itinerary_days.0.activities.0.booked_by');

        $this->assertSame($this->operator->id, $edit([]), 'a payload that does not mention the booker leaves it alone');
        $this->assertSame($this->admin->id, $edit(['bookedBy' => $this->admin->id]));
        $this->assertNull($edit(['bookedBy' => null]));
    }

    public function test_booking_staff_lists_admins_and_operators_for_admins_only(): void
    {
        User::factory()->create(['role' => UserRole::SuperAdmin->value, 'nama' => 'Bos']);

        $names = collect($this->actingAs($this->admin, 'sanctum')->getJson('/api/booking-staff')->assertOk()->json())->pluck('nama')->all();
        $this->assertEqualsCanonicalizing(['Ayu Admin', 'Made Operator'], $names);
        $this->actingAs($this->operator, 'sanctum')->getJson('/api/booking-staff')->assertForbidden();
    }
}
