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
        Schema::create('members', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->string('flexicare_id');
            $table->string('company_code');
            $table->string('first_name');
            $table->string('last_name');
            $table->string('middle_name')->nullable();
            $table->string('suffix')->nullable();
            $table->string('payee_code')->nullable();
            $table->enum('member_classification', ['child', 'employee', 'spouse', 'monther', 'father'])->default('employee');
            $table->string('employee_no')->nullable();
            $table->string('birthdate');
            $table->string('gender');
            $table->enum('civil_status', ['single', 'married', 'widowed', 'devorced'])->default('single');
            $table->string('email')->unique();
            $table->string('position')->nullable();
            $table->date('date_hired')->nullable();
            $table->string('division')->nullable();
            $table->enum('member_type', ['principal', 'dependent'])->default('principal');
            $table->string('principal_id')->nullable();
            $table->date('date_endorsed')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('members');
    }
};
