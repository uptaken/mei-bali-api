<?php

use App\Models\WhatsAppTemplate;
use App\Support\OrderWhatsApp;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Jenis penerbangan (Domestik / Internasional) pada order Layanan, durasi tur per hari itinerary (pilihan di form yang
 * sebelumnya tidak tersimpan), plus template WA Tour / Check In / Check Out / Transfer.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('order_layanan_details', 'jenis_penerbangan')) {
            Schema::table('order_layanan_details', fn (Blueprint $table) => $table->string('jenis_penerbangan')->nullable()->after('info_flight'));
        }

        if (! Schema::hasColumn('order_itinerary_days', 'durasi_id')) {
            Schema::table('order_itinerary_days', fn (Blueprint $table) => $table->unsignedBigInteger('durasi_id')->nullable()->after('tempat_drop_akhir'));
        }

        // Tidak menimpa template yang sudah ada: teksnya mungkin sudah diubah di Master.
        foreach (OrderWhatsApp::TEMPLATES as $template) {
            WhatsAppTemplate::firstOrCreate(['nama' => $template['nama']], ['kategori' => 'Operasional', 'isi' => $template['isi'], 'variabel' => $template['variabel']]);
        }
    }

    public function down(): void
    {
        // Sengaja kosong, sama seperti migrasi kolom master lainnya.
    }
};
