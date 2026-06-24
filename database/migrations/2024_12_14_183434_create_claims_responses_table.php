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
        Schema::create('claims_responses', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->string('member_claim_id');
            $table->string('member_id');
            $table->string('member_plan_links_id');
            $table->double('approved_amount', 10, 2);
            $table->double('rejected_amount', 10, 2);
            $table->enum('final_status', ['Approved', 'Partially approved', 'Rejected'])->default('Rejected');
            $table->string('member_plan_bucket_id')->nullable();
            $table->string('adjudicated_by');
            $table->string('remarks')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('claims_responses');
    }
};
