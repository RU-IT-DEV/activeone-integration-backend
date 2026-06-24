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
            $table->date('received_date')->nullable()->after('freshdesk_claim_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('member_claims', function (Blueprint $table) {
            $table->dropColumn('received_date');
        });
    }
};
