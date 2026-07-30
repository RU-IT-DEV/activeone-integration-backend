<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('customer_id');
            $table->string('customer_email');
            $table->string('customer_name');
            $table->string('shopify_cart_id');
            $table->string('shopify_order_name')->nullable();
            $table->string('financialStatus');
            $table->float('totalAmount');
            $table->boolean('test');
            $table->string('intellicare_status');
            $table->string('shopify_status');
            $table->string('activeone_status');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
