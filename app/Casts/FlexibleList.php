<?php

namespace App\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * Daftar yang disimpan sebagai JSON di kolom teks (mis. kota dalam satu negara, rencana harian paket).
 * Frontend mengirim dan membaca array. Isi lama yang berupa teks biasa ("Denpasar, Ubud") tetap terbaca:
 * dipecah per koma, sehingga data yang sudah ada tidak rusak.
 */
class FlexibleList implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): array
    {
        if ($value === null || $value === '') {
            return [];
        }

        $decoded = json_decode($value, true);

        if (is_array($decoded)) {
            return $decoded;
        }

        return array_values(array_filter(array_map('trim', explode(',', $value)), fn ($item) => $item !== ''));
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null) {
            return null;
        }

        return is_array($value) ? json_encode($value, JSON_UNESCAPED_UNICODE) : (string) $value;
    }
}
