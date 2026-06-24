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
        Schema::table('member_claims', function (Blueprint $table) {
            $table->string('vendor_name')->after('member_plan_links_id');
            $table->text('vendor_address')->after('vendor_name');
            $table->string('tin_number')->after('vendor_address');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('member_claims', function (Blueprint $table) {
            $table->dropColumn('vendor_name');
            $table->dropColumn('vendor_address');
        });
    }
};
