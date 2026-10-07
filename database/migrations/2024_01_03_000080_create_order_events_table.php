<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('kind'); // created | confirmed | assigned | wa | selesai | cancel_requested | cancel_approved | ...
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('at');
            $table->string('detail')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_events');
    }
};
