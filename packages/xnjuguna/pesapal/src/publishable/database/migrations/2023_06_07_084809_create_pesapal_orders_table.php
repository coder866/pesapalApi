<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;

 class  CreatePesapalOrdersTable extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('pesapal_orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_id');
            $table->datetime('trandate')->default(Carbon::now());
            $table->string('description')->nullable();
            $table->string('currency')->default('KES');
            $table->decimal('amount', 8, 2)->default(0);
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('cellphone')->nullable();
            $table->string('order_tracking_id')->nullable();
            $table->string('merchant_reference')->nullable();
            $table->string('status')->nullable();
            $table->string('payment_method')->nullable();
            $table->datetime('payment_date')->default(now());
            $table->string('payment_confirmation_code')->nullable();
            $table->string('payment_description')->nullable();
            $table->string('payment_message')->nullable();
            $table->string('payment_account')->nullable();
            $table->string('payment_status_code')->nullable();
            $table->string('payment_status_description')->nullable();
            $table->text('error')->nullable();
            $table->string('account_number')->nullable();
            $table->string('subscription_plan')->default('Monthly');
            $table->datetime('subscription_start')->default(now());
            $table->datetime('subscription_end')->default(Carbon::now()->addDays(30));
            $table->string('ordertype')->default('SUBSCRIPTION');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pesapal_orders');
    }
};
