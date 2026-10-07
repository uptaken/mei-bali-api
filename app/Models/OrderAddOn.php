<?php

namespace App\Models;

use App\Enums\PayableStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderAddOn extends Model
{
    protected $table = 'order_add_ons';

    protected $fillable = [
        'order_id',
        'product_id',
        'qty',
        'modal',
        'catatan',
        'skip_biaya',
        'supplier_nama',
        'bayar_status',
        'tanggal_bayar',
    ];

    protected function casts(): array
    {
        return [
            'qty' => 'integer',
            'modal' => 'integer',
            'skip_biaya' => 'boolean',
            'bayar_status' => PayableStatus::class,
            'tanggal_bayar' => 'date',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
