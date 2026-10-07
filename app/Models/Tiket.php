<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Master: Daftar Tiket — a bookable ticket product (distinct from an Order's `ticketRows`). */
class Tiket extends Model
{
    protected $table = 'tikets';

    protected $fillable = ['nama', 'kategori', 'harga_jual', 'modal', 'status'];

    protected function casts(): array
    {
        return [
            'harga_jual' => 'integer',
            'modal' => 'integer',
        ];
    }
}
