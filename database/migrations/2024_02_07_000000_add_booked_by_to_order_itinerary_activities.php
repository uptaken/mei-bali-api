<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Admin atau Operator yang membooking sebuah aktivitas tur; kosong bila belum diisi atau penggunanya sudah dihapus. */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('order_itinerary_activities', 'booked_by')) {
            Schema::table('order_itinerary_activities', function (Blueprint $table) {
                $table->foreignId('booked_by')->nullable()->constrained('users')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        // Sengaja kosong, sama seperti migrasi kolom lainnya.
    }
};
