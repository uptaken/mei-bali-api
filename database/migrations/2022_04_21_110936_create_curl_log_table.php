<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

use App\Http\Controllers\Helper\MigrationHelper;

class CreateCurlLogTable extends MigrationHelper
{
  /**
  * Run the migrations.
  *
  * @return void
  */
  public function up()
  {
    $context = $this;
    Schema::create('curl_log', function (Blueprint $table) use($context) {
      $context->base_column($table);
      $table->string('method',100)->nullable();
      $table->string('url',100)->nullable();
      $table->longText('header')->nullable();
      $table->longText('request')->nullable();
      $table->longText('response')->nullable();
    });
  }

  /**
  * Reverse the migrations.
  *
  * @return void
  */
  public function down()
  {
    Schema::dropIfExists('curl_log');
  }
}
