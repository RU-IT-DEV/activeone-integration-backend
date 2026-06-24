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
        Schema::table('benefit_periods', function (Blueprint $table) {
            $table->boolean('adj_selectable_flg')->default(false)->after('is_current');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('benefit_periods', function (Blueprint $table) {
            $table->dropColumn('adj_selectable_flg');
        });
    }
};
