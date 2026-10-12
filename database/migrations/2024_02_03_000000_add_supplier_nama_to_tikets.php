<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Vendor bawaan sebuah tiket: disalin ke baris order Ticket agar tagihannya langsung punya tujuan bayar. */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('tikets', 'supplier_nama')) {
            Schema::table('tikets', fn (Blueprint $table) => $table->string('supplier_nama')->nullable());
        }
    }

    public function down(): void
    {
        // Sengaja kosong, sama seperti migrasi kolom master lainnya.
    }
};
