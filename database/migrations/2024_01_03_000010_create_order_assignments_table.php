<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('hari')->default(1);
            $table->foreignId('vehicle_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('supplier_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedBigInteger('biaya_transport_modal')->default(0);
            $table->string('bayar_status')->default('Belum Bayar');
            $table->date('tanggal_bayar')->nullable();
            $table->timestamps();

            $table->index(['order_id', 'hari']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_assignments');
    }
};
