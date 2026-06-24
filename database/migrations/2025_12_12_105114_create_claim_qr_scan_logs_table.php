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
        
        Schema::create('claim_qr_scan_logs', function (Blueprint $table) {
            $table->id();
            
             // 1:1 relationship with member_claims
             $table->foreignId('member_claim_id')
             ->constrained('member_claims')
             ->onDelete('cascade');

            $table->string('claim_id')->nullable();
            $table->string('email')->nullable();
            $table->string('employee_name')->nullable();
            $table->string('box_no')->nullable();

            $table->boolean('is_email_sent')->default(false);
            $table->dateTime('received_date')->nullable();
            $table->text('remarks')->nullable();

            $table->timestamps();

            
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('claim_qr_scan_logs');
    }
};
