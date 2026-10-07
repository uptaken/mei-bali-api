<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->string('nomor')->unique();
            $table->foreignId('order_id')->constrained();
            $table->foreignId('client_id')->constrained();
            $table->unsignedBigInteger('total')->default(0);
            $table->unsignedBigInteger('sisa')->default(0);
            $table->date('tanggal_dibuat');
            $table->string('status')->default('Belum Ditagihkan');
            $table->text('catatan')->nullable();
            $table->date('tanggal_ditagihkan')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
