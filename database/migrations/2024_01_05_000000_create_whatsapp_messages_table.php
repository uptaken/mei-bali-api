<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_messages', function (Blueprint $table) {
            $table->id();
            // Polymorphic — an Order (assignment confirmation) or an Invoice (billing reminder) today,
            // anything else that needs a WA send later without a new table.
            $table->nullableMorphs('related');
            $table->foreignId('sent_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('to_phone');
            $table->string('to_name')->nullable();
            $table->text('message');
            $table->string('status')->default('pending'); // pending | sent | failed
            $table->json('provider_response')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_messages');
    }
};
