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
        DB::statement("ALTER TABLE benefit_category_options MODIFY type ENUM('core', 'uflex', 'choicepot','fsa')");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("ALTER TABLE benefit_category_options MODIFY type ENUM('core', 'uflex','choicepot')");
    }
};
