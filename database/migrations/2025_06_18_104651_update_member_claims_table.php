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

            DB::statement("ALTER TABLE member_claims CHANGE amount total_amount DOUBLE(10,2)");

            // Modify existing column if needed (optional)
            $table->integer('member_id')->nullable()->change();
            $table->string('claim_id')->nullable()->change();
            $table->string('member_plan_links_id')->nullable()->change();
            $table->string('coverage')->nullable()->change();
            $table->date('service_date')->nullable()->change();
            $table->string('vendor_name')->nullable()->change();
            $table->text('vendor_address')->nullable()->change();
            $table->string('tin_number')->nullable()->change();
            $table->string('status')->default('Pending')->change();

            // Add new columns
            $table->enum('type',['choicepot', 'reimbursement', 'fsa'])->default('reimbursement')->after('claim_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
