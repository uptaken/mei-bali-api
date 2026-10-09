<?php

namespace App\Services;

use Carbon\Carbon;

use App\Models\Order;
use Illuminate\Support\Facades\DB;

class NumberingService
{
    /** ORD-0241, ORD-0242, ... — same "ORD-0" + running counter scheme as the frontend prototype. */
    public function nextOrderCode(): string
    {
        $last = Order::query()->orderByDesc('kode')->value('kode');
        $lastSeq = $last ? (int) substr($last, strrpos($last, '-') + 2) : 240;

        return 'ORD-0'.($lastSeq + 1);
    }

    /** INV/2026/09/0241 — year/month from the Order's Tanggal Mulai, numeric suffix from its kode. */
    public function nextInvoiceNomor(Order $order): string
    {
        $date = Carbon::parse($order->tanggal_mulai) ?? $order->tanggal_pemakaian ?? now();
        $num = explode('-', $order->kode)[1] ?? '0000';

        return sprintf('INV/%s/%s/%s', $date->isoFormat('YYYY'), $date->isoFormat('MM'), $num);
    }

    /** Wraps a callback in a transaction with a row lock on the orders table, so concurrent creates never collide. */
    public function withOrderLock(\Closure $callback)
    {
        return DB::transaction(function () use ($callback) {
            DB::table('orders')->lockForUpdate()->count();

            return $callback();
        });
    }
}
