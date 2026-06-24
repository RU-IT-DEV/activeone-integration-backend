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
        Schema::create('benefit_category_options', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->string('name'); // e.g., Rice, Optical, Groceries
            $table->enum('type', ['core', 'uflex']); // Add type here
            $table->longText('description')->nullable(); // Optional
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('benefit_category_options');
    }
};
