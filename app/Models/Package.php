<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Package extends Model
{
    use HasFactory;

    protected $fillable = [
        // 'kode',
        'nama',
				'durasiHari',
				'hargaMulai',
        'kotaTermasuk',
				'deskripsi',
				'hari',
    ];
}
