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
        Schema::create('member_claims', function (Blueprint $table) {
            $table->id();
            $table->integer('member_id');
            $table->string('claim_id');
            $table->string('member_plan_links_id');
            $table->double('amount', 10, 2);
            $table->string('coverage');
            $table->string('category')->nullable();
            $table->date('service_date');
            $table->text('receipt')->nullable();
            $table->string('status');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('member_claims');
    }
};
