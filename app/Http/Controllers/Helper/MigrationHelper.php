<?php
namespace App\Http\Controllers\Helper;

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class MigrationHelper extends Migration
{
  protected function base_column($table){
    $table->string('id',100);
    $table->timestamps();
    $table->softdeletes();
    $table->string('created_by',100)->nullable();
    $table->string('updated_by',100)->nullable();
    $table->string('deleted_by',100)->nullable();

    $table->primary('id');
  }

  protected function payment_column($table){
    $table->string('payment_method_id',100)->nullable();
    $table->double('subtotal',20, 2)->default(0);
    $table->double('admin_fee',20, 2)->default(0);
    $table->double('total_price',20, 2)->default(0);

    $table->enum('status_payment', ['unpaid', 'paid',])->default('unpaid');
    $table->dateTime('paid_at')->nullable();
    $table->dateTime('payment_expired_at')->nullable();
    $table->string('va_no',100)->nullable();
    $table->text('url_payment')->nullable();
    $table->longText('qr_string')->nullable();
    $table->string('transaction_code', 100)->nullable();
    $table->string('credit_card_no', 100)->nullable();
    $table->string('credit_card_expired', 100)->nullable();
    $table->string('credit_card_cvv', 3)->nullable();
    $table->longText('credit_card_token_id')->nullable();
    $table->text('transaction_id')->nullable();

    $table->foreign('payment_method_id')->references('id')->on('payment_method')->onDelete('cascade');
  }
}
