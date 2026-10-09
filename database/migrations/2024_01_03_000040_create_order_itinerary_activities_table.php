<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_itinerary_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_itinerary_day_id')->constrained()->cascadeOnDelete();
            $table->string('aktivitas')->default('');
            $table->unsignedBigInteger('biaya')->default(0);
            $table->boolean('skip_biaya')->default(false);
            $table->string('supplier_tur')->nullable();
            $table->string('bayar_status')->default('Belum Bayar');
            $table->date('tanggal_bayar')->nullable();
            $table->unsignedInteger('urutan')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_itinerary_activities');
    }
};
