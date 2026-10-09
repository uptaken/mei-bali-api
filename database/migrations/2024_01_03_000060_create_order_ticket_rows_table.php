<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_ticket_rows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tiket_id')->nullable()->constrained('tikets')->nullOnDelete();
            $table->string('nama_tiket');
            $table->unsignedInteger('qty')->default(1);
            $table->unsignedBigInteger('modal_satuan')->default(0);
            $table->unsignedBigInteger('harga_jual_ref')->default(0);
            $table->string('supplier_nama')->nullable();
            $table->string('bayar_status')->default('Belum Bayar');
            $table->date('tanggal_bayar')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_ticket_rows');
    }
};
