<?php

use App\Models\WhatsAppTemplate;
use App\Support\LayananWhatsApp;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Jenis penerbangan (Domestik / Internasional) pada order Layanan, plus template WA Check In / Check Out / Transfer. */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('order_layanan_details', 'jenis_penerbangan')) {
            Schema::table('order_layanan_details', fn (Blueprint $table) => $table->string('jenis_penerbangan')->nullable()->after('info_flight'));
        }

        // Tidak menimpa template yang sudah ada: teksnya mungkin sudah diubah di Master.
        foreach (LayananWhatsApp::TEMPLATES as $template) {
            WhatsAppTemplate::firstOrCreate(['nama' => $template['nama']], ['kategori' => 'Operasional', 'isi' => $template['isi'], 'variabel' => $template['variabel']]);
        }
    }

    public function down(): void
    {
        // Sengaja kosong, sama seperti migrasi kolom master lainnya.
    }
};
