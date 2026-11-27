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
        Schema::create('daily_report_events', function (Blueprint $table) {
            $table->id();
            $table->string('report_name');
            $table->text('file_path');
            $table->datetime('generated_at')->nullable();
            $table->string('status');
            $table->boolean('mailable')->default(false);
            $table->boolean('is_mail_sent')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('daily_report_events');
    }
};
