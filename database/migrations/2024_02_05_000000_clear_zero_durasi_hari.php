<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Order Layanan/Ticket yang pernah disimpan dari form tersimpan dengan durasi 0 (itinerary kosong dihitung 0).
 * Nilai itu lalu dikirim balik oleh form dan ditolak validasi, sehingga order tidak bisa disimpan lagi. 0 berarti "tidak ada".
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('orders')->where('durasi_hari', 0)->update(['durasi_hari' => null]);
    }

    public function down(): void
    {
        // Tidak ada yang perlu dikembalikan: 0 dan kosong berarti sama.
    }
};
