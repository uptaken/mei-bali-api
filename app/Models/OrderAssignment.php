<?php

namespace App\Models;

use App\Enums\PayableStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One Supplier/Driver + Mobil assignment. A multi-day Tour (or any order needing more than one
 * vehicle — "1 mobil tidak cukup") carries several rows per day; `hari` alone is not unique.
 */
class OrderAssignment extends Model
{
    protected $fillable = [
        'order_id',
        'hari',
        'vehicle_id',
        'supplier_id',
        'biaya_transport_modal',
        'bayar_status',
        'tanggal_bayar',
    ];

    protected function casts(): array
    {
        return [
            'hari' => 'integer',
            'biaya_transport_modal' => 'integer',
            'bayar_status' => PayableStatus::class,
            'tanggal_bayar' => 'date:Y-m-d',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }
}
