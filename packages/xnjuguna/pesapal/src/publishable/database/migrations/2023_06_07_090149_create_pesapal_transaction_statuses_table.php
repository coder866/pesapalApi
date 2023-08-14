<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;

class CreatePesapalTransactionStatusesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('pesapal_transaction_statuses', function (Blueprint $table) {
            $table->id();
            $table->string('payment_method')->nullable();
            $table->decimal('amount', 8, 2)->default(0);
            $table->timestamp('created_date')->default(Carbon::now());
            $table->string('confirmation_code')->nullable();
            $table->string('payment_status_description')->nullable();
            $table->text('description')->nullable();
            $table->string('message')->nullable();
            $table->string('payment_account')->nullable();
            $table->text('call_back_url')->nullable();
            $table->unsignedTinyInteger('status_code')->nullable();
            $table->string('merchant_reference')->nullable();
            $table->string('payment_status_code')->nullable();
            $table->string('currency')->nullable();
            $table->json('error')->nullable();
            $table->string('status')->nullable();
            $table->timestamps();
        });

        //Go Easy
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('pesapal_transaction_statuses');
    }
}
