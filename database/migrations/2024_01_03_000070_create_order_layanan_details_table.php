<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_layanan_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('nama_tamu')->default('');
            $table->string('hotel_drop_off')->nullable();
            $table->string('pick_up_point')->nullable();
            $table->string('drop_off_point')->nullable();
            $table->string('jam_jemput')->nullable();
            $table->string('info_flight')->nullable();
            $table->unsignedInteger('jumlah_pax')->default(1);
            $table->unsignedBigInteger('biaya_modal')->default(0);
            $table->string('supplier_nama')->nullable();
            $table->string('bayar_status')->default('Belum Bayar');
            $table->date('tanggal_bayar')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_layanan_details');
    }
};
