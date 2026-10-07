<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
		public function up(): void
		{
				Schema::create('clients', function (Blueprint $table) {
						$table->id();
						$table->string('kode')->unique();
						$table->string('nama');
						$table->string('tipe')->nullable();
						$table->string('kontak')->nullable();
						$table->string('telepon')->nullable();
						$table->string('email')->nullable();
						$table->string('kota')->nullable();
						$table->string('negara')->nullable();
						$table->text('catatan')->nullable();
						$table->timestamps();
				});
		}

		public function down(): void
		{
				Schema::dropIfExists('clients');
		}
};
