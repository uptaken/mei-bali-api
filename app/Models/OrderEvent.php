<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Riwayat — append-only event log for an Order (created, confirmed, assigned, wa, selesai, ...). */
class OrderEvent extends Model
{
    public $timestamps = false;

    protected $fillable = ['order_id', 'kind', 'actor_id', 'at', 'detail'];

    protected function casts(): array
    {
        return ['at' => 'datetime'];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
