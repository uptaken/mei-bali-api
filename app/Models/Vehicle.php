<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Vehicle extends Model
{
    use \App\Models\Concerns\Archivable;
    // protected $fillable = ['nama', 'kapasitas', 'kategori', 'catatan', ];
		protected $guarded = [];
}
