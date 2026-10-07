<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoiceLine extends Model
{
    protected $fillable = [
        'invoice_id',
        'deskripsi',
        'qty',
        'modal',
        'harga_jual',
        'supplier_nama',
        'skip_biaya',
    ];

    protected function casts(): array
    {
        return [
            'qty' => 'integer',
            'modal' => 'integer',
            'harga_jual' => 'integer',
            'skip_biaya' => 'boolean',
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function subtotal(): int
    {
        return $this->qty * $this->harga_jual;
    }
}
