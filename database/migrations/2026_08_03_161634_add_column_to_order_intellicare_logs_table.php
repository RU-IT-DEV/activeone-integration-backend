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
        Schema::table('order_intellicare_logs', function (Blueprint $table) {
            $table->string('reference_number')->nullable()->after('order_id');
            $table->string('loa_date')->nullable()->after('prescription_location');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('order_intellicare_logs', function (Blueprint $table) {
            $table->dropColumn('reference_number');
            $table->dropColumn('loa_date');
        });
    }
};
