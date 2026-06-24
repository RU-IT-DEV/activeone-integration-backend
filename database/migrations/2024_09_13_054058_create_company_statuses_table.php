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
        Schema::create('company_statuses', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->string('company_id');
            $table->string('status');
            $table->string('contract_status'); 
            $table->date('effectivity_date'); 
            $table->boolean('is_current');
            $table->longText('reason')->nullable(); 
            $table->string('created_by');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('company_statuses');
    }
};
