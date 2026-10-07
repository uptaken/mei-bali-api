<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Product extends Model
{
    protected $fillable = ['product_category_id', 'nama', 'harga_jual', 'modal', 'deskripsi'];

    protected function casts(): array
    {
        return [
            'harga_jual' => 'integer',
            'modal' => 'integer',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class, 'product_category_id');
    }
}
