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
        Schema::table('benefit_category_options', function (Blueprint $table) {
            $table->integer('benefit_id')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('benefit_category_options', function (Blueprint $table) {
            $table->dropColumn('benefit_id');
        });
    }
};
