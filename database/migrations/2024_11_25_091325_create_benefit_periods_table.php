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
        Schema::create('benefit_periods', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->string('benefit_id');
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->date('effectivity_date');
            $table->date('expiration_date');
            $table->boolean('is_current');
            
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('benefit_periods');
    }
};
