<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
		public function up(): void
		{
				Schema::create('guests', function (Blueprint $table) {
						$table->id();
						$table->string('nama')->unique();
						$table->string('kategori')->nullable();
						$table->string('clientId')->nullable();
						$table->string('negara')->nullable();
						$table->string('bahasa')->nullable();
						$table->string('paspor')->nullable();
						$table->text('catatan')->nullable();
						$table->timestamps();
				});
		}

		public function down(): void
		{
				Schema::dropIfExists('guests');
		}
};
