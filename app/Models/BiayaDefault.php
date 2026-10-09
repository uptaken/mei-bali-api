<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BiayaDefault extends Model
{
    use HasFactory;

    protected $fillable = [
        'tipe',
        'ruteKategori',
        'biayaDefault',
    ];
}
