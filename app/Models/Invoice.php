<?php

namespace App\Models;

use App\Enums\InvoiceStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
            'tanggal_dibuat' => 'date',
            'status' => InvoiceStatus::class,
            'tanggal_ditagihkan' => 'date',
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
