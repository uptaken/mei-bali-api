<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
		public function up(): void
		{
				Schema::create('languages', function (Blueprint $table) {
						$table->id();
						$table->string('kode')->unique();
						$table->string('nama');
						$table->text('catatan')->nullable();
						$table->timestamps();
				});
		}

		public function down(): void
		{
				Schema::dropIfExists('languages');
		}
};