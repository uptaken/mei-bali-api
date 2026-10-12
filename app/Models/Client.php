<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Client extends Model
{
    use \App\Models\Concerns\Archivable;
    use HasFactory;

    protected $fillable = [
        'kode',
        'nama',
        'tipe',
        'kontak',
        'telepon',
        'email',
        'kota',
        'negara',
        'catatan',
        'alamat',
    ];

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /** Kode berikutnya untuk client baru: CLI-01, CLI-02, ... (angka terbesar yang sudah ada + 1, dilewati bila bentrok). */
    public static function nextKode(): string
    {
        $next = static::query()->pluck('kode')->map(fn ($kode) => (int) preg_replace('/\D/', '', $kode))->max() + 1;

        while (static::where('kode', sprintf('CLI-%02d', $next))->exists()) {
            $next++;
        }

        return sprintf('CLI-%02d', $next);
    }
}
