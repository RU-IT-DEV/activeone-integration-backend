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
        Schema::create('order_intellicare_logs', function (Blueprint $table) {
            $table->id();
            $table->integer('order_id');
            $table->string('account_no');
            $table->string('contract');
            $table->string('branch');
            $table->string('receipt_number');
            $table->string('prccode');
            $table->json('diagnosis');
            $table->string('prescription_location')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_intellicare_logs');
    }
};
