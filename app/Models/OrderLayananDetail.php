<?php

namespace App\Models;

use App\Enums\PayableStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Detail khusus order Layanan (transfer, check-in/out): tamu, titik jemput/antar, info flight, jumlah pax, dan modal.
 */
class OrderLayananDetail extends Model
{
    protected $fillable = [
        'order_id',
        'nama_tamu',
        'hotel_drop_off',
        'pick_up_point',
        'drop_off_point',
        'jam_jemput',
        'info_flight',
        'jumlah_pax',
        'biaya_modal',
        'supplier_nama',
        'bayar_status',
        'tanggal_bayar',
    ];

    protected function casts(): array
    {
        return [
            'jumlah_pax' => 'integer',
            'biaya_modal' => 'integer',
            'bayar_status' => PayableStatus::class,
            'tanggal_bayar' => 'date:Y-m-d',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
