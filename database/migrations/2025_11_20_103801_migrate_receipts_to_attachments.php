<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;


return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Process in chunks to avoid memory issues
        DB::table('member_claims')
        ->whereNotNull('receipt')
        ->where('receipt', '!=', '')
        ->orderBy('id')
        ->chunk(200, function ($claims) {
            foreach ($claims as $claim) {

                // Insert into attachments table
                DB::table('member_claims_attachments')->insert([
                    'member_claim_id' => $claim->id,
                    'filepath'        => $claim->receipt,
                    'created_at'      => now(),
                    'updated_at'      => now(),
                ]);
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('attachments', function (Blueprint $table) {
            //
        });
    }
};
