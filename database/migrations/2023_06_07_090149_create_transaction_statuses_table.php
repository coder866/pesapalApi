<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTransactionStatusesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('transaction_statuses', function (Blueprint $table) {
            $table->id();
            $table->string('payment_method');
            $table->decimal('amount', 8, 2);
            $table->timestamp('created_date');
            $table->string('confirmation_code');
            $table->string('payment_status_description');
            $table->text('description');
            $table->string('message');
            $table->string('payment_account');
            $table->text('call_back_url');
            $table->unsignedTinyInteger('status_code');
            $table->string('merchant_reference');
            $table->string('payment_status_code')->nullable();
            $table->string('currency');
            $table->json('error')->nullable();
            $table->string('status');
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
        Schema::dropIfExists('transaction_statuses');
    }
}
