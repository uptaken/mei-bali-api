<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Client;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdvanceOrderStatusesTest extends TestCase
{
    use RefreshDatabase;

    private Client $client;

    protected function setUp(): void
    {
        parent::setUp();
        $this->client = Client::create(['kode' => 'CLI-1', 'nama' => 'Klien']);
        $this->travelTo('2026-11-10 08:00:00');
    }

    /** @param array<string, mixed> $attributes */
    private function order(string $tipe, string $status, array $attributes = [], int $days = 0): Order
    {
        $order = Order::create($attributes + [
            'kode' => 'ORD-'.random_int(1000, 9999),
            'tipe' => $tipe,
            'status' => $status,
            'client_id' => $this->client->id,
            'nama_order' => 'Uji',
            'kode_group' => 'G-1',
            'dewasa' => 1,
            'anak' => 0,
            'created_by' => User::factory()->create()->id,
        ]);
        foreach (range(1, $days) as $day) {
            if ($days > 0) {
                $order->itineraryDays()->create(['hari' => $day]);
            }
        }

        return $order->refresh();
    }

    public function test_an_info_terkirim_order_starts_on_its_first_day(): void
    {
        $today = $this->order('tour', 'Info Terkirim', ['tanggal_mulai' => '2026-11-10'], 3);
        $tomorrow = $this->order('tour', 'Info Terkirim', ['tanggal_mulai' => '2026-11-11'], 3);

        $this->artisan('orders:advance-status')->assertSuccessful();

        $this->assertSame('Berlangsung', $today->refresh()->status->value);
        $this->assertSame('Info Terkirim', $tomorrow->refresh()->status->value);
        $event = $today->events()->first();
        $this->assertSame('berlangsung', $event->kind);
        $this->assertNull($event->actor_id);
    }

    public function test_a_multi_day_tour_finishes_only_after_its_last_day(): void
    {
        $lastDay = $this->order('tour', 'Berlangsung', ['tanggal_mulai' => '2026-11-08'], 3);
        $over = $this->order('tour', 'Berlangsung', ['tanggal_mulai' => '2026-11-07'], 3);

        $this->artisan('orders:advance-status')->assertSuccessful();

        $this->assertSame('Berlangsung', $lastDay->refresh()->status->value);
        $this->assertSame('Selesai', $over->refresh()->status->value);
        $this->assertSame('selesai', $over->events()->first()->kind);
    }

    public function test_layanan_and_ticket_orders_are_a_single_day(): void
    {
        $layanan = $this->order('layanan', 'Berlangsung', ['tanggal_mulai' => '2026-11-09', 'sub_tipe' => 'transfer']);
        $ticket = $this->order('ticket', 'Info Terkirim', ['tanggal_pemakaian' => '2026-11-10']);
        $oldTicket = $this->order('ticket', 'Info Terkirim', ['tanggal_pemakaian' => '2026-11-01']);

        $this->artisan('orders:advance-status')->assertSuccessful();

        $this->assertSame('Selesai', $layanan->refresh()->status->value);
        $this->assertSame('Berlangsung', $ticket->refresh()->status->value);
        $this->assertSame('Selesai', $oldTicket->refresh()->status->value, 'a missed run still closes an order that was never started');
    }

    public function test_orders_that_must_not_move_are_left_alone(): void
    {
        $waiting = $this->order('tour', 'Siap Kirim WA', ['tanggal_mulai' => '2026-10-01'], 1);
        $cancelling = $this->order('tour', 'Berlangsung', ['tanggal_mulai' => '2026-10-01', 'pending_cancellation' => true], 1);
        $cancelled = $this->order('tour', 'Dibatalkan', ['tanggal_mulai' => '2026-10-01'], 1);
        $noDate = $this->order('tour', 'Info Terkirim', [], 1);

        $this->artisan('orders:advance-status')->assertSuccessful();

        $this->assertSame('Siap Kirim WA', $waiting->refresh()->status->value);
        $this->assertSame('Berlangsung', $cancelling->refresh()->status->value);
        $this->assertSame('Dibatalkan', $cancelled->refresh()->status->value);
        $this->assertSame('Info Terkirim', $noDate->refresh()->status->value);
        $this->assertSame(0, \App\Models\OrderEvent::count());
    }

    public function test_running_it_twice_changes_nothing_more(): void
    {
        $this->order('tour', 'Info Terkirim', ['tanggal_mulai' => '2026-11-10'], 1);

        $this->artisan('orders:advance-status')->assertSuccessful();
        $this->artisan('orders:advance-status')->assertSuccessful();

        $this->assertSame(1, \App\Models\OrderEvent::count());
    }

    public function test_an_admin_can_close_an_order_by_hand_from_info_terkirim_or_berlangsung(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin->value]);
        $sent = $this->order('tour', 'Info Terkirim', ['tanggal_mulai' => '2026-12-01'], 1);
        $waiting = $this->order('tour', 'Siap Kirim WA', ['tanggal_mulai' => '2026-12-01'], 1);

        $this->actingAs($admin, 'sanctum')->postJson("/api/orders/{$sent->id}/selesai")->assertOk();
        $this->actingAs($admin, 'sanctum')->postJson("/api/orders/{$waiting->id}/selesai")->assertStatus(422);

        $this->assertSame('Selesai', $sent->refresh()->status->value);
        $this->assertSame($admin->id, $sent->events()->first()->actor_id);
        $this->assertSame('Siap Kirim WA', $waiting->refresh()->status->value);
    }
}
