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
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('sku');
            $table->string('name');
            $table->string('netsuite_item_id')->nullable();
            $table->string('currency')->default('PHP');
            $table->float('amount');
            $table->decimal('vat_percentage', 7, 4)->default(12);
            $table->float('no_vat_amount');
            $table->boolean('vatable');
            $table->text('description')->nullable();
            $table->boolean('status')->default(1);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
