<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('kode')->unique();
            $table->string('tipe'); // tour | layanan | ticket
            $table->string('sub_tipe')->nullable(); // check_in | check_out | transfer
            $table->foreignId('client_id')->constrained();
            $table->string('nama_order');
            $table->string('kode_group');
            $table->string('status');
            $table->unsignedBigInteger('total')->default(0);

            $table->date('tanggal_mulai')->nullable();
            $table->string('jam_mulai')->nullable();
            $table->unsignedInteger('durasi_hari')->nullable();
            $table->string('destinasi')->nullable();
            $table->string('bahasa_id')->nullable();

            $table->string('kota')->nullable();
            $table->json('kota_termasuk')->nullable();
            $table->string('paket_id')->nullable();

            $table->unsignedInteger('dewasa')->default(0);
            $table->unsignedInteger('anak')->default(0);
            $table->text('catatan')->nullable();

            // Legacy day-1 mirror — every screen that only ever looks at "the" assignment reads
            // these instead of joining order_assignments (same convention as the frontend).
            $table->foreignId('vehicle_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('supplier_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedBigInteger('biaya_transport_modal')->default(0);
            $table->text('catatan_penugasan')->nullable();
            $table->timestamp('wa_sent_at')->nullable();

            $table->date('tanggal_pemakaian')->nullable(); // ticket only
            $table->boolean('pending_cancellation')->default(false);

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->index(['tipe', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
