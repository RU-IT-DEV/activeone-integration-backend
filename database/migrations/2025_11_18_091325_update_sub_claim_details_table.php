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
        Schema::table('sub_claim_details', function (Blueprint $table) {
            $table->string('purpose')->nullable()->after('sub_category');
            $table->string('parking_location')->nullable()->after('description');
            $table->string('vehicle_plate_number')->nullable()->after('parking_location');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sub_claim_details', function (Blueprint $table) {
            $table->dropColumn('purpose');
            $table->dropColumn('parking_location');
            $table->dropColumn('vehicle_plate_number');
        });
    }
};
