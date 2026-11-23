<?php

namespace App\Console\Commands;

use App\Enums\OrderStatus;
use App\Models\Order;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Daily lifecycle step: an Info Terkirim order becomes Berlangsung on its first service day, and an
 * Info Terkirim/Berlangsung order becomes Selesai once its last service day has passed. Orders
 * waiting on a cancellation decision are left alone, and the Riwayat entries carry no actor ("Sistem").
 */
class AdvanceOrderStatuses extends Command
{
    protected $signature = 'orders:advance-status';

    protected $description = 'Move orders to Berlangsung on their start date and to Selesai after their last day';

    public function handle(): int
    {
        $today = today();
        $started = 0;
        $finished = 0;

        Order::query()
            ->whereIn('status', [OrderStatus::InfoTerkirim->value, OrderStatus::Berlangsung->value])
            ->where('pending_cancellation', false)
            ->chunkById(100, function ($orders) use ($today, &$started, &$finished) {
                foreach ($orders as $order) {
                    $start = $order->serviceStart();
                    $end = $order->serviceEnd();

                    if (! $start || ! $end) {
                        continue;
                    }

                    if ($end->lt($today)) {
                        $this->move($order, OrderStatus::Selesai, 'selesai');
                        $finished++;
                    } elseif ($order->status === OrderStatus::InfoTerkirim && $start->lte($today)) {
                        $this->move($order, OrderStatus::Berlangsung, 'berlangsung');
                        $started++;
                    }
                }
            });

        $this->info("{$started} order berlangsung, {$finished} order selesai.");

        return self::SUCCESS;
    }

    private function move(Order $order, OrderStatus $status, string $event): void
    {
        DB::transaction(function () use ($order, $status, $event) {
            $order->update(['status' => $status->value]);
            $order->events()->create(['kind' => $event, 'actor_id' => null, 'at' => now()]);
        });
    }
}
