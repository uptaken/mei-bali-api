<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
		public function up(): void
		{
				Schema::create('packages', function (Blueprint $table) {
						$table->id();
						// $table->string('kode')->unique();
						$table->string('nama');
						$table->double('durasiHari', 20, 0)->default(0);
						$table->double('hargaMulai', 20, 0)->default(0);
						$table->text('kotaTermasuk')->nullable();
						$table->text('deskripsi')->nullable();
						$table->text('hari')->nullable();
						$table->timestamps();
				});
		}

		public function down(): void
		{
				Schema::dropIfExists('packages');
		}
};