<?php

namespace App\Models;

use App\Enums\InvoiceStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Tagihan ke client untuk satu order. Baris (`lines`) menyimpan modal dan Harga Jual; `total` dan `sisa` dihitung
 * dari Harga Jual dikurangi pembayaran yang sudah masuk.
 */
class Invoice extends Model
{
    protected $fillable = [
        'nomor',
        'order_id',
        'client_id',
        'total',
        'sisa',
        'tanggal_dibuat',
        'status',
        'catatan',
        'tanggal_ditagihkan',
    ];

    protected function casts(): array
    {
        return [
            'total' => 'integer',
            'sisa' => 'integer',
						'modal' => 'integer',
            'tanggal_dibuat' => 'date:Y-m-d',
            'status' => InvoiceStatus::class,
            'tanggal_ditagihkan' => 'date:Y-m-d',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(InvoiceLine::class, 'invoice_id', 'id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(InvoiceEvent::class)->orderBy('at');
    }
}
