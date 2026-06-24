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
            $table->string('freshdesk_claim_id')
                  ->nullable()
                  ->after('claim_id'); // place it after claim_id
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('member_claims', function (Blueprint $table) {
            $table->dropColumn('freshdesk_claim_id');
        });
    }
};
