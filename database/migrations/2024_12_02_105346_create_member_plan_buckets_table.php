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
        Schema::create('member_plan_buckets', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->string('member_plan_link_id');
            $table->string('coverage_type');
            $table->decimal('allocated_limit', 15, 2); 
            $table->decimal('used_limit', 15, 2); 
            $table->decimal('remaining_limit', 15, 2); 
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('member_plan_buckets');
    }
};
