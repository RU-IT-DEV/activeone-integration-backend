<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('admin_navigations', function (Blueprint $table) {
            $table->id();
            $table->string('title', 100);
            $table->string('route', 255)->unique();
            $table->string('icon', 100)->default('mdi-menu');
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('admin_navigations'); }
};
