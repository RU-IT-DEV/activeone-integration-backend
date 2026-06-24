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
        Schema::create('bq_claims_upload', function (Blueprint $table) {
            $table->id();

            $table->integer('user_id');
            $table->integer('member_claim_id');
            $table->json('data');

            $table->boolean('is_pushed')->default(false);
            $table->timestamp('processing_at')->nullable();
            $table->timestamp('pushed_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bq_claims_upload');
    }
};
