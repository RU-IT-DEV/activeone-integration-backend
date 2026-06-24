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
        Schema::create('member_bank_details', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->foreignId('member_id')->constrained('members')->onDelete('cascade');
            $table->string('bank_name')->nullable();
            $table->string('account_name')->nullable();
            $table->string('account_number')->nullable();
            $table->string('branch')->nullable();
            $table->enum('account_type', ['savings', 'checking'])->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('member_bank_details');
    }
};
