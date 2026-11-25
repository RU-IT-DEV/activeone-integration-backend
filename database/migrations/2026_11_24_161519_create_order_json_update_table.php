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
        Schema::create('order_json_update', function (Blueprint $table) {
            $table->id();
            $table->integer('order_id');
            $table->string('update_type');
            $table->datetime('started_at');
            $table->datetime('processing_at')->nullable();
            $table->datetime('completed_at')->nullable();
            $table->string('status');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_json_update');
    }
};
