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
        Schema::create('order_details', function (Blueprint $table) {
            $table->id();
            $table->integer('order_id');
            $table->string('shopify_productId');
            $table->float('shopify_product_price');
            $table->string('image_url');
            $table->string('quantity');
            $table->string('sku');
            $table->string('code');
            $table->string('title');
            $table->string('type');
            $table->string('variantTitle');
            $table->string('unit');
            $table->float('amount');
            $table->float('vat_amount');
            $table->float('no_vat_amount');
            $table->boolean('taxable');
            $table->boolean('is_prescribed');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_details');
    }
};
