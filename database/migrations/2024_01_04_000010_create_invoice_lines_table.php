<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoice_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->string('deskripsi');
            $table->unsignedInteger('qty')->default(1);
            $table->unsignedBigInteger('modal')->default(0);
            $table->unsignedBigInteger('harga_jual')->default(0);
            $table->string('supplier_nama')->nullable();
            $table->boolean('skip_biaya')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_lines');
    }
};
