<?php

namespace App\Models;

use App\Enums\PayableStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItineraryActivity extends Model
{
    protected $fillable = [
        'order_itinerary_day_id',
        'aktivitas',
        'biaya',
        'skip_biaya',
        'supplier_tur',
        'bayar_status',
        'tanggal_bayar',
    ];

    protected function casts(): array
    {
        return [
            'biaya' => 'integer',
            'skip_biaya' => 'boolean',
            'bayar_status' => PayableStatus::class,
            'tanggal_bayar' => 'date',
        ];
    }

    public function day(): BelongsTo
    {
        return $this->belongsTo(OrderItineraryDay::class, 'order_itinerary_day_id');
    }
}
