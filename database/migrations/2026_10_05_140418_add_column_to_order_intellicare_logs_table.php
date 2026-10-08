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
            $table->string('prctype')->nullable()->after('receipt_number');
            $table->string('prcfirstname')->nullable()->after('prccode');
            $table->string('prclastname')->nullable()->after('prcfirstname');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('order_intellicare_logs', function (Blueprint $table) {
            $table->dropColumn('prctype');
            $table->dropColumn('prcfirstname');
            $table->dropColumn('prclastname');
        });
    }
};
