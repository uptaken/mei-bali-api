<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kolom yang sudah dikirim form master dan dipakai controller, tetapi belum dibuat migrasi mana pun
 * (menyimpan supplier/mobil/client gagal dengan error 500 "no column named ..."). Setiap kolom ditambah
 * hanya bila belum ada, jadi aman dijalankan di database yang kolomnya sudah dibuat manual.
 */
return new class extends Migration
{
    /** @var array<string, array<string, string>> tabel => [kolom => tipe] */
    private const COLUMNS = [
        'suppliers' => ['area' => 'string', 'catatan' => 'text'],
        'vehicles' => ['kategori' => 'string', 'catatan' => 'text'],
        'clients' => ['alamat' => 'text'],
    ];

    public function up(): void
    {
        foreach (self::COLUMNS as $table => $columns) {
            foreach ($columns as $column => $type) {
                if (! Schema::hasColumn($table, $column)) {
                    Schema::table($table, fn (Blueprint $t) => $t->{$type}($column)->nullable());
                }
            }
        }
    }

    public function down(): void
    {
        // Sengaja kosong: kolom bisa sudah ada sebelum migrasi ini dan berisi data.
    }
};
