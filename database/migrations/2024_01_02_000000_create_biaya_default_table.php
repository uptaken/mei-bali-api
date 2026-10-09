<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
		public function up(): void
		{
				Schema::create('biaya_defaults', function (Blueprint $table) {
						$table->id();
						$table->string('tipe')->nullable();
						$table->string('ruteKategori')->nullable();
						$table->double('biayaDefault', 20, 0)->default(0);
						$table->timestamps();
				});
		}

		public function down(): void
		{
				Schema::dropIfExists('biaya_defaults');
		}
};