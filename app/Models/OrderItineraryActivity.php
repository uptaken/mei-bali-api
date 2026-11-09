<?php

namespace App\Models;

use App\Enums\PayableStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Satu aktivitas dalam hari itinerary tur, dengan biaya (modal) dan supplier tur. `skip_biaya` berarti tidak ada tagihan
 * (mis. gratis atau sudah termasuk paket).
 */
class OrderItineraryActivity extends Model
{
    protected $fillable = [
        'order_itinerary_day_id',
        'aktivitas',
        'biaya',
        'skip_biaya',
        'supplier_tur',
        'booked_by',
        'urutan',
        'bayar_status',
        'tanggal_bayar',
    ];

    protected function casts(): array
    {
        return [
            'biaya' => 'integer',
            'skip_biaya' => 'boolean',
            'bayar_status' => PayableStatus::class,
            'tanggal_bayar' => 'date:Y-m-d',
        ];
    }

    /** Admin/Operator yang membooking aktivitas ini. Bukan `bookedBy`: di JSON namanya akan bentrok dengan kolom `booked_by`. */
    public function booker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'booked_by')->select('id', 'nama');
    }

    public function day(): BelongsTo
    {
        return $this->belongsTo(OrderItineraryDay::class, 'order_itinerary_day_id');
    }
}
