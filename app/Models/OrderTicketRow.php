<?php

namespace App\Models;

use App\Enums\PayableStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Satu baris tiket pada order Ticket: nama tiket, qty, modal satuan, dan supplier.
 */
class OrderTicketRow extends Model
{
    protected $fillable = [
        'order_id',
        'tiket_id',
        'nama_tiket',
        'qty',
        'modal_satuan',
        'harga_jual_ref',
        'supplier_nama',
        'bayar_status',
        'tanggal_bayar',
    ];

    protected function casts(): array
    {
        return [
            'qty' => 'integer',
            'modal_satuan' => 'integer',
            'harga_jual_ref' => 'integer',
            'bayar_status' => PayableStatus::class,
            'tanggal_bayar' => 'date:Y-m-d',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function tiket(): BelongsTo
    {
        return $this->belongsTo(Tiket::class);
    }
}
