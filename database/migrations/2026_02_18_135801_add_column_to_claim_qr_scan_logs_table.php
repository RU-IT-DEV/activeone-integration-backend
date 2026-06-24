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
        Schema::table('claim_qr_scan_logs', function (Blueprint $table) {
            $table->renameColumn('received_date', 'scanned_at');
            $table->dateTime('actual_received_date')->nullable()->after('received_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('claim_qr_scan_logs', function (Blueprint $table) {
            $table->renameColumn('scanned_at', 'received_date');
            $table->dropColumn('actual_received_date');
        });
    }
};
