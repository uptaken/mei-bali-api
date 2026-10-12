<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Master yang sudah dipakai order tidak boleh dihapus, tapi boleh dinonaktifkan: tetap ada untuk riwayat
 * (nama di order, invoice, tagihan lama) namun tidak muncul lagi di pilihan order baru.
 */
return new class extends Migration
{
    private const TABLES = ['clients', 'suppliers', 'vehicles', 'products'];

    public function up(): void
    {
        foreach (self::TABLES as $table) {
            Schema::table($table, fn (Blueprint $t) => $t->timestamp('archived_at')->nullable());
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $table) {
            Schema::table($table, fn (Blueprint $t) => $t->dropColumn('archived_at'));
        }
    }
};
