<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Nama driver yang membawa mobil dari supplier ini (kolom tipe_mobil lama dibiarkan; jenis mobil dipilih per order). */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('suppliers', 'nama_driver')) {
            Schema::table('suppliers', fn (Blueprint $table) => $table->string('nama_driver')->nullable());
        }
    }

    public function down(): void
    {
        // Sengaja kosong, sama seperti migrasi kolom master lainnya.
    }
};
