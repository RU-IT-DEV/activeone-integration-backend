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
        Schema::create('company_contract_periods', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->string('company_id');
            $table->date('contract_period_start');
            $table->date('contract_period_end');
            $table->string('policy_period');
            $table->json('policy');
            $table->json('account_officer');
            $table->boolean('is_current');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('company_contract_periods');
    }
};
