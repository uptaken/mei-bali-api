<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OrderItineraryDay extends Model
{
    protected $fillable = [
        'order_id',
        'hari',
        'waktu_penjemputan',
        'tempat_penjemputan',
        'waktu_drop_akhir',
        'tempat_drop_akhir',
        'catatan',
    ];

    protected function casts(): array
    {
        return [
					'hari' => 'integer',
					'totalBiaya' => 'integer',
				];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function activities(): HasMany
    {
        return $this->hasMany(OrderItineraryActivity::class, 'order_itinerary_day_id');
    }
}
