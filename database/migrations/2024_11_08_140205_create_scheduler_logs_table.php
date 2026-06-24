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
        Schema::create('scheduler_logs', function (Blueprint $table) {
            $table->id();
            $table->string('job_name');            // Name of the job
            $table->string('status');              // Status (e.g., 'success', 'failed')
            $table->text('message')->nullable();   // Log message or error details
            $table->timestamp('run_time');         // When the job was run
            $table->timestamps();  
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('scheduler_logs');
    }
};
