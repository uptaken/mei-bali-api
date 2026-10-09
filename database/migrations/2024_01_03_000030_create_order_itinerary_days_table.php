<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_itinerary_days', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('hari');
            $table->string('waktu_penjemputan')->nullable();
            $table->string('tempat_penjemputan')->nullable();
            $table->string('waktu_drop_akhir')->nullable();
            $table->string('tempat_drop_akhir')->nullable();
            $table->text('catatan')->nullable();
            $table->timestamps();

            $table->unique(['order_id', 'hari']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_itinerary_days');
    }
};
