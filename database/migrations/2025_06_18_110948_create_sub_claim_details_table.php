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
        Schema::create('sub_claim_details', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->unsignedBigInteger('member_claim_id'); 

            $table->string('category')->nullable();
            $table->string('sub_category')->nullable();
            $table->string('activities_or_items')->nullable();
            $table->text('description')->nullable();
            $table->string('beneficiary')->nullable();
            $table->string('relation_to_employee')->nullable();
            $table->string('vendor_name')->nullable();
            $table->date('receipt_date')->nullable();
            $table->string('vendor_tin')->nullable();
            $table->string('vendor_address')->nullable();
            $table->string('or_number')->nullable();
            $table->text('receipt')->nullable();
            $table->double('amount', 10, 2)->nullable();

            $table->foreign('member_claim_id')->references('id')->on('member_claims')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sub_claim_details');
    }
};
